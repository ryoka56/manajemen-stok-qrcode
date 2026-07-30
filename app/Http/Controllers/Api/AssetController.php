<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    // GET /api/assets
    // Mendukung: ?cari= (nama barang), ?kategori= (filter kategori),
    // ?ruangan= (filter ruangan/rak asal), ?page= & ?per_page= (paginasi)
    public function index(Request $request)
    {
        $query = Asset::with(['lokasiTerakhir', 'fotoScanTerbaru']);

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }
        if ($request->filled('ruangan')) {
            $query->where('ruangan_asal', $request->ruangan);
        }
        if ($request->filled('cari')) {
            $query->where('nama_barang', 'like', '%' . $request->cari . '%');
        }

        // per_page dibatasi max 100 supaya tidak disalahgunakan buat narik semua data sekaligus
        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return response()->json($query->latest()->paginate($perPage));
    }

    // DELETE /api/assets/bulk
    // Body: { "ids": [1, 2, 3, ...] } - hapus banyak barang sekaligus.
    // Ini SOFT delete (Asset pakai trait SoftDeletes) - barang masih ada di
    // database dengan deleted_at terisi, bisa dipulihkan lewat /assets/trash
    // + /assets/{id}/restore kalau ternyata salah pilih.
    public function destroyBulk(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:assets,id',
        ]);

        $jumlah = Asset::whereIn('id', $data['ids'])->delete();

        return response()->json([
            'message' => "$jumlah barang berhasil dihapus",
            'jumlah_dihapus' => $jumlah,
        ]);
    }

    // GET /api/assets/rekap
    // Statistik dihitung langsung oleh database (GROUP BY/COUNT), bukan
    // ditarik semua ke app lalu dihitung manual - jauh lebih ringan begitu
    // data sudah ribuan baris. Dipakai Tinjauan, Kelola Ruangan, dsb.
    public function rekap(Request $request)
    {
        $perStatus = Asset::select('status', DB::raw('count(*) as jumlah'))
            ->groupBy('status')
            ->get();

        $perKategori = Asset::select('kategori', DB::raw('count(*) as jumlah'))
            ->groupBy('kategori')
            ->orderByDesc('jumlah')
            ->get();

        // Lokasi SAAT INI = hasil scan terakhir kalau ada, kalau belum
        // pernah discan fallback ke ruangan_asal (sama seperti logika
        // yang dipakai di tampilan Kelola Barang).
        $perRuangan = DB::table('assets')
            ->leftJoinSub(
                DB::table('scan_logs')
                    ->select('asset_id', 'lokasi_input')
                    ->whereIn('id', function ($q) {
                        $q->select(DB::raw('MAX(id)'))->from('scan_logs')->groupBy('asset_id');
                    }),
                'scan_terakhir',
                'assets.id',
                '=',
                'scan_terakhir.asset_id'
            )
            ->whereNull('assets.deleted_at')
            ->select(
                DB::raw('COALESCE(scan_terakhir.lokasi_input, assets.ruangan_asal) as ruangan'),
                DB::raw('count(*) as jumlah')
            )
            ->groupBy('ruangan')
            ->orderByDesc('jumlah')
            ->get();

        return response()->json([
            'per_status' => $perStatus,
            'per_kategori' => $perKategori,
            'per_ruangan' => $perRuangan,
            'total' => Asset::count(),
        ]);
    }

    // GET /api/assets/trash - daftar barang yang sudah dihapus (soft delete), admin only
    public function trash()
    {
        return response()->json(
            Asset::onlyTrashed()->latest('deleted_at')->paginate(30)
        );
    }

    // POST /api/assets/{id}/restore - kembalikan barang yang sudah dihapus, admin only
    public function restore($id)
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $asset->restore();
        return response()->json(['message' => 'Barang berhasil dipulihkan', 'asset' => $asset]);
    }

    // DELETE /api/assets/{id}/force - hapus PERMANEN (gak bisa direstore lagi), admin only
    public function forceDelete($id)
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $nama = $asset->nama_barang;
        $asset->forceDelete();
        return response()->json(['message' => "\"$nama\" dihapus permanen"]);
    }

    // POST /api/assets
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_barang' => 'required|string|max:255',
            'kategori' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'ruangan_asal' => 'nullable|string|max:100',
        ]);

        // Generate kode aset unik otomatis, mis. AST-000123.
        // PENTING: pakai withTrashed() supaya baris yang sudah di-soft-delete
        // (masih ada di database, cuma ditandai deleted_at) tetap ikut
        // dihitung - kalau tidak, nomornya bisa "dipakai ulang" dan tabrakan
        // sama kode_aset punya barang yang sudah dihapus tapi belum permanen.
        $data['kode_aset'] = 'AST-' . str_pad((Asset::withTrashed()->max('id') + 1), 6, '0', STR_PAD_LEFT);

        $asset = Asset::create($data);

        return response()->json($asset, 201);
    }

    // GET /api/assets/{asset}
    public function show(Asset $asset)
    {
        return response()->json($asset->load(['scanLogs', 'lokasiTerakhir']));
    }

    // PUT /api/assets/{asset} - khusus admin (lihat middleware route).
    // Admin boleh ubah status langsung dari sini (override manual), beda
    // dengan alur Scan (POST /scan-logs) yang wajib TTD + centang ketentuan
    // untuk petugas. Perubahan lewat sini TIDAK membuat ScanLog baru,
    // jadi tidak tercatat di riwayat lokasi/TTD - cuma buat koreksi cepat.
    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'nama_barang' => 'sometimes|string|max:255',
            'kategori' => 'sometimes|string|max:100',
            'deskripsi' => 'nullable|string',
            'status' => 'sometimes|in:tersedia,dipinjam,rusak',
        ]);

        $asset->update($data);

        return response()->json($asset);
    }

    // DELETE /api/assets/{asset} - soft delete, bisa dipulihkan lewat trash
    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->json(['message' => 'Aset berhasil dihapus (bisa dipulihkan lewat menu Sampah)']);
    }

    // POST /api/assets/{asset}/foto - upload/ganti foto di slot 1/2/3 (admin only)
    // Body: multipart, field 'foto' (file gambar) + 'slot' (1, 2, atau 3)
    public function uploadFoto(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'slot' => 'required|integer|in:1,2,3',
            'foto' => 'required|image|max:5120', // maks 5MB
        ]);

        $kolom = 'foto_' . $data['slot'];

        // Hapus file lama di slot itu dulu (kalau ada) biar storage gak numpuk sampah
        if ($asset->$kolom) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($asset->$kolom);
        }

        $path = $request->file('foto')->store('asset-photos', 'public');
        $asset->update([$kolom => $path]);

        return response()->json($asset->fresh());
    }

    // DELETE /api/assets/{asset}/foto/{slot} - hapus foto di slot tertentu (admin only)
    public function hapusFoto(Asset $asset, $slot)
    {
        if (!in_array($slot, ['1', '2', '3'])) {
            return response()->json(['message' => 'Slot foto tidak valid'], 422);
        }
        $kolom = 'foto_' . $slot;

        if ($asset->$kolom) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($asset->$kolom);
        }
        $asset->update([$kolom => null]);

        return response()->json($asset->fresh());
    }

    // GET /foto/{path} - "serve" file foto lewat Laravel (bukan diakses
    // langsung dari folder storage statis), khusus biar bisa nambahin header
    // CORS. Soalnya Flutter Web (canvas renderer) ngambil gambar pakai
    // fetch/XHR, bukan tag <img> biasa - itu WAJIB ada header CORS dari
    // server, sedangkan file statis di /storage dilayani langsung sama web
    // server (Nginx/Caddy Railway), gak lewat kode Laravel sama sekali,
    // jadi konfigurasi CORS Laravel gak kesentuh di situ.
    public function tampilkanFoto(Request $request, $path)
    {
        $fullPath = storage_path('app/public/' . $path);

        if (!file_exists($fullPath) || !str_starts_with(realpath($fullPath), storage_path('app/public'))) {
            abort(404);
        }

        return response()->file($fullPath, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    // GET /api/assets/{asset}/qrcode
    // Menghasilkan gambar QR-code berisi kode_aset, untuk ditempel di barang fisik
    public function qrcode(Asset $asset)
    {
        $qr = QrCode::size(300)->generate($asset->kode_aset);
        return response($qr)->header('Content-Type', 'image/svg+xml');
    }

    // GET /api/assets/scan/{kode_aset}
    // Dipanggil aplikasi mobile setelah scan QR untuk ambil detail barang
    public function scan($kode_aset)
    {
        $asset = Asset::where('kode_aset', $kode_aset)
            ->with(['lokasiTerakhir', 'fotoScanTerbaru', 'scanLogs' => fn ($q) => $q->latest('scanned_at')->limit(5)])
            ->firstOrFail();

        return response()->json($asset);
    }
}
