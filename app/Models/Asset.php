<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'kode_aset',
        'nama_barang',
        'kategori',
        'deskripsi',
        'ruangan_asal',
        'status',
        'foto_1',
        'foto_2',
        'foto_3',
    ];

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class);
    }

    // lokasi terakhir tercatat (berdasarkan scan terbaru)
    public function lokasiTerakhir()
    {
        return $this->hasOne(ScanLog::class)->latestOfMany('scanned_at');
    }

    // Nama peminjam SAAT INI - cuma terisi kalau status barang = dipinjam.
    // Ambil dari nama_peminjam di scan terakhir (karena status jadi 'dipinjam'
    // itu justru DI-SET oleh scan itu sendiri, jadi log terakhir = aksi pinjam
    // yang bikin status jadi begini). Gak perlu kolom baru di tabel assets.
    protected $appends = ['peminjam_saat_ini', 'foto_urls'];

    public function getPeminjamSaatIniAttribute()
    {
        if ($this->status !== 'dipinjam') {
            return null;
        }
        return $this->relationLoaded('lokasiTerakhir')
            ? $this->lokasiTerakhir?->nama_peminjam
            : $this->lokasiTerakhir()->first()?->nama_peminjam;
    }

    // URL publik lengkap buat tiap slot foto (null kalau slot itu kosong).
    // Kolom database cuma nyimpen path relatif (mis. "asset-photos/xxx.jpg"),
    // jadi Flutter butuh URL lengkap buat nampilin gambarnya.
    // URL publik lengkap buat tiap slot foto (null kalau slot itu kosong).
    // Diarahkan ke route /api/foto/{path} (lewat kode Laravel, bukan file
    // statis langsung), biar responsenya bisa dikasih header CORS -
    // dibutuhkan Flutter Web yang ngambil gambar pakai fetch/XHR, bukan
    // tag <img> biasa yang gak butuh CORS. url() otomatis pakai https
    // berkat URL::forceScheme() di AppServiceProvider.
    public function getFotoUrlsAttribute()
    {
        $buat = fn ($path) => $path ? url('/api/foto/' . ltrim($path, '/')) : null;

        return [
            'foto_1' => $buat($this->foto_1),
            'foto_2' => $buat($this->foto_2),
            'foto_3' => $buat($this->foto_3),
        ];
    }
}
