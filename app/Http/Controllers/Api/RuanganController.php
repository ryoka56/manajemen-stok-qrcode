<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ruangan;
use Illuminate\Http\Request;

class RuanganController extends Controller
{
    // GET /api/ruangans - admin & petugas boleh lihat (untuk pilihan saat scan/pinjam)
    public function index()
    {
        return response()->json(Ruangan::orderBy('nama_ruangan')->get());
    }

    // POST /api/ruangans - khusus admin
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_ruangan' => 'required|string|max:100|unique:ruangans,nama_ruangan',
            'lokasi_gedung' => 'nullable|string|max:150',
            'keterangan' => 'nullable|string',
        ]);

        $ruangan = Ruangan::create($data);
        return response()->json($ruangan, 201);
    }

    // PUT /api/ruangans/{ruangan} - khusus admin
    public function update(Request $request, Ruangan $ruangan)
    {
        $data = $request->validate([
            'nama_ruangan' => 'required|string|max:100|unique:ruangans,nama_ruangan,' . $ruangan->id,
            'lokasi_gedung' => 'nullable|string|max:150',
            'keterangan' => 'nullable|string',
        ]);

        $ruangan->update($data);
        return response()->json($ruangan);
    }

    // DELETE /api/ruangans/{ruangan} - khusus admin
    public function destroy(Ruangan $ruangan)
    {
        $ruangan->delete();
        return response()->json(['message' => 'Ruangan berhasil dihapus']);
    }
}
