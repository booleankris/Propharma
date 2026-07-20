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

{{-- ========================= STRUK 1 (CUSTOMER) ========================= --}}
<div class="struk">
    <div class="text-center">
        <strong>APOTEK {{ $pharmacy_name }}</strong><br>
        {{ $pharmacy_address }}<br>
        Telp: 081257586688<br>
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
        @if (in_array($transaction->transaction_type, ['RESEP TUNAI', 'KREDIT']))
            <tr>
                <td class="info-label">Dokter</td>
                <td class="info-colon">:</td>
                <td class="info-value">{{ $transaction->doctors->name ?? '-' }}</td>
            </tr>
        @endif
    </table>

    <br>

    {{-- Transaction info --}}
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

    {{-- =========================
         ITEM LIST
    ========================= --}}
    @foreach ($transactionCart as $key => $groupItems)
        @if ($key !== 'single')
            <table style="width:100%">
                <tr>
                    <td><strong>Racikan {{ $key }}</strong></td>
                    <td class="text-right">
                        @php
                            // 1. Sum the 'total_price' of all medicines inside this specific Racikan
                            $racikanPrice = $groupItems->sum('total_price');

                            // 2. Get the embalase for this Racikan.
                            // Assuming the embalase fee (e.g., 20000) is saved on the rows for this recipe.
                            // We use first() to grab the fee once, so we don't accidentally multiply it
                            // if you have multiple medicines in the same Racikan.
                            $embalase = $groupItems->sum('embalase') ?? 0;
                        @endphp

                        {{-- Output the correctly calculated total (160,000 + 20,000 = 180,000) --}}
                        {{ number_format($racikanPrice + $embalase) }}
                    </td>
                </tr>
            </table>
        @else
            <table class="table-struk">
                {{-- Loop through $groupItems for the single items --}}
                @foreach ($groupItems as $item)
                    <tr>
                        <td class="col-name">
                            {{ $item->medicine->name }}
                        </td>
                        <td class="col-qty">
                            {{ $item->quantity }}
                        </td>
                        <td class="col-total">
                            {{-- Add the item's specific embalase to its total_price --}}
                            {{ number_format($item->total_price + ($item->embalase ?? 0)) }}
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    @endforeach

    <hr>

    Jumlah <span style="float:right">{{ number_format($totalPrice + $totalEmbalase ?? 0) }}</span><br>

    Discount <span style="float:right">-{{ number_format($totaldiscount) }}</span><br>
    <strong>Total Beli <span style="float:right">{{ number_format($totalFinalPrice) }}</span></strong><br>
    Bayar <span style="float:right">{{ number_format($transaction->paid) }}</span><br>
    Kembalian<span style="float:right">{{ number_format($transaction->changes) }}</span>

    <div class="text-center">
        Terima Kasih<br>
        Semoga Lekas Sembuh
    </div>
</div>

{{-- ========================= STRUK 2 (RESEP DETAIL) ========================= --}}
@if (in_array($transaction->transaction_type, ['RESEP TUNAI', 'KREDIT']))
    <div class="struk page-break">

        <div class="text-center">
            <strong>APOTEK SAHABAT</strong><br>
            Jl. Palang Merah Ind No.16 A-B-C<br>
            Telp: 081257586688<br>
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

        <br>

        Sub Total <span style="float:right">{{ number_format($totalPrice + $totalEmbalase) }}</span><br>

        Discount <span style="float:right">-{{ number_format($totaldiscount) }}</span><br>
        <strong>Jumlah <span style="float:right">{{ number_format($totalFinalPrice) }}</span></strong><br>
        Bayar <span style="float:right">{{ number_format($transaction->paid) }}</span><br>
        Kembalian<span style="float:right">{{ number_format($transaction->changes) }}</span>



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
