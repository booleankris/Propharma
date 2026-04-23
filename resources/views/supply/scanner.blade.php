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
        /* ── Bottom Sheet ── */
        .bottom-sheet-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5);
            z-index: 100;
            backdrop-filter: blur(2px);
        }

        .bottom-sheet-overlay.visible {
            display: block;
        }

        .bottom-sheet {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 101;
            background: #1e293b;
            border-radius: 24px 24px 0 0;
            padding: 0 20px 36px;
            transform: translateY(100%);
            transition: transform .35s cubic-bezier(.32, 1.2, .42, 1);
            max-width: 560px;
            margin: 0 auto;
        }

        .bottom-sheet.visible {
            transform: translateY(0);
        }

        /* drag handle */
        .sheet-handle {
            width: 40px;
            height: 4px;
            background: #334155;
            border-radius: 99px;
            margin: 12px auto 20px;
        }

        /* header */
        .sheet-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .sheet-header .medicine-name {
            font-size: 18px;
            font-weight: 800;
            color: #f1f5f9;
            line-height: 1.3;
            flex: 1;
            padding-right: 12px;
        }

        .sheet-header .medicine-code {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #475569;
            margin-top: 4px;
        }

        .sheet-close {
            background: #334155;
            border: none;
            color: #94a3b8;
            width: 32px;
            height: 32px;
            border-radius: 99px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            transition: background .15s;
        }

        .sheet-close:hover {
            background: #475569;
            color: #fff;
        }

        /* detail grid */
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 16px;
        }

        .detail-item {
            background: rgba(255, 255, 255, .04);
            border: 1px solid rgba(255, 255, 255, .07);
            border-radius: 14px;
            padding: 12px 14px;
        }

        .detail-item.span2 {
            grid-column: span 2;
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
            font-size: 17px;
            font-weight: 800;
            color: #e2e8f0;
        }

        .detail-item.highlight {
            border-color: rgba(59, 130, 246, .35);
            background: rgba(59, 130, 246, .08);
        }

        .detail-item.highlight .di-value {
            color: #60a5fa;
        }

        /* not found state inside sheet */
        .sheet-notfound {
            text-align: center;
            padding: 8px 0 4px;
        }

        .sheet-notfound .nf-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .sheet-notfound p {
            color: #fca5a5;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .sheet-notfound small {
            color: #64748b;
            font-size: 12px;
        }

        /* rescan button */
        .btn-rescan {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            transition: background .15s, transform .1s;
        }

        .btn-rescan:active {
            transform: scale(.97);
        }

        .btn-rescan:hover {
            background: #1d4ed8;
        }

        .btn-rescan.danger {
            background: rgba(220, 38, 38, .15);
            color: #fca5a5;
            border: 1px solid rgba(220, 38, 38, .25);
        }

        .btn-rescan.danger:hover {
            background: rgba(220, 38, 38, .25);
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
        {{-- Bottom sheet overlay --}}
        <div class="bottom-sheet-overlay" id="bs_overlay"></div>

        {{-- Bottom sheet --}}
        <div class="bottom-sheet" id="bottom_sheet">
            <div class="sheet-handle"></div>

            {{-- Found state --}}
            <div id="sheet_found" style="display:none;">
                <div class="sheet-header">
                    <div>
                        <div class="medicine-name" id="res_name">—</div>
                        <div class="medicine-code" id="res_code">—</div>
                    </div>
                    <button class="sheet-close" id="btn_close">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <path d="M18 6L6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="di-label">Satuan</div>
                        <div class="di-value" id="res_unit">—</div>
                    </div>
                    <div class="detail-item">
                        <div class="di-label">Harga Beli</div>
                        <div class="di-value" id="res_price">—</div>
                    </div>
                    <div class="detail-item highlight span2">
                        <div class="di-label">Stok Counter</div>
                        <div class="di-value" id="res_stock">—</div>
                    </div>
                </div>

                <button class="btn-rescan" id="btn_rescan">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0115 0M20 15a9 9 0 01-15 0" />
                    </svg>
                    Scan Lagi
                </button>
            </div>

            {{-- Not found state --}}
            <div id="sheet_notfound" style="display:none;">
                <div class="sheet-notfound">
                    <div class="nf-icon">🔍</div>
                    <p>Barcode tidak ditemukan</p>
                    <small>Periksa kembali dan coba scan ulang</small>
                </div>
                <br>
                <button class="btn-rescan danger" id="btn_rescan_alert">
                    Scan Ulang
                </button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/umd/index.min.js"></script>

    <script>
        /* ── State & DOM refs ─────────────────────────────────────────── */
        let scanning = true;

        const statusEl = document.getElementById('scan_status');
        const overlay = document.getElementById('bs_overlay');
        const sheet = document.getElementById('bottom_sheet');
        const shFound = document.getElementById('sheet_found');
        const shMissing = document.getElementById('sheet_notfound');

        /* ── Helpers ──────────────────────────────────────────────────── */
        function formatRupiah(val) {
            return 'Rp ' + parseInt(val || 0).toLocaleString('id-ID');
        }

        /* ── Bottom sheet ─────────────────────────────────────────────── */
        function openSheet(state) {
            shFound.style.display = state === 'found' ? 'block' : 'none';
            shMissing.style.display = state === 'notfound' ? 'block' : 'none';
            overlay.classList.add('visible');
            requestAnimationFrame(() => sheet.classList.add('visible'));
        }

        function closeSheet() {
            sheet.classList.remove('visible');
            overlay.classList.remove('visible');
            resetScan();
        }

        function resetScan() {
            statusEl.textContent = 'Siap scan…';
            scanning = true;
        }

        /* ── Show results ─────────────────────────────────────────────── */
        function showResult(data) {
            document.getElementById('res_name').textContent = data.name;
            document.getElementById('res_code').textContent = data.code;
            document.getElementById('res_unit').textContent = data.unit;
            document.getElementById('res_price').textContent = formatRupiah(data.raw_price);
            document.getElementById('res_stock').textContent = data.stock;
            statusEl.textContent = 'Ditemukan';
            scanning = false;
            openSheet('found');
        }

        function showNotFound() {
            statusEl.textContent = 'Tidak ditemukan';
            scanning = false;
            openSheet('notfound');
        }

        /* ── Event listeners (each button bound exactly once) ─────────── */
        overlay.addEventListener('click', closeSheet);
        document.getElementById('btn_close').addEventListener('click', closeSheet);
        document.getElementById('btn_rescan').addEventListener('click', closeSheet);
        document.getElementById('btn_rescan_alert').addEventListener('click', closeSheet);

        /* ── API lookup ───────────────────────────────────────────────── */
        async function lookupBarcode(barcode) {
            statusEl.textContent = 'Mencari…';
            try {
                const res = await fetch(`{{ route('supplies.scan') }}?barcode=${encodeURIComponent(barcode)}`);
                const data = await res.json();
                res.ok && data.found ? showResult(data) : showNotFound();
            } catch (err) {
                showNotFound();
            }
        }

        /* ── ZXing scanner ────────────────────────────────────────────── */
        const codeReader = new ZXing.BrowserMultiFormatReader();

        codeReader.decodeFromConstraints({
                video: {
                    facingMode: {
                        exact: 'environment'
                    },
                    width: {
                        ideal: 1280
                    },
                    height: {
                        ideal: 720
                    }
                }
            },
            'preview',
            (result) => {
                if (!scanning || !result) return;
                scanning = false;
                lookupBarcode(result.getText());
            }
        ).catch(() => {
            codeReader.decodeFromConstraints({
                    video: {
                        facingMode: 'environment'
                    }
                },
                'preview',
                (result) => {
                    if (!scanning || !result) return;
                    scanning = false;
                    lookupBarcode(result.getText());
                }
            ).catch(() => {
                statusEl.textContent = '⚠️ Kamera tidak dapat dibuka';
            });
        });
    </script>
@endsection
