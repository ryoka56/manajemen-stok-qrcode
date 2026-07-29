<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Illuminate\Http\Request;

class PengaturanController extends Controller
{
    // GET /api/pengaturan
    // Dipanggil dari layar Input Peminjaman (semua role, admin & petugas)
    // buat nampilin teks ketentuan/user agreement sebelum tanda tangan.
    public function show()
    {
        return response()->json([
            'deskripsi_persetujuan' => Pengaturan::ambil('deskripsi_persetujuan', ''),
        ]);
    }

    // PUT /api/pengaturan - khusus admin, ubah teks ketentuan peminjaman
    public function update(Request $request)
    {
        $data = $request->validate([
            'deskripsi_persetujuan' => 'required|string|max:5000',
        ]);

        Pengaturan::simpan('deskripsi_persetujuan', $data['deskripsi_persetujuan']);

        return response()->json([
            'message' => 'Ketentuan peminjaman berhasil diperbarui.',
            'deskripsi_persetujuan' => $data['deskripsi_persetujuan'],
        ]);
    }
}
