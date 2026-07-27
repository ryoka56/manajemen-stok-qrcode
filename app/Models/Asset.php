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
        'foto',
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
    protected $appends = ['peminjam_saat_ini'];

    public function getPeminjamSaatIniAttribute()
    {
        if ($this->status !== 'dipinjam') {
            return null;
        }
        return $this->relationLoaded('lokasiTerakhir')
            ? $this->lokasiTerakhir?->nama_peminjam
            : $this->lokasiTerakhir()->first()?->nama_peminjam;
    }
}
