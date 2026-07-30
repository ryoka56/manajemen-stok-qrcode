<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Foto yang diambil petugas pas scan (opsional - petugas boleh
            // gak upload apa-apa). Path relatif di storage/app/public, sama
            // persis pola 'tanda_tangan'.
            $table->string('foto')->nullable()->after('ketentuan_snapshot');

            // Admin bisa "matikan" foto ini biar gak dipakai/ditampilkan
            // sebagai foto AKTIF barang, TANPA menghapus filenya - jadi
            // masih ada di riwayat, cuma gak jadi foto utama. Beda dengan
            // hapus permanen (itu baru bener-bener hilang filenya).
            // Default true supaya foto yang baru diupload petugas otomatis
            // aktif (langsung kepakai jadi foto terbaru).
            $table->boolean('foto_aktif')->default(true)->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn(['foto', 'foto_aktif']);
        });
    }
};
