<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersetujuanDivisi;
use App\Models\Pengaturan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersetujuanDivisiController extends Controller
{
    // GET /api/persetujuan-divisi
    // Dicek TIAP KALI portal divisi dibuka (bukan cuma sekali login) -
    // wajib setuju lagi kalau: belum pernah setuju sama sekali, terakhir
    // NOLAK, atau isi ketentuannya sudah diubah admin sejak persetujuan
    // terakhir (konten_snapshot beda dari konten sekarang).
    public function status(Request $request)
    {
        $user = $request->user();
        $konten = Pengaturan::ambil('deskripsi_persetujuan_divisi', '');

        $terakhir = PersetujuanDivisi::where('user_id', $user->id)->latest()->first();

        $butuhPersetujuan = !$terakhir
            || $terakhir->status !== 'diterima'
            || $terakhir->konten_snapshot !== $konten;

        return response()->json([
            'butuh_persetujuan' => $butuhPersetujuan,
            'konten' => $konten,
            'status_terakhir' => $terakhir?->status,
            'alasan_ditolak_terakhir' => $terakhir?->status === 'ditolak' ? $terakhir->alasan_ditolak : null,
        ]);
    }

    // POST /api/persetujuan-divisi
    // setuju=true wajib sertakan tanda_tangan. setuju=false wajib sertakan
    // alasan_ditolak, TIDAK ada tanda_tangan - user tetap terkunci di layar
    // persetujuan (butuh_persetujuan tetap true) sampai dia setuju.
    public function submit(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'setuju' => 'required|boolean',
            'tanda_tangan' => 'required_if:setuju,true|nullable|string',
            'alasan_ditolak' => 'required_if:setuju,false|nullable|string|max:1000',
        ]);

        $konten = Pengaturan::ambil('deskripsi_persetujuan_divisi', '');

        $pathTandaTangan = null;
        if ($data['setuju'] && !empty($data['tanda_tangan'])) {
            $pathTandaTangan = $this->simpanTandaTangan($data['tanda_tangan']);
        }

        $baris = PersetujuanDivisi::create([
            'user_id' => $user->id,
            'status' => $data['setuju'] ? 'diterima' : 'ditolak',
            'tanda_tangan' => $pathTandaTangan,
            'alasan_ditolak' => $data['setuju'] ? null : ($data['alasan_ditolak'] ?? null),
            'konten_snapshot' => $konten,
        ]);

        return response()->json($baris, 201);
    }

    // GET /api/persetujuan-divisi/riwayat - khusus admin, lihat semua
    // riwayat persetujuan/penolakan tiap akun divisi (termasuk alasan tolak).
    public function riwayat()
    {
        return response()->json(
            PersetujuanDivisi::with('user:id,name,email')->latest()->paginate(30)
        );
    }

    private function simpanTandaTangan(string $dataUrl): string
    {
        if (str_contains($dataUrl, ',')) {
            $dataUrl = explode(',', $dataUrl, 2)[1];
        }

        $isi = base64_decode($dataUrl);
        $namaFile = 'tanda-tangan/' . Str::uuid() . '.png';
        Storage::disk('public')->put($namaFile, $isi);

        return $namaFile;
    }
}
