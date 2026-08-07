<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Ruangan;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DivisiController extends Controller
{
    // GET /api/divisi/ruangans
    // Daftar ruangan yang jadi tanggung jawab akun divisi yang lagi login
    // (buat pemilih ruangan di dashboard, kalau ditugaskan lebih dari satu).
    public function ruangans(Request $request)
    {
        return response()->json($request->user()->ruangans()->orderBy('nama_ruangan')->get());
    }

    // GET /api/divisi/rekap?ruangan_id=
    // Statistik "barang bulan ini vs bulan lalu" untuk SATU ruangan -
    // read-only, cuma buat dilihat (divisi tidak punya hak ubah apa pun
    // dari sini). ruangan_id WAJIB salah satu yang ditugaskan ke akun ini
    // (dicek di bawah) - dilonggarkan untuk admin supaya bisa pratinjau.
    public function rekap(Request $request)
    {
        $data = $request->validate([
            'ruangan_id' => 'required|exists:ruangans,id',
        ]);

        $user = $request->user();
        $ruangan = Ruangan::findOrFail($data['ruangan_id']);

        if (!$user->isAdmin() && !$user->ruangans()->where('ruangans.id', $ruangan->id)->exists()) {
            return response()->json(['message' => 'Kamu tidak ditugaskan ke ruangan ini.'], 403);
        }

        $namaRuangan = $ruangan->nama_ruangan;
        $sekarang = now();
        $akhirBulanLalu = $sekarang->copy()->startOfMonth()->subSecond(); // detik terakhir bulan lalu

        // "Lokasi SEKARANG" tiap barang = hasil scan TERAKHIR (apapun waktunya),
        // fallback ke ruangan_asal kalau belum pernah discan - sama persis
        // konsep yang dipakai di AssetController::rekap & Kelola Barang.
        $lokasiSekarangSub = DB::table('scan_logs')
            ->select('asset_id', 'lokasi_input')
            ->whereIn('id', function ($q) {
                $q->select(DB::raw('MAX(id)'))->from('scan_logs')->groupBy('asset_id');
            });

        $idSekarang = DB::table('assets')
            ->leftJoinSub($lokasiSekarangSub, 'lokasi_sekarang', 'assets.id', '=', 'lokasi_sekarang.asset_id')
            ->whereNull('assets.deleted_at')
            ->whereRaw('COALESCE(lokasi_sekarang.lokasi_input, assets.ruangan_asal) = ?', [$namaRuangan])
            ->pluck('assets.id');

        // "Lokasi PER AKHIR BULAN LALU" tiap barang = scan TERAKHIR yang
        // terjadi SEBELUM/PAS akhir bulan lalu, fallback ke ruangan_asal.
        // Barang yang baru dibuat SETELAH akhir bulan lalu otomatis tidak
        // ikut (tidak relevan buat perbandingan "bulan lalu").
        $lokasiBulanLaluSub = DB::table('scan_logs')
            ->select('asset_id', 'lokasi_input')
            ->whereIn('id', function ($q) use ($akhirBulanLalu) {
                $q->select(DB::raw('MAX(id)'))
                    ->from('scan_logs')
                    ->where('scanned_at', '<=', $akhirBulanLalu)
                    ->groupBy('asset_id');
            });

        $idBulanLalu = DB::table('assets')
            ->leftJoinSub($lokasiBulanLaluSub, 'lokasi_lalu', 'assets.id', '=', 'lokasi_lalu.asset_id')
            ->whereNull('assets.deleted_at')
            ->where('assets.created_at', '<=', $akhirBulanLalu)
            ->whereRaw('COALESCE(lokasi_lalu.lokasi_input, assets.ruangan_asal) = ?', [$namaRuangan])
            ->pluck('assets.id');

        $idMasuk = $idSekarang->diff($idBulanLalu)->values();   // ada sekarang, dulu belum
        $idKeluar = $idBulanLalu->diff($idSekarang)->values();  // dulu ada, sekarang sudah tidak

        return response()->json([
            'ruangan' => $ruangan,
            'jumlah_bulan_ini' => $idSekarang->count(),
            'jumlah_bulan_lalu' => $idBulanLalu->count(),
            'selisih' => $idSekarang->count() - $idBulanLalu->count(),
            'barang_masuk' => Asset::whereIn('id', $idMasuk)->orderBy('nama_barang')->get(),
            'barang_keluar' => Asset::withTrashed()->whereIn('id', $idKeluar)->orderBy('nama_barang')->get(),
            'barang_saat_ini' => Asset::with('lokasiTerakhir')->whereIn('id', $idSekarang)->orderBy('nama_barang')->get(),
            // Riwayat aktivitas terakhir di ruangan ini (barang masuk/discan
            // di sini) - murni informasi, divisi tidak bisa ubah apa pun.
            'riwayat_aktivitas' => ScanLog::with(['asset:id,nama_barang,kode_aset', 'user:id,name'])
                ->where('lokasi_input', $namaRuangan)
                ->latest('scanned_at')
                ->limit(30)
                ->get(),
        ]);
    }
}
