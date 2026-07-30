<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScanLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'user_id',
        'lokasi_input',
        'latitude',
        'longitude',
        'nama_petugas',
        'nama_peminjam',
        'catatan',
        'status_saat_itu',
        'status_sebelum',
        'is_peminjaman',
        'is_pengembalian',
        'tanda_tangan',
        'setuju_ketentuan',
        'ketentuan_snapshot',
        'foto',
        'foto_aktif',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_peminjaman' => 'boolean',
        'is_pengembalian' => 'boolean',
        'setuju_ketentuan' => 'boolean',
        'foto_aktif' => 'boolean',
    ];

    // URL lengkap gambar tanda tangan, lewat route /api/tanda-tangan/{path}
    // (biar dapat header CORS, sama seperti pola foto pada model Asset).
    protected $appends = ['tanda_tangan_url', 'foto_url'];

    public function getTandaTanganUrlAttribute(): ?string
    {
        if (!$this->tanda_tangan) {
            return null;
        }
        return url('/api/tanda-tangan/' . $this->tanda_tangan);
    }

    // Foto yang diambil petugas pas scan ini (kalau ada). Dilayani lewat
    // route /api/foto/{path} yang sudah generik (dipakai juga buat foto
    // barang di AssetController), tidak peduli file-nya di folder mana
    // di dalam storage/app/public.
    public function getFotoUrlAttribute(): ?string
    {
        if (!$this->foto) {
            return null;
        }
        return url('/api/foto/' . $this->foto);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
