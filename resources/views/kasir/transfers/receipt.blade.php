<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            font-size: 10px;
            color: #111;
        }

        h1 {
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 0 0 6px 0;
            line-height: 1.1;
        }

        .divider {
            border-top: 1px solid #333;
            margin: 6px 0 12px 0;
        }

        table.header-info {
            width: 100%;
            margin-bottom: 10px;
        }

        table.header-info td {
            vertical-align: top;
            padding: 1px 0;
            font-size: 10px;
        }

        .label {
            font-weight: 700;
            width: 115px;
            display: inline-block;
        }

        .date-box {
            text-align: right;
            font-size: 10px;
        }

        .date-value {
            font-weight: 700;
            font-size: 11px;
        }

        h2.section-title {
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 12px 0 6px 0;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 8.5px;
            line-height: 1.15;
        }

        table.items thead {
            display: table-header-group;
        }

        table.items tr {
            page-break-inside: avoid;
        }

        table.items th {
            text-align: left;
            font-style: italic;
            font-weight: 700;
            font-size: 8.5px;
            border-bottom: 1px solid #333;
            padding: 3px 2px;
        }

        table.items td {
            font-size: 8.5px;
            padding: 2.5px 2px;
            border-bottom: 1px solid #e5e5e5;
            vertical-align: middle;
        }

        .no-col {
            width: 24px;
            text-align: left;
        }

        .satuan-col {
            width: 48px;
            text-align: left;
        }

        .batch-col {
            width: 95px;
            text-align: left;
        }

        .ed-col {
            width: 65px;
            text-align: left;
        }

        .qty-col {
            text-align: left;
            width: 50px;
        }

        table.signatures {
            width: 100%;
            margin-top: 25px;
            page-break-inside: avoid;
        }

        table.signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }

        .sign-title {
            font-size: 11px;
            font-weight: 700;
            color: #111;
        }

        .sign-sub {
            font-size: 10px;
            color: #555;
            margin-top: 2px;
        }

        .sign-space {
            height: 50px;
        }

        .sign-name {
            font-size: 11px;
            font-weight: 700;
            color: #111;
        }
    </style>
</head>

<body>
    @php
        $sourcePharmacyName = $transfer->sourcePharmacy?->name
            ?? ($transfer->items->first()?->sourceBatch?->pharmacy?->name
            ?? ($transfer->items->first()?->source_type === 'gudang' ? 'GUDANG PMI' : ($transfer->users?->pharmacy?->name ?? 'GUDANG PMI')));

        $destinationPharmacyName = $transfer->destinationPharmacy?->name
            ?? ($transfer->items->first()?->batches?->pharmacy?->name ?? '—');

        $sortedItems = $transfer->items->sort(function ($a, $b) {
            $nameA = trim((string) ($a->batches?->medicines?->name ?? $a->requestedMedicine?->name ?? ''));
            $nameB = trim((string) ($b->batches?->medicines?->name ?? $b->requestedMedicine?->name ?? ''));
            $cmp = strcasecmp($nameA, $nameB);
            if ($cmp === 0) {
                $batchA = trim((string) ($a->batches?->name ?? $a->sourceBatch?->name ?? ''));
                $batchB = trim((string) ($b->batches?->name ?? $b->sourceBatch?->name ?? ''));
                return strcasecmp($batchA, $batchB);
            }
            return $cmp;
        })->values();

        $senderDisplayName = $senderName;
    @endphp

    <h1>Tanda Terima Barang</h1>
    <div class="divider"></div>

    <table class="header-info">
        <tr>
            <td width="70%">
                <div style="padding-top:6px"><span class="label">Nomor Terima</span>: {{ $transfer->code }}</div>
                <div style="padding-top:6px"><span class="label">Apotek Asal</span>: {{ $sourcePharmacyName }}</div>
                <div style="padding-top:6px"><span class="label">Apotek Tujuan</span>: {{ $destinationPharmacyName }}</div>
            </td>
            <td width="30%" class="date-box">
                <div>Date:</div>
                <div class="date-value">{{ $transfer->updated_at ? $transfer->updated_at->format('d F Y') : ($transfer->created_at ? $transfer->created_at->format('d F Y') : date('d F Y')) }}</div>
            </td>
        </tr>
    </table>

    <h2 class="section-title">Deskripsi Item</h2>

    <table class="items">
        <thead>
            <tr>
                <th class="no-col">No</th>
                <th>Item</th>
                <th class="satuan-col">Satuan</th>
                <th class="batch-col">Batch</th>
                <th class="ed-col">ED</th>
                <th class="qty-col">Quantity</th>
            </tr>
        </thead>
        <tbody>
            @foreach($sortedItems as $index => $item)
            @php
                $med = $item->batches?->medicines ?? ($item->requestedMedicine ?? ($item->sourceBatch?->medicines ?? null));
                $itemName = $med?->name ?? '—';
                $unit = $med?->unit ?? ($item->batches?->unit ?? '—');
                $batchName = $item->batches?->name ?? ($item->sourceBatch?->name ?? '—');
                $ed = safeDateFormat($item->sourceBatch?->expired_date ?? $item->batches?->expired_date);
                $qty = rtrim(rtrim(number_format((float) ($item->original_qty ?? $item->qty), 2, ',', '.'), '0'), ',');
            @endphp
            <tr>
                <td class="no-col">{{ $index + 1 }}</td>
                <td>{{ $itemName }}</td>
                <td class="satuan-col">{{ strtoupper($unit) }}</td>
                <td class="batch-col">{{ $batchName }}</td>
                <td class="ed-col">{{ $ed }}</td>
                <td class="qty-col">{{ $qty }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div class="sign-title">Yang Menyerahkan,</div>
                <div class="sign-sub">({{ $sourcePharmacyName }})</div>
                <div class="sign-space"></div>
                <div class="sign-name">
                    @if(!empty($senderDisplayName))
                        ( {{ $senderDisplayName }} )
                    @else
                        ( .................................... )
                    @endif
                </div>
            </td>
            <td>
                <div class="sign-title">Yang Menerima,</div>
                <div class="sign-sub">({{ $destinationPharmacyName }})</div>
                <div class="sign-space"></div>
                <div class="sign-name">
                    @if(!empty($receiverName))
                        ( {{ $receiverName }} )
                    @else
                        ( .................................... )
                    @endif
                </div>
            </td>
        </tr>
    </table>

</body>

</html>
