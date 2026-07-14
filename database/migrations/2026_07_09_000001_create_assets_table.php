<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('kode_aset')->unique(); // isi konten di dalam QR-code, mis. AST-0001
            $table->string('nama_barang');
            $table->string('kategori'); // mis. Elektronik, Furnitur, ATK, dst
            $table->text('deskripsi')->nullable();
            $table->string('ruangan_asal')->nullable(); // lokasi awal / rak gudang
            $table->string('status')->default('tersedia'); // tersedia, dipinjam, rusak, dll
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
