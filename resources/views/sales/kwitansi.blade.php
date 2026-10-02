<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Kwitansi {{ $transaction->transaction_code }} - {{ $pharmacy->name ?? 'Apotek' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Courier+Prime:wght@400;700&family=Charm:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root { --form-font: 'Courier Prime', Courier, monospace; --detail-font: 'Charm', cursive; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f6; color: #17212b; font-family: 'Plus Jakarta Sans', Arial, sans-serif; }
        .toolbar { padding: 16px 24px; background: white; border-bottom: 1px solid #ddd; }
        .toolbar-heading, .toolbar-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .toolbar-heading { justify-content: space-between; }
        .toolbar p { margin: 8px 0 0; font-size: 12px; color: #475569; line-height: 1.6; }
        button, .back-button { padding: 9px 15px; border: 1px solid transparent; border-radius: 6px; cursor: pointer; font: 600 12px 'Plus Jakarta Sans', Arial, sans-serif; text-decoration: none; }
        .back-button { color: #334155; border-color: #cbd5e1; background: white; }
        #view-button { color: #334155; border-color: #cbd5e1; background: #f8fafc; }
        #cancel-button { color: #b42318; border-color: #fecaca; background: #fff5f5; }
        #print-button { background: #146457; color: white; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        #status { color: #b42318; }
        .paper { width: 105mm; height: 220mm; margin: 24px auto; padding: 5mm; background: white; box-shadow: 0 8px 32px #0001; color: black; }
        .paper[hidden] { display: none; }
        .kop { height: 32mm; display: flex; align-items: center; gap: 8px; border-bottom: 3px double black; padding-bottom: 8px; }
        .kop-logo { width: 21mm; max-height: 26mm; object-fit: contain; flex-shrink: 0; }
        .kop-text { flex: 1; min-width: 0; text-align: center; font-family: Arial, sans-serif; }
        .kop h1 { font-family: Georgia, 'Times New Roman', serif; font-weight: 900; font-size: 19px; margin: 0 0 4px; }
        .kop p { margin: 1px 0; font-size: 10px; line-height: 1.2; overflow-wrap: anywhere; }
        .body-wrapper { width: 95mm; height: 170mm; margin-top: 5mm; position: relative; }
        .receipt-body { width: 170mm; height: 95mm; padding: 3px 8px; position: absolute; top: 0; left: 0; transform-origin: top left; transform: translateX(95mm) rotate(90deg); display: grid; grid-template-rows: 26px 32px 60px 78px minmax(0, 1fr); gap: 8px; font-family: var(--form-font); }
        .number { display: flex; align-items: center; gap: 12px; font-size: 14px; font-weight: 700; }
        .number b { border: 1.5px solid black; padding: 4px 12px; min-width: 160px; max-width: 430px; overflow-wrap: anywhere; font-size: 12px; }
        .row { display: grid; grid-template-columns: 146px 14px minmax(0, 1fr); font-size: 13px; align-items: start; }
        .label, .colon { padding-top: 4px; font-weight: 700; }
        .value { min-width: 0; overflow-wrap: anywhere; white-space: pre-wrap; font-family: var(--detail-font); font-weight: 700; font-size: 20px; line-height: 24px; overflow: auto; }
        #recipient { height: 30px; border-bottom: 1px dotted #555; }
        [contenteditable="plaintext-only"] { outline: none; }
        [contenteditable="plaintext-only"]:hover, [contenteditable="plaintext-only"]:focus { background-color: #fffbeb; box-shadow: 0 0 0 1px #d6b96c; }
        .words-frame { margin: 0 9px; position: relative; min-width: 0; }
        .words-frame::before { content: ''; position: absolute; inset: 0; border: 3px double black; transform: skewX(-12deg); pointer-events: none; }
        #words { height: 58px; padding: 3px 10px; font-size: 18px; line-height: 25px; }
        #payment-for { height: 76px; line-height: 24px; background-image: radial-gradient(circle, #666 .6px, transparent .8px); background-size: 4px 24px; background-position: 0 12px; }
        .receipt-footer { min-height: 0; display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; padding-bottom: 3px; }
        .amount-box { display: flex; align-items: center; gap: 12px; border-top: 3px double black; border-bottom: 3px double black; padding: 5px 12px 5px 2px; }
        .rp { font: 700 italic 24px var(--detail-font); }
        .amount-frame { position: relative; padding: 2px 10px; white-space: nowrap; font: 700 20px var(--detail-font); }
        .amount-frame::before { content: ''; position: absolute; inset: 0; border: 1px solid black; transform: skewX(-12deg); pointer-events: none; }
        #amount { display: inline-block; min-width: 90px; max-width: 180px; white-space: nowrap; overflow: hidden; vertical-align: bottom; }
        .signature { width: 220px; flex-shrink: 0; text-align: center; font-size: 12px; }
        .signature-date { font: 700 17px var(--detail-font); border-bottom: 1px dotted #666; }
        .wet-signature { height: 53px; }
        .operator { border-bottom: 1px dotted #777; padding-bottom: 2px; font: 700 18px/21px var(--detail-font); overflow-wrap: anywhere; max-height: 44px; overflow: hidden; }
        @media screen {
            .paper-viewport { max-width: 100%; overflow-x: auto; padding: 0 8px; }
            .edit-mode .paper { width: 180mm; height: auto; }
            .edit-mode .kop { width: 95mm; margin: 0 auto; }
            .edit-mode .body-wrapper { width: 170mm; height: 95mm; }
            .edit-mode .receipt-body { transform: none; }
        }
        @media screen and (max-width: 700px) { .paper { margin: 16px auto; } }
        @page { size: 105mm 220mm; margin: 0; }
        @media print {
            body { background: white; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar { display: none; }
            .paper { margin: 0; box-shadow: none; break-inside: avoid; }
            [contenteditable] { box-shadow: none !important; background-color: transparent !important; }
            body:not(.print-authorized) .paper { display: none; }
            .value { overflow: hidden; }
        }
    </style>
</head>
<body class="edit-mode">
    <div class="toolbar">
        <div class="toolbar-heading">
            <strong>Kwitansi {{ $transaction->transaction_code }}</strong>
            <div class="toolbar-actions">
                <a id="back-button" class="back-button" href="{{ route('salesdata.index') }}">Kembali</a>
                <button id="cancel-button" type="button">Batal</button>
                <button id="view-button" type="button" aria-pressed="false" aria-controls="receipt-paper">Lihat Format Cetak</button>
                <button id="print-button" type="button">Cetak Kwitansi</button>
            </div>
        </div>
        <p id="view-hint" aria-live="polite">Mode edit tegak. Saat dicetak, isi otomatis menyamping mengikuti format kwitansi fisik.</p>
        <p>Klik penerima, nominal, terbilang, atau keterangan pembayaran untuk mengedit. Nomor dan nama petugas dikunci.</p>
        <p>Kembali atau Batal sebelum cetak tidak memakai kesempatan cetak. Membuka dialog cetak memakai satu-satunya kesempatan, termasuk jika dialog dibatalkan.</p>
        <p id="status" role="status" aria-live="polite"></p>
    </div>
    <div class="paper-viewport">
    <main class="paper" id="receipt-paper">
        <header class="kop">
            @php
                $logoPath = null;
                foreach (array_filter([$pharmacy->logo ?? null, 'logo-sahabat.png', 'pmi_logo.png']) as $logo) {
                    if (file_exists(public_path('img/' . $logo))) {
                        $logoPath = asset('img/' . $logo);
                        break;
                    }
                }
            @endphp
            @if ($logoPath)<img class="kop-logo" src="{{ $logoPath }}" alt="Logo {{ $pharmacy->name ?? 'Apotek' }}">@endif
            <div class="kop-text">
                <h1>{{ strtoupper($pharmacy->name ?? 'APOTEK') }}</h1>
                <p>{{ $pharmacy->address }}</p>
                <p><b>Telp. {{ $pharmacy->phone }}</b></p>
                @if(!empty($pharmacy->pharmacist))<p>Apoteker : {{ $pharmacy->pharmacist }}</p>@endif
                @if(!empty($pharmacy->pharmacist_permit))<p>No. SIPA : {{ $pharmacy->pharmacist_permit }}</p>@endif
                @if(!empty($pharmacy->permit) || !empty($pharmacy->pharmacy_registration))<p>No. SIA : {{ $pharmacy->permit ?? $pharmacy->pharmacy_registration }}</p>@endif
            </div>
        </header>
        <div class="body-wrapper">
            <div class="receipt-body">
                <div class="number">KWITANSI NO. : <b>{{ $transaction->transaction_code }}</b></div>
                <div class="row"><span class="label">Sudah terima</span><span class="colon">:</span><div class="value" contenteditable="plaintext-only" id="recipient" data-fit-size="20" data-fit-min="14">{{ $patient?->name ?? 'Pelanggan Umum' }}</div></div>
                <div class="row"><span class="label">Banyaknya Uang</span><span class="colon">:</span><div class="words-frame"><div class="value" id="words" contenteditable="plaintext-only" aria-label="Terbilang" data-fit-size="18" data-fit-min="13">{{ $terbilang }}</div></div></div>
                <div class="row"><span class="label">Untuk Pembayaran</span><span class="colon">:</span><div class="value" id="payment-for" contenteditable="plaintext-only" data-fit-size="20" data-fit-min="13">{{ $paymentFor }}</div></div>
                <footer class="receipt-footer">
                    <div class="amount-box"><span class="rp">Rp.</span><div class="amount-frame"><span id="amount" contenteditable="plaintext-only" aria-label="Nominal rupiah" data-fit-size="20" data-fit-min="13">{{ number_format($totalPrice, 0, ',', '.') }}</span>,00,-</div></div>
                    <div class="signature">
                        <div class="signature-date">{{ $pharmacy->city ?? 'Samarinda' }}, {{ \Carbon\Carbon::parse($transaction->updated_at)->format('d/m/y') }}</div>
                        <div class="wet-signature"></div>
                        <div class="operator" data-fit-size="18" data-fit-min="12">{{ $operator }}</div>
                    </div>
                </footer>
            </div>
        </div>
    </main>
    </div>
    <script src="{{ asset('js/kwitansi.js') }}?v={{ filemtime(public_path('js/kwitansi.js')) }}"></script>
    <script>
        initializeKwitansi({
            claimUrl: @json(route('sales.kwitansi.claim', $transaction->id)),
            returnUrl: @json(route('salesdata.index')),
            transactionId: @json((string) $transaction->id)
        });
    </script>
</body>
</html>
