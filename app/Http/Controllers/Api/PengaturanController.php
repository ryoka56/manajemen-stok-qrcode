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
    // Sekalian dibalikin ketentuan khusus akun Divisi (dipakai layar
    // persetujuan awal portal divisi) - satu endpoint aja biar app gak
    // perlu 2 request terpisah.
    public function show()
    {
        return response()->json([
            'deskripsi_persetujuan' => Pengaturan::ambil('deskripsi_persetujuan', ''),
            'deskripsi_persetujuan_divisi' => Pengaturan::ambil('deskripsi_persetujuan_divisi', ''),
        ]);
    }

    // PUT /api/pengaturan - khusus admin, ubah teks ketentuan peminjaman
    // dan/atau ketentuan divisi. Keduanya opsional (sometimes) - admin
    // boleh update salah satu saja tanpa perlu kirim ulang yang lain.
    public function update(Request $request)
    {
        $data = $request->validate([
            'deskripsi_persetujuan' => 'sometimes|required|string|max:5000',
            'deskripsi_persetujuan_divisi' => 'sometimes|required|string|max:5000',
        ]);

        if (empty($data)) {
            return response()->json(['message' => 'Tidak ada perubahan dikirim.'], 422);
        }

        if (array_key_exists('deskripsi_persetujuan', $data)) {
            Pengaturan::simpan('deskripsi_persetujuan', $data['deskripsi_persetujuan']);
        }
        if (array_key_exists('deskripsi_persetujuan_divisi', $data)) {
            Pengaturan::simpan('deskripsi_persetujuan_divisi', $data['deskripsi_persetujuan_divisi']);
        }

        return response()->json([
            'message' => 'Ketentuan berhasil diperbarui.',
            'deskripsi_persetujuan' => Pengaturan::ambil('deskripsi_persetujuan', ''),
            'deskripsi_persetujuan_divisi' => Pengaturan::ambil('deskripsi_persetujuan_divisi', ''),
        ]);
    }
}
