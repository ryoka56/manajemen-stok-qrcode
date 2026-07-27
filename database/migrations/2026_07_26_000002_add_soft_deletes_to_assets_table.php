<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Soft delete: barang yang "dihapus" cuma ditandai deleted_at, gak
    // langsung hilang dari database. Bisa direstore kalau admin salah pencet
    // (apalagi sekarang ada fitur hapus banyak sekaligus, resikonya lebih
    // besar kalau salah pilih). Baru benar-benar hilang kalau di-forceDelete.
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
