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

        if (!empty($data['expected_role']) && $user->role !== $data['expected_role']) {
            $pesan = $data['expected_role'] === 'admin'
                ? 'Akun ini bukan akun admin. Gunakan link login petugas.'
                : 'Akun ini adalah akun admin. Gunakan link login admin.';
            throw ValidationException::withMessages([
                'email' => [$pesan],
            ]);
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
    // Tinggal tambah/hapus item di sini kalau admin mau buka/tutup provider lain.
    private const DOMAIN_EMAIL_DIIZINKAN = [
        'gmail.com',
        'yahoo.com',
        'yahoo.co.id',
        'outlook.com',
        'hotmail.com',
        'live.com',
        'icloud.com',
        'proton.me',
        'komdigi.go.id',
        'protonmail.com',
    ];

    // Aturan email: wajib pakai alamat email asli dari provider yang
    // terverifikasi (bukan asal ketik domain ngawur - rawan buat
    // keamanan). Dicek dua lapis:
    // 1) domainnya harus salah satu dari DOMAIN_EMAIL_DIIZINKAN,
    // 2) 'email:rfc,dns' mastiin domain itu beneran punya record MX aktif
    //    (jadi bukan cuma formatnya bener, tapi domainnya juga nyata).
    private function aturanEmailGmail(): array
    {
        $domainPola = implode('|', array_map(
            fn ($d) => preg_quote($d, '/'),
            self::DOMAIN_EMAIL_DIIZINKAN
        ));

        return [
            'required',
            'string',
            'email:rfc,dns',
            'max:150',
            'regex:/^[a-zA-Z0-9._%+-]+@(' . $domainPola . ')$/i',
        ];
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

    private array $pesanValidasiAkun = [
        'email.regex' => 'Email harus dari provider yang didukung: Gmail, Yahoo, Outlook, Hotmail, Live, iCloud, atau Proton (mis. nama@gmail.com).',
        'email.email' => 'Format email tidak valid atau domainnya tidak terdaftar.',
        'password.min' => 'Password minimal 8 karakter.',
        'password.regex' => 'Password minimal 8 karakter dan harus mengandung huruf serta angka.',
    ];

    // POST /api/users
    // Hanya admin yang boleh bikin akun petugas baru
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => array_merge($this->aturanEmailGmail(), ['unique:users,email']),
            'password' => $this->aturanPassword(),
            'role' => 'required|in:admin,petugas',
        ], $this->pesanValidasiAkun);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        return response()->json($user, 201);
    }

    // GET /api/users
    // Daftar semua akun petugas/admin (untuk dikelola admin)
    public function index()
    {
        return response()->json(User::orderBy('name')->get());
    }

    // PUT /api/users/{user}
    // Edit akun (nama, email, role, opsional ganti password) - khusus admin
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => array_merge($this->aturanEmailGmail(), ['unique:users,email,' . $user->id]),
            'password' => array_merge(['nullable'], $this->aturanPassword()),
            'role' => 'required|in:admin,petugas',
        ], $this->pesanValidasiAkun);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        return response()->json($user);
    }

    // DELETE /api/users/{user}
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'Akun berhasil dihapus']);
    }
}
