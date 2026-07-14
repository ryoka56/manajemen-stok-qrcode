<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    // GET /api/reports/excel
    // Export daftar aset ke file CSV (bisa langsung dibuka di Excel)
    public function exportExcel(Request $request): StreamedResponse
    {
        $assets = Asset::with('lokasiTerakhir')->orderBy('kode_aset')->get();

        $namaFile = 'laporan-aset-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$namaFile\"",
        ];

        $callback = function () use ($assets) {
            $file = fopen('php://output', 'w');

            // BOM biar karakter Excel terbaca dengan benar (termasuk simbol/huruf khusus)
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'Kode Aset', 'Nama Barang', 'Kategori', 'Status',
                'Ruangan Asal', 'Deskripsi', 'Lokasi Terakhir', 'Waktu Scan Terakhir',
            ]);

            foreach ($assets as $a) {
                fputcsv($file, [
                    $a->kode_aset,
                    $a->nama_barang,
                    $a->kategori,
                    $a->status,
                    $a->ruangan_asal ?? '-',
                    $a->deskripsi ?? '-',
                    $a->lokasiTerakhir->lokasi_input ?? '-',
                    $a->lokasiTerakhir->scanned_at ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // GET /api/reports/pdf
    // Export daftar aset ke file PDF (untuk lampiran/cetak laporan)
    public function exportPdf(Request $request)
    {
        $assets = Asset::with('lokasiTerakhir')->orderBy('kode_aset')->get();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.aset', [
            'assets' => $assets,
            'tanggal' => now()->format('d F Y'),
        ])->setPaper('a4', 'landscape');

        $namaFile = 'laporan-aset-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($namaFile);
    }
}
