<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Slot foto (1, 2, atau 3) yang "diambil alih" foto ini dari
            // barang - null kalau scan ini gak ada fotonya, ATAU kalau
            // ternyata udah gak ada slot kosong pas diupload (ditolak,
            // gak sampai kesimpan). Nilai 1/2/3 ini merujuk ke kolom
            // foto_1/foto_2/foto_3 di tabel assets.
            //
            // PENTING: kolom foto_1/2/3 di assets TIDAK PERNAH ditimpa oleh
            // proses ini - dia tetap nyimpen foto ASLI (biasanya upload
            // admin) apa adanya. Yang "ketutupan" itu cuma TAMPILANNYA:
            // kalau ada scan_log dengan slot=N dan foto_aktif=true, DIA yang
            // ditampilkan buat slot N (bukan assets.foto_N). Begitu
            // foto_aktif dimatiin admin, otomatis balik nampilin
            // assets.foto_N lagi - gak perlu kolom "foto lama" terpisah,
            // karena foto lamanya emang gak pernah dihapus dari awal.
            $table->unsignedTinyInteger('slot')->nullable()->after('foto_aktif');
            $table->index(['asset_id', 'slot', 'foto_aktif']);
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex(['asset_id', 'slot', 'foto_aktif']);
            $table->dropColumn('slot');
        });
    }
};
