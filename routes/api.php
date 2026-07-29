<?php

use App\Http\Controllers\Api\AssetController;
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
// lewat query string (?token=...), makanya middleware 'token.query' harus
// jalan LEBIH DULU sebelum 'auth:sanctum' supaya headernya sempat disalin.
// Sengaja TIDAK dibatasi 'admin' - tombol export ada juga di layar petugas
// (home_screen.dart), jadi siapa saja yang sudah login boleh mengunduh.
Route::middleware(['token.query', 'auth:sanctum'])->group(function () {
    Route::get('/reports/excel', [ReportController::class, 'exportExcel']);
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);
});

// ---------- Wajib login ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/assets', [AssetController::class, 'index']);
    // Static path HARUS di atas /assets/{asset} (route matching dari atas ke bawah),
    // kalau tidak "rekap"/"trash"/"scan" bakal ketangkep sebagai parameter {asset}.
    Route::middleware('admin')->group(function () {
        Route::get('/assets/rekap', [AssetController::class, 'rekap']);
        Route::get('/assets/trash', [AssetController::class, 'trash']);
        Route::post('/assets/{id}/restore', [AssetController::class, 'restore'])->whereNumber('id');
        Route::delete('/assets/{id}/force', [AssetController::class, 'forceDelete'])->whereNumber('id');
    });
    Route::get('/assets/scan/{kode_aset}', [AssetController::class, 'scan']);
    Route::get('/assets/{asset}', [AssetController::class, 'show']);
    Route::get('/assets/{asset}/qrcode', [AssetController::class, 'qrcode']);

    Route::get('/scan-logs', [ScanLogController::class, 'index']);
    Route::post('/scan-logs', [ScanLogController::class, 'store']);
    Route::get('/scan-logs/peta', [ScanLogController::class, 'peta']);

    // Master data - boleh dilihat admin & petugas (untuk pilihan dropdown)
    Route::get('/ruangans', [RuanganController::class, 'index']);
    Route::get('/kategoris', [KategoriController::class, 'index']);
    Route::get('/pegawais', [PegawaiController::class, 'index']);
    // Teks ketentuan/user agreement peminjaman - boleh dibaca admin & petugas
    Route::get('/pengaturan', [PengaturanController::class, 'show']);

    // ---------- Khusus admin ----------
    Route::middleware('admin')->group(function () {
        Route::post('/assets', [AssetController::class, 'store']);
        // Route bulk HARUS didaftarkan sebelum /assets/{asset} supaya "bulk"
        // tidak ketangkep sebagai parameter {asset} (route matching dari atas ke bawah).
        Route::delete('/assets/bulk', [AssetController::class, 'destroyBulk']);
        Route::put('/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);
        Route::post('/assets/{asset}/foto', [AssetController::class, 'uploadFoto']);
        Route::delete('/assets/{asset}/foto/{slot}', [AssetController::class, 'hapusFoto']);

        Route::get('/users', [AuthController::class, 'index']);
        Route::post('/users', [AuthController::class, 'store']);
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

        // Ubah teks ketentuan peminjaman - khusus admin
        Route::put('/pengaturan', [PengaturanController::class, 'update']);
    });
});
