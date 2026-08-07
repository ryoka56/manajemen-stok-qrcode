<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isDivisi(): bool
    {
        return $this->role === 'divisi';
    }

    public function scanLogs()
    {
        return $this->hasMany(ScanLog::class);
    }

    // Ruangan yang jadi tanggung jawab akun divisi ini (bisa lebih dari 1,
    // ditentukan admin lewat Kelola Akun). Kosong/tidak relevan untuk
    // role admin/petugas.
    public function ruangans()
    {
        return $this->belongsToMany(Ruangan::class, 'divisi_ruangan')->withTimestamps();
    }

    public function persetujuanDivisi()
    {
        return $this->hasMany(PersetujuanDivisi::class);
    }
}
