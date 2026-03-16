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

        /* ✅ PAGE BREAK */
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


        <div class="header">

            <div>
                <strong>GUDANG SAHABAT</strong><br>
                Jl. Palang Merah Ind No. 16 A-B-C<br>
                SAMARINDA
            </div>

            <div>
                <table>
                    <tr>
                        <td>No. Terima</td>
                        <td>:</td>
                        <td>{{ $invoice->receiving?->code }}</td>
                    </tr>
                    <tr>
                        <td>Tgl. Terima</td>
                        <td>:</td>
                        <td>{{ $invoice->receiving?->date }}</td>
                    </tr>
                </table>
            </div>

        </div>

        {{-- ================= CREDITor INFO ================= --}}
        <strong>
            Kreditur : {{ $invoice->creditor->name ?? '-' }}
            &nbsp; | &nbsp;
            No Faktur : {{ $invoice->invoice_number }}
            &nbsp; | &nbsp;
            Tanggal : {{ $invoice->invoice_date }}
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
                @php $grandTotal = 0; @endphp

                @foreach ($invoice->receiving_items as $i => $item)
                    @php
                        $subtotal = $item->qty_received * $item->order_items->price;
                        $grandTotal += $subtotal;
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
                            {{ $item->order_items->medicines->unit }}
                        </td>

                        <td class="text-right">
                            {{ number_format($item->order_items->price, 0, ',', '.') }}
                        </td>

                        <td class="text-center">
                            {{ $item->discount ?? 0 }}
                        </td>
                        <td class="text-center">
                            {{ $item->extra_discount ?? 0 }}
                        </td>
                        <td class="text-right">
                            {{ number_format($subtotal, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>

            <tfoot>
                @php
                    $ppn = floor($grandTotal * 0.11); // 11% PPN
                    $grandTotalWithPpn = $grandTotal + $ppn;
                @endphp

                <tr>
                    <th colspan="7" class="text-right">TOTAL</th>
                    <th class="text-right">
                        {{ number_format($grandTotal, 0, ',', '.') }}
                    </th>
                </tr>

                <tr>
                    <th colspan="7" class="text-right">TOTAL PPN (11%)</th>
                    <th class="text-right">
                        {{ number_format($ppn, 0, ',', '.') }}
                    </th>
                </tr>

                <tr>
                    <th colspan="7" class="text-right">TOTAL DISKON</th>
                    <th class="text-right">
                        {{ number_format($totaldiscount, 0, ',', '.') }}
                    </th>
                </tr>
                <tr>
                    <th colspan="7" class="text-right">TOTAL</th>
                    <th class="text-right">
                        {{ number_format($grandTotal + $ppn - $totaldiscount, 0, ',', '.') }}
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


    </div>

    <script>
        window.onload = () => {
            window.print();
            window.onafterprint = () => window.close();
        };
    </script>

</body>

</html>
