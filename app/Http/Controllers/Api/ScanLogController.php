<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ScanLog;
use Illuminate\Http\Request;

class ScanLogController extends Controller
{
    // GET /api/scan-logs
    // Riwayat aktivitas scan. Petugas hanya lihat riwayat miliknya sendiri,
    // admin bisa lihat semua (atau filter by user_id tertentu).
    public function index(Request $request)
    {
        $query = ScanLog::with(['asset:id,nama_barang,kode_aset,kategori,status', 'user:id,name']);

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
            'nama_peminjam' => 'required|string|max:100',
            'catatan' => 'nullable|string',
            'status' => 'nullable|in:tersedia,dipinjam,rusak',
        ]);

        $asset = Asset::where('kode_aset', $data['kode_aset'])->firstOrFail();
        $user = $request->user();

        $statusSebelum = $asset->status;
        $statusBaru = $data['status'] ?? $asset->status;

        // Statistik "Peminjaman" HANYA naik kalau transisinya beneran
        // (tersedia/rusak) -> dipinjam. Selain itu (mis. tersedia <-> rusak)
        // dianggap bukan aksi pinjam, jadi tidak dihitung.
        $isPeminjaman = $statusSebelum !== 'dipinjam' && $statusBaru === 'dipinjam';
        $isPengembalian = $statusSebelum === 'dipinjam' && $statusBaru !== 'dipinjam';

        $log = ScanLog::create([
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'lokasi_input' => $data['lokasi_input'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'nama_petugas' => $user->name,
            'nama_peminjam' => $data['nama_peminjam'],
            'catatan' => $data['catatan'] ?? null,
            'status_saat_itu' => $statusBaru,
            'status_sebelum' => $statusSebelum,
            'is_peminjaman' => $isPeminjaman,
            'is_pengembalian' => $isPengembalian,
            'scanned_at' => now(),
        ]);

        if ($statusBaru !== $statusSebelum) {
            $asset->update(['status' => $statusBaru]);
        }

        return response()->json($log, 201);
    }

    // GET /api/scan-logs/peta
    public function peta(Request $request)
    {
        $query = ScanLog::with(['asset:id,nama_barang,kode_aset,kategori,status', 'user:id,name'])
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
