<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 18px; margin-bottom: 2px; }
        .subjudul { color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background-color: #29C5E8; color: #000; }
        tr:nth-child(even) { background-color: #f5f7fa; }
        .status-ada { color: #2E9E5B; font-weight: bold; }
        .status-dipinjam { color: #CC9A2E; font-weight: bold; }
        .kondisi-tersedia { color: #2E9E5B; font-weight: bold; }
        .kondisi-rusak { color: #B03A3A; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 9px; color: #999; }
        .ttd-img { height: 40px; }
        .ttd-kosong { color: #bbb; font-style: italic; }
    </style>
</head>
<body>
    <h1>Laporan Aset Digital</h1>
    <div class="subjudul">BBLSDM Komdigi Medan &mdash; Dicetak pada {{ $tanggal }}</div>

    <table>
        <thead>
            <tr>
                <th>Kode Aset</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Status</th>
                <th>Kondisi</th>
                <th>Ruangan Asal</th>
                <th>Lokasi Terakhir</th>
                <th>Waktu Scan Terakhir</th>
                <th>Tanda Tangan Peminjam</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assets as $a)
            <tr>
                <td>{{ $a->kode_aset }}</td>
                <td>{{ $a->nama_barang }}</td>
                <td>{{ $a->kategori }}</td>
                <td class="status-{{ $a->status }}">{{ $a->status === 'dipinjam' ? 'Dipinjam' : 'Tersedia' }}</td>
                <td class="kondisi-{{ $a->kondisi }}">{{ $a->kondisi === 'rusak' ? 'Rusak' : 'Baik' }}</td>
                <td>{{ $a->ruangan_asal ?? '-' }}</td>
                <td>{{ $a->lokasiTerakhir->lokasi_input ?? '-' }}</td>
                <td>{{ $a->lokasiTerakhir ? \Carbon\Carbon::parse($a->lokasiTerakhir->scanned_at)->format('d/m/Y H:i') : '-' }}</td>
                <td>
                    @if (isset($tandaTanganBase64[$a->id]))
                        <img class="ttd-img" src="{{ $tandaTanganBase64[$a->id] }}">
                    @else
                        <span class="ttd-kosong">-</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">Total aset: {{ $assets->count() }} barang &mdash; Sistem Manajemen Aset Digital Berbasis QR-Code</div>
</body>
</html>
