<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            // Path file gambar tanda tangan digital (disimpan di storage/app/public,
            // sama seperti pola foto_1/foto_2/foto_3 pada tabel assets).
            // Wajib diisi kalau aksi ini adalah peminjaman (status jadi 'dipinjam').
            $table->string('tanda_tangan')->nullable()->after('catatan');

            // Centang persetujuan user agreement. Wajib true kalau meminjam.
            $table->boolean('setuju_ketentuan')->default(false)->after('tanda_tangan');

            // Salinan teks ketentuan PERSIS seperti yang ditampilkan & disetujui
            // saat itu. Disimpan terpisah dari tabel pengaturans supaya kalau
            // admin mengubah teks ketentuan di kemudian hari, riwayat lama tetap
            // menunjukkan versi ketentuan yang benar-benar disetujui peminjam.
            $table->text('ketentuan_snapshot')->nullable()->after('setuju_ketentuan');
        });
    }

    public function down(): void
    {
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn(['tanda_tangan', 'setuju_ketentuan', 'ketentuan_snapshot']);
        });
    }
};
