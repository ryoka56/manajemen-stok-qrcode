<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Sekarang petugas juga boleh upload foto_1/2/3 (sebelumnya khusus admin),
    // jadi kita butuh catat SIAPA yang upload & KAPAN, supaya bisa ditampilkan
    // di UI ("Diupload oleh: <nama> - <tanggal>"). Disimpan sebagai kolom biasa
    // (bukan lewat relasi user_id) karena lebih sederhana untuk ditampilkan
    // langsung & tetap valid meski akun uploader-nya nanti dihapus.
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('foto_1_oleh')->nullable()->after('foto_1');
            $table->timestamp('foto_1_pada')->nullable()->after('foto_1_oleh');
            $table->string('foto_2_oleh')->nullable()->after('foto_2');
            $table->timestamp('foto_2_pada')->nullable()->after('foto_2_oleh');
            $table->string('foto_3_oleh')->nullable()->after('foto_3');
            $table->timestamp('foto_3_pada')->nullable()->after('foto_3_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['foto_1_oleh', 'foto_1_pada', 'foto_2_oleh', 'foto_2_pada', 'foto_3_oleh', 'foto_3_pada']);
        });
    }
};
