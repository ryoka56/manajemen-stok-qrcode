<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\ScanLogController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Sistem Informasi Manajemen Aset Digital Berbasis QR-Code
|--------------------------------------------------------------------------
| Semua endpoint di bawah bisa ditambah middleware auth:sanctum
| kalau butuh login untuk petugas gudang.
*/

Route::prefix('assets')->group(function () {
    Route::get('/', [AssetController::class, 'index']);
    Route::post('/', [AssetController::class, 'store']);
    Route::get('/{asset}', [AssetController::class, 'show']);
    Route::put('/{asset}', [AssetController::class, 'update']);
    Route::delete('/{asset}', [AssetController::class, 'destroy']);
    Route::get('/{asset}/qrcode', [AssetController::class, 'qrcode']);
    Route::get('/scan/{kode_aset}', [AssetController::class, 'scan']);
});

Route::prefix('scan-logs')->group(function () {
    Route::get('/', [ScanLogController::class, 'index']);
    Route::post('/', [ScanLogController::class, 'store']);
    Route::get('/peta', [ScanLogController::class, 'peta']);
});

Route::prefix('reports')->group(function () {
    Route::get('/excel', [ReportController::class, 'exportExcel']);
    Route::get('/pdf', [ReportController::class, 'exportPdf']);
});
