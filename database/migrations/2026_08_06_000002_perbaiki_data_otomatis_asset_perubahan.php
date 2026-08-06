<?php

use App\Models\AssetPerubahan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    // Data-fix, BUKAN perubahan skema. Sebelum ini, kolom 'otomatis' belum
    // ada di $fillable AssetPerubahan, jadi setiap kali AssetController
    // (store/update) bikin baris audit trail dengan 'otomatis' => true,
    // nilainya diam-diam DIBUANG oleh mass-assignment guard Laravel dan
    // baris itu kesimpen dengan default kolom (false). Akibatnya semua
    // riwayat tambah/edit barang langsung nyasar dianggap "usulan foto"
    // (otomatis=false) padahal bukan, dan tab "Riwayat Aktivitas" (yang
    // filter otomatis=true) jadi selalu kosong.
    //
    // Baris yang KELIRU ditandai itu bisa dibedakan dari usulan foto asli:
    // usulan foto asli SELALU punya key 'foto' di data_usulan (lihat
    // PerubahanController::ajukanEdit) dan TIDAK PERNAH berjenis 'tambah'.
    // Jadi baris yang jenis='tambah' ATAU data_usulan-nya tidak mengandung
    // key 'foto' pasti hasil audit trail otomatis, bukan usulan foto -
    // ini yang dibetulkan jadi otomatis=true.
    public function up(): void
    {
        AssetPerubahan::where('otomatis', false)
            ->chunkById(200, function ($baris) {
                foreach ($baris as $p) {
                    $adalahUsulanFoto = $p->jenis === 'edit' && array_key_exists('foto', $p->data_usulan ?? []);
                    if (!$adalahUsulanFoto) {
                        $p->update(['otomatis' => true]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Sengaja tidak ada rollback data - tidak mungkin membedakan lagi
        // mana baris yang "aslinya" false vs yang dibetulkan di sini.
    }
};
