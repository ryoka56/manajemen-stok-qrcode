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
    protected $appends = ['peminjam_saat_ini', 'foto_urls', 'foto_scan_url', 'foto_slot_info'];

    public function getPeminjamSaatIniAttribute()
    {
        if ($this->status !== 'dipinjam') {
            return null;
        }
        return $this->relationLoaded('lokasiTerakhir')
            ? $this->lokasiTerakhir?->nama_peminjam
            : $this->lokasiTerakhir()->first()?->nama_peminjam;
    }

    // Foto AKTIF paling baru dari riwayat scan petugas, TANPA peduli slot -
    // dipakai buat panel ringkas "Foto dari Petugas" di Kelola Barang.
    // Beda dengan getFotoUrlsAttribute() di bawah yang integrasinya per-slot.
    public function getFotoScanUrlAttribute(): ?string
    {
        $log = ScanLog::where('asset_id', $this->id)
            ->whereNotNull('foto')
            ->where('foto_aktif', true)
            ->orderByDesc('scanned_at')
            ->first();

        return $log?->foto_url;
    }

    // Ambil scan_log AKTIF terbaru buat tiap slot (1,2,3) yang punya foto -
    // dipakai bareng oleh getFotoUrlsAttribute() & getFotoSlotInfoAttribute()
    // biar gak dobel query. Key = nomor slot, value = ScanLog.
    private function overridePetugasPerSlot(): array
    {
        return ScanLog::where('asset_id', $this->id)
            ->whereNotNull('slot')
            ->whereNotNull('foto')
            ->where('foto_aktif', true)
            ->orderByDesc('scanned_at')
            ->get()
            ->groupBy('slot')
            ->map(fn ($grup) => $grup->first()) // udah urut terbaru dulu -> ambil yang paling baru per slot
            ->all();
    }

    // URL publik lengkap buat tiap slot foto (null kalau slot itu kosong).
    // Kolom database cuma nyimpen path relatif (mis. "asset-photos/xxx.jpg"),
    // jadi Flutter butuh URL lengkap buat nampilin gambarnya.
    //
    // Tiap slot dicek DULU apakah ada foto AKTIF dari petugas yang lagi
    // "menempati" slot itu (lihat overridePetugasPerSlot()) - kalau ada,
    // itu yang ditampilkan. Kalau gak ada (belum pernah ditempati petugas,
    // atau lagi dinonaktifkan admin), baru fallback ke foto ASLI slot itu
    // (assets.foto_1/2/3 - yang gak pernah kesentuh/ketimpa oleh proses ini).
    //
    // Diarahkan ke route /api/foto/{path} (lewat kode Laravel, bukan file
    // statis langsung), biar responsenya bisa dikasih header CORS -
    // dibutuhkan Flutter Web yang ngambil gambar pakai fetch/XHR, bukan
    // tag <img> biasa yang gak butuh CORS. url() otomatis pakai https
    // berkat URL::forceScheme() di AppServiceProvider.
    public function getFotoUrlsAttribute()
    {
        $buat = fn ($path) => $path ? url('/api/foto/' . ltrim($path, '/')) : null;
        $override = $this->overridePetugasPerSlot();

        return [
            'foto_1' => isset($override[1]) ? $override[1]->foto_url : $buat($this->foto_1),
            'foto_2' => isset($override[2]) ? $override[2]->foto_url : $buat($this->foto_2),
            'foto_3' => isset($override[3]) ? $override[3]->foto_url : $buat($this->foto_3),
        ];
    }

    // Info tambahan per slot: apakah slot itu lagi ditempati foto petugas
    // (bukan foto asli), dan kapan diupload - dipakai frontend buat nampilin
    // badge "Dari petugas • [tanggal]" di grid foto Kelola Barang.
    public function getFotoSlotInfoAttribute()
    {
        $override = $this->overridePetugasPerSlot();

        $buat = function (int $slot) use ($override) {
            if (isset($override[$slot])) {
                return [
                    'dari_petugas' => true,
                    'diupload_pada' => $override[$slot]->scanned_at,
                    'scan_log_id' => $override[$slot]->id,
                ];
            }
            return ['dari_petugas' => false, 'diupload_pada' => null, 'scan_log_id' => null];
        };

        return ['foto_1' => $buat(1), 'foto_2' => $buat(2), 'foto_3' => $buat(3)];
    }
}
