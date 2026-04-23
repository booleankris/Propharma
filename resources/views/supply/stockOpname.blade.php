@extends('layouts.app')

@section('title', 'Stock Opname')
@section('style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            overflow-x: hidden;
            max-width: 100%;
        }

        .section,
        .section-body {
            max-width: 100%;
            overflow-x: hidden;
        }

        /* ── Layout grid ── */
        .opname-grid {
            display: grid;
            grid-template-columns: minmax(0, 360px) minmax(0, 1fr);
            gap: 16px;
            align-items: start;
            width: 100%;
        }

        @media (max-width: 768px) {
            .opname-grid {
                grid-template-columns: minmax(0, 1fr) !important;
            }
        }

        /* ── Card panels ── */
        .card-panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 18px;
            min-width: 0;
            overflow: hidden;
            width: 100%;
        }

        .section-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .07em;
            color: #94a3b8;
            margin-bottom: 10px;
        }

        /* ── Form fields ── */
        .field-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 5px;
        }

        .field-input {
            width: 100%;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            background: #fff;
            color: #1e293b;
            transition: border .15s, box-shadow .15s;
            outline: none;
        }

        .field-input:focus,
        .nav-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12) !important;
        }

        .field-input[readonly] {
            background: #f8fafc;
            color: #94a3b8;
            cursor: default;
        }

        .field-input.border-red-500 {
            border-color: #ef4444 !important;
            color: #dc2626 !important;
        }

        select.field-input {
            height: 38px;
        }

        /* ── Opname form grid ── */
        .opname-form-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        @media (max-width: 600px) {
            .opname-form-grid {
                grid-template-columns: 1fr;
            }
        }

        /* ── DataTable overrides ── */
        #medicines_data,
        #orderItemsTable {
            width: 100% !important;
            max-width: 100%;
            table-layout: fixed;
        }

        #medicines_data td,
        #medicines_data th,
        #orderItemsTable td,
        #orderItemsTable th {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dataTables_wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table.dataTable thead th,
        table.dataTable thead td {
            padding: 9px 12px !important;
            background: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: .04em !important;
            color: #64748b !important;
            white-space: nowrap;
        }

        table.dataTable tbody td {
            padding: 9px 12px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        table.dataTable tbody tr:hover {
            background: #f8fafc !important;
        }

        /* ── DataTable controls ── */
        .dataTables_wrapper .top,
        .dataTables_wrapper .bottom {
            display: flex !important;
            flex-direction: row !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 6px !important;
            padding: 0 0 8px 0 !important;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            float: none !important;
            text-align: left !important;
        }

        .dataTables_length label,
        .dataTables_filter label {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            font-size: 13px !important;
            white-space: nowrap !important;
            margin: 0 !important;
        }

        .dataTables_filter input {
            display: inline-block !important;
            width: 130px !important;
            max-width: 130px !important;
            padding: 5px 10px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 13px !important;
            outline: none !important;
            margin: 0 !important;
        }

        .dataTables_filter input:focus {
            border-color: #3b82f6 !important;
        }

        .dataTables_length select {
            width: auto !important;
            padding: 4px 6px !important;
            border-radius: 8px !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 13px !important;
            margin: 0 !important;
        }

        .dataTables_info {
            font-size: 12px !important;
            color: #94a3b8 !important;
            white-space: nowrap !important;
        }

        /* ── Pagination ── */
        .dataTables_wrapper .dataTables_paginate {
            display: flex !important;
            flex-wrap: wrap !important;
            align-items: center !important;
            gap: 3px !important;
            justify-content: flex-end !important;
            padding: 6px 0 0 !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 4px 9px !important;
            border-radius: 6px !important;
            margin: 0 !important;
            font-size: 12px !important;
            background: #f1f5f9 !important;
            border: none !important;
            cursor: pointer !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb !important;
            color: #fff !important;
            border: none !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: .4 !important;
            background: transparent !important;
            cursor: default !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:not(.current):not(.disabled):hover {
            background: #e2e8f0 !important;
            color: #1e293b !important;
            border: none !important;
        }

        .paginate_button.previous {
            background: #fee2e2 !important;
            color: #b91c1c !important;
        }

        .paginate_button.next {
            background: #dcfce7 !important;
            color: #166534 !important;
        }

        @media (max-width: 768px) {
            .dataTables_paginate .paginate_button {
                padding: 4px 7px !important;
                font-size: 11px !important;
            }

            .dataTables_filter input {
                width: 110px !important;
                max-width: 110px !important;
            }
        }

        /* ── Log panel ── */
        .log-panel {
            max-height: 36vh;
            overflow-x: auto;
            overflow-y: auto;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .log-panel {
                max-height: 240px;
            }
        }

        .log-panel::-webkit-scrollbar {
            width: 4px;
            height: 4px;
        }

        .log-panel::-webkit-scrollbar-track {
            background: transparent;
        }

        .log-panel::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 99px;
        }

        #orderItemsTable thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8fafc !important;
        }

        /* ── Stat cards ── */
        .stat-row {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-top: 12px;
        }

        @media (max-width: 480px) {
            .stat-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            padding: 10px 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .stat-card .val {
            font-size: 20px;
            font-weight: 800;
            color: #2563eb;
            line-height: 1;
        }

        .stat-card .lbl {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
            white-space: nowrap;
        }

        /* ── Medicine table rows ── */
        #medicines_data tbody tr {
            cursor: pointer;
            transition: background .1s;
        }

        #medicines_data tbody tr:hover {
            background: #eff6ff !important;
        }

        #medicines_data tbody tr.active {
            background: #dbeafe !important;
        }

        #orderItemsTable tbody tr.table-primary {
            background: #dbeafe !important;
        }

        /* ── Discrepancy badge ── */
        #discrepancy_badge {
            display: none;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 99px;
            margin-top: 6px;
        }

        /* ── Buttons ── */
        .btn-primary,
        .btn-danger,
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 14px;
            height: 36px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .btn-export {
            background: #dcfce7;
            color: #166534;
        }

        .btn-export:hover {
            background: #bbf7d0;
        }

        /* ── Scanner FAB — mobile only, fixed bottom-right ── */
        .scanner-fab {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 999;
            background: #2563eb;
            color: #fff;
            border-radius: 99px;
            padding: 13px 20px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .scanner-fab svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        @media (max-width: 768px) {
            .scanner-fab {
                display: flex;
            }
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 pb-8">
        <div class="section-body">

            {{-- Page header --}}
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                                           M9 5a2 2 0 002 2h2a2 2 0 002-2
                                           M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Stock Opname</h1>
                    <p class="text-[12px] text-gray-400">Rekonsiliasi stok fisik vs sistem</p>
                </div>
            </div>

            <div class="opname-grid">

                {{-- LEFT: Medicine selector --}}
                <div class="card-panel">
                    <span class="section-label">Pilih Obat</span>

                    <div class="grid grid-cols-1 gap-3 mb-4">
                        <div>
                            <label class="field-label">Rentang Tanggal</label>
                            <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..." class="field-input"
                                autocomplete="off">
                        </div>
                        <div>
                            <label class="field-label">Obat Dipilih</label>
                            <input type="text" readonly id="medicine_name" placeholder="Klik 2x pada tabel..."
                                class="field-input" autocomplete="off">
                        </div>
                    </div>

                    <table id="medicines_data" class="w-full text-sm text-left text-gray-600">
                        <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">Nama</th>
                                <th class="px-3 py-2">Satuan</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <p class="text-[11px] text-gray-400 mt-2">* Double-click baris untuk memilih obat</p>
                </div>

                {{-- RIGHT: Log + Opname form --}}
                <div class="flex flex-col gap-4">

                    {{-- Hidden fields --}}
                    <input type="hidden" id="medicine_id">
                    <input type="hidden" id="medicine_stock">
                    <input type="hidden" id="batches_id">
                    <input type="hidden" id="expired_date">

                    {{-- Stock log panel --}}
                    <div class="card-panel">
                        <span class="section-label">Riwayat Stok</span>
                        <div class="log-panel">
                            <table id="orderItemsTable" class="w-full text-sm text-left text-gray-600">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2">#</th>
                                        <th class="px-3 py-2">Tanggal</th>
                                        <th class="px-3 py-2">Kode</th>
                                        <th class="px-3 py-2">Tipe</th>
                                        <th class="px-3 py-2">Saldo Awal</th>
                                        <th class="px-3 py-2">Qty</th>
                                        <th class="px-3 py-2">Jumlah</th>
                                        <th class="px-3 py-2">Saldo Kini</th>
                                        <th class="px-3 py-2">Ket.</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="stat-row">
                            <div class="stat-card">
                                <span class="val" id="qty_awal">—</span>
                                <span class="lbl">QTY Awal</span>
                            </div>
                            <div class="stat-card">
                                <span class="val" id="qty_beli">—</span>
                                <span class="lbl">QTY Beli</span>
                            </div>
                            <div class="stat-card">
                                <span class="val" id="qty_jual">—</span>
                                <span class="lbl">QTY Jual</span>
                            </div>
                            <div class="stat-card">
                                <span class="val" id="qty_akhir">—</span>
                                <span class="lbl">QTY Akhir</span>
                            </div>
                        </div>
                    </div>

                    {{-- Opname form --}}
                    <div class="card-panel">
                        <span class="section-label">Input Opname</span>

                        <div class="opname-form-grid">
                            <div>
                                <label class="field-label">Stok Fisik (Gudang)</label>
                                <input type="number" id="stock_physic" data-nav-enter="counter_stock_physic"
                                    onkeyup="countDiscrepancy()" placeholder="Stok gudang fisik..."
                                    class="nav-input field-input" autocomplete="off">
                            </div>
                            <div>
                                <label class="field-label">Stok Fisik (Counter)</label>
                                <input type="number" id="counter_stock_physic" data-nav-enter="batch_select"
                                    placeholder="Stok counter fisik..." class="nav-input field-input" autocomplete="off">
                            </div>
                            <div>
                                <label class="field-label">Selisih Stok</label>
                                <input type="text" readonly id="stock_discrepancy" placeholder="—"
                                    class="field-input">
                                <span id="discrepancy_badge"></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="field-label">
                                Batch
                                <span
                                    style="font-size:11px;font-weight:400;text-transform:none;letter-spacing:0;color:#94a3b8;margin-left:4px;">
                                    (opsional — default: expired terdekat)
                                </span>
                            </label>
                            <select id="batch_select" data-nav-enter="submit" class="nav-input field-input">
                                <option value="">— Otomatis (FEFO) —</option>
                            </select>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button id="save_opname" class="btn-primary">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z" />
                                    <polyline points="17 21 17 13 7 13 7 21" />
                                    <polyline points="7 3 7 8 15 8" />
                                </svg>
                                Simpan
                            </button>
                            <button id="back" class="btn-danger">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Kembali
                            </button>
                            <a href="{{ route('supplies.printstockopname') }}" target="_blank" class="btn-export">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Export Excel
                            </a>
                        </div>
                    </div>

                </div>
            </div>{{-- /.opname-grid --}}

        </div>
    </section>

    {{-- Scanner FAB — rendered outside the grid so position:fixed works correctly --}}
    <a href="{{ route('supplies.scanner') }}" class="scanner-fab" title="Scan Barcode">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M5 9V7a2 2 0 012-2h2M15 5h2a2 2 0 012 2v2M19 15v2a2 2 0 01-2 2h-2M9 19H7a2 2 0 01-2-2v-2" />
            <rect x="9" y="9" width="6" height="6" rx="1" />
        </svg>
        <span>Scan</span>
    </a>
@endsection
@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        /* ── State ─────────────────────────────────────────────────────── */
        let total_stock = 0; // current system stock (from selected batch / log)
        let orderItemsTable, medicineData;
        let startDate = '',
            endDate = '',
            searchMedicine = '';

        /* ── Enter-key navigation ──────────────────────────────────────── */
        // Map: elementId → nextElementId  (or 'submit')
        const NAV_MAP = {
            'stock_physic': 'counter_stock_physic',
            'counter_stock_physic': 'batch_select',
            'batch_select': 'submit',
        };

        function initEnterNavigation() {
            Object.entries(NAV_MAP).forEach(([id, nextId]) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    if (nextId === 'submit') {
                        SaveOpname();
                    } else {
                        document.getElementById(nextId)?.focus();
                    }
                });
            });
        }

        /* ── Load batches into <select> ────────────────────────────────── */
        function loadBatches(medicine_id) {
            const select = document.getElementById('batch_select');
            select.innerHTML = '<option value="">Memuat batch…</option>';

            fetch(`{{ route('supplies.batches') }}?medicine_id=${medicine_id}`)
                .then(res => res.json())
                .then(batches => {
                    select.innerHTML = '<option value="">— Otomatis (FEFO) —</option>';

                    let totalBatchStock = 0; // ← accumulate here

                    batches.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        opt.textContent = `${b.name} — Exp: ${b.expired_date} (Stok: ${b.stock})`;
                        opt.dataset.stock = b.stock;
                        select.appendChild(opt);
                        totalBatchStock += parseInt(b.stock || 0); // ← sum all batches
                    });

                    // ← Update qty_akhir with real total storage stock
                    $('#qty_akhir').text(totalBatchStock);
                    $('#medicine_stock').val(totalBatchStock);
                    total_stock = totalBatchStock;

                    updateTotalStockFromSelect();
                })
                .catch(() => {
                    select.innerHTML = '<option value="">— Gagal memuat batch —</option>';
                });
        }

        /* ── Sync total_stock when batch selection changes ─────────────── */
        function updateTotalStockFromSelect() {
            const select = document.getElementById('batch_select');
            const selectedOpt = select.options[select.selectedIndex];

            if (selectedOpt && selectedOpt.value !== '') {
                // User picked a specific batch → use that batch's stock
                total_stock = parseInt(selectedOpt.dataset.stock) || 0;
            } else {
                // "Otomatis (FEFO)" → use first real option's stock
                const firstBatch = select.options[1]; // index 0 is the placeholder
                total_stock = firstBatch ? (parseInt(firstBatch.dataset.stock) || 0) : 0;
            }

            $('#medicine_stock').val(total_stock);
            // Recalculate discrepancy if physic is already filled
            if ($('#stock_physic').val() !== '') countDiscrepancy();
        }

        document.getElementById('batch_select')
            ?.addEventListener('change', updateTotalStockFromSelect);

        /* ── Discrepancy indicator ─────────────────────────────────────── */
        function countDiscrepancy() {
            const val = $('#stock_physic').val();
            const input = document.getElementById('stock_discrepancy');
            const badge = document.getElementById('discrepancy_badge');

            if (val === '') {
                input.value = '';
                input.classList.remove('border-red-500', 'text-red-600');
                badge.style.display = 'none';
                return;
            }

            const stockPhysic = parseInt(val) || 0;
            const discrepancy = stockPhysic - total_stock;
            input.value = discrepancy;

            if (discrepancy !== 0) {
                input.classList.add('border-red-500', 'text-red-600');
                if (discrepancy > 0) {
                    badge.textContent = `+${discrepancy} Lebih`;
                    badge.style.cssText =
                        'display:inline-block;background:#dcfce7;color:#16a34a;font-size:12px;font-weight:700;padding:3px 10px;border-radius:99px;margin-top:6px;';
                } else {
                    badge.textContent = `${discrepancy} Kurang`;
                    badge.style.cssText =
                        'display:inline-block;background:#fee2e2;color:#dc2626;font-size:12px;font-weight:700;padding:3px 10px;border-radius:99px;margin-top:6px;';
                }
            } else {
                input.classList.remove('border-red-500', 'text-red-600');
                badge.style.display = 'none';
            }
        }

        /* ── Save opname ───────────────────────────────────────────────── */
        function SaveOpname() {
            const medicineId = $('#medicine_id').val();
            const stockPhysic = $('#stock_physic').val();
            const counterStockPhysic = $('#counter_stock_physic').val();
            const batchesId = $('#batch_select').val(); // '' = let backend use FEFO

            if (!medicineId) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Pilih obat terlebih dahulu!',
                    position: 'topRight'
                });
                return;
            }
            if (stockPhysic === '') {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Isi stok fisik gudang terlebih dahulu!',
                    position: 'topRight'
                });
                document.getElementById('stock_physic').focus();
                return;
            }

            const btn = document.getElementById('save_opname');
            btn.disabled = true;
            btn.innerHTML =
                `<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="40" stroke-dashoffset="15"/></svg> Menyimpan…`;

            $.ajax({
                url: "{{ route('supplies.opname') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    medicine_id: medicineId,
                    stock_physic: stockPhysic,
                    counter_stock_physic: counterStockPhysic,
                    batches_id: batchesId, // empty string → backend picks FEFO
                },
                success: function(response) {
                    iziToast.success({
                        title: 'Berhasil',
                        message: response.message || 'Stok berhasil disimpan!',
                        position: 'topRight'
                    });

                    // Update total_stock from server response
                    total_stock = response.qty_after ?? total_stock;
                    $('#medicine_stock').val(total_stock);

                    // Reset input fields (keep medicine & batch list intact)
                    $('#stock_physic, #counter_stock_physic, #stock_discrepancy').val('');
                    $('#batch_select').prop('selectedIndex', 0);
                    document.getElementById('discrepancy_badge').style.display = 'none';
                    document.getElementById('stock_discrepancy').classList.remove('border-red-500',
                        'text-red-600');

                    // Reload stock log
                    orderItemsTable.ajax.reload(null, false);

                    // Reload batches to reflect updated stock numbers
                    loadBatches(medicineId);

                    document.getElementById('stock_physic').focus();
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
                    btn.innerHTML =
                        `<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Simpan`;
                }
            });
        }

        /* ── DOM ready ─────────────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', function() {
            initEnterNavigation();

            // Date range picker
            flatpickr("#dateRange", {
                mode: "range",
                dateFormat: "Y-m-d",
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                        endDate = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                    } else {
                        startDate = endDate = '';
                    }
                    orderItemsTable.ajax.reload();
                }
            });

            // Stock log DataTable
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                deferLoading: 0,
                ajax: {
                    url: "{{ route('supplies.medicineStockLog') }}",
                    data: d => {
                        d.searchMedicine = searchMedicine;
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'transaction_code'
                    },
                    {
                        data: 'type'
                    },
                    {
                        data: 'qty_before'
                    },
                    {
                        data: 'stock'
                    },
                    {
                        data: 'qty_after'
                    },
                    {
                        data: 'supply'
                    },
                    {
                        data: 'status'
                    },
                ],
                paging: true,
                searching: false,
                info: false,
                language: {
                    emptyTable: "Silakan pilih obat terlebih dahulu"
                },
            });

            // Medicine DataTable
            medicineData = $('#medicines_data').DataTable({
                responsive: true,
                serverSide: true,
                ajax: "{{ route('supplies.medicines') }}",
                dom: '<"top"lf>rt<"bottom"ip>', // ← this controls the layout order
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'unit'
                    },
                ],
                pageLength: 10,
                initComplete: function() {
                    $('#medicines_data_filter input').focus();
                }
            });

            // Double-click a medicine row to select it
            $('#medicines_data tbody').on('dblclick', 'tr', function() {
                const medicine = medicineData.row(this).data();
                if (!medicine) return;

                $('#medicines_data tbody tr').removeClass('active');
                $(this).addClass('active');

                $('#medicine_name').val(medicine.name);
                $('#medicine_id').val(medicine.id);
                searchMedicine = medicine.name;

                // Load batches (FEFO first)
                loadBatches(medicine.id);

                // Load stock log for this medicine
                orderItemsTable.ajax.reload(function() {
                    // Update stat cards from the last row (most recent state)
                    orderItemsTable.rows().every(function() {
                        const row = this.data();
                        if (row.name === searchMedicine) {
                            total_stock = row.qty_after_number || 0;
                            $('#medicine_stock').val(total_stock);
                            $('#qty_awal').text(row.stock_start?.qty_before ?? 0);
                            $('#qty_beli').text(row.total_orders ?? 0);
                            $('#qty_jual').text(row.total_sales ?? 0);
                            $('#qty_akhir').text(total_stock);
                            return false;
                        }
                    });

                    document.getElementById('stock_physic').focus();
                });
            });

            $('#save_opname').on('click', SaveOpname);
            $('#back').on('click', () => window.location.href = "{{ route('home') }}");
        });
    </script>
@endsection
