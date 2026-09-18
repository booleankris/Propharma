<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kwitansi {{ $transaction->transaction_code }} - {{ $pharmacy->name ?? 'Apotek' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Dynalight&family=Ephesis&display=swap" rel="stylesheet">

    <style>
        :root {
            --form-font: 'Courier Prime', Courier, monospace;
            --detail-font: 'Dynalight', 'Ephesis', cursive;
        }

        /* ── Reset & Base ────────────────────────────────────────────── */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif;
            background-color: #f1f5f9;
            color: #111827;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* ── Screen Toolbar ──────────────────────────────────────────── */
        .toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 9999;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .toolbar-title {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .btn-print {
            background-color: #2563eb;
            color: #ffffff;
            box-shadow: 0 1px 2px rgba(37, 99, 235, 0.2);
        }

        .btn-print:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #f1f5f9;
        }

        .btn-close {
            background-color: #f1f5f9;
            color: #64748b;
        }

        .btn-close:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }

        /* ── Paper Container ─────────────────────────────────────────── */
        .paper-viewport {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 76px 16px 40px;
            min-height: 100vh;
        }

        .kwitansi-paper {
            width: 105mm;
            min-height: 220mm;
            background: #ffffff;
            padding: 18px 16px 20px 16px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.08);
            border-radius: 4px;
            position: relative;
            box-sizing: border-box;
        }

        /* ── KOP APOTEK (Lurus / Horizontal) ────────────────────────── */
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2px;
        }

        .kop-logo-td {
            width: 60px;
            vertical-align: middle;
            text-align: center;
            padding-right: 8px;
        }

        .kop-logo-img {
            max-width: 54px;
            max-height: 64px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }

        .kop-text-td {
            vertical-align: middle;
        }

        .kop-name {
            font-size: 13.5px;
            font-weight: 800;
            color: #000;
            letter-spacing: 0.3px;
            line-height: 1.2;
            margin-bottom: 2px;
        }

        .kop-detail {
            font-size: 9px;
            line-height: 1.28;
            color: #111;
        }

        .kop-double-line {
            border-top: 1.8px solid #000;
            border-bottom: 0.8px solid #000;
            height: 2px;
            margin-top: 5px;
            margin-bottom: 8px;
        }

        /* ── BADAN KWITANSI (Menyamping / Rotated Mode) ──────────────── */
        .kwitansi-body-wrapper {
            width: 100%;
            height: 590px;
            position: relative;
            overflow: hidden;
            background: #fff;
        }

        /* Rotated exactly matching client photograph */
        .kwitansi-body-wrapper.rotated-mode .kwitansi-canvas {
            width: 590px;
            height: 360px;
            position: absolute;
            top: 0;
            left: 0;
            transform-origin: top left;
            transform: translateX(360px) rotate(90deg);
        }

        /* Normal horizontal upright mode (toggleable for reading on screen) */
        .kwitansi-body-wrapper.upright-mode {
            height: auto;
            overflow: visible;
        }

        .kwitansi-body-wrapper.upright-mode .kwitansi-canvas {
            width: 100%;
            height: auto;
            position: relative;
            transform: none;
        }

        .kwitansi-canvas {
            font-family: var(--form-font);
            padding: 8px 10px 4px 6px;
            font-size: 11px;
            color: #000;
        }

        /* ── Baris 1: KWITANSI NO. ──────────────────────────────────── */
        .row-kwitansi-no {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            font-family: var(--form-font);
        }

        .kwitansi-no-label {
            font-family: var(--form-font);
            font-weight: 700;
            font-size: 11.5px;
            letter-spacing: 0.5px;
        }

        .kwitansi-no-box {
            border: 1.4px solid #000;
            padding: 2px 14px;
            font-weight: 700;
            font-size: 11.5px;
            font-family: var(--form-font);
            letter-spacing: 0.5px;
            min-width: 110px;
            text-align: center;
        }

        /* ── Baris Form (Sudah terima, Banyaknya Uang, Untuk Pembayaran) */
        .form-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 8px;
            position: relative;
            font-family: var(--form-font);
        }

        .form-label {
            font-family: var(--form-font);
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            width: 140px;
            flex-shrink: 0;
            letter-spacing: -0.1px;
        }

        .form-colon {
            font-family: var(--form-font);
            width: 14px;
            text-align: center;
            flex-shrink: 0;
            font-weight: 700;
        }

        .form-fill-dotted {
            flex-grow: 1;
            position: relative;
            border-bottom: 1px dotted #444;
            min-height: 18px;
            display: flex;
            align-items: flex-end;
            padding-bottom: 1px;
        }

        .form-fill-value {
            font-family: var(--detail-font);
            font-size: 23px;
            font-weight: 400;
            color: #0b132b;
            padding: 0 4px;
            letter-spacing: 0.3px;
            line-height: 1;
        }

        .handwritten-look {
            font-family: var(--detail-font);
            font-size: 23px;
            font-weight: 400;
            color: #0b132b;
            line-height: 1;
        }

        /* ── Kotak Terbilang (Banyaknya Uang - Jajaran Genjang Bergaris Ganda) */
        .terbilang-double-slash-box {
            flex-grow: 1;
            border: 1.2px solid #000;
            border-radius: 2px;
            padding: 4px 10px;
            margin-left: 2px;
            position: relative;
            background: #fafafa;
            box-shadow: inset 0 0 0 1.2px #000;
        }

        .terbilang-text {
            font-family: var(--detail-font);
            font-size: 20px;
            font-weight: 400;
            color: #09090b;
            line-height: 1.25;
            letter-spacing: 0.3px;
        }

        /* ── Dotted line blank guides ────────────────────────────────── */
        .dotted-line-blank {
            width: 100%;
            height: 17px;
            border-bottom: 1px dotted #444;
            margin-left: 154px;
            width: calc(100% - 154px);
            margin-top: 4px;
            font-family: var(--detail-font);
            font-size: 21px;
            line-height: 1;
        }

        /* ── Bottom Section: Kotak Nominal Rp & Tanda Tangan / Cap ───── */
        .bottom-section {
            margin-top: 18px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
        }

        /* Kotak Nominal Rp */
        .nominal-container {
            display: flex;
            align-items: center;
            gap: 6px;
            font-family: var(--form-font);
        }

        .rp-prefix {
            font-family: var(--form-font);
            font-size: 15px;
            font-weight: 700;
            font-style: normal;
        }

        .nominal-box {
            border: 1.5px solid #000;
            box-shadow: inset 0 0 0 1px #000;
            padding: 4px 16px;
            font-family: var(--form-font);
            font-size: 13.5px;
            font-weight: 700;
            letter-spacing: 0.8px;
            min-width: 140px;
            text-align: center;
            background: #fff;
            position: relative;
        }

        .nominal-box::before,
        .nominal-box::after {
            content: '';
            position: absolute;
            top: 2px;
            bottom: 2px;
            width: 3px;
            border-left: 1.2px solid #000;
            border-right: 1.2px solid #000;
        }

        .nominal-box::before {
            left: 3px;
        }

        .nominal-box::after {
            right: 3px;
        }

        /* ── Editable Fields Styling ────────────────────────────────── */
        .editable-field {
            cursor: text;
            outline: none;
            transition: background-color 0.15s, box-shadow 0.15s;
            border-radius: 2px;
            padding: 1px 3px;
        }

        .editable-field:hover {
            background-color: rgba(254, 240, 138, 0.45);
            box-shadow: 0 0 0 1px #eab308;
        }

        .editable-field:focus {
            background-color: #fefce8;
            box-shadow: 0 0 0 1.5px #ca8a04;
        }

        /* Tempat Tanggal & Tanda Tangan (Tanpa Stempel) */
        .signature-container {
            text-align: center;
            width: 200px;
            position: relative;
            font-family: var(--form-font);
        }

        .sig-date {
            font-family: var(--detail-font);
            font-size: 18px;
            font-weight: 400;
            color: #0b132b;
            border-bottom: 1px dotted #444;
            padding-bottom: 2px;
            margin-bottom: 6px;
            display: inline-block;
            min-width: 150px;
        }

        .sig-area {
            position: relative;
            height: 64px;
            margin: 2px 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sig-image {
            max-height: 58px;
            max-width: 110px;
            position: relative;
            transform: rotate(-3deg);
        }

        .sig-name {
            font-family: var(--detail-font);
            font-size: 18px;
            font-weight: 400;
            color: #0f172a;
        }

        /* ── PRINT RULES ─────────────────────────────────────────────── */
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .paper-viewport {
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
            }

            .kwitansi-paper {
                box-shadow: none !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 4mm 5mm 5mm 5mm !important;
                width: 100% !important;
                min-height: auto !important;
            }

            @page {
                size: 105mm 220mm;
                margin: 0mm;
            }
        }
    </style>
