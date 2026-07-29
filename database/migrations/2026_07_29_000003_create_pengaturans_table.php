<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel konfigurasi umum aplikasi. Untuk sekarang cuma dipakai buat
        // simpan 1 baris teks ketentuan/user agreement peminjaman barang,
        // yang bisa diedit admin lewat Pengaturan. Sengaja dibikin tabel
        // key-value sederhana (bukan cuma 1 kolom di tabel lain) supaya
        // gampang ditambah pengaturan lain di masa depan tanpa migration baru.
        Schema::create('pengaturans', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed nilai default untuk teks ketentuan peminjaman
        \Illuminate\Support\Facades\DB::table('pengaturans')->insert([
            'key' => 'deskripsi_persetujuan',
            'value' => 'Dengan ini saya menyatakan bertanggung jawab penuh atas barang yang '
                . 'saya pinjam, akan menjaga dan merawat barang dengan baik, serta bersedia '
                . 'mengganti kerugian apabila terjadi kerusakan atau kehilangan yang '
                . 'diakibatkan oleh kelalaian saya.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturans');
    }
};
