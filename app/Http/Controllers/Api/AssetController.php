<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AssetController extends Controller
{
    // GET /api/assets
    public function index(Request $request)
    {
        $query = Asset::with('lokasiTerakhir');

        if ($request->filled('kategori')) {
            $query->where('kategori', $request->kategori);
        }
        if ($request->filled('cari')) {
            $query->where('nama_barang', 'like', '%' . $request->cari . '%');
        }

        return response()->json($query->latest()->paginate(20));
    }

    // POST /api/assets
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_barang' => 'required|string|max:255',
            'kategori' => 'required|string|max:100',
            'deskripsi' => 'nullable|string',
            'ruangan_asal' => 'nullable|string|max:100',
        ]);

        // generate kode aset unik otomatis, mis. AST-000123
        $data['kode_aset'] = 'AST-' . str_pad((Asset::max('id') + 1), 6, '0', STR_PAD_LEFT);

        $asset = Asset::create($data);

        return response()->json($asset, 201);
    }

    // GET /api/assets/{asset}
    public function show(Asset $asset)
    {
        return response()->json($asset->load(['scanLogs', 'lokasiTerakhir']));
    }

    // PUT /api/assets/{asset}
    public function update(Request $request, Asset $asset)
    {
        $data = $request->validate([
            'nama_barang' => 'sometimes|string|max:255',
            'kategori' => 'sometimes|string|max:100',
            'deskripsi' => 'nullable|string',
            'status' => 'sometimes|string|max:50',
        ]);

        $asset->update($data);

        return response()->json($asset);
    }

    // DELETE /api/assets/{asset}
    public function destroy(Asset $asset)
    {
        $asset->delete();
        return response()->json(['message' => 'Aset berhasil dihapus']);
    }

    // GET /api/assets/{asset}/qrcode
    // Menghasilkan gambar QR-code berisi kode_aset, untuk ditempel di barang fisik
    public function qrcode(Asset $asset)
    {
        $qr = QrCode::size(300)->generate($asset->kode_aset);
        return response($qr)->header('Content-Type', 'image/svg+xml');
    }

    // GET /api/assets/scan/{kode_aset}
    // Dipanggil aplikasi mobile setelah scan QR untuk ambil detail barang
    public function scan($kode_aset)
    {
        $asset = Asset::where('kode_aset', $kode_aset)
            ->with(['lokasiTerakhir', 'scanLogs' => fn ($q) => $q->latest('scanned_at')->limit(5)])
            ->firstOrFail();

        return response()->json($asset);
    }
}
