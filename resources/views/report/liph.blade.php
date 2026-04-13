<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }

        h2 {
            font-size: 12px;
            margin: 0;
        }

        p {
            margin: 2px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: center;
            font-weight: bold;
            background: #e9e9e9;
        }

        td {
            border: 1px solid #000;
            padding: 4px 6px;
        }

        .group {
            font-weight: bold;
        }

        .bold {
            font-weight: bold;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .no-border td {
            border: none;
        }
    </style>
</head>

<body>
    <h2>{{ $pharmacyName }}</h2>
    <p>{{ $pharmacyAddress }}</p>
    <p><strong>Laporan Penjualan Harian (LIPH)</strong></p>
    <p>Tanggal : {{ $startDate }} s/d {{ $endDate }} (Seluruh)</p>

    <table>
        <thead>
            <tr>
                <th width="4%">No.</th>
                <th>Pelanggan</th>
                <th width="8%">Lembar</th>
                <th width="6%">R/</th>
                <th width="10%">Jasa</th>
                <th width="10%">Embalase</th>
                <th width="10%">Potongan</th>
                <th width="12%">Netto</th>
            </tr>
        </thead>
        <tbody>
            {{-- Penjualan Kredit --}}
            <tr>
                <td colspan="8" class="group">Penjualan Kredit</td>
            </tr>
            @foreach ($kredit['rows'] as $row)
                <tr>
                    <td class="center">{{ $row['no'] }}</td>
                    <td class="bold">{{ $row['label'] }}</td>
                    <td class="right bold">{{ number_format($row['lembar']) }}</td>
                    <td class="right bold">{{ number_format($row['r']) }}</td>
                    <td class="right bold">{{ $row['embalase'] !== null ? number_format($row['embalase']) : '' }}</td>
                    <td class="right bold">{{ number_format($row['jasa']) }}</td>
                    <td class="right bold">{{ $row['potongan'] !== null ? number_format($row['potongan']) : '' }}</td>
                    <td class="right bold">{{ number_format($row['netto']) }}</td>
                </tr>
            @endforeach
            <tr class="bold">
                <td></td>
                <td class="center bold">Sub Total</td>
                <td class="right bold">{{ number_format($kredit['sub']['lembar']) }}</td>
                <td class="right bold">{{ number_format($kredit['sub']['r']) }}</td>
                <td class="right bold">
                    {{ $kredit['sub']['embalase'] !== null ? number_format($kredit['sub']['embalase']) : '' }}</td>
                <td class="right bold">{{ number_format($kredit['sub']['jasa']) }}</td>
                <td class="right bold">
                    {{ $kredit['sub']['potongan'] !== null ? number_format($kredit['sub']['potongan']) : '' }}</td>
                <td class="right bold">{{ number_format($kredit['sub']['netto']) }}</td>
            </tr>

            {{-- Penjualan Tunai --}}
            <tr>
                <td colspan="8" class="group">Penjualan Tunai</td>
            </tr>
            @foreach ($tunai['rows'] as $row)
                <tr>
                    <td class="center">{{ $row['no'] }}</td>
                    <td>{{ $row['label'] }}</td>
                    <td class="right">{{ number_format($row['lembar']) }}</td>
                    <td class="right">{{ number_format($row['r']) }}</td>
                    <td class="right">{{ $row['embalase'] !== null ? number_format($row['embalase']) : '' }}</td>
                    <td class="right">{{ number_format($row['jasa']) }}</td>
                    <td class="right">{{ $row['potongan'] !== null ? number_format($row['potongan']) : '' }}</td>
                    <td class="right">{{ number_format($row['netto']) }}</td>
                </tr>
            @endforeach
            <tr>
                <td></td>
                <td class="center bold">Sub Total</td>
                <td class="right bold">{{ number_format($tunai['sub']['lembar']) }}</td>
                <td class="right bold">{{ number_format($tunai['sub']['r']) }}</td>
                <td class="right bold">
                    {{ $tunai['sub']['embalase'] !== null ? number_format($tunai['sub']['embalase']) : '' }}</td>
                <td class="right bold">{{ number_format($tunai['sub']['jasa']) }}</td>
                <td class="right bold">
                    {{ $tunai['sub']['potongan'] !== null ? number_format($tunai['sub']['potongan']) : '' }}</td>
                <td class="right bold">{{ number_format($tunai['sub']['netto']) }}</td>
            </tr>

            {{-- Grand Total --}}
            <tr>
                <td></td>
                <td class="center bold">Grand Total</td>
                <td class="right bold">{{ number_format($grand['lembar']) }}</td>
                <td class="right bold">{{ number_format($grand['r']) }}</td>
                <td class="right bold">{{ $grand['embalase'] !== null ? number_format($grand['embalase']) : '' }}</td>
                <td class="right bold">{{ number_format($grand['jasa']) }}</td>
                <td class="right bold">{{ $grand['potongan'] !== null ? number_format($grand['potongan']) : '' }}</td>
                <td class="right bold">{{ number_format($grand['netto']) }}</td>
            </tr>
        </tbody>
    </table>
</body>

</html>
