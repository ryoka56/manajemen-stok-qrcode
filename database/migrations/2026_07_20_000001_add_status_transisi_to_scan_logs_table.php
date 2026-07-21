<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Status barang SEBELUM scan ini terjadi (untuk tahu jenis transisinya).
            $table->string('status_sebelum')->nullable()->after('status_saat_itu');

            // true HANYA kalau transisinya (tersedia/rusak) -> dipinjam.
            // Dipakai supaya statistik "peminjaman" tidak ikut naik ketika
            // status cuma berubah tersedia <-> rusak (bukan aksi pinjam).
            $table->boolean('is_peminjaman')->default(false)->after('status_sebelum');

            // true kalau transisinya dipinjam -> (tersedia/rusak), yaitu aksi pengembalian.
            $table->boolean('is_pengembalian')->default(false)->after('is_peminjaman');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn(['status_sebelum', 'is_peminjaman', 'is_pengembalian']);
        });
    }
};
