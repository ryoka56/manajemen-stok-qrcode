<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pengaturan extends Model
{
    protected $fillable = ['key', 'value'];

    // Ambil satu nilai pengaturan by key, dengan default fallback kalau belum ada barisnya.
    public static function ambil(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    // Simpan/update satu nilai pengaturan by key (bikin baris baru kalau belum ada).
    public static function simpan(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
