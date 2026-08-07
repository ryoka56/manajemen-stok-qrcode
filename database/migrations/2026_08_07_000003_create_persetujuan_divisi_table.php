<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Riwayat persetujuan/penolakan user-agreement akun Divisi saat pertama
    // kali buka portal. Disimpan sebagai RIWAYAT (bukan 1 baris per user
    // yang di-update terus) supaya kalau admin ubah isi ketentuannya dan
    // divisi diminta setuju ulang, jejak yang lama tetap ada buat audit -
    // baris TERBARU per user yang dipakai buat nentuin status akses saat ini.
    public function up(): void
    {
        Schema::create('persetujuan_divisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['diterima', 'ditolak']);
            // Path file tanda tangan (sama pola dg scan_logs.tanda_tangan) -
            // nullable karena kalau status='ditolak' gak ada TTD sama sekali.
            $table->string('tanda_tangan')->nullable();
            $table->text('alasan_ditolak')->nullable();
            // Salinan PERSIS teks ketentuan pada saat itu - dipakai buat
            // bandingin apakah ketentuan yang berlaku SEKARANG masih sama
            // dengan yang terakhir disetujui user ini. Kalau beda (admin
            // sudah ubah), berarti user wajib setuju ulang.
            $table->text('konten_snapshot');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('persetujuan_divisi');
    }
};
