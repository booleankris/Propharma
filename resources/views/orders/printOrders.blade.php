<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 12px;
            color: #111;
        }

        h1 {
            font-size: 26px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 0 0 12px 0;
            line-height: 1.2;
        }

        .divider {
            border-top: 1px solid #333;
            margin: 10px 0 20px 0;
        }

        table.header-info {
            width: 100%;
            margin-bottom: 20px;
        }

        table.header-info td {
            vertical-align: top;
            padding: 2px 0;
        }

        .label {
            font-weight: 700;
            width: 140px;
            display: inline-block;
        }

        .date-box {
            text-align: right;
        }

        .date-value {
            font-weight: 700;
            font-size: 13px;
        }

        h2.section-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 25px 0 10px 0;
        }

        .invoice-label {
            font-weight: 700;
            margin: 16px 0 6px 0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        table.items th {
            text-align: left;
            font-style: italic;
            font-weight: normal;
            border-bottom: 1px solid #333;
            padding-bottom: 6px;
        }

        table.items td {
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }

        .qty-col,
        .total-col,
        .pbf-col {
            text-align: left;
        }

        table.grand-total {
            width: 100%;
            margin-top: 20px;
        }

        table.grand-total td {
            padding: 4px 0;
        }

        .gt-label {
            text-align: right;
            padding-right: 20px;
            font-weight: 700;
        }

        .gt-value {
            text-align: right;
            width: 160px;
        }

        .gt-final .gt-label,
        .gt-final .gt-value {
            font-size: 14px;
            border-top: 1px solid #333;
            padding-top: 8px;
        }
    </style>
</head>

<body>

    @foreach ($groupedByPBF as $pbfName => $detailsList)
        @if (!$loop->first)
            <div style="page-break-before: always;"></div>
        @endif

        <h1>Tanda Penerimaan Barang</h1>
        <div class="divider"></div>

        <table class="header-info">
            <tr>
                <td width="70%">
                    <div><span class="label">Nomor Pemesanan</span>: {{ $order->code }}</div>
                    <div><span class="label">Nama PBF</span>: {{ $pbfName }}</div>
                </td>
                <td width="30%" class="date-box">
                    <div>Date:</div>
                    <div class="date-value">{{ $receiving->updated_at?->format('d F Y') ?? '—' }}</div>
                </td>
            </tr>
        </table>

        <h2 class="section-title">Deskripsi Item</h2>

        @foreach ($detailsList as $details)
            <div class="invoice-label">
                Nama Faktur: {{ $details->invoice_number }}
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="qty-col">Qty Pesan</th>
                        <th class="qty-col">Qty Diterima</th>
                        <th class="total-col">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @php $fakturTotal = 0; @endphp
                    @foreach ($details->receiving_items as $item)
                        @php $fakturTotal += $item->total; @endphp
                        <tr>
                            <td>{{ $item->order_items?->medicines?->name ?? '—' }}</td>
                            <td class="qty-col">{{ $item->order_items?->quantity ?? '—' }}</td>
                            <td class="qty-col">{{ $item->qty_received }}</td>
                            <td class="total-col">Rp. {{ number_format($item->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @php
                $ppnType = strtoupper(trim($details->invoice_ppn ?? $details->creditor?->ppn_type ?? 'TANPA'));
                if ($ppnType === 'EXCLUDE') {
                    $ppn = floor($fakturTotal * 0.11);
                    $finalTotal = $fakturTotal + $ppn;
                    $ppnLabel = 'PPN (11%)';
                } elseif ($ppnType === 'INCLUDE') {
                    $finalTotal = $fakturTotal;
                    $hna = floor($fakturTotal / 1.11);
                    $ppn = $finalTotal - $hna;
                    $ppnLabel = 'PPN (Include)';
                } else {
                    $ppn = 0;
                    $finalTotal = $fakturTotal;
                    $ppnLabel = 'PPN (Tanpa)';
                }
            @endphp

            <table class="grand-total">
                <tr>
                    <td class="gt-label">Subtotal</td>
                    <td class="gt-value">Rp. {{ number_format($fakturTotal, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="gt-label">{{ $ppnLabel }}</td>
                    <td class="gt-value">Rp. {{ number_format($ppn, 0, ',', '.') }}</td>
                </tr>
                <tr class="gt-final">
                    <td class="gt-label">Total</td>
                    <td class="gt-value">Rp. {{ number_format($finalTotal, 0, ',', '.') }}</td>
                </tr>
            </table>
        @endforeach
    @endforeach

</body>

</html>
