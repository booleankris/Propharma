<style>
    @media print {
        body {
            width: 58mm;
            margin: 0;
            padding: 0;
            font-family: monospace;
            font-size: 11px;
        }

        .page-break {
            page-break-before: always;
        }
    }

    .struk {
        width: 58mm;
    }

    .text-center {
        text-align: center;
    }

    .text-right {
        text-align: right;
    }

    hr {
        border: none;
        border-top: 1px dashed #000;
        margin: 4px 0;
    }

    /* =========================
       TABLE ITEM STRUK
    ========================= */
    .table-struk {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }

    .table-struk td {
        font-size: 11px;
        padding: 0;
        vertical-align: top;
    }

    .col-name {
        width: 60%;
        word-break: break-word;
        white-space: normal;
    }

    .col-qty {
        width: 15%;
        text-align: center;
    }

    .col-total {
        width: 25%;
        text-align: right;
    }

    .table-struk tr {
        line-height: 1.8;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
    }

    .info-table td {
        font-size: 11px;
        padding: 0;
        vertical-align: top;
        line-height: 1.5;
    }

    .info-label {
        width: 45%;
        white-space: nowrap;
    }

    .info-colon {
        width: 5%;
        text-align: center;
    }

    .info-value {
        width: 50%;
        word-break: break-word;
        white-space: normal;
    }
</style>
<div class="struk">

    <div class="text-center">
        <strong>APOTEK {{ $pharmacy_name }}</strong><br>
        {{ $pharmacy_address }}<br>
        Telp: {{ $pharmacy_phone }}<br>
        SAMARINDA
    </div>

    <hr>

    <div style="text-align: center">
        Bukti Pembayaran<br>
        {{ $transaction->updated_at->format('d/m/Y H:i:s') }}
    </div>
    <br>

    <table class="info-table">
        <tr>
            <td class="info-label">Nama</td>
            <td class="info-colon">:</td>
            <td class="info-value">{{ $transaction->patients->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Alamat</td>
            <td class="info-colon">:</td>
            <td class="info-value">{{ $transaction->patients->address ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">No.Telp</td>
            <td class="info-colon">:</td>
            <td class="info-value">{{ $transaction->patients->phone ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Dokter</td>
            <td class="info-colon">:</td>
            <td class="info-value">{{ $transaction->doctors->name ?? '-' }}</td>
        </tr>
    </table>

    <hr>

    <table class="info-table">
        <tr>
            <td class="info-label">Nomor</td>
            <td class="info-colon">:</td>
            <td class="info-value">{{ $transaction->transaction_code }}</td>
        </tr>
        <tr>
            <td class="info-label">Operator</td>
            <td class="info-colon">:</td>
            <td class="info-value">{{ $operator }}</td>
        </tr>
    </table>

    <hr>

    {{-- ITEM --}}
    @foreach ($transactionCart as $items)
        <table class="table-struk">
            @foreach ($items as $item)
                <tr>
                    <td class="col-name">
                        {{ $item->medicine->name }}
                        <hr>
                    </td>
                    <td class="col-qty">
                        {{ $item->quantity }}
                    </td>
                    <td class="col-total">
                        {{ number_format($item->total_price + $item->embalase) }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endforeach

    @if (!empty($service))
        <table class="table-struk">
            <tr>
                <td class="col-name">{{ $service->service_name }}</td>
                <td class="col-qty">1</td>
                <td class="col-total">{{ number_format((float) $service->price) }}</td>
            </tr>
        </table>
    @endif

    <br>

    Sub Total <span style="float:right">{{ number_format($totalPrice + $totalEmbalase + ($serviceAmount ?? 0)) }}</span><br>

    Diskon <span style="float:right">-{{ number_format($totaldiscount) }}</span><br>
    <strong>Jumlah <span style="float:right">{{ number_format($totalFinalPrice) }}</span></strong><br>
    @if (($shippingFee ?? 0) > 0)
        Ongkir{{ ($shippingLabel ?? '') !== '' ? ' (' . $shippingLabel . ')' : '' }} <span style="float:right">{{ number_format($shippingFee) }}</span><br>
        <strong>Total + Ongkir <span style="float:right">{{ number_format($totalFinalPrice + $shippingFee) }}</span></strong><br>
    @endif
    Bayar <span style="float:right">{{ number_format($transaction->paid) }}</span><br>
    Kembalian<span style="float:right">{{ number_format($transaction->changes) }}</span>



    <hr>

    <div class="text-center">
        Terima Kasih<br>
        Semoga Sehat Selalu
    </div>

</div>
<script>
    window.onload = () => {
        window.print();
        window.onafterprint = () => window.close();
    }
</script>
