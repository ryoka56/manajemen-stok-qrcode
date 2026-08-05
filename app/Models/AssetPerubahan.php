<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetPerubahan extends Model
{
    protected $table = 'asset_perubahan';

    protected $fillable = [
        'asset_id',
        'jenis',
        'data_usulan',
        'data_lama',
        'status',
        'alasan_ditolak',
        'diajukan_oleh',
        'diproses_oleh',
        'diproses_pada',
    ];

    protected $casts = [
        'data_usulan' => 'array',
        'data_lama' => 'array',
        'diproses_pada' => 'datetime',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function pengaju()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function pemroses()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
