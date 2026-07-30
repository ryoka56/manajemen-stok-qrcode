<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Dipakai buat filter di layar Kelola Barang & Tinjauan
            // (?status=..., ?kategori=..., ?ruangan=...). Tanpa index,
            // MySQL harus scan semua baris tiap kali difilter - masih
            // kerasa cepat di 700 baris, tapi bakal melambat kalau
            // datanya nambah terus.
            $table->index('status');
            $table->index('kategori');
            $table->index('ruangan_asal');
        });

        Schema::table('scan_logs', function (Blueprint $table) {
            // Kombinasi (asset_id, scanned_at) ini yang dipakai query
            // "ambil scan TERAKHIR tiap barang" (relasi lokasiTerakhir
            // pakai latestOfMany). Ini query yang paling sering jalan -
            // muncul di HAMPIR SEMUA layar (daftar barang, riwayat, peta,
            // laporan). scan_logs juga tabel yang paling cepat tumbuh
            // sekarang (tiap aksi scan wajib bikin baris baru, apapun
            // statusnya), jadi index ini penting buat jaga-jaga ke depan.
            $table->index(['asset_id', 'scanned_at']);

            // Dipakai query cascade rename ruangan (lihat RuanganController)
            // dan filter riwayat per lokasi.
            $table->index('lokasi_input');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['kategori']);
            $table->dropIndex(['ruangan_asal']);
        });

        Schema::table('scan_logs', function (Blueprint $table) {
            $table->dropIndex(['asset_id', 'scanned_at']);
            $table->dropIndex(['lokasi_input']);
        });
    }
};
