<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use Illuminate\Http\Request;

class PegawaiController extends Controller
{
    // GET /api/pegawais - admin & petugas boleh lihat (dipakai buat dropdown
    // "Nama Peminjam" pas scan/pinjam barang)
    public function index()
    {
        return response()->json(Pegawai::orderBy('nama_pegawai')->get());
    }

    // POST /api/pegawais - khusus admin
    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_pegawai' => 'required|string|max:100|unique:pegawais,nama_pegawai',
            'jabatan' => 'nullable|string|max:100',
        ]);

        $pegawai = Pegawai::create($data);
        return response()->json($pegawai, 201);
    }

    // PUT /api/pegawais/{pegawai} - khusus admin
    public function update(Request $request, Pegawai $pegawai)
    {
        $data = $request->validate([
            'nama_pegawai' => 'required|string|max:100|unique:pegawais,nama_pegawai,' . $pegawai->id,
            'jabatan' => 'nullable|string|max:100',
        ]);

        $pegawai->update($data);
        return response()->json($pegawai);
    }

    // DELETE /api/pegawais/{pegawai} - khusus admin
    public function destroy(Pegawai $pegawai)
    {
        $pegawai->delete();
        return response()->json(['message' => 'Pegawai berhasil dihapus']);
    }
}
