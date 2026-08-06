<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\PerubahanController;
use App\Http\Controllers\Api\ScanLogController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RuanganController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\PegawaiController;
use App\Http\Controllers\Api\StatistikController;
use App\Http\Controllers\Api\PengaturanController;
use Illuminate\Support\Facades\Route;

// ---------- Publik ----------
Route::post('/login', [AuthController::class, 'login']);
Route::get('/foto/{path}', [AssetController::class, 'tampilkanFoto'])->where('path', '.*');
// Tanda tangan digital disimpan & dilayani sama persis seperti foto barang
// (butuh header CORS manual, lihat komentar di AssetController::tampilkanFoto).
Route::get('/tanda-tangan/{path}', [AssetController::class, 'tampilkanFoto'])->where('path', '.*');

// ---------- Laporan (Excel/PDF) ----------
// Dibuka lewat browser baru (bukan dari dalam app), jadi tokennya dikirim
// lewat query string (?token=...). Middleware 'token.query' sekarang
// memvalidasi token itu SENDIRI (lihat komentar di TokenFromQueryString),
// jadi tidak perlu 'auth:sanctum' lagi di sini - malah bisa bentrok karena
// guard-nya beda (auth:sanctum spesifik guard 'sanctum', sedangkan
// TokenFromQueryString set user di guard default).
// Sengaja TIDAK dibatasi 'admin' - tombol export ada juga di layar petugas
// (home_screen.dart), jadi siapa saja yang sudah login boleh mengunduh.
Route::middleware(['token.query'])->group(function () {
    Route::get('/reports/excel', [ReportController::class, 'exportExcel']);
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);
});

// ---------- Wajib login ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/assets', [AssetController::class, 'index']);
    // Static path HARUS di atas /assets/{asset} (route matching dari atas ke bawah),
    // kalau tidak "rekap"/"trash"/"scan"/"bulk" bakal ketangkep sebagai parameter {asset}.
    //
    // Tambah & edit barang (field teks) sekarang boleh admin & petugas
    // KEDUANYA - petugas tidak lagi lewat alur usulan buat ini (langsung
    // diterapkan + dicatat sebagai riwayat, lihat AssetController). Yang
    // MASIH admin-only: rekap, sampah/restore, hapus permanen, hapus barang,
    // hapus bulk, dan upload/hapus foto LANGSUNG (foto dari petugas tetap
    // lewat /perubahan yang butuh ACC - lihat PerubahanController).
    Route::post('/assets', [AssetController::class, 'store']);
    Route::put('/assets/{asset}', [AssetController::class, 'update']);
    Route::middleware('admin')->group(function () {
        Route::get('/assets/rekap', [AssetController::class, 'rekap']);
        Route::get('/assets/trash', [AssetController::class, 'trash']);
        Route::post('/assets/{id}/restore', [AssetController::class, 'restore'])->whereNumber('id');
        Route::delete('/assets/{id}/force', [AssetController::class, 'forceDelete'])->whereNumber('id');
        // Route bulk HARUS didaftarkan sebelum /assets/{asset} supaya "bulk"
        // tidak ketangkep sebagai parameter {asset} (route matching dari atas ke bawah).
        Route::delete('/assets/bulk', [AssetController::class, 'destroyBulk']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);
        Route::post('/assets/{asset}/foto', [AssetController::class, 'uploadFoto']);
        Route::delete('/assets/{asset}/foto/{slot}', [AssetController::class, 'hapusFoto']);
    });
    Route::get('/assets/scan/{kode_aset}', [AssetController::class, 'scan']);
    Route::get('/assets/{asset}', [AssetController::class, 'show']);
    Route::get('/assets/{asset}/qrcode', [AssetController::class, 'qrcode']);

    // Usulan FOTO dari petugas (satu-satunya yang masih butuh ACC admin -
    // lihat komentar di PerubahanController). index() & ajukanEdit() dibuka
    // untuk semua yang login - kontrol role (petugas vs admin) ditangani di
    // dalam controller-nya sendiri, sedangkan setujui()/tolak() memang khusus admin.
    Route::get('/perubahan', [PerubahanController::class, 'index']);
    Route::post('/perubahan', [PerubahanController::class, 'ajukanEdit']);
    Route::middleware('admin')->group(function () {
        Route::post('/perubahan/{perubahan}/setujui', [PerubahanController::class, 'setujui']);
        Route::post('/perubahan/{perubahan}/tolak', [PerubahanController::class, 'tolak']);
    });

    Route::get('/scan-logs', [ScanLogController::class, 'index']);
    Route::post('/scan-logs', [ScanLogController::class, 'store']);
    Route::get('/scan-logs/peta', [ScanLogController::class, 'peta']);

    // Master data - boleh dilihat admin & petugas (untuk pilihan dropdown)
    Route::get('/ruangans', [RuanganController::class, 'index']);
    // Dipakai layar Detail Ruangan setelah scan QR ruangan (fitur QR ruangan) - admin & petugas.
    Route::get('/ruangans/{ruangan}/barang', [RuanganController::class, 'barang']);
    Route::get('/kategoris', [KategoriController::class, 'index']);
    Route::get('/pegawais', [PegawaiController::class, 'index']);
    // Teks ketentuan/user agreement peminjaman - boleh dibaca admin & petugas
    Route::get('/pengaturan', [PengaturanController::class, 'show']);

    // ---------- Khusus admin ----------
    Route::middleware('admin')->group(function () {
        Route::get('/users', [AuthController::class, 'index']);
        Route::post('/users', [AuthController::class, 'store']);
        // PENTING: route PUT ini sebelumnya belum pernah didaftarkan sama sekali,
        // padahal AuthController::update() dan ApiService.updateUser() di Flutter
        // sudah ada - itu sebabnya edit akun (termasuk ganti password) SELALU
        // gagal (404) walau kodenya kelihatan benar. Ditambahkan di sini.
        Route::put('/users/{user}', [AuthController::class, 'update']);
        Route::delete('/users/{user}', [AuthController::class, 'destroy']);

        Route::post('/ruangans', [RuanganController::class, 'store']);
        Route::put('/ruangans/{ruangan}', [RuanganController::class, 'update']);
        Route::delete('/ruangans/{ruangan}', [RuanganController::class, 'destroy']);

        Route::post('/kategoris', [KategoriController::class, 'store']);
        Route::delete('/kategoris/{kategori}', [KategoriController::class, 'destroy']);
        Route::post('/pegawais', [PegawaiController::class, 'store']);
        Route::delete('/pegawais/{pegawai}', [PegawaiController::class, 'destroy']);

        Route::get('/scan-logs/statistik', [ScanLogController::class, 'statistik']);
        Route::get('/scan-logs/grafik-tahunan', [ScanLogController::class, 'grafikTahunan']);
        Route::get('/scan-logs/grafik', [ScanLogController::class, 'grafik']);
        // Kelola foto hasil scan petugas - khusus admin
        Route::put('/scan-logs/{scanLog}/foto', [ScanLogController::class, 'toggleFoto']);
        Route::delete('/scan-logs/{scanLog}/foto', [ScanLogController::class, 'hapusFoto']);

        // Ubah teks ketentuan peminjaman - khusus admin
        Route::put('/pengaturan', [PengaturanController::class, 'update']);
    });
});
