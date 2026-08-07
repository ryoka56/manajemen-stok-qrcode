<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Kolom 'role' di users itu ENUM('admin','petugas') - Laravel gak punya
    // cara "rapi" nambah opsi ke ENUM lewat Blueprint (beda dari nambah
    // kolom baru), jadi harus ALTER TABLE mentah. 'divisi' = akun
    // penanggung jawab satu/lebih ruangan, portalnya digabung sama
    // Petugas (dibedakan di sisi app based nilai role ini).
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'petugas', 'divisi') NOT NULL DEFAULT 'petugas'");
    }

    public function down(): void
    {
        // Turunkan balik akun 'divisi' yang mungkin sudah dibuat jadi
        // 'petugas' dulu SEBELUM ganti definisi ENUM - kalau tidak, ALTER
        // TABLE bakal gagal karena ada baris dengan nilai yang gak lagi ada
        // di enum barunya.
        DB::table('users')->where('role', 'divisi')->update(['role' => 'petugas']);
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'petugas') NOT NULL DEFAULT 'petugas'");
    }
};
