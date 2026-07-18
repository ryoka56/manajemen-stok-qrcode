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

        $log = ScanLog::create([
            'asset_id' => $asset->id,
            'user_id' => $user->id,
            'lokasi_input' => $data['lokasi_input'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'nama_petugas' => $user->name,
            'nama_peminjam' => $data['nama_peminjam'],
            'catatan' => $data['catatan'] ?? null,
            'status_saat_itu' => $data['status'] ?? $asset->status,
            'scanned_at' => now(),
        ]);

        if (!empty($data['status']) && $data['status'] !== $asset->status) {
            $asset->update(['status' => $data['status']]);
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
    public function statistik(Request $request)
    {
        $periode = $request->get('periode', 'harian');
        $sekarang = now();

        $query = ScanLog::query();

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
