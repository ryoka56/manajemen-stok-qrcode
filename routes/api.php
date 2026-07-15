<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\ScanLogController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sistem Informasi Manajemen Aset Digital Berbasis QR-Code
|--------------------------------------------------------------------------
*/

// ---------- Publik (tidak perlu login) ----------
Route::post('/login', [AuthController::class, 'login']);

// ---------- Wajib login (admin & petugas) ----------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Lihat daftar barang & detail: admin & petugas boleh
    Route::get('/assets', [AssetController::class, 'index']);
    Route::get('/assets/{asset}', [AssetController::class, 'show']);
    Route::get('/assets/{asset}/qrcode', [AssetController::class, 'qrcode']);
    Route::get('/assets/scan/{kode_aset}', [AssetController::class, 'scan']);

    // Scan/pinjam barang: admin & petugas boleh
    Route::get('/scan-logs', [ScanLogController::class, 'index']);
    Route::post('/scan-logs', [ScanLogController::class, 'store']);
    Route::get('/scan-logs/peta', [ScanLogController::class, 'peta']);

    // ---------- Khusus admin ----------
    Route::middleware('admin')->group(function () {
        Route::post('/assets', [AssetController::class, 'store']);
        Route::put('/assets/{asset}', [AssetController::class, 'update']);
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);

        Route::get('/reports/excel', [ReportController::class, 'exportExcel']);
        Route::get('/reports/pdf', [ReportController::class, 'exportPdf']);

        Route::get('/users', [AuthController::class, 'index']);
        Route::post('/users', [AuthController::class, 'store']);
        Route::delete('/users/{user}', [AuthController::class, 'destroy']);
    });
});
