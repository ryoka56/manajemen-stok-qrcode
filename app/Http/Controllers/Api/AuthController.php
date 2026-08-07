<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/login
    // $data['expected_role'] (opsional) dikirim dari app admin/petugas untuk
    // memastikan akun yang login memang punya role yang sesuai portalnya.
    // Ditolak di SERVER (bukan cuma dicek di app) supaya token sama sekali
    // tidak pernah diterbitkan untuk kombinasi portal+role yang salah.
    // Portal 'petugas' menerima DUA role: 'petugas' ATAUPUN 'divisi' -
    // keduanya berbagi portal yang sama (dibedakan tampilannya di app
    // berdasarkan role akun yang login), jadi expected_role tetap dikirim
    // 'petugas' dari app dan validasinya di bawah yang melonggarkan.
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'expected_role' => 'nullable|in:admin,petugas',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        if (!empty($data['expected_role'])) {
            // Portal 'petugas' menerima role 'petugas' MAUPUN 'divisi'.
            $cocok = $data['expected_role'] === 'admin'
                ? $user->role === 'admin'
                : in_array($user->role, ['petugas', 'divisi'], true);

            if (!$cocok) {
                $pesan = $data['expected_role'] === 'admin'
                    ? 'Akun ini bukan akun admin. Gunakan link login petugas.'
                    : 'Akun ini adalah akun admin. Gunakan link login admin.';
                throw ValidationException::withMessages([
                    'email' => [$pesan],
                ]);
            }
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil logout']);
    }

    // GET /api/me
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // Domain email yang diperbolehkan buat daftar/edit akun. Bukan cuma
    // Gmail - provider besar lain yang beneran ada & terverifikasi juga
    // diizinkan, tapi TETAP nolak domain asal-asalan/palsu.
    // Tinggal tambah/hapus item di sini kalau admin mau buka/tutup provider
    // lain. Daftarnya HARUS disamakan dengan _domainEmailDiizinkan di
    // pengaturan_screen.dart (Flutter) - dua-duanya validasi email yang sama,
    // cuma satu di server (wajib dipatuhi) satu di client (feedback cepat).
    private const DOMAIN_EMAIL_DIIZINKAN = [
        'gmail.com',
        'yahoo.com',
        'yahoo.co.id',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'icloud.com',
        'komdigi.go.id',
        'proton.me',
        'protonmail.com',
    ];

    // Aturan email: wajib pakai alamat email asli dari provider yang
    // terverifikasi (bukan asal ketik domain ngawur - rawan buat
    // keamanan). Dicek dua lapis:
    // 1) domainnya harus salah satu dari DOMAIN_EMAIL_DIIZINKAN,
    // 2) 'email:rfc,strict,dns' - beberapa validator email digabung:
    //    - rfc: format dasar sesuai standar RFC 5322
    //    - strict: lebih ketat dari rfc biasa (nolak hal aneh kayak titik
    //      berturut-turut, dsb yang lolos di validator 'rfc' biasa)
    //    - dns: domainnya harus punya record MX aktif (bukan cuma formatnya
    //      bener, tapi domainnya juga beneran ada & bisa nerima email)
    //    Sengaja TIDAK pakai validator 'spoof' - itu butuh ekstensi PHP
    //    'intl' yang belum aktif di server (Railway), dan kalau dipaksa
    //    malah bikin exception 500 setiap kali nambah/edit akun.
    private function aturanEmailGmail(): array
    {
        $domainPola = implode('|', array_map(
            fn ($d) => preg_quote($d, '/'),
            self::DOMAIN_EMAIL_DIIZINKAN
        ));

        return [
            'required',
            'string',
            'email:rfc,strict,dns',
            'max:150',
            'regex:/^[a-zA-Z0-9._%+-]+@(' . $domainPola . ')$/i',
        ];
    }

    // Gmail mengabaikan titik di local-part dan apapun setelah '+' (alias),
    // jadi "j.ohn.doe@gmail.com", "johndoe@gmail.com", dan
    // "johndoe+petugas@gmail.com" semuanya nyasar ke kotak masuk yang SAMA.
    // Tanpa normalisasi ini, satu orang bisa "asal-asalan" bikin banyak akun
    // yang keliatan beda padahal email tujuannya sama persis - atau
    // sebaliknya, mengaku pakai email tertentu padahal itu bukan alamat asli
    // yang dia kontrol penuh. Dipanggil sebelum validasi & sebelum disimpan,
    // supaya prosesnya konsisten (dicek dg bentuk yang sama, disimpan
    // dengan bentuk yang sama).
    private function normalisasiEmail(string $email): string
    {
        $email = strtolower(trim($email));

        if (!str_contains($email, '@')) {
            return $email;
        }

        [$lokal, $domain] = explode('@', $email, 2);

        if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
            $lokal = explode('+', $lokal)[0];   // buang alias setelah '+'
            $lokal = str_replace('.', '', $lokal); // titik diabaikan Gmail
            $domain = 'gmail.com';
        }

        return $lokal . '@' . $domain;
    }

    // Aturan password: minimal 8 karakter, dan wajib ada huruf & angkanya.
    private function aturanPassword(): array
    {
        return [
            'required',
            'string',
            'min:8',
            'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/', // wajib ada huruf & angka
        ];
    }

    // Sama seperti aturanPassword(), TAPI tanpa 'required' - dipakai waktu
    // edit akun, di mana password boleh dikosongkan/tidak dikirim sama
    // sekali (artinya "tidak diganti"). 'required' dan 'nullable' tidak
    // boleh digabung dalam satu rule set (saling bertentangan) - itu bug
    // sebelumnya yang bikin ganti password di akun yang sudah ada selalu
    // gagal walau passwordnya sudah diisi benar.
    private function aturanPasswordOpsional(): array
    {
        return [
            'string',
            'min:8',
            'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/',
        ];
    }

    private array $pesanValidasiAkun = [
        'email.regex' => 'Domain email ini tidak diizinkan. Cek daftar domain yang didukung di menu Pengaturan.',
        'email.email' => 'Format email tidak valid atau domainnya tidak terdaftar.',
        'password.min' => 'Password minimal 8 karakter.',
        'password.regex' => 'Password minimal 8 karakter dan harus mengandung huruf serta angka.',
    ];

    // POST /api/users
    // Hanya admin yang boleh bikin akun petugas/divisi baru. Untuk
    // role='divisi', 'ruangan_ids' WAJIB diisi (minimal 1 ruangan yang jadi
    // tanggung jawabnya) - divisi tanpa ruangan gak ada gunanya.
    public function store(Request $request)
    {
        if ($request->filled('email')) {
            $request->merge(['email' => $this->normalisasiEmail($request->input('email'))]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => array_merge($this->aturanEmailGmail(), ['unique:users,email']),
            'password' => $this->aturanPassword(),
            'role' => 'required|in:admin,petugas,divisi',
            'ruangan_ids' => 'required_if:role,divisi|array|min:1',
            'ruangan_ids.*' => 'integer|exists:ruangans,id',
        ], $this->pesanValidasiAkun);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        if ($data['role'] === 'divisi') {
            $user->ruangans()->sync($data['ruangan_ids']);
        }

        return response()->json($user->load('ruangans'), 201);
    }

    // GET /api/users
    // Daftar semua akun petugas/admin/divisi (untuk dikelola admin) -
    // sekalian bawa ruangan yang ditugaskan (relevan untuk role divisi).
    public function index()
    {
        return response()->json(User::with('ruangans')->orderBy('name')->get());
    }

    // PUT /api/users/{user}
    // Edit akun (nama, email, role, opsional ganti password, opsional ganti
    // ruangan yang ditanggungjawabkan kalau role='divisi') - khusus admin.
    public function update(Request $request, User $user)
    {
        if ($request->filled('email')) {
            $request->merge(['email' => $this->normalisasiEmail($request->input('email'))]);
        }

        // Password itu OPSIONAL saat edit (kosong/tidak dikirim = tidak diganti).
        // Sebelumnya rule password gabungan ['nullable', 'required', ...] -
        // 'nullable' cuma melewatkan validasi kalau nilainya benar-benar null,
        // padahal dari form Flutter yang dikirim adalah STRING KOSONG saat
        // pengguna tidak mau ganti password (lihat ApiService.updateUser: kunci
        // 'password' malah sengaja tidak disertakan sama sekali kalau kosong,
        // tapi kalau suatu saat dikirim string kosong tetap akan gagal kena
        // 'required'/'min:8'). Makanya di sini rule password HANYA diterapkan
        // kalau field-nya memang diisi (filled), sesuai instruksi 'sometimes'.
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => array_merge($this->aturanEmailGmail(), ['unique:users,email,' . $user->id]),
            'password' => array_merge(['sometimes', 'nullable'], $this->aturanPasswordOpsional()),
            'role' => 'required|in:admin,petugas,divisi',
            'ruangan_ids' => 'required_if:role,divisi|array|min:1',
            'ruangan_ids.*' => 'integer|exists:ruangans,id',
        ], $this->pesanValidasiAkun);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        // Sync ruangan tanggung jawab: kalau role-nya BUKAN divisi (lagi),
        // lepas semua assignment - misalnya admin turunkan role divisi jadi
        // petugas, jangan sampai relasi lama nyangkut di database.
        if ($data['role'] === 'divisi') {
            $user->ruangans()->sync($data['ruangan_ids']);
        } else {
            $user->ruangans()->sync([]);
        }

        return response()->json($user->load('ruangans'));
    }

    // DELETE /api/users/{user}
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'Akun berhasil dihapus']);
    }
}
