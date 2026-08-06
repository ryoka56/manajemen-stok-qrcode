<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPerubahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PerubahanController extends Controller
{
    // GET /api/perubahan
    // Dipakai buat DUA hal sekaligus (dibedakan lewat ?status= dan/atau
    // ?otomatis=):
    // - Persetujuan (admin): usulan FOTO yang beneran nunggu ACC, otomatis=false.
    // - Riwayat aktivitas (admin): baris otomatis=true, hasil tambah/edit
    //   langsung petugas (dan admin) - murni buat audit, bukan buat diklik ACC.
    // Petugas: cuma lihat baris yang DIA ajukan sendiri (buat cek status
    // usulan foto miliknya, atau riwayat perubahannya sendiri).
    public function index(Request $request)
    {
        $query = AssetPerubahan::with(['asset', 'pengaju:id,name', 'pemroses:id,name']);

        $user = $request->user();
        if (!$user->isAdmin()) {
            $query->where('diajukan_oleh', $user->id);
        } else {
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('otomatis')) {
                $query->where('otomatis', $request->boolean('otomatis'));
            }
        }

        return response()->json($query->latest()->paginate(20));
    }

    // POST /api/perubahan - petugas mengusulkan FOTO baru/pengganti buat
    // barang yang SUDAH ADA (baik lama, maupun baru saja ditambahkan lewat
    // AssetController::store). Ini SATU-SATUNYA hal yang masih butuh ACC
    // admin - field teks (nama/kategori/dst) sudah diterapkan langsung lewat
    // AssetController::update, tidak lewat sini lagi.
    // Body: asset_id (wajib) + file 'foto' + 'slot' (1-3).
    public function ajukanEdit(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Admin mengubah foto langsung lewat Kelola Barang, bukan lewat usulan.'], 403);
        }

        $data = $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'foto' => 'required|image|max:5120',
            'slot' => 'required|integer|in:1,2,3',
        ]);

        $asset = Asset::findOrFail($data['asset_id']);

        // Satu barang cuma boleh punya SATU usulan foto aktif (menunggu) di
        // satu waktu - kalau petugas upload lagi sebelum yg lama diproses,
        // ditolak dengan pesan jelas (sesuai keputusan: bukan ditimpa, bukan antre).
        $sudahAdaTertunda = AssetPerubahan::where('asset_id', $asset->id)->where('status', 'menunggu')->exists();
        if ($sudahAdaTertunda) {
            return response()->json([
                'message' => 'Barang ini masih punya usulan foto yang menunggu ACC admin. '
                    . 'Tunggu sampai disetujui/ditolak dulu sebelum mengajukan foto baru.',
            ], 409);
        }

        // Disimpan di disk terpisah ('asset-photos-pending') supaya TIDAK
        // langsung menimpa foto asli barang sebelum di-ACC admin.
        $path = $request->file('foto')->store('asset-photos-pending', 'public');

        $perubahan = AssetPerubahan::create([
            'asset_id' => $asset->id,
            'jenis' => 'edit',
            'data_usulan' => ['foto' => ['slot' => (int) $data['slot'], 'path' => $path]],
            'data_lama' => null,
            'status' => 'menunggu',
            'otomatis' => false,
            'diajukan_oleh' => $user->id,
        ]);

        return response()->json($perubahan->load('asset'), 201);
    }

    // POST /api/perubahan/{perubahan}/setujui - admin only (lihat middleware route)
    public function setujui(Request $request, AssetPerubahan $perubahan)
    {
        if ($perubahan->status !== 'menunggu') {
            return response()->json(['message' => 'Usulan ini sudah diproses sebelumnya.'], 409);
        }

        DB::transaction(function () use ($perubahan, $request) {
            $usulan = $perubahan->data_usulan;
            $foto = $usulan['foto'] ?? null;
            unset($usulan['foto']);

            if ($perubahan->jenis === 'tambah') {
                $usulan['kode_aset'] = 'AST-' . str_pad((Asset::withTrashed()->max('id') + 1), 6, '0', STR_PAD_LEFT);
                $asset = Asset::create($usulan);
            } else {
                $asset = Asset::findOrFail($perubahan->asset_id);
                $asset->update($usulan);
            }

            if ($foto) {
                $kolom = 'foto_' . $foto['slot'];
                if ($asset->$kolom) {
                    Storage::disk('public')->delete($asset->$kolom);
                }
                // Pindahkan file dari disk pending ke disk asli assets (bukan cuma
                // rename path) supaya konsisten dengan foto yang diupload admin.
                $isi = Storage::disk('public')->get($foto['path']);
                $pathBaru = 'asset-photos/' . basename($foto['path']);
                Storage::disk('public')->put($pathBaru, $isi);
                Storage::disk('public')->delete($foto['path']);

                $asset->update([
                    $kolom => $pathBaru,
                    $kolom . '_oleh' => $perubahan->pengaju?->name,
                    $kolom . '_pada' => now(),
                ]);
            }

            $perubahan->update([
                'status' => 'disetujui',
                'diproses_oleh' => $request->user()->id,
                'diproses_pada' => now(),
            ]);
        });

        return response()->json($perubahan->fresh(['asset', 'pengaju', 'pemroses']));
    }

    // POST /api/perubahan/{perubahan}/tolak - admin only. Body: alasan (opsional)
    public function tolak(Request $request, AssetPerubahan $perubahan)
    {
        if ($perubahan->status !== 'menunggu') {
            return response()->json(['message' => 'Usulan ini sudah diproses sebelumnya.'], 409);
        }

        $data = $request->validate(['alasan' => 'nullable|string|max:500']);

        // Bersihkan file foto pending yang gak jadi dipakai, biar storage gak nyampah.
        $foto = $perubahan->data_usulan['foto'] ?? null;
        if ($foto && Storage::disk('public')->exists($foto['path'])) {
            Storage::disk('public')->delete($foto['path']);
        }

        $perubahan->update([
            'status' => 'ditolak',
            'alasan_ditolak' => $data['alasan'] ?? null,
            'diproses_oleh' => $request->user()->id,
            'diproses_pada' => now(),
        ]);

        return response()->json($perubahan->fresh(['asset', 'pengaju', 'pemroses']));
    }
}
