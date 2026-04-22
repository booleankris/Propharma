@extends('layouts.app')

@section('title', 'Scan Barcode')

@section('style')
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            background: #0f172a;
        }

        .scanner-wrapper {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            background: #0f172a;
            color: #f8fafc;
            padding: 0;
        }

        /* ── Top bar ── */
        .scanner-topbar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: rgba(255, 255, 255, .04);
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .scanner-topbar a {
            color: #94a3b8;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .scanner-topbar a:hover {
            color: #fff;
        }

        .scanner-topbar h1 {
            font-size: 16px;
            font-weight: 700;
            color: #f1f5f9;
            margin: 0;
        }

        /* ── Viewfinder ── */
        .viewfinder-wrap {
            position: relative;
            width: 100%;
            max-width: 480px;
            margin: 24px auto 0;
            border-radius: 20px;
            overflow: hidden;
            background: #000;
            aspect-ratio: 1 / 1;
        }

        #preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        /* scanning line animation */
        .scan-line {
            position: absolute;
            left: 10%;
            width: 80%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
            top: 0;
            animation: scanMove 2s linear infinite;
            pointer-events: none;
        }

        @keyframes scanMove {
            0% {
                top: 10%;
            }

            100% {
                top: 90%;
            }
        }

        /* corner brackets */
        .corner {
            position: absolute;
            width: 36px;
            height: 36px;
            border-color: #3b82f6;
            border-style: solid;
            pointer-events: none;
        }

        .corner.tl {
            top: 12px;
            left: 12px;
            border-width: 3px 0 0 3px;
            border-radius: 4px 0 0 0;
        }

        .corner.tr {
            top: 12px;
            right: 12px;
            border-width: 3px 3px 0 0;
            border-radius: 0 4px 0 0;
        }

        .corner.bl {
            bottom: 12px;
            left: 12px;
            border-width: 0 0 3px 3px;
            border-radius: 0 0 0 4px;
        }

        .corner.br {
            bottom: 12px;
            right: 12px;
            border-width: 0 3px 3px 0;
            border-radius: 0 0 4px 0;
        }

        /* status badge */
        .scan-status {
            position: absolute;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, .6);
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 14px;
            border-radius: 99px;
            white-space: nowrap;
            backdrop-filter: blur(6px);
        }

        /* ── Result card ── */
        .result-area {
            flex: 1;
            padding: 20px;
            max-width: 480px;
            width: 100%;
            margin: 0 auto;
        }

        .result-card {
            background: rgba(255, 255, 255, .05);
            border: 1px solid rgba(255, 255, 255, .1);
            border-radius: 16px;
            padding: 20px;
            display: none;
        }

        .result-card.visible {
            display: block;
        }

        .result-card .medicine-name {
            font-size: 18px;
            font-weight: 800;
            color: #f1f5f9;
            margin-bottom: 4px;
        }

        .result-card .medicine-code {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 16px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .detail-item {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 12px;
            padding: 12px 14px;
        }

        .detail-item .di-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #475569;
            margin-bottom: 4px;
        }

        .detail-item .di-value {
            font-size: 16px;
            font-weight: 800;
            color: #e2e8f0;
        }

        .detail-item.highlight {
            border-color: rgba(59, 130, 246, .4);
            background: rgba(59, 130, 246, .08);
        }

        .detail-item.highlight .di-value {
            color: #60a5fa;
        }

        /* ── Alert ── */
        .scan-alert {
            display: none;
            margin-top: 16px;
            background: rgba(220, 38, 38, .12);
            border: 1px solid rgba(220, 38, 38, .3);
            border-radius: 14px;
            padding: 16px 18px;
            color: #fca5a5;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
        }

        .scan-alert.visible {
            display: block;
        }

        /* ── Rescan button ── */
        .btn-rescan {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 16px;
            padding: 13px;
            background: #2563eb;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: background .15s;
        }

        .btn-rescan:hover {
            background: #1d4ed8;
        }
    </style>
@endsection

