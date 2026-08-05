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
    // Field data barang yang boleh diusulkan petugas & disnapshot sebagai data_lama.
    // 'status' (ada/dipinjam) SENGAJA tidak termasuk - itu murni hasil alur
    // Scan, bukan sesuatu yang diusulkan lewat Kelola Barang.
    private const FIELD_BOLEH_DIUBAH = ['nama_barang', 'kategori', 'deskripsi', 'ruangan_asal', 'kondisi'];

    // GET /api/perubahan
    // Admin: lihat semua usulan (bisa difilter ?status=menunggu|disetujui|ditolak).
    // Petugas: cuma lihat usulan yang DIA ajukan sendiri (buat cek status/alasan ditolak).
    public function index(Request $request)
    {
        $query = AssetPerubahan::with(['asset', 'pengaju:id,name', 'pemroses:id,name']);

        $user = $request->user();
        if (!$user->isAdmin()) {
            $query->where('diajukan_oleh', $user->id);
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(20));
    }

    // POST /api/perubahan - petugas mengusulkan EDIT barang yang sudah ada.
    // Body: asset_id (wajib) + salah satu/lebih dari nama_barang/kategori/
    // deskripsi/ruangan_asal/kondisi, dan/atau file 'foto' + 'slot' (1-3).
    public function ajukanEdit(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Admin mengubah barang langsung lewat Kelola Barang, bukan lewat usulan.'], 403);
        }

        $data = $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'nama_barang' => 'sometimes|string|max:255',
            'kategori' => 'sometimes|string|max:100',
            'deskripsi' => 'nullable|string',
            'ruangan_asal' => 'nullable|string|max:100',
            'kondisi' => 'sometimes|in:tersedia,rusak',
            'foto' => 'nullable|image|max:5120',
            'slot' => 'required_with:foto|integer|in:1,2,3',
        ]);

        $asset = Asset::findOrFail($data['asset_id']);

        // Satu barang cuma boleh punya SATU usulan aktif (menunggu) di satu
        // waktu - kalau petugas edit lagi sebelum yg lama diproses, ditolak
        // dengan pesan jelas (sesuai keputusan: bukan ditimpa, bukan antre).
        $sudahAdaTertunda = AssetPerubahan::where('asset_id', $asset->id)->where('status', 'menunggu')->exists();
        if ($sudahAdaTertunda) {
            return response()->json([
                'message' => 'Barang ini masih punya usulan perubahan yang menunggu ACC admin. '
                    . 'Tunggu sampai disetujui/ditolak dulu sebelum mengajukan perubahan baru.',
            ], 409);
        }

        $usulan = collect($data)->only(self::FIELD_BOLEH_DIUBAH)->filter(fn ($v) => $v !== null)->all();

        if ($request->hasFile('foto')) {
            // Disimpan di disk terpisah ('asset-photos-pending') supaya TIDAK
            // langsung menimpa foto asli barang sebelum di-ACC admin.
            $path = $request->file('foto')->store('asset-photos-pending', 'public');
            $usulan['foto'] = ['slot' => (int) $data['slot'], 'path' => $path];
        }

        if (empty($usulan)) {
            return response()->json(['message' => 'Tidak ada perubahan yang diajukan.'], 422);
        }

        $dataLama = collect($asset->only(self::FIELD_BOLEH_DIUBAH))->all();

        $perubahan = AssetPerubahan::create([
            'asset_id' => $asset->id,
            'jenis' => 'edit',
            'data_usulan' => $usulan,
            'data_lama' => $dataLama,
            'status' => 'menunggu',
            'diajukan_oleh' => $user->id,
        ]);

        return response()->json($perubahan->load('asset'), 201);
    }

    // POST /api/perubahan/tambah - petugas mengusulkan barang BARU.
    // Barang belum benar-benar ada di tabel assets sampai admin ACC.
    public function ajukanTambah(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Admin menambah barang langsung lewat Kelola Barang, bukan lewat usulan.'], 403);
        }

        $data = $request->validate([
            'nama_barang' => 'required|string|max:255',
            'kategori' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'ruangan_asal' => 'nullable|string|max:100',
            'kondisi' => 'sometimes|in:tersedia,rusak',
            'foto' => 'nullable|image|max:5120',
            'slot' => 'nullable|integer|in:1,2,3',
        ]);

        $usulan = collect($data)->only(self::FIELD_BOLEH_DIUBAH)->filter(fn ($v) => $v !== null)->all();
        $usulan['kondisi'] = $usulan['kondisi'] ?? 'tersedia';

        if ($request->hasFile('foto')) {
            $path = $request->file('foto')->store('asset-photos-pending', 'public');
            $usulan['foto'] = ['slot' => (int) ($data['slot'] ?? 1), 'path' => $path];
        }

        $perubahan = AssetPerubahan::create([
            'asset_id' => null,
            'jenis' => 'tambah',
            'data_usulan' => $usulan,
            'data_lama' => null,
            'status' => 'menunggu',
            'diajukan_oleh' => $user->id,
        ]);

        return response()->json($perubahan, 201);
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
