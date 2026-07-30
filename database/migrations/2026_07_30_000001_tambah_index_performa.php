<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lanjutan dari 2026_07_26_000001_add_indexes_for_performance.php.
    // Migration itu sudah nutupin: assets(kategori, ruangan_asal, status)
    // dan scan_logs(asset_id, user_id, is_peminjaman) - jadi TIDAK diulang
    // di sini (bikin error "index already exists" kalau diulang).
    // Ini nambahin yang belum ke-cover:
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Composite index (asset_id, scanned_at) - beda dari index
            // asset_id tunggal yang sudah ada. Ini yang dipakai relasi
            // lokasiTerakhir() (latestOfMany 'scanned_at') buat ambil scan
            // TERAKHIR tiap barang tanpa perlu extra sort - query yang
            // paling sering jalan, muncul di hampir semua layar.
            $table->index(['asset_id', 'scanned_at']);

            // Dipakai query cascade rename ruangan (lihat RuanganController)
            // dan filter riwayat per lokasi.
            $table->index('lokasi_input');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex(['asset_id', 'scanned_at']);
            $table->dropIndex(['lokasi_input']);
        });
    }
};
