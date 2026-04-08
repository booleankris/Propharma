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
            width: 220px !important; padding: 6px 10px !important;
            border-radius: 6px !important; border: 1px solid #d1d5db !important; outline: none !important;
        }
        .dataTables_length select {
            padding: 4px 8px !important; border-radius: 6px !important; border: 1px solid #d1d5db !important;
        }
        #medicineTable thead th {
            background-color: #f8fafc !important; font-weight: 600 !important;
            font-size: 11px !important; text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important; padding: 10px !important;
        }
        #medicineTable tbody td { padding: 11px 10px !important; font-size: 13px !important; vertical-align: middle !important; }
        #medicineTable tbody tr { cursor: pointer; }
        #medicineTable tbody tr:hover { background-color: #f1f5f9 !important; }
        #medicineTable tbody tr.active { background-color: #dbeafe !important; }
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
                                            <th>#</th><th>Kode</th><th>Nama Supplier</th><th>Total</th>
                                        </tr>
                                    </thead>
                                </table>
                                <div id="tableScroll" onscroll="handleScroll()">
                                    <table><tbody id="searchResults"></tbody></table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Hidden fields --}}
                    <input id="medicine_id"    type="hidden">
                    <input id="cart_id"        type="hidden">
                    <input id="transaction_id" type="hidden">
                    <input id="old_qty"        type="hidden">
                    <input id="content"        type="hidden">

                    {{-- Detail Obat --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Detail Obat</p>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Kode obat</label>
                            <input id="medicine_code" type="text" readonly placeholder="Kode Obat" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Satuan</label>
                            <input id="unit" type="text" readonly placeholder="—" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Nama obat</label>
                        <input id="medicine_name" type="text" readonly placeholder="—" tabindex="-1"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Harga satuan</label>
                            <input id="item_price" type="text" readonly placeholder="Rp 0" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Isi</label>
                            <input id="content_display" type="text" readonly placeholder="—" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    {{-- Rincian Retur --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Rincian Retur</p>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Qty beli</label>
                            <input id="qty_in" type="number" placeholder="0" tabindex="-1" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Qty retur</label>
                            <input id="qty" type="number" placeholder="0"
                                oninput="calculateReturTotal()"
                                class="retur-input w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Harga resep</label>
                            <input id="price" type="text" readonly placeholder="Rp 0" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Jumlah retur</label>
                            <input id="total_retur" type="text" readonly placeholder="Rp 0" tabindex="-1"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                        row.innerHTML = `
                            <td>${((page - 1) * res.per_page) + index + 1}</td>
                            <td>${item.transaction_code}</td>
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
            selectedTransactionCode = row.children[1].innerText.trim();
            document.getElementById('searchInput').value = selectedTransactionCode;
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
        // CALCULATE
        // ═════════════════════════════════════════════════════════════════════════════

        function calculateReturTotal() {
            const returQty  = parseFloat(document.getElementById('qty').value) || 0;
            const itemPrice = parseFloat(
                (document.getElementById('item_price').value || '0').replace(/[^\d.-]/g, '')
            ) || 0;
            document.getElementById('total_retur').value =
                'Rp ' + (returQty * itemPrice).toLocaleString('id-ID');
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
                        opt.textContent      = `${b.name}  |  Exp: ${expFormatted}  |  Stok: ${b.stock}`;
                        opt.dataset.batchName   = b.name;
                        opt.dataset.expiredDate = b.expired_date ?? '';
                        select.appendChild(opt);
                    });

                    select.disabled = false;
                    batchesReady    = true;

                    // Auto-select first batch and focus select so Enter-flow can continue
                    if (batches.length === 1) {
                        select.selectedIndex = 1; // skip placeholder, select only option
                    }
                    select.focus();
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
            const qtyRetur      = document.getElementById('qty').value;
            const oldQty        = document.getElementById('old_qty').value;
            const totalRetur    = document.getElementById('total_retur').value.replace(/[^\d.-]/g, '');
            const batchSelect   = document.getElementById('batch_select');
            const batchId       = batchSelect.value;

            if (!transactionId) return iziToast.warning({ title: 'Perhatian', message: 'Pilih transaksi pembelian terlebih dahulu.', position: 'topRight' });
            if (!medicineId)    return iziToast.warning({ title: 'Perhatian', message: 'Pilih obat dari tabel kanan.', position: 'topRight' });
            if (!batchId)       return iziToast.warning({ title: 'Perhatian', message: 'Pilih batch obat.', position: 'topRight' });
            if (!qtyRetur || parseFloat(qtyRetur) < 1)            return iziToast.warning({ title: 'Perhatian', message: 'Qty retur harus minimal 1.', position: 'topRight' });
            if (parseFloat(qtyRetur) > parseFloat(oldQty))        return iziToast.warning({ title: 'Perhatian', message: 'Qty retur tidak boleh melebihi qty beli.', position: 'topRight' });

            const btnSimpan = document.getElementById('btnSimpan');
            const btnLabel  = document.getElementById('btnSimpanLabel');
            btnSimpan.disabled   = true;
            btnLabel.textContent = 'Menyimpan...';

            axios.post('{{ route('returdata.returorderitems') }}', {
                transaction_id: transactionId,
                medicine_id:    medicineId,
                batch_id:       batchId,
                qty_retur:      qtyRetur,
                total_retur:    totalRetur,
                old_qty:        oldQty,
            })
            .then(res => {
                iziToast.success({ title: 'Berhasil', message: res.data.message ?? 'Retur berhasil disimpan.', position: 'topRight' });
                medicineTable?.ajax.reload(null, false);
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
            ['medicine_id','cart_id','old_qty','content','medicine_code','unit',
             'medicine_name','item_price','content_display','qty_in','qty','price','total_retur']
                .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });

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
            $('#medicine_code').val(data.code);
            $('#medicine_id').val(data.medicine_id);
            $('#medicine_name').val(data.name);
            $('#transaction_id').val(data.receiving_id);
            $('#unit').val(data.unit);
            $('#price').val('Rp ' + Number(data.total).toLocaleString('id-ID'));
            $('#item_price').val(data.raw_price);
            $('#old_qty').val(data.qty_received);
            $('#cart_id').val(data.id);
            $('#qty_in').val(data.qty_received);
            $('#content_display').val(data.content);
            $('#content').val(data.content);
            $('#qty').val('');
            $('#total_retur').val('');

            if (data.medicine_id) loadBatches(data.medicine_id);
            // qty gets focus AFTER batches load (loadBatches calls select.focus() at end)
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

                columns: [
                    { data: null, render: (d, t, r, m) => m.row + 1 },
                    { data: 'name' },
                    { data: 'qty_received', className: 'text-end' },
                    { data: 'total', className: 'text-end', render: val => 'Rp ' + Number(val).toLocaleString('id-ID') },
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