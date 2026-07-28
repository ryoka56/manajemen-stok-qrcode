<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 3 slot foto per barang. Disimpan sebagai path relatif di storage
    // (bukan base64 di kolom), biar database gak bengkak - filenya beneran
    // taruh di disk 'public' Laravel, kolom ini cuma nyimpen path-nya.
    // Kolom 'foto' (tunggal) dari migration awal belum pernah kepakai,
    // jadi di-rename jadi 'foto_1' aja daripada nambah kolom baru lagi.
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('foto', 'foto_1');
        });
        Schema::table('assets', function (Blueprint $table) {
            $table->string('foto_2')->nullable();
            $table->string('foto_3')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['foto_2', 'foto_3']);
        });
        Schema::table('assets', function (Blueprint $table) {
            $table->renameColumn('foto_1', 'foto');
        });
    }
};
