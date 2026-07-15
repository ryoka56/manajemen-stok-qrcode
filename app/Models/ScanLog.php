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
        'catatan',
        'status_saat_itu',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
