<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Index buat kolom yang sering dipakai WHERE/filter/JOIN. Belum kerasa
    // bedanya waktu data masih ratusan baris, tapi begitu data assets/scan_logs
    // mulai ribuan-puluhan ribu baris, query filter kategori/ruangan/status
    // atau riwayat per-barang/per-petugas bakal jauh lebih cepat dengan index ini.
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->index('kategori');
            $table->index('ruangan_asal');
            $table->index('status');
        });

        Schema::table('scan_logs', function (Blueprint $table) {
            $table->index('asset_id');
            $table->index('user_id');
            $table->index('is_peminjaman');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['kategori']);
            $table->dropIndex(['ruangan_asal']);
            $table->dropIndex(['status']);
        });

        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex(['asset_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['is_peminjaman']);
        });
    }
};
