<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Ruangan;
use App\Models\ScanLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RuanganController extends Controller
{
    // GET /api/ruangans - admin & petugas boleh lihat (untuk pilihan saat scan/pinjam)
    public function index()
    {
        return response()->json(Ruangan::orderBy('nama_ruangan')->get());
    }

    // POST /api/ruangans - khusus admin
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_ruangan' => 'required|string|max:100|unique:ruangans,nama_ruangan',
            'lokasi_gedung' => 'nullable|string|max:150',
            'keterangan' => 'nullable|string',
        ]);

        $ruangan = Ruangan::create($data);
        return response()->json($ruangan, 201);
    }

    // PUT /api/ruangans/{ruangan} - khusus admin
    public function update(Request $request, Ruangan $ruangan)
    {
        $data = $request->validate([
            'nama_ruangan' => 'required|string|max:100|unique:ruangans,nama_ruangan,' . $ruangan->id,
            'lokasi_gedung' => 'nullable|string|max:150',
            'keterangan' => 'nullable|string',
        ]);

        // PENTING: 'ruangan_asal' di tabel assets dan 'lokasi_input' di
        // scan_logs itu TEKS NAMA ruangan (bukan foreign key ke id ruangan
        // ini). Jadi kalau nama ruangannya cuma diganti di tabel ruangans
        // doang, semua barang & riwayat yang masih nyimpen nama LAMA jadi
        // "hilang" - gak ketemu lagi pas dicari/difilter pakai nama baru.
        // Makanya di sini sekalian di-cascade: semua baris yang masih
        // pakai nama lama, ikut diupdate ke nama baru. Dibungkus transaction
        // supaya kalau salah satu update gagal di tengah jalan, semuanya
        // dibatalkan (gak ada data nyangkut setengah-setengah).
        $namaLama = $ruangan->nama_ruangan;
        $namaBaru = $data['nama_ruangan'];

        DB::transaction(function () use ($ruangan, $data, $namaLama, $namaBaru) {
            $ruangan->update($data);

            if ($namaLama !== $namaBaru) {
                Asset::where('ruangan_asal', $namaLama)->update(['ruangan_asal' => $namaBaru]);
                ScanLog::where('lokasi_input', $namaLama)->update(['lokasi_input' => $namaBaru]);
            }
        });

        return response()->json($ruangan->fresh());
    }

    // DELETE /api/ruangans/{ruangan} - khusus admin
    public function destroy(Ruangan $ruangan)
    {
        $ruangan->delete();
        return response()->json(['message' => 'Ruangan berhasil dihapus']);
    }
}
