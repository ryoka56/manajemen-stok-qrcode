<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ScanLog;
use Illuminate\Http\Request;

class ScanLogController extends Controller
{
    // GET /api/scan-logs
    // Riwayat lengkap semua aktivitas scan & perpindahan barang, terbaru duluan
    public function index(Request $request)
    {
        $query = ScanLog::with('asset:id,nama_barang,kode_aset,kategori,status');

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        return response()->json(
            $query->latest('scanned_at')->paginate(30)
        );
    }

    // POST /api/scan-logs
    // Dipanggil setelah user scan QR di lokasi tujuan dan mengisi form lokasi.
    // Nama petugas wajib diisi (siapa yang mengambil/memindahkan barang),
    // dan status barang bisa sekaligus diperbarui di sini (mis. jadi "dipinjam").
    public function store(Request $request)
    {
        $data = $request->validate([
            'kode_aset' => 'required|string|exists:assets,kode_aset',
            'lokasi_input' => 'required|string|max:255', // "barang ini ada di ruangan A"
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'nama_petugas' => 'required|string|max:100', // wajib: siapa yang mengambil barang
            'catatan' => 'nullable|string',
            'status' => 'nullable|in:tersedia,dipinjam,rusak',
        ]);

        $asset = Asset::where('kode_aset', $data['kode_aset'])->firstOrFail();

        $log = ScanLog::create([
            'asset_id' => $asset->id,
            'lokasi_input' => $data['lokasi_input'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'nama_petugas' => $data['nama_petugas'],
            'catatan' => $data['catatan'] ?? null,
            'status_saat_itu' => $data['status'] ?? $asset->status,
            'scanned_at' => now(),
        ]);

        // Kalau status ikut diubah saat scan, update juga status utama asetnya
        if (!empty($data['status']) && $data['status'] !== $asset->status) {
            $asset->update(['status' => $data['status']]);
        }

        return response()->json($log, 201);
    }

    // GET /api/scan-logs/peta
    // Ambil semua titik lokasi terbaru tiap aset untuk ditampilkan di peta (GIS overview)
    public function peta()
    {
        $titik = ScanLog::with('asset:id,nama_barang,kode_aset,kategori,status')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')->from('scan_logs')->groupBy('asset_id');
            })
            ->get();

        return response()->json($titik);
    }
}
