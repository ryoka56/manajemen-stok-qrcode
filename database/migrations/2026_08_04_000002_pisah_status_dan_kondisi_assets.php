<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // SEBELUM: kolom `status` di assets nyampur 2 konsep sekaligus dalam
    // 1 nilai ('tersedia' | 'dipinjam' | 'rusak') - gak bisa nunjukin
    // barang yang RUSAK tapi lagi DIPINJAM di saat bersamaan.
    //
    // SESUDAH: dipecah jadi 2 kolom independen:
    //   - status  : 'ada'      | 'dipinjam'   -> lagi di gudang / lagi dipakai
    //   - kondisi : 'tersedia' | 'rusak'      -> kondisi fisik barangnya
    // Kombinasi bebas (barang rusak boleh tetap dipinjam, sesuai keputusan).
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('kondisi')->default('tersedia')->after('status');
        });

        // Migrasi data lama: pindahkan info 'rusak' ke kolom kondisi,
        // lalu normalisasi status jadi cuma 'ada'/'dipinjam'.
        DB::table('assets')->where('status', 'rusak')->update(['kondisi' => 'rusak', 'status' => 'ada']);
        DB::table('assets')->where('status', 'tersedia')->update(['status' => 'ada']);
        // 'dipinjam' dibiarkan apa adanya, kondisinya default 'tersedia' (gak ada info rusak sebelumnya).

        Schema::table('assets', function (Blueprint $table) {
            $table->index('kondisi');
        });

        // scan_logs juga butuh kolom kondisi terpisah, biar riwayat tetap akurat
        // (dulu cuma nyatet status_saat_itu/status_sebelum yg nyampur).
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->string('kondisi_saat_itu')->nullable()->after('status_sebelum');
            $table->string('kondisi_sebelum')->nullable()->after('kondisi_saat_itu');
        });

        // Normalisasi data lama di scan_logs juga, biar konsisten dgn assets di atas.
        DB::table('scan_logs')->where('status_saat_itu', 'rusak')->update(['kondisi_saat_itu' => 'rusak', 'status_saat_itu' => 'ada']);
        DB::table('scan_logs')->where('status_saat_itu', 'tersedia')->update(['status_saat_itu' => 'ada']);
        DB::table('scan_logs')->where('status_sebelum', 'rusak')->update(['kondisi_sebelum' => 'rusak', 'status_sebelum' => 'ada']);
        DB::table('scan_logs')->where('status_sebelum', 'tersedia')->update(['status_sebelum' => 'ada']);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['kondisi']);
            $table->dropColumn('kondisi');
        });
        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropColumn(['kondisi_saat_itu', 'kondisi_sebelum']);
        });
    }
};
