<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Nama orang yang meminjam/mengambil barang - beda dari nama_petugas
            // (nama_petugas = akun yang login & melakukan scan)
            $table->string('nama_peminjam')->nullable()->after('nama_petugas');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn('nama_peminjam');
        });
    }
};
