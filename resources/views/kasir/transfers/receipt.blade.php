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

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
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

        .qty-col {
            text-align: left;
            width: 120px;
        }

        .receiver {
            margin-top: 30px;
        }
    </style>
</head>

<body>

    <h1>Tanda Terima Barang</h1>
    <div class="divider"></div>

    <table class="header-info">
        <tr>
            <td width="70%">
                <div style="padding-top:6px"><span class="label">Nomor Terima</span>: {{ $transfer->code }}</div>
                <div style="padding-top:6px"><span class="label">Apotek Asal</span>: {{ $transfer->users?->pharmacy?->name ?? '—' }}</div>
                <div style="padding-top:6px"><span class="label">Apotek Tujuan</span>: {{ $transfer->batches?->pharmacy?->name ?? '—' }}</div>
            </td>
            <td width="30%" class="date-box">
                <div>Date:</div>
                <div class="date-value">{{ $transfer->updated_at->format('d F Y') }}</div>
            </td>
        </tr>
    </table>

    <h2 class="section-title">Deskripsi Item</h2>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="qty-col">Quantity</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $transfer->batches?->medicines?->name ?? '—' }}</td>
                <td class="qty-col">{{ $transfer->stock }}</td>
            </tr>
        </tbody>
    </table>



</body>

</html>
