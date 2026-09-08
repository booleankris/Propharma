@extends('layouts.app')

@section('title', 'Retur Pembelian')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <style>
        .dropdown-table {
            width: 100%;
            position: absolute;
            z-index: 9999;
            margin-top: 4px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            display: none;
        }
        .dropdown-table table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .dropdown-table thead th {
            position: sticky; top: 0; background: #f9fafb; color: #374151;
            font-weight: 600; padding: 9px 12px; border-bottom: 1px solid #e5e7eb;
            text-transform: uppercase; font-size: 11px; letter-spacing: .04em;
        }
        .dropdown-table tbody tr { cursor: pointer; transition: background-color .12s; }
        .dropdown-table tbody tr:hover,
        .dropdown-table tbody tr.active { background-color: #dbeafe; }
        .dropdown-table td {
            padding: 9px 12px; border-bottom: 1px solid #f1f5f9;
            color: #111827; vertical-align: middle;
        }
        .dropdown-table tbody tr:last-child td { border-bottom: none; }
        .dropdown-table td:first-child { width: 36px; color: #9ca3af; font-size: 12px; }
        .dropdown-table td:nth-child(4) { font-weight: 600; color: #16a34a; }
        #tableScroll { max-height: 250px; overflow-y: auto; }

        .dataTables_wrapper .top {
            display: flex !important; justify-content: space-between !important;
            align-items: center !important; margin-bottom: 12px !important;
        }
        .dataTables_filter input {
            width: 220px !important; font-size: 12px !important; padding: 6px 10px !important;
            border-radius: 6px !important; border: 1px solid #d1d5db !important; outline: none !important;
        }
        .dataTables_length select {
            padding: 6px 28px 6px 12px !important; border-radius: 6px !important; border: 1px solid #d1d5db !important;
        }
        #medicineTable thead th {
            background-color: #f8fafc !important; font-weight: 600 !important;
            font-size: 11px !important; text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important; padding: 10px !important;
        }
        #medicineTable tbody td { padding: 12px 10px !important; font-size: 13px !important; vertical-align: middle !important; border-bottom: 1px solid #f1f5f9 !important; }
        #medicineTable tbody tr { cursor: pointer; transition: background-color 0.15s; }
        #medicineTable tbody tr:hover { background-color: #f8fafc !important; }
        #medicineTable tbody tr.active { background-color: #eff6ff !important; border-left: 3px solid #3b82f6 !important; }
        #medicineTable tbody tr.keyboard-focus { background-color: #eff6ff !important; outline: 2px solid #93c5fd; outline-offset: -2px; }
        .dataTables_paginate .paginate_button { padding: 5px 10px !important; border-radius: 6px !important; }
        .text-end { text-align: right !important; }

        /* Visual focus ring for retur-input fields */
        .retur-input:focus { border-color: #3b82f6 !important; box-shadow: 0 0 0 3px rgba(59,130,246,.15) !important; }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                {{-- ─── LEFT: Form Panel ──────────────────────────────────────────── --}}
                <div class="bg-white border border-gray-100 rounded-xl p-6">

                    <h1 class="text-[16px] font-semibold text-gray-800 mb-4">Retur Pembelian</h1>

                    {{-- Info Retur --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Informasi Retur</p>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Tanggal retur</label>
                            <input type="text" id="returdate" value="{{ $now }}" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Nomor retur</label>
                            <input type="text" id="returnumber" value="{{ $retur_code }}" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    {{-- Search --}}
                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Cari pembelian</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="6.5" cy="6.5" r="4.5" />
                                <line x1="10.5" y1="10.5" x2="14" y2="14" />
                            </svg>
                            <input type="text" id="searchInput" placeholder="Cari kode pembelian..."
                                oninput="searchSalesData(this.value)" autocomplete="off"
                                class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">

                            <div id="searchDropdown" class="dropdown-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th><th>Kode & Faktur</th><th>Nama Supplier</th><th>Total</th>
                                        </tr>
                                    </thead>
                                </table>
                                <div id="tableScroll" onscroll="handleScroll()">
                                    <table><tbody id="searchResults"></tbody></table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Nomor faktur</label>
                        <input id="invoice_number" type="text" readonly placeholder="—" tabindex="-1"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                    </div>

                    {{-- Hidden fields --}}
                    <input id="medicine_id"    type="hidden">
                    <input id="cart_id"        type="hidden">
                    <input id="transaction_id" type="hidden">
                    <input id="old_qty"        type="hidden">
                    <input id="content"        type="hidden">
                    <input id="retur_type"     type="hidden" value="packaging">

                    {{-- Detail Obat --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Detail Obat</p>

                    <div class="grid grid-cols-3 gap-2.5 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Kode obat</label>
                            <input id="medicine_code" type="text" readonly placeholder="Kode Obat" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Kemasan</label>
                            <input id="packaging_display" type="text" readonly placeholder="—" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-700 font-medium px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Satuan Eceran</label>
                            <input id="unit" type="text" readonly placeholder="—" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-700 font-medium px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Nama obat</label>
                        <input id="medicine_name" type="text" readonly placeholder="—" tabindex="-1"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[12px] font-medium text-gray-500">Harga satuan</label>
                                <span id="price_type_label" class="text-[10px] font-semibold text-blue-600 uppercase"></span>
                            </div>
                            <input id="item_price" type="text" readonly placeholder="Rp 0" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Isi per kemasan</label>
                            <input id="content_display" type="text" readonly placeholder="—" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    {{-- Rincian Retur --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-2">Rincian Retur</p>

                    {{-- Opsi Tipe Retur: Kemasan vs Eceran --}}
                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1.5">Pilihan Satuan Retur</label>
                        <div class="grid grid-cols-2 gap-2 p-1 bg-gray-100 rounded-lg border border-gray-200" id="returTypeContainer">
                            <button type="button" id="btnTypePackaging" onclick="setReturType('packaging')"
                                class="flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-[12px] font-semibold transition-all duration-150 bg-white text-blue-700 shadow-sm border border-blue-200">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <span id="labelPackaging">Per Kemasan</span>
                            </button>
                            <button type="button" id="btnTypeUnit" onclick="setReturType('unit')"
                                class="flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-[12px] font-medium transition-all duration-150 text-gray-600 hover:text-gray-900 border border-transparent">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                <span id="labelUnit">Eceran</span>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Qty beli</label>
                            <input id="qty_in" type="text" placeholder="0" tabindex="-1" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-700 font-medium px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[12px] font-medium text-gray-500">Qty retur</label>
                                <span id="qty_unit_badge" class="text-[10px] font-semibold text-blue-600 uppercase"></span>
                            </div>
                            <div class="relative">
                                <input id="qty" type="number" step="any" placeholder="0"
                                    oninput="calculateReturTotal()"
                                    class="retur-input w-full rounded-lg border border-gray-200 bg-white pl-3 pr-14 py-2 text-[13px] font-medium focus:outline-none">
                                <span id="qty_unit_suffix" class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-semibold text-gray-400 pointer-events-none"></span>
                            </div>
                            <p id="qty_hint" class="text-[11px] text-gray-400 mt-1 truncate"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Total faktur</label>
                            <input id="price" type="text" readonly placeholder="Rp 0" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Jumlah retur</label>
                            <input id="total_retur" type="text" readonly placeholder="Rp 0" tabindex="-1" data-raw="0"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-700 font-semibold px-3 py-2 text-[13px] focus:outline-none">
                            <p id="calc_hint" class="hidden text-[11px] text-blue-600 font-medium mt-1"></p>
                        </div>
                    </div>

                    {{-- Batch select --}}
                    <div class="mb-5">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Batch &amp; Exp. Date</label>
                        <div id="batch_loading"
                            class="hidden w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-[13px] text-gray-400 animate-pulse">
                            Memuat batch...
                        </div>
                        <select id="batch_select"
                            class="retur-input w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px]
                                   focus:outline-none disabled:bg-gray-50 disabled:text-gray-400 disabled:cursor-not-allowed"
                            disabled>
                            <option value="">— Pilih obat terlebih dahulu —</option>
                        </select>
                        <p id="batch_empty_note" class="hidden mt-1 text-[11px] text-red-400">
                            Tidak ada batch dengan stok tersedia untuk obat ini.
                        </p>
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex gap-2">
                        <button id="btnSimpan" type="button" onclick="submitRetur()"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#2196F3] hover:bg-[#1976D2] text-white text-[13px] font-medium transition-colors duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="2 9 6 13 14 4" />
                            </svg>
                            <span id="btnSimpanLabel">Simpan</span>
                        </button>
                        <button type="button" id="back"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#e95050] hover:bg-[#d43e3e] text-white text-[13px] font-medium transition-colors duration-150">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round">
                                <line x1="4" y1="4" x2="12" y2="12" />
                                <line x1="12" y1="4" x2="4" y2="12" />
                            </svg>
                            Batal
                        </button>
                    </div>
                </div>

                {{-- ─── RIGHT: Item Table ──────────────────────────────────────────── --}}
                <div class="bg-white border border-gray-100 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest">Item pembelian</p>
                        <span id="itemCount"
                            class="text-[11px] font-medium bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full">0 item</span>
                    </div>
                    <table id="medicineTable" class="w-full text-[13px] border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left text-[11px] font-medium text-gray-400 pb-2 pr-3">#</th>
                                <th class="text-left text-[11px] font-medium text-gray-400 pb-2 pr-3">Obat</th>
                                <th class="text-right text-[11px] font-medium text-gray-400 pb-2 pr-3">Qty</th>
                                <th class="text-right text-[11px] font-medium text-gray-400 pb-2">Total</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <script>
        // ─── State ────────────────────────────────────────────────────────────────────
        let page                    = 1;
        let keyword                 = '';
        let loading                 = false;
        let hasMore                 = true;
        let activeIndex             = -1;       // search dropdown row index
        let tableKeyIndex           = -1;       // medicine table keyboard row index
        let selectedTransactionCode = null;
        let medicineTable           = null;
        let batchesReady            = false;    // true once batch select is populated
        let currentMedicine         = null;     // currently selected medicine row data

        axios.defaults.headers.common['X-CSRF-TOKEN'] = '{{ csrf_token() }}';

        // ═════════════════════════════════════════════════════════════════════════════
        // SEARCH DROPDOWN
        // ═════════════════════════════════════════════════════════════════════════════

        function searchSalesData(value) {
            keyword     = value.trim();
            page        = 1;
            hasMore     = true;
            activeIndex = -1;
            if (keyword.length < 1) { hideDropdown(); return; }
            document.getElementById('searchResults').innerHTML = '';
            fetchData();
        }

        function fetchData() {
            if (loading || !hasMore) return;
            loading = true;
            fetch(`{{ route('returdata.returorderdata') }}?search=${encodeURIComponent(keyword)}&page=${page}`)
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('searchResults');
                    if (page === 1 && res.data.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:16px;color:#9ca3af;">Tidak ada data ditemukan</td></tr>`;
                        hasMore = false;
                        showDropdown();
                        return;
                    }
                    res.data.forEach((item, index) => {
                        const row = document.createElement('tr');
                        row.dataset.code = item.transaction_code;
                        row.dataset.invoice = item.invoice_number || '';
                        row.innerHTML = `
                            <td>${((page - 1) * res.per_page) + index + 1}</td>
                            <td>${item.transaction_code}<br><small style="color: #9ca3af; font-size: 11px;">Faktur: ${item.invoice_number || '-'}</small></td>
                            <td>${item.name ?? '-'}</td>
                            <td>${Number(item.final_price).toLocaleString('id-ID')}</td>`;
                        tbody.appendChild(row);
                    });
                    hasMore = res.current_page < res.last_page;
                    page++;
                    showDropdown();
                })
                .finally(() => { loading = false; });
        }

        function showDropdown() { document.getElementById('searchDropdown').style.display = 'block'; }
        function hideDropdown() { document.getElementById('searchDropdown').style.display = 'none'; }

        function selectRow(row) {
            selectedTransactionCode = row.dataset.code;
            document.getElementById('searchInput').value = selectedTransactionCode;
            document.getElementById('invoice_number').value = row.dataset.invoice || '—';
            hideDropdown();
            activeIndex   = -1;
            tableKeyIndex = -1;
            // Reload table then wait for first row to be available
            if (medicineTable) {
                medicineTable.ajax.reload(function () {
                    // After reload auto-focus first row via keyboard
                    tableKeyIndex = 0;
                    highlightTableRow(tableKeyIndex);
                }, false);
            }
        }

        function updateActiveRow(rows) {
            rows.forEach(r => r.classList.remove('active'));
            if (activeIndex >= 0) {
                rows[activeIndex].classList.add('active');
                rows[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        }

        function handleScroll() {
            const el = document.getElementById('tableScroll');
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 5) fetchData();
        }

        // ═════════════════════════════════════════════════════════════════════════════
        // MEDICINE TABLE KEYBOARD NAVIGATION
        // ═════════════════════════════════════════════════════════════════════════════

        function getTableRows() {
            return [...document.querySelectorAll('#medicineTable tbody tr')];
        }

        function highlightTableRow(idx) {
            const rows = getTableRows();
            rows.forEach(r => r.classList.remove('keyboard-focus', 'active'));
            if (idx >= 0 && idx < rows.length) {
                rows[idx].classList.add('keyboard-focus');
                rows[idx].scrollIntoView({ block: 'nearest' });
            }
        }

        function selectTableRow(idx) {
            const rows = getTableRows();
            if (idx < 0 || idx >= rows.length) return;
            rows.forEach(r => r.classList.remove('keyboard-focus', 'active'));
            rows[idx].classList.add('active');
            const data = medicineTable.row(rows[idx]).data();
            if (data) loadMedicineForRetur(data);
        }

        // ═════════════════════════════════════════════════════════════════════════════
        // TIPE RETUR (KEMASAN vs ECERAN) & KALKULASI
        // ═════════════════════════════════════════════════════════════════════════════

        function setReturType(type) {
            if (!currentMedicine) return;

            // Jika obat tidak memiliki kemasan atau isi <= 1, paksa unit (eceran)
            if (type === 'packaging' && (currentMedicine.content <= 1 || !currentMedicine.packaging)) {
                iziToast.info({ title: 'Info', message: 'Obat ini tidak memiliki kemasan (hanya tersedia satuan eceran).', position: 'topRight' });
                type = 'unit';
            }

            document.getElementById('retur_type').value = type;

            const btnPackaging = document.getElementById('btnTypePackaging');
            const btnUnit      = document.getElementById('btnTypeUnit');

            if (type === 'packaging') {
                btnPackaging.className = 'flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-[12px] font-semibold transition-all duration-150 bg-white text-blue-700 shadow-sm border border-blue-200';
                btnUnit.className      = 'flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-[12px] font-medium transition-all duration-150 text-gray-600 hover:text-gray-900 border border-transparent';
            } else {
                btnUnit.className      = 'flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-[12px] font-semibold transition-all duration-150 bg-white text-blue-700 shadow-sm border border-blue-200';
                btnPackaging.className = 'flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-[12px] font-medium transition-all duration-150 text-gray-600 hover:text-gray-900 border border-transparent';
            }

            updateFormForSelectedType();
            calculateReturTotal();
        }

        function updateFormForSelectedType() {
            if (!currentMedicine) return;

            const type          = document.getElementById('retur_type').value;
            const packagingName = currentMedicine.packaging || 'Kemasan';
            const unitName      = currentMedicine.unit || 'Eceran';

            if (type === 'packaging') {
                const packPrice = parseFloat(currentMedicine.pack_price) || 0;
                const maxQty    = parseFloat(currentMedicine.remaining_pack !== undefined ? currentMedicine.remaining_pack : currentMedicine.qty_received_pack) || 0;

                $('#item_price').val('Rp ' + Number(packPrice).toLocaleString('id-ID'));
                document.getElementById('item_price').dataset.raw = packPrice;
                $('#price_type_label').text('/ ' + packagingName);

                $('#qty_in').val(currentMedicine.qty_received_pack + ' ' + packagingName);
                $('#old_qty').val(maxQty);
                $('#qty_unit_badge').text(packagingName);
                $('#qty_unit_suffix').text(packagingName);

                const totalEquiv = maxQty * currentMedicine.content;
                $('#qty_hint').text(`Maks. retur: ${maxQty} ${packagingName} (= ${Number(totalEquiv).toLocaleString('id-ID')} ${unitName})`);
            } else {
                const unitPrice = parseFloat(currentMedicine.unit_price) || 0;
                const maxQty    = parseFloat(currentMedicine.remaining_unit !== undefined ? currentMedicine.remaining_unit : currentMedicine.qty_received_unit) || 0;

                $('#item_price').val('Rp ' + Number(unitPrice).toLocaleString('id-ID'));
                document.getElementById('item_price').dataset.raw = unitPrice;
                $('#price_type_label').text('/ ' + unitName);

                $('#qty_in').val(currentMedicine.qty_received_unit + ' ' + unitName);
                $('#old_qty').val(maxQty);
                $('#qty_unit_badge').text(unitName);
                $('#qty_unit_suffix').text(unitName);

                if (currentMedicine.content > 1) {
                    const packEquiv = (maxQty / currentMedicine.content).toFixed(1).replace(/\.0$/, '');
                    $('#qty_hint').text(`Maks. retur: ${Number(maxQty).toLocaleString('id-ID')} ${unitName} (${packEquiv} ${packagingName})`);
                } else {
                    $('#qty_hint').text(`Maks. retur: ${Number(maxQty).toLocaleString('id-ID')} ${unitName}`);
                }
            }
        }

        function calculateReturTotal() {
            if (!currentMedicine) return;

            const oldQty = parseFloat(document.getElementById('old_qty').value) || 0;
            let returQty = parseFloat(document.getElementById('qty').value) || 0;

            if (oldQty > 0 && returQty > oldQty) {
                returQty = oldQty;
                document.getElementById('qty').value = returQty;
            }

            const type = document.getElementById('retur_type').value;
            const pricePerUnit = type === 'packaging'
                ? parseFloat(currentMedicine.pack_price || 0)
                : parseFloat(currentMedicine.unit_price || 0);

            const rawTotal = Math.round(returQty * pricePerUnit);
            document.getElementById('total_retur').value = 'Rp ' + Number(rawTotal).toLocaleString('id-ID');
            document.getElementById('total_retur').dataset.raw = rawTotal;

            const calcHint = document.getElementById('calc_hint');
            if (returQty > 0) {
                const unitLabel = type === 'packaging' ? (currentMedicine.packaging || 'Kemasan') : (currentMedicine.unit || 'Eceran');
                calcHint.textContent = `${returQty} ${unitLabel} × Rp ${Number(pricePerUnit).toLocaleString('id-ID')} = Rp ${Number(rawTotal).toLocaleString('id-ID')}`;
                calcHint.classList.remove('hidden');
            } else {
                calcHint.classList.add('hidden');
            }
        }

        // ═════════════════════════════════════════════════════════════════════════════
        // BATCH SELECT
        // ═════════════════════════════════════════════════════════════════════════════

        function loadBatches(medicineId) {
            const select    = document.getElementById('batch_select');
            const loadingEl = document.getElementById('batch_loading');
            const emptyNote = document.getElementById('batch_empty_note');

            batchesReady         = false;
            select.innerHTML     = '<option value="">Memuat batch...</option>';
            select.disabled      = true;
            loadingEl.classList.remove('hidden');
            emptyNote.classList.add('hidden');

            axios.get('{{ route('returdata.getBatchesByOrderedMedicine') }}', { params: { medicine_id: medicineId } })
                .then(res => {
                    const batches = res.data;
                    select.innerHTML = '';

                    if (!batches.length) {
                        select.innerHTML = '<option value="">Tidak ada batch tersedia</option>';
                        emptyNote.classList.remove('hidden');
                        return;
                    }

                    const placeholder = document.createElement('option');
                    placeholder.value       = '';
                    placeholder.textContent = '— Pilih batch —';
                    select.appendChild(placeholder);

                    batches.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        const expFormatted = b.expired_date
                            ? new Date(b.expired_date).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
                            : '-';
                        let phTag = '';
                        if (b.pharmacy_id == 9) phTag = '[Gudang] ';
                        else if (b.pharmacy_id == 1) phTag = '[Pelayanan] ';

                        let stockText = `${b.stock} ${currentMedicine?.unit || ''}`;
                        if (currentMedicine && currentMedicine.content > 1) {
                            const packCount = Math.floor(b.stock / currentMedicine.content);
                            const remainder = b.stock % currentMedicine.content;
                            let packText = `${packCount} ${currentMedicine.packaging || 'BOX'}`;
                            if (remainder > 0) packText += ` + ${remainder} ${currentMedicine.unit || 'TAB'}`;
                            stockText = `${b.stock} ${currentMedicine.unit || 'TAB'} (${packText})`;
                        }

                        opt.textContent         = `${phTag}${b.name}  |  Exp: ${expFormatted}  |  Stok: ${stockText}`;
                        opt.dataset.batchName   = b.name;
                        opt.dataset.expiredDate = b.expired_date ?? '';
                        opt.dataset.stock       = b.stock;
                        select.appendChild(opt);
                    });

                    select.disabled = false;
                    batchesReady    = true;

                    // Auto-select first batch and focus qty
                    if (batches.length === 1) {
                        select.selectedIndex = 1;
                    }
                    document.getElementById('qty').focus();
                })
                .catch(() => {
                    select.innerHTML = '<option value="">Gagal memuat batch</option>';
                    iziToast.error({ title: 'Error', message: 'Gagal memuat data batch.', position: 'topRight' });
                })
                .finally(() => { loadingEl.classList.add('hidden'); });
        }

        // ═════════════════════════════════════════════════════════════════════════════
        // SUBMIT
        // ═════════════════════════════════════════════════════════════════════════════

        function submitRetur() {
            const medicineId    = document.getElementById('medicine_id').value;
            const transactionId = document.getElementById('transaction_id').value;
            const returType     = document.getElementById('retur_type').value || 'unit';
            const qtyRetur      = parseFloat(document.getElementById('qty').value) || 0;
            const oldQty        = parseFloat(document.getElementById('old_qty').value) || 0;
            const batchSelect   = document.getElementById('batch_select');
            const batchId       = batchSelect.value;
            const content       = parseInt(document.getElementById('content').value) || 1;

            if (!transactionId) return iziToast.warning({ title: 'Perhatian', message: 'Pilih transaksi pembelian terlebih dahulu.', position: 'topRight' });
            if (!medicineId)    return iziToast.warning({ title: 'Perhatian', message: 'Pilih obat dari tabel kanan.', position: 'topRight' });
            if (!batchId)       return iziToast.warning({ title: 'Perhatian', message: 'Pilih batch obat.', position: 'topRight' });
            if (qtyRetur <= 0)  return iziToast.warning({ title: 'Perhatian', message: 'Qty retur harus lebih dari 0.', position: 'topRight' });
            if (qtyRetur > oldQty) return iziToast.warning({ title: 'Perhatian', message: 'Qty retur tidak boleh melebihi batas retur.', position: 'topRight' });

            const selectedOption = batchSelect.options[batchSelect.selectedIndex];
            const batchStock     = selectedOption ? parseFloat(selectedOption.dataset.stock || 0) : 0;
            const actualDeduct   = returType === 'packaging' ? (qtyRetur * content) : qtyRetur;

            if (batchStock < actualDeduct) {
                const unitName = currentMedicine?.unit || 'satuan';
                return iziToast.warning({ 
                    title: 'Perhatian', 
                    message: `Stok batch (${batchStock} ${unitName}) tidak mencukupi untuk retur (${actualDeduct} ${unitName}).`, 
                    position: 'topRight' 
                });
            }

            const rawTotalAttr = document.getElementById('total_retur').dataset.raw;
            const pricePerUnit = returType === 'packaging' ? (currentMedicine?.pack_price || 0) : (currentMedicine?.unit_price || 0);
            let totalRetur = (rawTotalAttr !== undefined && rawTotalAttr !== '' && !isNaN(rawTotalAttr))
                ? parseFloat(rawTotalAttr)
                : Math.round(qtyRetur * pricePerUnit);

            const btnSimpan = document.getElementById('btnSimpan');
            const btnLabel  = document.getElementById('btnSimpanLabel');
            btnSimpan.disabled   = true;
            btnLabel.textContent = 'Menyimpan...';

            axios.post('{{ route('returdata.returorderitems') }}', {
                transaction_id: parseInt(transactionId),
                medicine_id:    parseInt(medicineId),
                batch_id:       parseInt(batchId),
                retur_type:     returType,
                qty_retur:      qtyRetur,
                total_retur:    totalRetur,
                old_qty:        oldQty,
            })
            .then(res => {
                iziToast.success({ title: 'Berhasil', message: res.data.message ?? 'Retur berhasil disimpan.', position: 'topRight' });
                medicineTable?.ajax.reload(null, false);
                if (res.data.retur_code) {
                    document.getElementById('returnumber').value = res.data.retur_code;
                }
                resetReturForm();
            })
            .catch(err => {
                iziToast.error({ title: 'Gagal', message: err.response?.data?.message ?? 'Terjadi kesalahan, coba lagi.', position: 'topRight' });
            })
            .finally(() => {
                btnSimpan.disabled   = false;
                btnLabel.textContent = 'Simpan';
            });
        }

        // ═════════════════════════════════════════════════════════════════════════════
        // RESET
        // ═════════════════════════════════════════════════════════════════════════════

        function resetReturForm() {
            currentMedicine = null;
            ['medicine_id','cart_id','old_qty','content','medicine_code','unit','packaging_display',
             'medicine_name','item_price','content_display','qty_in','qty','price','total_retur', 'invoice_number']
                .forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = '';
                        delete el.dataset.raw;
                    }
                });

            $('#labelPackaging').text('Per Kemasan');
            $('#labelUnit').text('Eceran');
            $('#price_type_label').text('');
            $('#qty_unit_badge').text('');
            $('#qty_unit_suffix').text('');
            $('#qty_hint').text('');
            document.getElementById('calc_hint').classList.add('hidden');

            const btnPackaging = document.getElementById('btnTypePackaging');
            btnPackaging.disabled = false;
            btnPackaging.classList.remove('opacity-50', 'cursor-not-allowed');

            const batchSelect        = document.getElementById('batch_select');
            batchSelect.innerHTML    = '<option value="">— Pilih obat terlebih dahulu —</option>';
            batchSelect.disabled     = true;
            batchesReady             = false;
            tableKeyIndex            = -1;
            document.getElementById('batch_empty_note').classList.add('hidden');
            $('#medicineTable tbody tr').removeClass('active keyboard-focus');

            // Return focus to search so cashier can start next retur immediately
            document.getElementById('searchInput').focus();
        }

        // ═════════════════════════════════════════════════════════════════════════════
        // LOAD MEDICINE INTO FORM
        // ═════════════════════════════════════════════════════════════════════════════

        function loadMedicineForRetur(data) {
            currentMedicine = data;

            $('#medicine_code').val(data.code);
            $('#medicine_id').val(data.medicine_id);
            $('#medicine_name').val(data.name);
            $('#transaction_id').val(data.receiving_id);
            $('#unit').val(data.unit);
            $('#packaging_display').val(data.packaging || '—');
            $('#price').val('Rp ' + Number(data.total).toLocaleString('id-ID'));
            $('#cart_id').val(data.id);
            $('#content_display').val(data.content);
            $('#content').val(data.content);

            // Update button labels with actual packaging & unit names
            const packLabel = data.packaging ? `Per Kemasan (${data.packaging})` : 'Per Kemasan';
            const unitLabel = data.unit ? `Eceran (${data.unit})` : 'Eceran';
            $('#labelPackaging').text(packLabel);
            $('#labelUnit').text(unitLabel);

            const btnPackaging = document.getElementById('btnTypePackaging');
            if (data.content <= 1 || !data.packaging) {
                btnPackaging.disabled = true;
                btnPackaging.classList.add('opacity-50', 'cursor-not-allowed');
                btnPackaging.title = 'Obat ini tidak memiliki kemasan (hanya satuan eceran)';
                setReturType('unit');
            } else {
                btnPackaging.disabled = false;
                btnPackaging.classList.remove('opacity-50', 'cursor-not-allowed');
                btnPackaging.title = '';
                // Default to packaging if original order was pack, or packaging if content > 1
                setReturType('packaging');
            }

            $('#qty').val('');
            $('#total_retur').val('');
            document.getElementById('total_retur').dataset.raw = '0';
            document.getElementById('calc_hint').classList.add('hidden');

            if (data.medicine_id) loadBatches(data.medicine_id);
        }

        // ═════════════════════════════════════════════════════════════════════════════
        // GLOBAL ENTER-KEY NAVIGATION
        //
        // Flow:
        //   searchInput  → (select row with Enter) → medicine table keyboard nav
        //   medicine table (Enter on row) → qty field
        //   qty field (Enter) → batch_select
        //   batch_select (Enter, value selected) → submitRetur()
        // ═════════════════════════════════════════════════════════════════════════════

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;

            const active = document.activeElement;
            if (!active) return;

            // ── 1. Search input ───────────────────────────────────────────────
            if (active.id === 'searchInput') {
                const rows    = document.querySelectorAll('#searchResults tr');
                const dropdown = document.getElementById('searchDropdown');
                if (dropdown.style.display !== 'none' && rows.length && activeIndex >= 0) {
                    e.preventDefault();
                    selectRow(rows[activeIndex]);
                }
                return;
            }

            // ── 2. Medicine table keyboard nav ────────────────────────────────
            // When table has keyboard focus (user pressed ↓ after transaction selected)
            if (active.id === 'medicineTableNav') {
                e.preventDefault();
                selectTableRow(tableKeyIndex);
                // loadMedicineForRetur focuses batch_select after async load
                return;
            }

            // ── 3. qty field → batch_select ───────────────────────────────────
            if (active.id === 'qty') {
                e.preventDefault();
                const batch = document.getElementById('batch_select');
                if (!batch.disabled) {
                    batch.focus();
                } else {
                    iziToast.warning({ title: 'Perhatian', message: 'Tunggu batch selesai dimuat.', position: 'topRight' });
                }
                return;
            }

            // ── 4. batch_select → submit ──────────────────────────────────────
            if (active.id === 'batch_select') {
                e.preventDefault();
                if (!active.value) {
                    iziToast.warning({ title: 'Perhatian', message: 'Pilih batch terlebih dahulu.', position: 'topRight' });
                    return;
                }
                submitRetur();
                return;
            }
        });

        // ═════════════════════════════════════════════════════════════════════════════
        // ARROW-KEY NAV: search dropdown + medicine table
        // ═════════════════════════════════════════════════════════════════════════════

        document.addEventListener('keydown', function (e) {
            const active   = document.activeElement;
            const dropdown = document.getElementById('searchDropdown');

            // ── Search dropdown arrow nav ─────────────────────────────────────
            if (active.id === 'searchInput' && dropdown.style.display !== 'none') {
                const rows = document.querySelectorAll('#searchResults tr');
                if (!rows.length) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, rows.length - 1);
                    updateActiveRow(rows);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    updateActiveRow(rows);
                }
                return;
            }

            // ── Medicine table arrow nav (active after transaction selected) ──
            // We listen on the page level when no input is focused, or when the
            // hidden nav anchor is focused
            const tableRows = getTableRows();
            if (!tableRows.length) return;

            const focusInForm = ['searchInput','qty','batch_select'].includes(active?.id)
                || active?.tagName === 'INPUT'
                || active?.tagName === 'SELECT';

            if (!focusInForm && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault();
                if (e.key === 'ArrowDown') tableKeyIndex = Math.min(tableKeyIndex + 1, tableRows.length - 1);
                if (e.key === 'ArrowUp')   tableKeyIndex = Math.max(tableKeyIndex - 1, 0);
                highlightTableRow(tableKeyIndex);
                // Keep a focusable anchor on the table area
                document.getElementById('medicineTableNav')?.focus();
            }

            if (!focusInForm && e.key === 'Enter' && tableKeyIndex >= 0) {
                e.preventDefault();
                selectTableRow(tableKeyIndex);
            }
        });

        // ═════════════════════════════════════════════════════════════════════════════
        // DOM READY
        // ═════════════════════════════════════════════════════════════════════════════

        $(document).ready(function () {

            // Hover highlight on search results
            document.getElementById('searchResults').addEventListener('mouseover', function (e) {
                const row = e.target.closest('tr');
                if (!row) return;
                const rows = [...this.querySelectorAll('tr')];
                rows.forEach(r => r.classList.remove('active'));
                row.classList.add('active');
                activeIndex = rows.indexOf(row);
            });

            // Click row in search dropdown
            document.getElementById('searchResults').addEventListener('click', function (e) {
                const row = e.target.closest('tr');
                if (row) { selectRow(row); e.stopPropagation(); }
            });

            // Outside click closes dropdown
            document.addEventListener('click', function (e) {
                if (!document.getElementById('searchInput').contains(e.target) &&
                    !document.getElementById('searchDropdown').contains(e.target)) hideDropdown();
            });

            // Escape closes dropdown
            document.getElementById('searchInput').addEventListener('keydown', function (e) {
                if (e.key === 'Escape') hideDropdown();
            });

            // Back button
            $('#back').on('click', () => window.location.href = "{{ route('home') }}");

            // ─── DataTable ────────────────────────────────────────────────────
            medicineTable = $('#medicineTable').DataTable({
                processing:   true,
                paging:       true,
                searching:    true,
                ordering:     false,
                info:         true,
                lengthChange: true,
                autoWidth:    false,

                ajax: {
                    url:    '{{ route('returdata.ordermedicines') }}',
                    data:   d => { d.transaction_code = selectedTransactionCode; },
                    dataSrc: '',
                },
                
                dom: '<"flex items-center justify-between mb-4"lf>rt<"flex items-center justify-between mt-4"ip>',
                language: {
                    search: "", 
                    searchPlaceholder: "Cari item...",
                    lengthMenu: "Show _MENU_ entries"
                },

                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1 },
                    { data: 'name', render: function(data, type, row) {
                        let ratioBadge = '';
                        if (row.content > 1) {
                            ratioBadge = `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">1 ${row.packaging || 'Kemasan'} = ${row.content} ${row.unit || 'Eceran'}</span>`;
                        }
                        return `<div>
                                    <div class="font-medium text-gray-800">${data}</div>
                                    <div class="text-[11px] text-gray-400 mt-0.5 flex items-center gap-1.5 flex-wrap">
                                        <span>Kode: ${row.code || '-'}</span>
                                        <span>&bull;</span>
                                        <span>${row.unit || '-'}</span>
                                        ${ratioBadge ? `<span>&bull;</span>` + ratioBadge : ''}
                                    </div>
                                </div>`;
                    }},
                    { data: 'qty_received', className: 'text-end', render: function(data, type, row) {
                        if (row.is_pack || row.content > 1) {
                            return `<div class="text-right">
                                        <div class="font-semibold text-gray-800">${row.qty_received_pack} <span class="text-[11px] font-normal text-gray-500">${row.packaging || 'BOX'}</span></div>
                                        <div class="text-[11px] text-gray-400">(${Number(row.qty_received_unit).toLocaleString('id-ID')} ${row.unit || 'TAB'})</div>
                                    </div>`;
                        }
                        return `<span class="font-semibold text-gray-800">${data}</span> <span class="text-[11px] text-gray-400">${row.unit || ''}</span>`;
                    }},
                    { data: 'total', className: 'text-end', render: val => '<span class="font-medium text-gray-700">Rp ' + Number(val).toLocaleString('id-ID') + '</span>' },
                ],

                drawCallback: function () {
                    const count = this.api().rows().count();
                    document.getElementById('itemCount').textContent = count + ' item';
                    // Re-apply keyboard highlight after redraw (e.g. pagination)
                    if (tableKeyIndex >= 0) highlightTableRow(tableKeyIndex);
                },
            });

            // Mouse click on medicine table row
            $('#medicineTable tbody').on('click', 'tr', function () {
                const data = medicineTable.row(this).data();
                if (!data) return;
                tableKeyIndex = $('#medicineTable tbody tr').index(this);
                $('#medicineTable tbody tr').removeClass('active keyboard-focus');
                $(this).addClass('active');
                loadMedicineForRetur(data);
            });
        });
    </script>

    {{-- Hidden focusable anchor used to keep keyboard focus inside medicine table area --}}
    <span id="medicineTableNav" tabindex="0" style="position:absolute;opacity:0;pointer-events:none;"></span>
@endsection