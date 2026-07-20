<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\ScanLogController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RuanganController;
use App\Http\Controllers\Api\KategoriController;
use App\Http\Controllers\Api\StatistikController;
use Illuminate\Support\Facades\Route;

// ---------- Publik ----------
Route::post('/login', [AuthController::class, 'login']);

// ---------- Laporan (Excel/PDF) ----------
// Dibuka lewat browser baru (bukan dari dalam app), jadi tokennya dikirim
// lewat query string (?token=...), makanya middleware 'token.query' harus
// jalan LEBIH DULU sebelum 'auth:sanctum' supaya headernya sempat disalin.
Route::middleware(['token.query', 'auth:sanctum', 'admin'])->group(function () {
    Route::get('/reports/excel', [ReportController::class, 'exportExcel']);
    Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);
});

// ---------- Wajib login ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{asset}', [AssetController::class, 'show']);
    Route::get('/assets/{asset}/qrcode', [AssetController::class, 'qrcode']);
    Route::get('/assets/scan/{kode_aset}', [AssetController::class, 'scan']);

    Route::get('/scan-logs', [ScanLogController::class, 'index']);
    Route::post('/scan-logs', [ScanLogController::class, 'store']);
    Route::get('/scan-logs/peta', [ScanLogController::class, 'peta']);

    // Master data - boleh dilihat admin & petugas (untuk pilihan dropdown)
    Route::get('/ruangans', [RuanganController::class, 'index']);
    Route::get('/kategoris', [KategoriController::class, 'index']);

    // ---------- Khusus admin ----------
    Route::middleware('admin')->group(function () {
        Route::post('/assets', [AssetController::class, 'store']);
        Route::put('/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);

        Route::get('/users', [AuthController::class, 'index']);
        Route::post('/users', [AuthController::class, 'store']);
        Route::delete('/users/{user}', [AuthController::class, 'destroy']);

        Route::post('/ruangans', [RuanganController::class, 'store']);
        Route::put('/ruangans/{ruangan}', [RuanganController::class, 'update']);
        Route::delete('/ruangans/{ruangan}', [RuanganController::class, 'destroy']);

        Route::post('/kategoris', [KategoriController::class, 'store']);
        Route::delete('/kategoris/{kategori}', [KategoriController::class, 'destroy']);

        Route::get('/scan-logs/statistik', [ScanLogController::class, 'statistik']);
        Route::get('/scan-logs/grafik-tahunan', [ScanLogController::class, 'grafikTahunan']);
        Route::get('/scan-logs/grafik', [ScanLogController::class, 'grafik']);
    });
});
