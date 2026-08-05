<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Menyimpan usulan tambah/edit barang dari petugas yang menunggu ACC
    // admin. Kelola Barang di portal petugas TIDAK langsung mengubah tabel
    // `assets` - semua perubahan (data barang & foto) mampir ke sini dulu.
    public function up(): void
    {
        Schema::create('asset_perubahan', function (Blueprint $table) {
            $table->id();

            // null kalau ini usulan TAMBAH barang baru (barangnya belum ada di tabel assets)
            $table->foreignId('asset_id')->nullable()->constrained('assets')->cascadeOnDelete();

            $table->enum('jenis', ['tambah', 'edit'])->default('edit');

            // Data yang diusulkan petugas, disimpan sebagai JSON supaya fleksibel
            // (bisa berisi nama_barang/kategori/deskripsi/ruangan_asal/kondisi,
            // dan/atau foto: {slot, path}).
            $table->json('data_usulan');

            // Snapshot data lama SEBELUM diubah (null kalau jenis = tambah) -
            // dipakai admin buat lihat perbandingan sebelum/sesudah waktu ACC.
            $table->json('data_lama')->nullable();

            $table->enum('status', ['menunggu', 'disetujui', 'ditolak'])->default('menunggu');
            $table->text('alasan_ditolak')->nullable();

            $table->foreignId('diajukan_oleh')->constrained('users')->cascadeOnDelete();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diproses_pada')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_perubahan');
    }
};