</head>

<body>

    {{-- Screen Toolbar --}}
    <div class="toolbar no-print">
        <div class="toolbar-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <span>Kwitansi Pembayaran: <strong>{{ $transaction->transaction_code }}</strong></span>
            <span style="font-size:11px; color:#059669; background:#ecfdf5; border:1px solid #a7f3d0; padding:3px 8px; border-radius:6px; font-weight:600; display:inline-flex; align-items:center; gap:4px; margin-left:8px;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Teks dapat langsung diklik & diedit
            </span>
        </div>
        <div class="toolbar-actions">
            <div style="display:inline-flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #cbd5e1; padding:5px 10px; border-radius:8px; font-size:12px; font-weight:600;">
                <span style="color:#64748b;">Font Isi:</span>
                <select id="detailFontSelector" onchange="switchDetailFont(this.value)" style="border:none; background:transparent; font-size:12px; font-weight:700; color:#1e293b; cursor:pointer; outline:none;">
                    <option value="'Dynalight', 'Ephesis', cursive">Dynalight</option>
                    <option value="'Ephesis', 'Dynalight', cursive">Ephesis</option>
                </select>
            </div>
            <button type="button" onclick="location.reload()" class="btn btn-secondary" title="Kembalikan ke data awal">
                Reset
            </button>
            <button type="button" onclick="toggleOrientation()" class="btn btn-secondary" id="btnToggleMode"
                title="Beralih antara mode cetak menyamping sesuai foto fisik klien atau tegak">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
                </svg>
                <span id="btnToggleText">Mode: Menyamping (Sesuai Foto)</span>
            </button>
            <button type="button" onclick="window.print()" class="btn btn-print">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>Cetak Kwitansi</span>
            </button>
            <button type="button" onclick="window.close()" class="btn btn-close">
                Tutup
            </button>
        </div>
    </div>

    {{-- Paper Viewport --}}
    <div class="paper-viewport">
        <div class="kwitansi-paper">

            {{-- 1. KOP APOTEK RESMI (Lurus / Horizontal) --}}
            <table class="kop-table">
                <tr>
                    <td class="kop-logo-td">
                        @php
                            $logoPath = null;
                            if (!empty($pharmacy->logo) && file_exists(public_path('img/' . $pharmacy->logo))) {
                                $logoPath = asset('img/' . $pharmacy->logo);
                            } elseif (file_exists(public_path('img/pmi_logo.png'))) {
                                $logoPath = asset('img/pmi_logo.png');
                            } elseif (file_exists(public_path('img/logo-sahabat.png'))) {
                                $logoPath = asset('img/logo-sahabat.png');
                            }
                        @endphp
                        @if ($logoPath)
                            <img src="{{ $logoPath }}" alt="Logo Apotek" class="kop-logo-img">
                        @else
                            <div
                                style="font-weight:bold; font-size:11px; border:1px solid #000; padding:4px; border-radius:4px;">
                                APOTEK
                            </div>
                        @endif
                    </td>
                    <td class="kop-text-td">
                        <div class="kop-name editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit nama apotek">{{ strtoupper($pharmacy->name ?? 'APOTEK SAHABAT') }}</div>
                        <div class="kop-detail">
                            <div class="editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit alamat">{{ $pharmacy->address ?? 'Jl. Palang Merah No.16 B, Sidodadi' }}</div>
                            <div class="editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit telepon">Telp. {{ $pharmacy->phone ?? '0812 5758 6688' }}</div>
                            <div class="editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit apoteker">Apoteker : {{ $pharmacy->pharmacist ?? 'apt. Nurlina Muliani, S.Farm., M. Farm' }}</div>
                            <div class="editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit SIPA">No. SIPA : {{ $pharmacy->pharmacist_permit ?? '12440004111020004' }}</div>
                            @if (!empty($pharmacy->permit))
                                <div class="editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit SIA">No. SIA : {{ $pharmacy->permit }}</div>
                            @elseif(!empty($pharmacy->pharmacy_registration))
                                <div class="editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit SIA">No. SIA : {{ $pharmacy->pharmacy_registration }}</div>
                            @endif
                        </div>
                    </td>
                </tr>
            </table>

            {{-- Garis Ganda Pemisah Kop --}}
            <div class="kop-double-line"></div>

            {{-- 2. BADAN KWITANSI (Menyamping / Rotated) --}}
            <div class="kwitansi-body-wrapper rotated-mode" id="kwitansiWrapper">
                <div class="kwitansi-canvas">

                    {{-- Baris 1: KWITANSI NO. : [ ... ] --}}
                    <div class="row-kwitansi-no">
                        <span class="kwitansi-no-label">KWITANSI NO. :</span>
                        <div class="kwitansi-no-box editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit nomor kwitansi">{{ $transaction->transaction_code }}</div>
                    </div>

                    {{-- Baris 2: Sudah terima : <Nama Pasien / Pembeli> --}}
                    <div class="form-row">
                        <span class="form-label">Sudah terima</span>
                        <span class="form-colon">:</span>
                        <div class="form-fill-dotted">
                            <span class="form-fill-value handwritten-look editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit nama pembeli/pasien">
                                {{ $patient?->name ?? 'Pelanggan Umum' }}
                            </span>
                        </div>
                    </div>

                    {{-- Baris 3: Banyaknya Uang : // Terbilang // --}}
                    <div class="form-row" style="align-items: center;">
                        <span class="form-label">Banyaknya Uang</span>
                        <span class="form-colon">:</span>
                        <div class="terbilang-double-slash-box">
                            <span class="terbilang-text editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit terbilang">
                                {{ $terbilang }} .
                            </span>
                        </div>
                    </div>

                    {{-- Baris 4: Untuk Pembayaran : <Obat-obatan ...> --}}
                    <div class="form-row">
                        <span class="form-label">Untuk Pembayaran</span>
                        <span class="form-colon">:</span>
                        <div class="form-fill-dotted">
                            <span class="form-fill-value handwritten-look editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit keterangan pembayaran">
                                {{ $paymentFor }}
                            </span>
                        </div>
                    </div>

                    {{-- Garis Titik-titik Tambahan Panduan Tulisan --}}
                    <div class="dotted-line-blank editable-field" contenteditable="true" spellcheck="false" title="Klik untuk tambah keterangan baris 2"></div>
                    <div class="dotted-line-blank editable-field" contenteditable="true" spellcheck="false" title="Klik untuk tambah keterangan baris 3"></div>

                    {{-- Bagian Bawah: Nominal Rp (Kiri) & Tempat/Tanggal/TTD (Kanan - Tanpa Stempel) --}}
                    <div class="bottom-section">

                        {{-- Kotak Nominal Rp --}}
                        <div class="nominal-container">
                            <span class="rp-prefix">Rp.</span>
                            <div class="nominal-box editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit nominal">
                                {{ number_format($totalPrice, 0, ',', '.') }},00,-
                            </div>
                        </div>

                        {{-- Tempat, Tanggal, dan Tanda Tangan (Tanpa Stempel) --}}
                        <div class="signature-container">
                            <div class="sig-date editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit kota & tanggal">
                                {{ $pharmacy->city ?? 'Samarinda' }},
                                {{ \Carbon\Carbon::parse($transaction->updated_at)->format('d/m/y') }}
                            </div>

                            <div class="sig-area">
                                {{-- Signature Image if available --}}
                                @php
                                    $sigPath = null;
                                    if (
                                        !empty($pharmacy->signature) &&
                                        file_exists(public_path('img/' . $pharmacy->signature))
                                    ) {
                                        $sigPath = asset('img/' . $pharmacy->signature);
                                    } elseif (file_exists(public_path('img/ttd-sahabat.png'))) {
                                        $sigPath = asset('img/ttd-sahabat.png');
                                    }
                                @endphp
                                @if ($sigPath)
                                    <img src="{{ $sigPath }}" alt="Tanda Tangan" class="sig-image">
                                @else
                                    <div style="height: 48px;"></div>
                                @endif
                            </div>

                            <div class="sig-name editable-field" contenteditable="true" spellcheck="false" title="Klik untuk edit nama petugas">
                                ( {{ $operator ?? 'Petugas Farmasi' }} )
                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>

    <script>
        // Toggle view mode between client rotated format and upright format
        function toggleOrientation() {
            const wrapper = document.getElementById('kwitansiWrapper');
            const btnText = document.getElementById('btnToggleText');
            if (wrapper.classList.contains('rotated-mode')) {
                wrapper.classList.remove('rotated-mode');
                wrapper.classList.add('upright-mode');
                btnText.textContent = 'Mode: Tegak (Lurus Layar)';
            } else {
                wrapper.classList.remove('upright-mode');
                wrapper.classList.add('rotated-mode');
                btnText.textContent = 'Mode: Menyamping (Sesuai Foto)';
            }
        }

        // Switch detail font between Dynalight and Ephesis
        function switchDetailFont(font) {
            document.documentElement.style.setProperty('--detail-font', font);
            localStorage.setItem('kwitansi_detail_font', font);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const saved = localStorage.getItem('kwitansi_detail_font');
            if (saved) {
                document.documentElement.style.setProperty('--detail-font', saved);
                const select = document.getElementById('detailFontSelector');
                if (select) select.value = saved;
            }
        });
    </script>
</body>

</html>
