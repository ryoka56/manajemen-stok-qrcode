<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersetujuanDivisi extends Model
{
    protected $table = 'persetujuan_divisi';

    protected $fillable = [
        'user_id',
        'status',
        'tanda_tangan',
        'alasan_ditolak',
        'konten_snapshot',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
