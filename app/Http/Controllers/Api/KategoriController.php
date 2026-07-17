<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use Illuminate\Http\Request;

class KategoriController extends Controller
{
    // GET /api/kategoris - admin & petugas boleh lihat (untuk pilihan saat tambah barang)
    public function index()
    {
        return response()->json(Kategori::orderBy('nama_kategori')->get());
    }

    // POST /api/kategoris - khusus admin
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_kategori' => 'required|string|max:100|unique:kategoris,nama_kategori',
            'keterangan' => 'nullable|string',
        ]);

        $kategori = Kategori::create($data);
        return response()->json($kategori, 201);
    }

    // DELETE /api/kategoris/{kategori} - khusus admin
    public function destroy(Kategori $kategori)
    {
        $kategori->delete();
        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }
}
