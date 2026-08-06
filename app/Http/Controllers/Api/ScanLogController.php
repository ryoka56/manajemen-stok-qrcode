<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Pengaturan;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScanLogController extends Controller
{
    // GET /api/scan-logs
    // Riwayat aktivitas scan. Petugas hanya lihat riwayat miliknya sendiri,
    // admin bisa lihat semua (atau filter by user_id tertentu).
    public function index(Request $request)
    {
        $query = ScanLog::with(['asset:id,nama_barang,kode_aset,kategori,status,kondisi', 'user:id,name']);

        $user = $request->user();

        if ($user && !$user->isAdmin()) {
            // Petugas: hanya riwayat miliknya sendiri
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            // Admin: boleh filter riwayat petugas tertentu
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        return response()->json(
            $query->latest('scanned_at')->paginate(30)
        );
    }

    // POST /api/scan-logs
    // Dipanggil setelah user scan QR di lokasi tujuan dan mengisi form lokasi.
    // Nama petugas & user_id otomatis diambil dari akun yang sedang login.
    public function store(Request $request)
    {
        $data = $request->validate([
            'kode_aset' => 'required|string|exists:assets,kode_aset',
            'lokasi_input' => 'required|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            // Nama peminjam cuma WAJIB diisi kalau aksi ini bikin status
            // jadi 'dipinjam'. Kalau lagi mengembalikan/status lain, boleh
            // kosong - gak masuk akal nanya "siapa peminjam" pas ngembaliin.
            'nama_peminjam' => 'required|string|max:100',
            'catatan' => 'nullable|string',
            // status = 'ada'/'dipinjam'/'hilang' (aksi pinjam/kembalikan/lapor
            // hilang). kondisi = 'tersedia'/'rusak' (kondisi fisik barang) -
            // keduanya independen, barang boleh saja rusak TAPI tetap
            // dipinjam (sesuai keputusan), jadi TIDAK saling membatasi di sini.
            'status' => 'nullable|in:ada,dipinjam,hilang',
            'kondisi' => 'nullable|in:tersedia,rusak',
            // Tanda tangan digital & centang persetujuan ketentuan WAJIB di
            // setiap aksi scan (apapun status barunya). Dikirim sebagai data
            // URL base64 (hasil ekspor canvas signature pad dari Flutter),
            // makanya divalidasi sebagai string, bukan 'image'.
            'tanda_tangan' => 'required|string',
            'setuju_ketentuan' => 'required|boolean|accepted',
            // CATATAN: opsi upload foto SUDAH DIPINDAH dari alur scan ini ke
            // Kelola Barang (petugas lewat usulan/ACC, admin langsung) - lihat
            // PerubahanController & AssetController::uploadFoto. Field 'foto'
            // sengaja tidak diterima lagi di sini.
        ]);

        $asset = Asset::where('kode_aset', $data['kode_aset'])->firstOrFail();
        $user = $request->user();

        $statusSebelum = $asset->status;
        $statusBaru = $data['status'] ?? $asset->status;
        $kondisiSebelum = $asset->kondisi;
        $kondisiBaru = $data['kondisi'] ?? $asset->kondisi;

        // Cegah barang yang LAGI dipinjam orang lain "dipinjam" lagi oleh
        // scan lain (mis. 2 petugas gak sadar scan barang yang sama).
        // Kalau status barunya sama-sama 'dipinjam' padahal sebelumnya juga
        // sudah 'dipinjam', berarti bukan aksi pinjam baru - tolak dengan
        // pesan jelas siapa yang lagi pinjam sekarang.
        if ($statusSebelum === 'dipinjam' && $statusBaru === 'dipinjam') {
            $peminjamSekarang = $asset->lokasiTerakhir?->nama_peminjam;
            return response()->json([
                'message' => $peminjamSekarang
                    ? "Barang ini sedang dipinjam oleh \"$peminjamSekarang\". Kembalikan dulu sebelum dipinjamkan lagi."
                    : 'Barang ini sedang dipinjam. Kembalikan dulu sebelum dipinjamkan lagi.',
            ], 409);
        }

        // Statistik "Peminjaman" HANYA naik kalau transisinya beneran
        // ada -> dipinjam (murni soal status, gak peduli kondisi berubah atau tidak).
        $isPeminjaman = $statusSebelum !== 'dipinjam' && $statusBaru === 'dipinjam';
        $isPengembalian = $statusSebelum === 'dipinjam' && $statusBaru !== 'dipinjam';

        // Tanda tangan & ketentuan disimpan untuk SETIAP scan log (apapun
        // transisinya), karena validasi di atas sudah mewajibkan keduanya.
        $pathTandaTangan = $this->simpanTandaTangan($data['tanda_tangan']);
        $ketentuanSnapshot = Pengaturan::ambil('deskripsi_persetujuan', '');

        $log = ScanLog::create([
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'lokasi_input' => $data['lokasi_input'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'nama_petugas' => $user->name,
            'nama_peminjam' => $data['nama_peminjam'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'status_saat_itu' => $statusBaru,
            'status_sebelum' => $statusSebelum,
            'kondisi_saat_itu' => $kondisiBaru,
            'kondisi_sebelum' => $kondisiSebelum,
            'is_peminjaman' => $isPeminjaman,
            'is_pengembalian' => $isPengembalian,
            'tanda_tangan' => $pathTandaTangan,
            'setuju_ketentuan' => (bool) $data['setuju_ketentuan'],
            'ketentuan_snapshot' => $ketentuanSnapshot,
            'scanned_at' => now(),
        ]);

        $perubahanAsset = [];
        if ($statusBaru !== $statusSebelum) $perubahanAsset['status'] = $statusBaru;
        if ($kondisiBaru !== $kondisiSebelum) $perubahanAsset['kondisi'] = $kondisiBaru;
        if (!empty($perubahanAsset)) {
            $asset->update($perubahanAsset);
        }

        return response()->json($log, 201);
    }

    // Decode data URL base64 (hasil export canvas signature pad Flutter,
    // format "data:image/png;base64,....") lalu simpan sebagai file PNG
    // di storage/app/public/tanda-tangan, pola sama seperti foto barang.
    private function simpanTandaTangan(string $dataUrl): string
    {
        if (str_contains($dataUrl, ',')) {
            $dataUrl = explode(',', $dataUrl, 2)[1];
        }

        $isi = base64_decode($dataUrl);
        $namaFile = 'tanda-tangan/' . Str::uuid() . '.png';
        Storage::disk('public')->put($namaFile, $isi);

        return $namaFile;
    }

    // PUT /api/scan-logs/{scanLog}/foto - khusus admin
    // Nyalain/matiin foto ini TANPA menghapus filenya. Dipakai admin buat
    // nyembunyiin foto yang gak pantas/salah dari tampilan barang, tapi
    // tetap nyimpen jejaknya buat riwayat (siapa tau perlu ditinjau lagi).
    public function toggleFoto(Request $request, ScanLog $scanLog)
    {
        if (!$scanLog->foto) {
            return response()->json(['message' => 'Scan log ini tidak punya foto.'], 422);
        }

        $data = $request->validate(['aktif' => 'required|boolean']);

        $scanLog->update(['foto_aktif' => $data['aktif']]);

        return response()->json($scanLog->fresh());
    }

    // DELETE /api/scan-logs/{scanLog}/foto - khusus admin
    // Beda dengan toggle di atas - ini BENERAN hapus filenya secara
    // permanen (gak bisa dipulihkan). Baris ScanLog-nya sendiri tetap ada
    // (riwayat lokasi/tanda tangan/dll di baris itu tidak ikut hilang),
    // cuma bagian foto-nya yang dibersihkan.
    public function hapusFoto(ScanLog $scanLog)
    {
        if (!$scanLog->foto) {
            return response()->json(['message' => 'Scan log ini tidak punya foto.'], 422);
        }

        Storage::disk('public')->delete($scanLog->foto);
        $scanLog->update(['foto' => null, 'foto_aktif' => true, 'slot' => null]);

        return response()->json(['message' => 'Foto berhasil dihapus permanen.']);
    }

    // GET /api/scan-logs/peta
    public function peta(Request $request)
    {
        $query = ScanLog::with(['asset:id,nama_barang,kode_aset,kategori,status,kondisi', 'user:id,name'])
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('scan_logs')->groupBy('asset_id');
            });

        $user = $request->user();
        if ($user && !$user->isAdmin()) {
            // Petugas cuma lihat titik barang yang pernah dia scan sendiri
            $query->where('user_id', $user->id);
        }

        return response()->json($query->get());
    }

    // GET /api/scan-logs/statistik?periode=harian|mingguan|bulanan|tahunan|semua
    // Khusus admin - hitung jumlah peminjaman per periode, plus rincian per hari.
    // GET /api/scan-logs/grafik-tahunan?tahun=2026
    // Data jumlah peminjaman per bulan (Jan-Des) untuk ditampilkan sebagai grafik batang
    // di Dashboard Tinjauan, mirip statistik peminjaman bulanan.
    public function grafikTahunan(Request $request)
    {
        $tahun = $request->get('tahun', now()->year);

        $data = ScanLog::whereYear('scanned_at', $tahun)
            ->selectRaw('MONTH(scanned_at) as bulan, COUNT(*) as jumlah')
            ->groupBy('bulan')
            ->orderBy('bulan')
            ->get()
            ->keyBy('bulan');

        $hasil = [];
        for ($i = 1; $i <= 12; $i++) {
            $hasil[] = [
                'bulan' => $i,
                'jumlah' => $data->has($i) ? $data[$i]->jumlah : 0,
            ];
        }

        // Barang paling banyak dipinjam sepanjang tahun ini
        $topItem = ScanLog::whereYear('scanned_at', $tahun)
            ->join('assets', 'assets.id', '=', 'scan_logs.asset_id')
            ->selectRaw('assets.nama_barang, COUNT(*) as jumlah')
            ->groupBy('assets.nama_barang')
            ->orderByDesc('jumlah')
            ->first();

        return response()->json([
            'tahun' => (int) $tahun,
            'data_bulanan' => $hasil,
            'total_tahun_ini' => array_sum(array_column($hasil, 'jumlah')),
            'top_item' => $topItem?->nama_barang,
        ]);
    }

    // GET /api/scan-logs/grafik?periode=mingguan|bulanan|tahunan
    // Data grafik untuk Dashboard Tinjauan, dengan rincian breakdown per alat
    // di setiap titik (bar) agar bisa ditampilkan sebagai tooltip saat bar di-tap.
    // - mingguan  : 7 titik (Sen-Min) untuk minggu berjalan
    // - bulanan   : 1 titik per tanggal pada bulan berjalan
    // - tahunan   : 12 titik (Jan-Des) pada tahun berjalan
    public function grafik(Request $request)
    {
        $periode = $request->get('periode', 'bulanan');
        $sekarang = now();

        $namaHari = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $namaBulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

        $titik = []; // masing-masing: ['label' => ..., 'awal' => Carbon, 'akhir' => Carbon]

        switch ($periode) {
            case 'mingguan':
                $mulaiMinggu = $sekarang->copy()->startOfWeek();
                for ($i = 0; $i < 7; $i++) {
                    $hari = $mulaiMinggu->copy()->addDays($i);
                    $titik[] = [
                        'label' => $namaHari[$i],
                        'awal' => $hari->copy()->startOfDay(),
                        'akhir' => $hari->copy()->endOfDay(),
                    ];
                }
                break;

            case 'tahunan':
                for ($i = 1; $i <= 12; $i++) {
                    $awal = $sekarang->copy()->startOfYear()->month($i)->startOfMonth();
                    $titik[] = [
                        'label' => $namaBulan[$i - 1],
                        'awal' => $awal,
                        'akhir' => $awal->copy()->endOfMonth(),
                    ];
                }
                break;

            case 'bulanan':
            default:
                $periode = 'bulanan';
                $jumlahHari = $sekarang->copy()->daysInMonth;
                for ($i = 1; $i <= $jumlahHari; $i++) {
                    $tgl = $sekarang->copy()->startOfMonth()->day($i);
                    $titik[] = [
                        'label' => (string) $i,
                        'awal' => $tgl->copy()->startOfDay(),
                        'akhir' => $tgl->copy()->endOfDay(),
                    ];
                }
                break;
        }

        $hasil = [];
        foreach ($titik as $t) {
            $queryTitik = ScanLog::where('is_peminjaman', true)->whereBetween('scanned_at', [$t['awal'], $t['akhir']]);
            $jumlah = (clone $queryTitik)->count();

            $breakdown = (clone $queryTitik)
                ->join('assets', 'assets.id', '=', 'scan_logs.asset_id')
                ->selectRaw('assets.nama_barang, COUNT(*) as jumlah')
                ->groupBy('assets.nama_barang')
                ->orderByDesc('jumlah')
                ->get();

            $hasil[] = [
                'label' => $t['label'],
                'tanggal' => $t['awal']->toDateString(),
                'jumlah' => $jumlah,
                'breakdown' => $breakdown,
            ];
        }

        $awalPeriode = $titik[0]['awal'];
        $akhirPeriode = end($titik)['akhir'];

        $topItem = ScanLog::where('is_peminjaman', true)->whereBetween('scanned_at', [$awalPeriode, $akhirPeriode])
            ->join('assets', 'assets.id', '=', 'scan_logs.asset_id')
            ->selectRaw('assets.nama_barang, COUNT(*) as jumlah')
            ->groupBy('assets.nama_barang')
            ->orderByDesc('jumlah')
            ->first();

        return response()->json([
            'periode' => $periode,
            'data' => $hasil,
            'total' => array_sum(array_column($hasil, 'jumlah')),
            'top_item' => $topItem?->nama_barang,
        ]);
    }

    public function statistik(Request $request)
    {
        $periode = $request->get('periode', 'harian');
        $sekarang = now();

        // Statistik "peminjaman" cuma menghitung transisi status beneran
        // (tersedia/rusak) -> dipinjam. Perubahan status lain (mis.
        // tersedia <-> rusak) tidak dianggap aksi pinjam.
        $query = ScanLog::query()->where('is_peminjaman', true);

        switch ($periode) {
            case 'harian':
                $query->whereDate('scanned_at', $sekarang->toDateString());
                break;
            case 'mingguan':
                $query->whereBetween('scanned_at', [
                    $sekarang->copy()->startOfWeek(),
                    $sekarang->copy()->endOfWeek(),
                ]);
                break;
            case 'bulanan':
                $query->whereYear('scanned_at', $sekarang->year)
                      ->whereMonth('scanned_at', $sekarang->month);
                break;
            case 'tahunan':
                $query->whereYear('scanned_at', $sekarang->year);
                break;
            case 'semua':
            default:
                // tidak difilter, ambil semua data
                break;
        }

        $total = (clone $query)->count();

        // Rincian jumlah peminjaman per tanggal, untuk ditampilkan sebagai grafik sederhana
        $rincian = (clone $query)
            ->selectRaw('DATE(scanned_at) as tanggal, COUNT(*) as jumlah')
            ->groupBy('tanggal')
            ->orderBy('tanggal')
            ->get();

        // Barang paling sering dipinjam pada periode ini
        $barangTerpopuler = (clone $query)
            ->join('assets', 'assets.id', '=', 'scan_logs.asset_id')
            ->selectRaw('assets.nama_barang, COUNT(*) as jumlah')
            ->groupBy('assets.nama_barang')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        return response()->json([
            'periode' => $periode,
            'total' => $total,
            'rincian_harian' => $rincian,
            'barang_terpopuler' => $barangTerpopuler,
        ]);
    }
}
