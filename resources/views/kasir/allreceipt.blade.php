<style>
    @media print {
        body {
            width: 58mm;
            margin: 0;
            padding: 0;
            font-family: monospace;
            font-size: 11px;
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
    }
</style>

<div class="struk">
    <div class="text-center">
        <strong>APOTEK SAHABAT</strong><br>
        Jl. Palang Merah Ind No.16 A-B-C<br>
        Telp: 081257586688<br>
        SAMARINDA
    </div>

    <hr>

    Bukti Pembayaran<br>
    {{ $transaction->updated_at->format('d/m/Y H:i:s') }}<br>

    <br>
    Nama : {{ $transaction->patients->name ?? '-' }}<br>
    Alamat : {{ $transaction->patients->address ?? '-' }}<br>
    <hr>
    Nama Dokter : {{ $transaction->doctors->name ?? '-' }}<br>
    <hr>

    @foreach ($transactionCart as $groupKey => $items)
        @foreach ($items as $item)
            {{ $item->medicine->name }}<br>
            {{ $item->quantity }} x {{ number_format($item->item_price) }}
            <span style="float:right">{{ number_format($item->raw_total) }}</span><br>
        @endforeach
    @endforeach

    <hr>

    Sub Total
    <span style="float:right;">{{ number_format($totalRawTotal) }}</span><br>

    Discount
    <span style="float:right;">-{{ number_format($totaldiscount) }}</span><br>

    <strong>
        Jumlah
        <span style="float:right;">{{ number_format($payment) }}</span>
    </strong><br>

    Bayar
    <span style="float:right;">{{ number_format($transaction->paid) }}</span><br>

    Kembalian
    <span style="float:right;">{{ number_format($transaction->changes) }}</span>

    <hr>
    Kasir : {{ auth()->user()->name }}<br>
    <hr>

    <div class="text-center">
        <span>Terima Kasih</span><br>
        <span>Semoga Lekas Sembuh</span>
    </div>
</div>

<script>
    window.onload = () => {
        window.print();
        window.onafterprint = () => {
            window.location.href = "{{ route('transaction', 'resep') }}";
        }
    }
</script>
