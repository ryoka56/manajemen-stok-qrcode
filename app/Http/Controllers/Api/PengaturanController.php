<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengaturanController extends Controller
{
    // Dipakai kalau baris pengaturannya belum pernah diisi sama sekali
    // (fallback pertama kali sebelum admin pernah nambah/hapus apa-apa).
    private const DOMAIN_EMAIL_DEFAULT = [
        'gmail.com', 'yahoo.com', 'yahoo.co.id', 'outlook.com', 'hotmail.com',
        'live.com', 'icloud.com', 'proton.me', 'protonmail.com', 'komdigi.go.id',
    ];

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

    // Daftar domain email yang boleh dipakai buat akun admin/petugas.
    // Dipakai bareng-bareng oleh AuthController (validasi saat bikin/edit
    // akun) dan endpoint di bawah ini (buat ditampilkan & dikelola admin
    // lewat Pengaturan > Domain Email). Disimpan di tabel pengaturans yang
    // sama, cuma key-nya beda ('domain_email_diizinkan'), format CSV.
    public static function daftarDomainEmail(): array
    {
        $csv = Pengaturan::ambil('domain_email_diizinkan');

        if ($csv === null || trim($csv) === '') {
            return self::DOMAIN_EMAIL_DEFAULT;
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv))));
    }

    private static function simpanDaftarDomainEmail(array $domains): void
    {
        Pengaturan::simpan('domain_email_diizinkan', implode(',', $domains));
    }

    // GET /api/pengaturan/domain-email - boleh dibaca admin & petugas
    // (dipakai buat validasi format email di form Kelola Akun)
    public function domainEmail()
    {
        return response()->json(['domains' => self::daftarDomainEmail()]);
    }

    // POST /api/pengaturan/domain-email - tambah 1 domain baru, khusus admin
    public function tambahDomainEmail(Request $request)
    {
        $data = $request->validate([
            // Format domain standar: label-label dipisah titik, tiap label
            // cuma huruf/angka/strip, gak boleh diawali/diakhiri strip.
            'domain' => [
                'required', 'string', 'max:255',
                'regex:/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))+$/i',
            ],
        ], [
            'domain.regex' => 'Format domain tidak valid (contoh yang benar: contoh.com).',
        ]);

        $domain = strtolower(trim($data['domain']));

        // Domain harus beneran punya record MX aktif (bisa nerima email),
        // bukan cuma domain ngasal yang formatnya kebetulan valid.
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            throw ValidationException::withMessages([
                'domain' => ['Domain ini sepertinya tidak aktif/tidak bisa menerima email. Periksa lagi penulisannya.'],
            ]);
        }

        $daftar = self::daftarDomainEmail();

        if (in_array($domain, $daftar, true)) {
            throw ValidationException::withMessages([
                'domain' => ['Domain ini sudah ada di daftar.'],
            ]);
        }

        $daftar[] = $domain;
        self::simpanDaftarDomainEmail($daftar);

        return response()->json(['message' => 'Domain berhasil ditambahkan.', 'domains' => $daftar], 201);
    }

    // DELETE /api/pengaturan/domain-email - hapus 1 domain, khusus admin
    public function hapusDomainEmail(Request $request)
    {
        $data = $request->validate(['domain' => 'required|string']);
        $domain = strtolower(trim($data['domain']));

        $daftar = self::daftarDomainEmail();

        if (!in_array($domain, $daftar, true)) {
            throw ValidationException::withMessages([
                'domain' => ['Domain ini tidak ada di daftar.'],
            ]);
        }

        // Minimal harus tetap ada 1 domain, kalau tidak admin bisa
        // mengunci diri sendiri (gak ada domain valid buat bikin akun baru).
        if (count($daftar) <= 1) {
            throw ValidationException::withMessages([
                'domain' => ['Minimal harus ada 1 domain yang diizinkan.'],
            ]);
        }

        $daftar = array_values(array_filter($daftar, fn ($d) => $d !== $domain));
        self::simpanDaftarDomainEmail($daftar);

        return response()->json(['message' => 'Domain berhasil dihapus.', 'domains' => $daftar]);
    }
}
