<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Menyimpan status barang pada saat scan itu terjadi,
            // supaya riwayat tetap akurat meskipun status aset berubah lagi setelahnya.
            $table->string('status_saat_itu')->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn('status_saat_itu');
        });
    }
};
