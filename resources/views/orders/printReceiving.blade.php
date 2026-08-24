<!DOCTYPE html>
<html>

<head>
    <title>Print Penerimaan</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000;
        }

        .container {
            width: 95%;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        table.main {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table.main th,
        table.main td {
            border: 1px solid #000;
            padding: 5px;
        }

        table.main th {
            text-align: center;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        @media print {
            .page-break {
                page-break-after: always;
                break-after: page;
            }
        }
    </style>
</head>

<body onload="window.print()">

    <div class="container">

        @foreach ($receiving->receiving_details as $detail)
            <div class="header">

                <div>
                    <strong> {{ $receiving->pharmacy->name }}</strong><br>
                    {{ $receiving->pharmacy->address }}<br>

                </div>

                <div>
                    <table>
                        <tr>
                            <td>No. Terima</td>
                            <td>:</td>
                            <td>{{ $detail->receiving_details_code ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Tgl. Terima</td>
                            <td>:</td>
                            <td>{{ \Carbon\Carbon::parse($detail->created_at)->translatedFormat('d F Y') }}</td>
                        </tr>
                    </table>
                </div>

            </div>

            {{-- ================= CREDITor INFO ================= --}}
            <strong>
                Kreditur : {{ $detail->creditor->name ?? '-' }}
                &nbsp; | &nbsp;
                No Faktur : {{ $detail->invoice_number }}
                &nbsp; | &nbsp;
                Tanggal : {{ $detail->invoice_date }}
            </strong>

            {{-- ================= TABLE ================= --}}
            <table class="main">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Obat</th>
                        <th>Beli</th>
                        <th>Satuan</th>
                        <th>Harga</th>
                        <th>Discount</th>
                        <th>Extra Discount</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>

                <tbody>
                    @php
                        $ppnType = strtoupper(trim($detail->invoice_ppn ?? ($detail->creditor?->ppn_type ?? 'TANPA')));
                        $detailSubtotal = 0;
                        $detailDiscount = 0;
                    @endphp

                    @foreach ($detail->receiving_items as $i => $item)
                        @php
                            // Harga dasar (HNA) per satuan
                            $activePrice = floatval($item->raw_price ?? ($item->order_items->price ?? 0));
                            $qty = floatval($item->qty_received ?? 0);

                            // Gross = qty × HNA (sebelum diskon, sebelum PPN)
                            $itemGross = $qty * $activePrice;

                            $discVal = floatval($item->discount ?? 0);
                            $extraDiscVal = floatval($item->extra_discount ?? 0);

                            // Hitung nominal diskon dari gross HNA
                            $nomDisc = $discVal > 0 && $discVal <= 100 ? ($itemGross * $discVal) / 100 : $discVal;
                            $nomExtraDisc =
                                $extraDiscVal > 0 && $extraDiscVal <= 100
                                    ? ($itemGross * $extraDiscVal) / 100
                                    : $extraDiscVal;

                            $itemDiscount = $nomDisc + $nomExtraDisc;

                            $detailSubtotal += $itemGross;
                            $detailDiscount += $itemDiscount;
                        @endphp

                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>

                            <td>
                                {{ $item->order_items->medicines->name }}
                            </td>

                            <td class="text-center">
                                {{ $item->qty_received }}
                            </td>

                            <td class="text-center">
                                @if ($item->order_items->pack == 1)
                                    {{ $item->order_items->medicines->packaging }}
                                @else
                                    {{ $item->order_items->medicines->unit }}
                                @endif
                            </td>

                            <td class="text-right">
                                {{ number_format($activePrice, 0, ',', '.') }}
                            </td>

                            <td class="text-center">
                                {{ $discVal > 0 && $discVal <= 100 ? $discVal . '%' : number_format($discVal, 0, ',', '.') }}
                                {{ $discVal > 0 && $discVal <= 100 ? ' (' . number_format($nomDisc, 0, ',', '.') . ')' : '' }}
                            </td>
                            <td class="text-center">
                                {{ $extraDiscVal > 0 && $extraDiscVal <= 100 ? $extraDiscVal . '%' : number_format($extraDiscVal, 0, ',', '.') }}
                                {{ $extraDiscVal > 0 && $extraDiscVal <= 100 ? ' (' . number_format($nomExtraDisc, 0, ',', '.') . ')' : '' }}
                            </td>
                            <td class="text-right">
                                {{ number_format($itemGross, 0, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>

                <tfoot>
                    @php
                        // DPP = Total Harga - Total Diskon
                        $detailDpp = max(0, $detailSubtotal - $detailDiscount);

                        if ($ppnType === 'EXCLUDE') {
                            $detailPpn = floor($detailDpp * 0.11);
                            $detailGrandTotal = $detailDpp + $detailPpn;
                        } elseif ($ppnType === 'INCLUDE') {
                            $detailGrandTotal = $detailDpp;
                            $detailHna = floor($detailDpp / 1.11);
                            $detailPpn = $detailGrandTotal - $detailHna;
                        } else {
                            // TANPA
                            $detailPpn = 0;
                            $detailGrandTotal = $detailDpp;
                        }
                    @endphp

                    <tr>
                        <th colspan="7" class="text-right">TOTAL HARGA</th>
                        <th class="text-right">
                            {{ number_format($detailSubtotal, 0, ',', '.') }}
                        </th>
                    </tr>

                    <tr>
                        <th colspan="7" class="text-right">POTONGAN</th>
                        <th class="text-right">
                            {{ number_format($detailDiscount, 0, ',', '.') }}
                        </th>
                    </tr>

                    <tr>
                        <th colspan="7" class="text-right">DPP</th>
                        <th class="text-right">
                            {{ number_format($detailDpp, 0, ',', '.') }}
                        </th>
                    </tr>

                    <tr>
                        <th colspan="7" class="text-right">PPN
                            ({{ $ppnType === 'EXCLUDE' ? '11%' : ($ppnType === 'INCLUDE' ? 'Include' : 'Tanpa PPN') }})
                        </th>
                        <th class="text-right">
                            {{ number_format($detailPpn, 0, ',', '.') }}
                        </th>
                    </tr>

                    <tr>
                        <th colspan="7" class="text-right">JUMLAH TAGIHAN</th>
                        <th class="text-right">
                            {{ number_format($detailGrandTotal, 0, ',', '.') }}
                        </th>
                    </tr>
                </tfoot>

            </table>

            {{-- ================= SIGNATURE ================= --}}
            <br><br>

            <table width="100%">
                <tr>
                    <td width="50%" class="text-center">
                        Pengentry,<br><br><br>
                        ( __________ )
                    </td>

                    <td width="50%" class="text-center">
                        Validasi,<br><br><br>
                        ( __________ )
                    </td>
                </tr>
            </table>

            @if (!$loop->last && $loop->count > 1)
                <div class="page-break"></div>
            @endif
        @endforeach

    </div>

    <script>
        window.onload = () => {
            window.print();
            window.onafterprint = () => window.close();
        };
    </script>

</body>

</html>
