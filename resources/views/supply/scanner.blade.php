@extends('layouts.app')

@section('title', 'Scan Barcode')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
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
            padding-bottom: 20px;
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
            max-height: 90dvh;
            overflow-y: auto;
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
            color: #94a3b8;
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

        /* Form Opname Inputs */
        .form-opname {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f1f5f9;
            padding: 12px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #3b82f6;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }
        select.form-control option {
            background: #1e293b;
            color: #f1f5f9;
        }

        .form-control.discrepancy {
            background: transparent;
            border: none;
            padding: 0;
            font-size: 20px;
            text-align: center;
        }
        
        .form-control.discrepancy.error {
            color: #fca5a5;
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

        /* buttons */
        .btn-wrapper {
            display: flex;
            gap: 10px;
        }

        .btn-action {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex: 1;
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

        .btn-action:active {
            transform: scale(.97);
        }

        .btn-action:hover {
            background: #1d4ed8;
        }

        .btn-action.secondary {
            background: #334155;
            color: #e2e8f0;
        }

        .btn-action.secondary:hover {
            background: #475569;
        }

        .btn-action.danger {
            background: rgba(220, 38, 38, .15);
            color: #fca5a5;
            border: 1px solid rgba(220, 38, 38, .25);
        }

        .btn-action.danger:hover {
            background: rgba(220, 38, 38, .25);
        }

        #discrepancy_badge {
            display: none;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 99px;
            font-weight: 700;
            margin-left: auto;
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

                <div class="detail-grid" style="{{ canAccessWarehouseStock() ? '' : 'grid-template-columns: 1fr;' }}">
                    <div class="detail-item">
                        <div class="di-label">Satuan</div>
                        <div class="di-value" id="res_unit">—</div>
                    </div>
                    <div class="detail-item">
                        <div class="di-label">Harga Beli</div>
                        <div class="di-value" id="res_price">—</div>
                    </div>
                    @if(canAccessWarehouseStock())
                    <div class="detail-item highlight">
                        <div class="di-label">Stok Gudang</div>
                        <div class="di-value" id="res_stock_gudang">—</div>
                    </div>
                    @endif
                    <div class="detail-item highlight">
                        <div class="di-label">Stok Counter</div>
                        <div class="di-value" id="res_stock_counter">—</div>
                    </div>
                </div>

                {{-- Opname Form (Matches Desktop) --}}
                <div class="form-opname">
                    <input type="hidden" id="medicine_id">
                    
                    <div style="display: grid; grid-template-columns: {{ canAccessWarehouseStock() ? '1fr 1fr' : '1fr' }}; gap: 10px;">
                        @if(canAccessWarehouseStock())
                        <div class="form-group">
                            <label>Fisik Gudang</label>
                            <input type="number" id="stock_physic" class="form-control" placeholder="0" onkeyup="countDiscrepancy()">
                        </div>
                        @else
                        <input type="hidden" id="stock_physic" value="0">
                        @endif
                        <div class="form-group">
                            <label>Fisik Counter</label>
                            <input type="number" id="counter_stock_physic" class="form-control" placeholder="0" onkeyup="countDiscrepancy()">
                        </div>
                    </div>

                    <div class="form-group" style="text-align: center; margin: 15px 0;">
                        <label style="display: flex; justify-content: center; align-items: center; gap: 8px;">
                            Selisih Stok <span id="discrepancy_badge"></span>
                        </label>
                        <input type="text" readonly id="stock_discrepancy" class="form-control discrepancy" placeholder="—">
                    </div>

                    <div class="form-group">
                        <label>Penyesuaian Batch (Opsional)</label>
                        <select id="batch_select" class="form-control">
                            <option value="">— Otomatis (FEFO) —</option>
                        </select>
                    </div>
                </div>

                <div class="btn-wrapper">
                    <button class="btn-action secondary" id="btn_rescan">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0115 0M20 15a9 9 0 01-15 0" />
                        </svg>
                        Tutup & Scan
                    </button>
                    <button class="btn-action" id="btn_save_opname">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        Simpan Opname
                    </button>
                </div>
            </div>

            {{-- Not found state --}}
            <div id="sheet_notfound" style="display:none;">
                <div class="sheet-notfound">
                    <div class="nf-icon">🔍</div>
                    <p>Barcode tidak ditemukan</p>
                    <small>Periksa kembali dan coba scan ulang</small>
                </div>
                <br>
                <button class="btn-action danger" id="btn_rescan_alert">
                    Scan Ulang
                </button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.20.0/umd/index.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script>
        /* ── State & DOM refs ─────────────────────────────────────────── */
        let scanning = true;

        const statusEl = document.getElementById('scan_status');
        const overlay = document.getElementById('bs_overlay');
        const sheet = document.getElementById('bottom_sheet');
        const shFound = document.getElementById('sheet_found');
        const shMissing = document.getElementById('sheet_notfound');

        let current_storage_stock = 0;
        let current_counter_stock = 0;
        let total_stock = 0; // current system stock (storage + counter)

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
            $('#stock_physic, #counter_stock_physic, #stock_discrepancy').val('');
            $('#batch_select').prop('selectedIndex', 0);
            document.getElementById('discrepancy_badge').style.display = 'none';
            document.getElementById('stock_discrepancy').classList.remove('error');
        }

        /* ── Opname Logic (Matches Desktop) ────────────────────────────── */
        function loadBatches(medicine_id) {
            const select = document.getElementById('batch_select');
            select.innerHTML = '<option value="">Memuat batch…</option>';

            const canSeeWarehouse = {{ canAccessWarehouseStock() ? 'true' : 'false' }};

            fetch(`{{ route('supplies.batches') }}?medicine_id=${medicine_id}`)
                .then(res => res.json())
                .then(batches => {
                    select.innerHTML = '<option value="">— Otomatis (FEFO) —</option>';

                    let totalStorageStock = 0;
                    let totalCounterStock = 0;

                    batches.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        const gStock = parseInt(b.stock || 0);
                        const cStock = parseInt(b.counter_stock || 0);
                        opt.textContent = canSeeWarehouse
                            ? `${b.name} — Exp: ${b.expired_date} (Gdg: ${gStock}, Ctr: ${cStock})`
                            : `${b.name} — Exp: ${b.expired_date} (Stok: ${cStock})`;
                        opt.dataset.stock = gStock;
                        opt.dataset.counterStock = cStock;
                        select.appendChild(opt);
                        
                        totalStorageStock += gStock;
                        totalCounterStock += cStock;
                    });

                    updateTotalStockFromSelect();
                    // Set default display
                    if (document.getElementById('res_stock_gudang')) {
                        document.getElementById('res_stock_gudang').textContent = totalStorageStock;
                    }
                    if (document.getElementById('res_stock_counter')) {
                        document.getElementById('res_stock_counter').textContent = totalCounterStock;
                    }
                })
                .catch(() => {
                    select.innerHTML = '<option value="">— Gagal memuat batch —</option>';
                });
        }

        function updateTotalStockFromSelect() {
            const select = document.getElementById('batch_select');
            const selectedOpt = select.options[select.selectedIndex];

            if (selectedOpt && selectedOpt.value !== '') {
                current_storage_stock = parseInt(selectedOpt.dataset.stock) || 0;
                current_counter_stock = parseInt(selectedOpt.dataset.counterStock) || 0;
            } else {
                let sumStorage = 0;
                let sumCounter = 0;
                for (let i = 1; i < select.options.length; i++) {
                    sumStorage += parseInt(select.options[i].dataset.stock) || 0;
                    sumCounter += parseInt(select.options[i].dataset.counterStock) || 0;
                }
                current_storage_stock = sumStorage;
                current_counter_stock = sumCounter;
            }

            total_stock = current_storage_stock + current_counter_stock;

            if ($('#stock_physic').val() !== '' || $('#counter_stock_physic').val() !== '') {
                countDiscrepancy();
            }
        }

        document.getElementById('batch_select')?.addEventListener('change', updateTotalStockFromSelect);

        function countDiscrepancy() {
            const valGudang = $('#stock_physic').val();
            const valCounter = $('#counter_stock_physic').val();
            const input = document.getElementById('stock_discrepancy');
            const badge = document.getElementById('discrepancy_badge');

            if (valGudang === '' && valCounter === '') {
                input.value = '';
                input.classList.remove('error');
                badge.style.display = 'none';
                return;
            }

            const gudangPhysic = valGudang !== '' ? (parseInt(valGudang) || 0) : current_storage_stock;
            const counterPhysic = valCounter !== '' ? (parseInt(valCounter) || 0) : current_counter_stock;

            const totalPhysic = gudangPhysic + counterPhysic;
            const discrepancy = totalPhysic - total_stock;
            input.value = (discrepancy > 0 ? '+' : '') + discrepancy;

            if (discrepancy !== 0) {
                input.classList.add('error');
                if (discrepancy > 0) {
                    badge.textContent = `+${discrepancy} Lebih`;
                    badge.style.cssText = 'display:inline-block;background:#059669;color:#fff;';
                } else {
                    badge.textContent = `${discrepancy} Kurang`;
                    badge.style.cssText = 'display:inline-block;background:#dc2626;color:#fff;';
                }
            } else {
                input.classList.remove('error');
                badge.style.display = 'none';
            }
        }

        function SaveOpname() {
            const medicineId = $('#medicine_id').val();
            const stockPhysic = $('#stock_physic').val();
            const counterStockPhysic = $('#counter_stock_physic').val();
            const batchesId = $('#batch_select').val();

            if (!medicineId) {
                iziToast.warning({ title: 'Peringatan', message: 'Obat tidak valid.', position: 'topRight' });
                return;
            }
            if (stockPhysic === '') {
                iziToast.warning({ title: 'Peringatan', message: 'Isi stok fisik gudang terlebih dahulu!', position: 'topRight' });
                document.getElementById('stock_physic').focus();
                return;
            }

            const btn = document.getElementById('btn_save_opname');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Menyimpan...';

            $.ajax({
                url: "{{ route('supplies.opname') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    medicine_id: medicineId,
                    stock_physic: stockPhysic,
                    counter_stock_physic: counterStockPhysic,
                    batches_id: batchesId,
                },
                success: function(response) {
                    iziToast.success({
                        title: 'Berhasil',
                        message: response.message || 'Stok berhasil disimpan!',
                        position: 'topRight'
                    });
                    closeSheet(); // Automatically close and ready for next scan
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Terjadi kesalahan!';
                    iziToast.error({
                        title: 'Gagal',
                        message: msg,
                        position: 'topRight'
                    });
                },
                complete: function() {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            });
        }
        
        document.getElementById('btn_save_opname').addEventListener('click', SaveOpname);


        /* ── Show results ─────────────────────────────────────────────── */
        function showResult(data) {
            document.getElementById('res_name').textContent = data.name;
            document.getElementById('res_code').textContent = data.code;
            document.getElementById('res_unit').textContent = data.unit;
            document.getElementById('res_price').textContent = formatRupiah(data.raw_price);
            document.getElementById('medicine_id').value = data.id;
            
            // Start loading batches so we get real total stock from the server logic
            loadBatches(data.id);

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
        overlay.addEventListener('click', (e) => {
            if(e.target === overlay) closeSheet();
        });
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
