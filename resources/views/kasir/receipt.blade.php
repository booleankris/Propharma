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
        line-height: 1.2;
    }
</style>

{{-- =========================
     STRUK 1 (CUSTOMER)
========================= --}}
<div class="struk">
    <div class="text-center">
        <strong>APOTEK SAHABAT</strong><br>
        Jl. Palang Merah Ind No.16 A-B-C<br>
        Telp: 081257586688<br>
        SAMARINDA
    </div>

    <hr>

    Bukti Pembayaran<br>
    {{ $transaction->updated_at->format('d/m/Y H:i:s') }}<br><br>

    Nama : {{ $transaction->patients->name ?? '-' }}<br>
    Alamat : {{ $transaction->patients->address ?? '-' }}<br>
    No.Telp : {{ $transaction->patients->phone ?? '-' }}<br>

    @if (in_array($transaction->transaction_type, ['RESEP TUNAI', 'KREDIT']))
        <hr>
        Nama Dokter : {{ $transaction->doctors->name ?? '-' }}
    @endif

    <hr>

    {{-- =========================
         ITEM LIST
    ========================= --}}
    @foreach ($transactionCart as $key => $items)
        @if ($key !== 'single')
            <table style="width:100%">
                <tr>
                    <td><strong>Racikan {{ $key }}</strong></td>
                    <td class="text-right">
                        {{ number_format($items->sum('final_price')) }}
                    </td>
                </tr>
            </table>
        @else
            <table class="table-struk">
                @foreach ($items as $item)
                    <tr>
                        <td class="col-name">
                            {{ $item->medicine->name }}
                        </td>
                        <td class="col-qty">
                            {{ $item->quantity }}
                        </td>
                        <td class="col-total">
                            {{ number_format($item->raw_total) }}
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach

    <hr>

    Jumlah <span style="float:right">{{ number_format($totalRawTotal) }}</span><br>
    Discount <span style="float:right">-{{ number_format($totaldiscount) }}</span><br>
    <strong>Total Beli <span style="float:right">{{ number_format($payment) }}</span></strong><br>
    Bayar <span style="float:right">{{ number_format($transaction->paid) }}</span><br>
    Kembalian<span style="float:right">{{ number_format($transaction->changes) }}</span>

    <hr>

    Kasir : {{ auth()->user()->name }}<br>

    <hr>

    <div class="text-center">
        Terima Kasih<br>
        Semoga Lekas Sembuh
    </div>
</div>

{{-- =========================
     STRUK 2 (RESEP DETAIL)
========================= --}}
@if (in_array($transaction->transaction_type, ['RESEP TUNAI', 'KREDIT']))
    <div class="struk page-break">

        <div class="text-center">
            <strong>APOTEK SAHABAT</strong><br>
            Jl. Palang Merah Ind No.16 A-B-C<br>
            Telp: 081257586688<br>
            SAMARINDA
        </div>

        <hr>

        Bukti Pembayaran<br>
        {{ $transaction->updated_at->format('d/m/Y H:i:s') }}<br><br>

        Nama : {{ $transaction->patients->name ?? '-' }}<br>
        Alamat : {{ $transaction->patients->address ?? '-' }}<br>

        <hr>
        Nama Dokter : {{ $transaction->doctors->name ?? '-' }}
        <hr>
        <br>
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
                            {{ number_format($item->raw_total) }}
                        </td>
                    </tr>
                @endforeach
            </table>
        @endforeach

        <br>
        Sub Total <span style="float:right">{{ number_format($totalRawTotal) }}</span><br>
        Discount <span style="float:right">-{{ number_format($totaldiscount) }}</span><br>
        <strong>Jumlah <span style="float:right">{{ number_format($payment) }}</span></strong><br>
        Bayar <span style="float:right">{{ number_format($transaction->paid) }}</span><br>
        Kembalian<span style="float:right">{{ number_format($transaction->changes) }}</span>

        <hr>

        Kasir : {{ auth()->user()->name }}<br>

        <hr>

        <div class="text-center">
            Terima Kasih<br>
            Semoga Lekas Sembuh
        </div>

    </div>
@endif

<script>
    window.onload = () => {
        window.print();
        window.onafterprint = () => window.close();
    }
</script>
