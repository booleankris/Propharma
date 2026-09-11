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

                    @if(canAccessWarehouseStock())
                    {{-- Target Mode Segmented Switch --}}
                    <div class="form-group mb-3">
                        <label>Lokasi / Mode Opname</label>
                        <div style="display: flex; background: rgba(0,0,0,0.3); border-radius: 12px; padding: 4px; gap: 4px; border: 1px solid rgba(255,255,255,0.1);">
                            <button type="button" id="btn_mode_pelayanan" class="mode-tab active" style="flex:1; padding: 8px 12px; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; background: #2563eb; color: #fff;">
                                🏪 Pelayanan / Toko
                            </button>
                            <button type="button" id="btn_mode_gudang" class="mode-tab" style="flex:1; padding: 8px 12px; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.2s; background: transparent; color: #94a3b8;">
                                📦 Gudang PMI
                            </button>
                        </div>
                    </div>
                    @endif
                    <input type="hidden" id="target_mode" value="pelayanan">

                    {{-- Fisik Stok Input --}}
                    <div class="form-group">
                        <label id="label_stock_physic">Stok Fisik Pelayanan</label>
                        <input type="number" id="current_stock_physic" class="form-control" placeholder="0" onkeyup="countDiscrepancy()">
                    </div>

                    {{-- Expired Date & Batch Name Form --}}
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group">
                            <label>Expired Date (ED)</label>
                            <input type="date" id="expired_date" class="form-control" onchange="checkExpiredDateMatch()">
                        </div>
                        <div class="form-group">
                            <label>No. / Nama Batch</label>
                            <input type="text" id="batch_name" class="form-control" placeholder="Contoh: BTH01">
                        </div>
                    </div>
                    <input type="hidden" id="selected_batch_id" value="">

                    {{-- Etalase Selection & Quick Add (Hidden in Gudang mode) --}}
                    <div class="form-group" id="group_etalase">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="margin-bottom: 0;">Pilih Etalase</label>
                            <button type="button" id="btn_open_add_etalase" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px; padding: 3px 8px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                + Tambah Etalase
                            </button>
                        </div>
                        <select id="etalase_select" class="form-control">
                            <option value="">Memuat etalase…</option>
                        </select>
                    </div>

                    {{-- Batch selector hidden dropdown reference for stock calculation --}}
                    <select id="batch_select" style="display: none;"></select>

                    <div class="form-group" style="text-align: center; margin: 15px 0 5px;">
                        <label style="display: flex; justify-content: center; align-items: center; gap: 8px;">
                            Selisih Stok <span id="discrepancy_badge"></span>
                        </label>
                        <input type="text" readonly id="stock_discrepancy" class="form-control discrepancy" placeholder="—">
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

    {{-- Modal Quick Add Etalase --}}
    <div id="modal_add_etalase" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 200; backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #1e293b; border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; width: 100%; max-width: 400px; padding: 20px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
            <h3 style="color: #fff; font-size: 16px; font-weight: 700; margin: 0 0 12px 0;">+ Tambah Etalase Baru</h3>
            <div class="form-group mb-3">
                <label style="color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase;">Nama Etalase</label>
                <input type="text" id="new_etalase_name" class="form-control" placeholder="Contoh: Etalase 8, Kulkas Baru...">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px;">
                <button type="button" id="btn_close_etalase_modal" class="btn-action secondary" style="flex: 1; padding: 10px;">Batal</button>
                <button type="button" id="btn_submit_add_etalase" class="btn-action" style="flex: 1; padding: 10px;">Simpan</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Batch Sama --}}
    <div id="modal_batch_confirm" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 200; backdrop-filter: blur(4px); align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #1e293b; border: 1px solid rgba(255,255,255,0.15); border-radius: 20px; width: 100%; max-width: 420px; padding: 22px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);">
            <div style="text-align: center; margin-bottom: 12px;">
                <div style="font-size: 32px; margin-bottom: 8px;">ℹ️</div>
                <h3 style="color: #fff; font-size: 16px; font-weight: 700; margin: 0 0 6px 0;">Batch dengan ED Sama Ditemukan</h3>
                <p style="color: #94a3b8; font-size: 13px; margin: 0; line-height: 1.4;">
                    Tanggal ED <strong id="dup_ed_text" style="color: #60a5fa;"></strong> sudah ada di sistem dengan batch <strong id="dup_batch_text" style="color: #f59e0b;"></strong>.
                    <br>Apakah ingin menggabungkan ke batch yang sudah ada?
                </p>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px; margin-top: 20px;">
                <button type="button" id="btn_confirm_merge_batch" class="btn-action" style="padding: 12px;">
                    Ya, Gabung
                </button>
                <button type="button" id="btn_confirm_new_batch" class="btn-action secondary" style="padding: 12px;">
                    Tidak, Buat Baru Saja
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
        let total_stock = 0;
        let cachedBatches = [];
        let matchedBatchOnEd = null;

        /* ── Helpers ──────────────────────────────────────────────────── */
        function formatRupiah(val) {
            return 'Rp ' + parseInt(val || 0).toLocaleString('id-ID');
        }

        /* ── Etalase List & Quick Add ─────────────────────────────────── */
        function loadEtalases(selectedId = null) {
            const select = document.getElementById('etalase_select');
            select.innerHTML = '<option value="">Memuat etalase…</option>';

            fetch(`{{ route('items.select') }}`)
                .then(res => res.json())
                .then(data => {
                    select.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item.name;
                            if (selectedId && selectedId == item.id) {
                                opt.selected = true;
                            }
                            select.appendChild(opt);
                        });
                    } else {
                        select.innerHTML = '<option value="">— Tidak ada etalase —</option>';
                    }
                })
                .catch(() => {
                    select.innerHTML = '<option value="">— Gagal memuat etalase —</option>';
                });
        }

        document.getElementById('btn_open_add_etalase')?.addEventListener('click', () => {
            document.getElementById('new_etalase_name').value = '';
            document.getElementById('modal_add_etalase').style.display = 'flex';
            document.getElementById('new_etalase_name').focus();
        });

        document.getElementById('btn_close_etalase_modal')?.addEventListener('click', () => {
            document.getElementById('modal_add_etalase').style.display = 'none';
        });

        document.getElementById('btn_submit_add_etalase')?.addEventListener('click', () => {
            const name = $('#new_etalase_name').val().trim();
            if (!name) {
                iziToast.warning({ title: 'Peringatan', message: 'Nama etalase tidak boleh kosong.', position: 'topRight' });
                return;
            }

            const btn = document.getElementById('btn_submit_add_etalase');
            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            $.ajax({
                url: "{{ route('items.store') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    name: name
                },
                success: function(res) {
                    iziToast.success({ title: 'Berhasil', message: 'Etalase baru ditambahkan!', position: 'topRight' });
                    document.getElementById('modal_add_etalase').style.display = 'none';
                    loadEtalases(res.data?.id);
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Gagal menambahkan etalase!';
                    iziToast.error({ title: 'Gagal', message: msg, position: 'topRight' });
                },
                complete: function() {
                    btn.disabled = false;
                    btn.textContent = 'Simpan';
                }
            });
        });

        /* ── Target Mode Toggle ───────────────────────────────────────── */
        function setTargetMode(mode) {
            $('#target_mode').val(mode);
            const btnPel = document.getElementById('btn_mode_pelayanan');
            const btnGud = document.getElementById('btn_mode_gudang');
            const grpEtalase = document.getElementById('group_etalase');
            const lblPhysic = document.getElementById('label_stock_physic');

            if (mode === 'gudang') {
                if (btnGud) {
                    btnGud.style.background = '#2563eb';
                    btnGud.style.color = '#fff';
                }
                if (btnPel) {
                    btnPel.style.background = 'transparent';
                    btnPel.style.color = '#94a3b8';
                }
                if (grpEtalase) grpEtalase.style.display = 'none';
                if (lblPhysic) lblPhysic.textContent = 'Stok Fisik Gudang PMI';
            } else {
                if (btnPel) {
                    btnPel.style.background = '#2563eb';
                    btnPel.style.color = '#fff';
                }
                if (btnGud) {
                    btnGud.style.background = 'transparent';
                    btnGud.style.color = '#94a3b8';
                }
                if (grpEtalase) grpEtalase.style.display = 'block';
                if (lblPhysic) lblPhysic.textContent = 'Stok Fisik Pelayanan';
            }

            countDiscrepancy();
        }

        document.getElementById('btn_mode_pelayanan')?.addEventListener('click', () => setTargetMode('pelayanan'));
        document.getElementById('btn_mode_gudang')?.addEventListener('click', () => setTargetMode('gudang'));

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
            $('#current_stock_physic, #stock_discrepancy, #expired_date, #batch_name, #selected_batch_id').val('');
            $('#batch_select').prop('selectedIndex', 0);
            document.getElementById('discrepancy_badge').style.display = 'none';
            document.getElementById('stock_discrepancy').classList.remove('error');
            cachedBatches = [];
            matchedBatchOnEd = null;
        }

        /* ── Opname Logic ─────────────────────────────────────────────── */
        function loadBatches(medicine_id) {
            const select = document.getElementById('batch_select');
            select.innerHTML = '<option value="">Memuat batch…</option>';
            const canSeeWarehouse = {{ canAccessWarehouseStock() ? 'true' : 'false' }};

            fetch(`{{ route('supplies.batches') }}?medicine_id=${medicine_id}`)
                .then(res => res.json())
                .then(batches => {
                    cachedBatches = batches || [];
                    select.innerHTML = '<option value="">— Otomatis (FEFO) —</option>';

                    let totalStorageStock = 0;
                    let totalCounterStock = 0;

                    batches.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        const gStock = parseInt(b.stock || 0);
                        const cStock = parseInt(b.counter_stock || 0);
                        opt.dataset.stock = gStock;
                        opt.dataset.counterStock = cStock;
                        opt.dataset.expiredDate = b.expired_date;
                        opt.dataset.name = b.name;
                        select.appendChild(opt);
                        
                        totalStorageStock += gStock;
                        totalCounterStock += cStock;
                    });

                    current_storage_stock = totalStorageStock;
                    current_counter_stock = totalCounterStock;
                    total_stock = totalStorageStock + totalCounterStock;

                    // Set default display
                    if (document.getElementById('res_stock_gudang')) {
                        document.getElementById('res_stock_gudang').textContent = totalStorageStock;
                    }
                    if (document.getElementById('res_stock_counter')) {
                        document.getElementById('res_stock_counter').textContent = totalCounterStock;
                    }

                    countDiscrepancy();
                })
                .catch(() => {
                    cachedBatches = [];
                    select.innerHTML = '<option value="">— Gagal memuat batch —</option>';
                });
        }

        /* ── ED Match & Batch Modal Confirmation ──────────────────────── */
        function checkExpiredDateMatch() {
            const enteredEd = $('#expired_date').val();
            if (!enteredEd || !cachedBatches || cachedBatches.length === 0) return;

            // Normalize entered date to YYYY-MM-DD
            const formattedEntered = enteredEd.replace(/\//g, '-');

            const matched = cachedBatches.find(b => {
                if (!b.expired_date) return false;
                const bEd = b.expired_date.substring(0, 10);
                return bEd === formattedEntered;
            });

            if (matched) {
                matchedBatchOnEd = matched;
                $('#dup_ed_text').text(matched.expired_date);
                $('#dup_batch_text').text(matched.name);
                document.getElementById('modal_batch_confirm').style.display = 'flex';
            }
        }

        document.getElementById('btn_confirm_merge_batch')?.addEventListener('click', () => {
            if (matchedBatchOnEd) {
                $('#batch_name').val(matchedBatchOnEd.name);
                $('#selected_batch_id').val(matchedBatchOnEd.id);
            }
            document.getElementById('modal_batch_confirm').style.display = 'none';
            document.getElementById('batch_name').focus();
        });

        document.getElementById('btn_confirm_new_batch')?.addEventListener('click', () => {
            $('#selected_batch_id').val('');
            $('#batch_name').val('');
            document.getElementById('modal_batch_confirm').style.display = 'none';
            document.getElementById('batch_name').focus();
        });

        function countDiscrepancy() {
            const valPhysic = $('#current_stock_physic').val();
            const input = document.getElementById('stock_discrepancy');
            const badge = document.getElementById('discrepancy_badge');
            const mode = $('#target_mode').val();

            if (valPhysic === '') {
                input.value = '';
                input.classList.remove('error');
                badge.style.display = 'none';
                return;
            }

            const physicNum = parseInt(valPhysic) || 0;
            const systemTargetStock = (mode === 'gudang') ? current_storage_stock : current_counter_stock;
            const discrepancy = physicNum - systemTargetStock;
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
            const valPhysic = $('#current_stock_physic').val();
            const targetMode = $('#target_mode').val() || 'pelayanan';
            const etalasesId = $('#etalase_select').val();
            const customBatchName = $('#batch_name').val();
            const customExpiredDate = $('#expired_date').val();
            const batchesId = $('#selected_batch_id').val();

            if (!medicineId) {
                iziToast.warning({ title: 'Peringatan', message: 'Obat tidak valid.', position: 'topRight' });
                return;
            }
            if (valPhysic === '') {
                iziToast.warning({ title: 'Peringatan', message: 'Isi stok fisik terlebih dahulu!', position: 'topRight' });
                document.getElementById('current_stock_physic').focus();
                return;
            }

            const btn = document.getElementById('btn_save_opname');
            const originalContent = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Menyimpan...';

            const payload = {
                _token: "{{ csrf_token() }}",
                medicine_id: medicineId,
                target_mode: targetMode,
                batches_id: batchesId || null,
                custom_batch_name: customBatchName || null,
                custom_expired_date: customExpiredDate || null,
                etalases_id: (targetMode === 'pelayanan') ? (etalasesId || null) : null,
            };

            if (targetMode === 'gudang') {
                payload.stock_physic = valPhysic;
            } else {
                payload.counter_stock_physic = valPhysic;
            }

            $.ajax({
                url: "{{ route('supplies.opname') }}",
                type: 'POST',
                data: payload,
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
            loadEtalases();

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
