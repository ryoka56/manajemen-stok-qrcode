<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Satu akun divisi bisa tanggung jawab lebih dari 1 ruangan sekaligus
    // (admin yang menentukan lewat Kelola Akun) - makanya many-to-many,
    // bukan kolom ruangan_id tunggal di tabel users.
    public function up(): void
    {
        Schema::create('divisi_ruangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ruangan_id')->constrained('ruangans')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'ruangan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisi_ruangan');
    }
};