@section('content')
    <div class="scanner-wrapper">

        {{-- Top bar --}}
        <div class="scanner-topbar">
            <a href="{{ route('home') }}">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1>Scan Barcode Obat</h1>
        </div>

        {{-- Viewfinder --}}
        <div style="padding: 0 20px;">
            <div class="viewfinder-wrap">
                <video id="preview" playsinline autoplay muted></video>
                <div class="scan-line"></div>
                <div class="corner tl"></div>
                <div class="corner tr"></div>
                <div class="corner bl"></div>
                <div class="corner br"></div>
                <div class="scan-status" id="scan_status">Mengarahkan kamera…</div>
            </div>
        </div>

        {{-- Result area --}}
        <div class="result-area">

            {{-- Success card --}}
            <div class="result-card" id="result_card">
                <div class="medicine-name" id="res_name">—</div>
                <div class="medicine-code" id="res_code">—</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="di-label">Satuan</div>
                        <div class="di-value" id="res_unit">—</div>
                    </div>
                    <div class="detail-item">
                        <div class="di-label">Harga Beli</div>
                        <div class="di-value" id="res_price">—</div>
                    </div>
                    <div class="detail-item highlight" style="grid-column: span 2;">
                        <div class="di-label">Stok Counter</div>
                        <div class="di-value" id="res_stock">—</div>
                    </div>
                </div>

                <button class="btn-rescan" id="btn_rescan">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0115 0M20 15a9 9 0 01-15 0" />
                    </svg>
                    Scan Lagi
                </button>
            </div>

            {{-- Not found alert --}}
            <div class="scan-alert" id="scan_alert">
                ⚠️ Barcode tidak ditemukan. Periksa kembali dan coba scan ulang.
                <button class="btn-rescan" id="btn_rescan_alert" style="margin-top:12px;">
                    Scan Ulang
                </button>
            </div>

        </div>
    </div>
@endsection

@section('scripts')
    {{-- ZXing barcode scanner library --}}
    <script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/umd/index.min.js"></script>

    <script>
        const statusEl = document.getElementById('scan_status');
        const resultCard = document.getElementById('result_card');
        const alertEl = document.getElementById('scan_alert');
        let scanning = true;

        // ── Format currency ────────────────────────────────────────────────
        function formatRupiah(val) {
            return 'Rp ' + parseInt(val || 0).toLocaleString('id-ID');
        }

        // ── Show result card ───────────────────────────────────────────────
        function showResult(data) {
            document.getElementById('res_name').textContent = data.name;
            document.getElementById('res_code').textContent = data.code;
            document.getElementById('res_unit').textContent = data.unit;
            document.getElementById('res_price').textContent = formatRupiah(data.raw_price);
            document.getElementById('res_stock').textContent = data.stock;

            resultCard.classList.add('visible');
            alertEl.classList.remove('visible');
            statusEl.textContent = '✅ Ditemukan';
            scanning = false;
        }

        // ── Show not-found alert ───────────────────────────────────────────
        function showNotFound() {
            alertEl.classList.add('visible');
            resultCard.classList.remove('visible');
            statusEl.textContent = '❌ Tidak ditemukan';
            scanning = false;
        }

        // ── Lookup barcode via API ─────────────────────────────────────────
        async function lookupBarcode(barcode) {
            statusEl.textContent = 'Mencari…';
            try {
                const res = await fetch(`{{ route('supplies.scan') }}?barcode=${encodeURIComponent(barcode)}`);
                if (res.ok) {
                    const data = await res.json();
                    data.found ? showResult(data) : showNotFound();
                } else {
                    showNotFound();
                }
            } catch (err) {
                showNotFound();
            }
        }

        // ── Rescan ─────────────────────────────────────────────────────────
        function resetScan() {
            resultCard.classList.remove('visible');
            alertEl.classList.remove('visible');
            statusEl.textContent = 'Mengarahkan kamera…';
            scanning = true;
        }

        document.getElementById('btn_rescan').addEventListener('click', resetScan);
        document.getElementById('btn_rescan_alert').addEventListener('click', resetScan);

        // ── Start ZXing scanner ────────────────────────────────────────────
        const codeReader = new ZXing.BrowserMultiFormatReader();

        codeReader.listVideoInputDevices().then(devices => {
            // Prefer back camera on mobile
            const device = devices.find(d =>
                /back|rear|environment/i.test(d.label)
            ) || devices[devices.length - 1];

            if (!device) {
                statusEl.textContent = '⚠️ Kamera tidak ditemukan';
                return;
            }

            statusEl.textContent = 'Siap scan…';

            codeReader.decodeFromVideoDevice(device.deviceId, 'preview', (result, err) => {
                if (!scanning) return; // debounce — wait for user to rescan
                if (result) {
                    scanning = false; // pause scanning while we look up
                    lookupBarcode(result.getText());
                }
            });
        }).catch(() => {
            statusEl.textContent = '⚠️ Izin kamera ditolak';
        });
    </script>
@endsection
