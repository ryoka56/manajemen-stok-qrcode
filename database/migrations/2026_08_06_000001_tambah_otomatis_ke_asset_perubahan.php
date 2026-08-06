<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Menandai baris asset_perubahan yang dibuat OTOMATIS oleh sistem saat
    // admin/petugas tambah/edit barang LANGSUNG lewat Kelola Barang (bukan
    // usulan yang diklik ACC). Baris begini status-nya langsung 'disetujui'
    // dari awal - kolom ini yang membedakan dari usulan FOTO asli (yang
    // beneran menunggu diklik setuju/tolak admin).
    // false (default) = usulan asli (foto). true = riwayat/audit trail otomatis.
    public function up(): void
    {
        Schema::table('asset_perubahan', function (Blueprint $table) {
            $table->boolean('otomatis')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('asset_perubahan', function (Blueprint $table) {
            $table->dropColumn('otomatis');
        });
    }
};
