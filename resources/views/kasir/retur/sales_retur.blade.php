@extends('layouts.app')

@section('title', 'Sales Data')

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

        .dropdown-table table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dropdown-table thead th {
            position: sticky;
            top: 0;
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            padding: 9px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: .04em;
        }

        .dropdown-table tbody tr {
            cursor: pointer;
            transition: background-color .12s;
        }

        .dropdown-table tbody tr:hover,
        .dropdown-table tbody tr.active {
            background-color: #dbeafe;
        }

        .dropdown-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #111827;
            vertical-align: middle;
        }

        .dropdown-table tbody tr:last-child td {
            border-bottom: none;
        }

        .dropdown-table td:first-child {
            width: 36px;
            color: #9ca3af;
            font-size: 12px;
        }

        .dropdown-table td:nth-child(4) {
            font-weight: 600;
            color: #16a34a;
        }

        #tableScroll {
            max-height: 250px;
            overflow-y: auto;
        }

        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter {
            margin-bottom: 10px;
        }

        .dataTables_filter input {
            width: 220px !important;
            font-size: 12px !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            outline: none !important;
        }

        .dataTables_length select {
            padding: 6px 28px 6px 12px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }

        #medicineTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
            padding: 10px !important;
        }

        #medicineTable tbody td {
            padding: 12px 10px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #medicineTable tbody tr {
            cursor: pointer;
            transition: background-color 0.15s;
        }

        #medicineTable tbody tr:hover {
            background-color: #f8fafc !important;
        }

        #medicineTable tbody tr.active {
            background-color: #eff6ff !important;
            border-left: 3px solid #3b82f6 !important;
        }

        #medicineTable tbody tr.keyboard-focus {
            background-color: #eff6ff !important;
            outline: 2px solid #93c5fd;
            outline-offset: -2px;
        }

        .dataTables_paginate .paginate_button {
            padding: 5px 10px !important;
            border-radius: 6px !important;
        }

        .text-end {
            text-align: right !important;
        }

        /* Visual focus ring for retur-input fields */
        .retur-input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .15) !important;
        }

        #medicineTable tbody tr.row-locked {
            cursor: not-allowed !important;
            background-color: #f9fafb !important;
        }

        #medicineTable tbody tr.row-locked:hover {
            background-color: #f3f4f6 !important;
        }

        .badge-locked {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            font-size: 10px;
            font-weight: 600;
            color: #e11d48;
            background-color: #ffe4e6;
            border: 1px solid #fecdd3;
            border-radius: 9999px;
            white-space: nowrap;
        }

        .badge-partial {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            font-size: 10px;
            font-weight: 600;
            color: #b45309;
            background-color: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 9999px;
            white-space: nowrap;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                {{-- LEFT: Form Panel --}}
                <div class="bg-white border border-gray-100 rounded-xl p-6">

                    {{-- Info Banner --}}
                    <div class="flex items-start gap-2.5 p-3 mb-4 rounded-xl bg-blue-50 border border-blue-200/80 text-blue-900 text-[12px] leading-relaxed">
                        <svg class="w-4 h-4 text-blue-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <span class="font-semibold text-blue-950">Aturan Retur:</span> Item obat dapat diretur berkali-kali selama tidak melebihi sisa quantity yang dibeli. Item akan <strong>dikunci otomatis</strong> jika seluruh quantity-nya telah selesai diretur.
                        </div>
                    </div>

                    {{-- Info Retur --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Informasi Retur</p>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Tanggal retur</label>
                            <input type="text" value="{{ $now }}" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Nomor retur</label>
                            <input type="text" id="retur_code_display" value="{{ $retur_code }}" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    {{-- Search --}}
                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Cari transaksi</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="6.5" cy="6.5" r="4.5" />
                                <line x1="10.5" y1="10.5" x2="14" y2="14" />
                            </svg>
                            <input type="text" id="searchInput" placeholder="Cari kode / nama pelanggan..."
                                oninput="searchSalesData(this.value)" autocomplete="off"
                                class="w-full rounded-lg border border-gray-200 bg-white pl-9 pr-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">

                            {{-- Dropdown --}}
                            <div id="searchDropdown" class="dropdown-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Kode</th>
                                            <th>Nama Pelanggan</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                </table>
                                <div id="tableScroll" onscroll="handleScroll()">
                                    <table>
                                        <tbody id="searchResults"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100 my-5">

                    {{-- Detail Obat --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Detail Obat</p>

                    {{-- Hidden fields --}}
                    <input id="medicine_id" type="hidden">
                    <input id="cart_id" type="hidden">
                    <input id="transaction_id" type="hidden">
                    <input id="old_qty" type="hidden">

                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Kode obat</label>
                            <input id="medicine_code" type="text" readonly placeholder="OTC"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Satuan</label>
                            <input id="unit" type="text" readonly placeholder="—"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Nama obat</label>
                        <input id="medicine_name" type="text" readonly placeholder="—"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                    </div>

                    <div class="mb-3">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Harga satuan</label>
                        <input id="item_price" type="text" readonly placeholder="Rp 0"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                    </div>

                    <hr class="border-gray-100 my-5">

                    {{-- Rincian Retur --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Rincian Retur</p>

                    <div class="grid grid-cols-3 gap-3 mb-3">
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Qty retur</label>
                            {{-- Change data-nav-enter on qty from "batch" to "batch_select" --}}
                            <input id="qty" type="number" placeholder="0" data-nav-enter="batch_select"
                                oninput="calculateReturTotal()"
                                class="nav-input w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            <p id="qty_max_hint" class="text-[11px] text-gray-400 mt-1"></p>
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Harga resep</label>
                            <input id="price" type="text" readonly placeholder="Rp 0"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Jumlah retur</label>
                            <input id="total_retur" type="text" placeholder="Rp 0"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                    </div>

                    <div class="mb-5">
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Batch & Expired Date</label>
                        <select id="batch_select" data-nav-enter="submit"
                            class="nav-input w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            <option value="">— Pilih batch / etalase —</option>
                        </select>
                        <input id="batch" type="hidden">
                        <input id="expired_date" type="hidden">
                        <input id="transfer_id" type="hidden">
                    </div>

                    <div class="flex gap-2">
                        <button type="button" id="submitBtn" onclick="submitRetur()"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#2196F3] hover:bg-[#1976D2] text-white text-[13px] font-medium transition-colors duration-150">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="2 9 6 13 14 4" />
                            </svg>
                            Simpan
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

                {{-- RIGHT: Item Table --}}
                <div class="bg-white border border-gray-100 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest">Item retur</p>
                            <span id="transStatusBadge" class="hidden text-[10px] font-semibold bg-red-100 text-red-700 border border-red-200 px-2 py-0.5 rounded-full">
                                Seluruh Item Telah Diretur
                            </span>
                        </div>
                        <span id="itemCount"
                            class="text-[11px] font-medium bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full">0
                            item</span>
                    </div>

                    {{-- Alert banner saat semua item selesai diretur --}}
                    <div id="allLockedBanner" class="hidden mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-red-800 text-[12px] flex items-center gap-2">
                        <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>Semua item pada transaksi ini telah diretur penuh. Transaksi ini telah dikunci untuk retur.</span>
                    </div>

                    <table id="medicineTable" class="w-full text-[13px] border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left text-[11px] font-medium text-gray-400 pb-2 pr-3">#</th>
                                <th class="text-left text-[11px] font-medium text-gray-400 pb-2 pr-3">Obat</th>
                                <th class="text-right text-[11px] font-medium text-gray-400 pb-2 pr-3">Qty</th>
                                <th class="text-right text-[11px] font-medium text-gray-400 pb-2">Harga</th>
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
        let page = 1;
        let keyword = '';
        let loading = false;
        let hasMore = true;
        let activeIndex = -1;
        let selectedTransactionCode = null;
        let medicineTable = null;

        // ─── Search ───────────────────────────────────────────────────────────────────
        function searchSalesData(value) {
            keyword = value.trim();
            page = 1;
            hasMore = true;
            activeIndex = -1;

            if (keyword.length < 1) {
                hideDropdown();
                return;
            }

            document.getElementById('searchResults').innerHTML = '';
            fetchData();
        }

        // ─── Fetch ────────────────────────────────────────────────────────────────────
        function fetchData() {
            if (loading || !hasMore) return;
            loading = true;

            fetch(`{{ route('returdata.returdata') }}?search=${keyword}&page=${page}`)
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('searchResults');

                    if (page === 1 && res.data.length === 0) {
                        tbody.innerHTML =
                            `<tr><td colspan="4" style="text-align:center; padding:16px; color:#9ca3af;">Tidak ada data ditemukan</td></tr>`;
                        hasMore = false;
                        showDropdown();
                        return;
                    }

                    res.data.forEach((item, index) => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${((page - 1) * res.per_page) + index + 1}</td>
                            <td>${item.transaction_code}</td>
                            <td>${item.name}</td>
                            <td>${item.final_price}</td>
                        `;
                        tbody.appendChild(row);
                    });

                    hasMore = res.current_page < res.last_page;
                    page++;
                    showDropdown();
                })
                .finally(() => {
                    loading = false;
                });
        }

        // ─── Dropdown helpers ─────────────────────────────────────────────────────────
        function showDropdown() {
            document.getElementById('searchDropdown').style.display = 'block';
        }

        function hideDropdown() {
            document.getElementById('searchDropdown').style.display = 'none';
        }

        function selectRow(row) {
            selectedTransactionCode = row.children[1].innerText;
            document.getElementById('searchInput').value = selectedTransactionCode;
            hideDropdown();
            medicineTable?.ajax.reload();
        }

        function updateActiveRow(rows) {
            rows.forEach(r => r.classList.remove('active'));
            if (activeIndex >= 0) {
                rows[activeIndex].classList.add('active');
                rows[activeIndex].scrollIntoView({
                    block: 'nearest'
                });
            }
        }

        // ─── Scroll (infinite load) ───────────────────────────────────────────────────
        function handleScroll() {
            const el = document.getElementById('tableScroll');
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 5) {
                fetchData();
            }
        }

        // ─── Calculate retur total ────────────────────────────────────────────────────
        function calculateReturTotal() {
            const maxQty = parseFloat(document.getElementById('old_qty').value) || 0;
            let returQty = parseFloat(document.getElementById('qty').value) || 0;

            if (returQty < 0) {
                returQty = 0;
                document.getElementById('qty').value = 0;
            } else if (returQty > maxQty) {
                returQty = maxQty;
                document.getElementById('qty').value = maxQty;
                iziToast.warning({
                    title: 'Batas Qty',
                    message: 'Qty retur tidak boleh melebihi sisa yang dapat diretur (' + maxQty + ').',
                    position: 'topRight'
                });
            }

            const remainingPrice = parseFloat(
                (document.getElementById('price').value || '0').replace(/[^\d.-]/g, '')
            ) || 0;

            const itemPrice = parseFloat(
                (document.getElementById('item_price').value || '0').replace(/[^\d.-]/g, '')
            ) || 0;

            let total = 0;
            if (returQty >= maxQty && maxQty > 0) {
                total = remainingPrice;
            } else if (itemPrice > 0) {
                total = remainingPrice - Math.ceil(((maxQty - returQty) * itemPrice) / 1000) * 1000;
                if (total <= 0) {
                    total = Math.round(returQty * itemPrice);
                }
            } else if (maxQty > 0) {
                total = Math.round((returQty / maxQty) * remainingPrice);
            }

            document.getElementById('total_retur').value = Math.max(0, Math.round(total));
        }

        // ─── Enter key navigation ─────────────────────────────────────────────────────
        function initEnterNavigation() {
            document.querySelectorAll('input.nav-input').forEach(input => {
                input.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const nextTarget = this.getAttribute('data-nav-enter');
                    if (!nextTarget) return;
                    if (nextTarget === 'submit') {
                        document.getElementById('submitBtn').click();
                    } else {
                        const nextEl = document.getElementById(nextTarget);
                        if (nextEl) nextEl.focus();
                    }
                });
            });
            document.querySelectorAll('select.nav-input').forEach(select => {
                select.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const nextTarget = this.getAttribute('data-nav-enter');
                    if (!nextTarget) return;
                    if (nextTarget === 'submit') {
                        document.getElementById('submitBtn').click();
                    } else {
                        const nextEl = document.getElementById(nextTarget);
                        if (nextEl) nextEl.focus();
                    }
                });
            });
        }

        // ─── Reset form ───────────────────────────────────────────────────────────────
        function resetReturForm() {
            $('#medicine_id').val('');
            $('#cart_id').val('');
            $('#transaction_id').val('');
            $('#old_qty').val('');
            $('#medicine_code').val('');
            $('#medicine_name').val('');
            $('#unit').val('');
            $('#item_price').val('');
            $('#price').val('');
            $('#qty').val('').removeAttr('max').attr('placeholder', '0');
            $('#qty_max_hint').html('');
            $('#total_retur').val('');
            $('#batch').val('');
            $('#expired_date').val('');
            $('#transfer_id').val('');
            $('#batch_select').html('<option value="">— Pilih batch / etalase —</option>');

            // Remove active row highlight from table
            $('#medicineTable tbody tr').removeClass('active');
        }

        // ─── AJAX Submit ──────────────────────────────────────────────────────────────
        function submitRetur() {
            const medicine_id = $('#medicine_id').val();
            const cart_id = $('#cart_id').val();
            const transaction_id = $('#transaction_id').val();
            const qty_retur = $('#qty').val();
            const total_retur = $('#total_retur').val();
            const transfer_id = $('#transfer_id').val();
            const medicine_code = $('#medicine_code').val();
            const medicine_name = $('#medicine_name').val() || 'obat ini';
            const old_qty = $('#old_qty').val();

            // Basic validation
            if (!medicine_id || !cart_id) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Pilih obat terlebih dahulu dari tabel.',
                    position: 'topRight'
                });
                return;
            }

            if (!qty_retur || parseFloat(qty_retur) <= 0) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Qty retur harus diisi dan lebih besar dari 0.',
                    position: 'topRight'
                });
                $('#qty').focus();
                return;
            }

            if (!transfer_id) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Pilih batch & etalase terlebih dahulu.',
                    position: 'topRight'
                });
                $('#batch_select').focus();
                return;
            }

            const maxAllowedQty = parseFloat(old_qty) || 0;
            const willBeLocked = (parseFloat(qty_retur) >= maxAllowedQty);
            const remainingAfterRetur = Math.max(0, maxAllowedQty - parseFloat(qty_retur));

            const isFallback = (transfer_id === 'fallback');
            const confirmTitle = isFallback ? 'Konfirmasi Retur (Penyesuaian)' : 'Konfirmasi Retur';
            let confirmText = '';

            if (isFallback) {
                confirmText = `PERHATIAN (PENYESUAIAN NON-BATCH):\nObat "${medicine_name}" diproses sebagai penyesuaian transaksi sebanyak ${qty_retur} item.\n` +
                    (willBeLocked
                        ? `Seluruh sisa quantity obat ini akan habis diretur dan akan DIKUNCI.`
                        : `Sisa ${remainingAfterRetur} item masih dapat diretur kembali nanti jika diperlukan.`) +
                    `\n\nLanjutkan proses retur?`;
            } else {
                confirmText = `Konfirmasi retur obat "${medicine_name}" sebanyak ${qty_retur} item.\n` +
                    (willBeLocked
                        ? `Seluruh sisa quantity obat ini akan habis diretur dan akan DIKUNCI pada transaksi ini.`
                        : `Sisa ${remainingAfterRetur} item masih dapat diretur kembali nanti jika diperlukan.`) +
                    `\n\nApakah Anda yakin ingin memproses retur ini?`;
            }

            // Peringatan konfirmasi retur dan penguncian
            swal({
                title: confirmTitle,
                text: confirmText,
                icon: 'warning',
                buttons: {
                    cancel: {
                        text: 'Batal',
                        value: null,
                        visible: true,
                        className: 'btn btn-secondary',
                        closeModal: true,
                    },
                    confirm: {
                        text: willBeLocked ? 'Ya, Retur & Kunci' : 'Ya, Proses Retur',
                        value: true,
                        visible: true,
                        className: willBeLocked ? 'btn btn-danger' : 'btn btn-primary',
                        closeModal: true
                    }
                },
                dangerMode: willBeLocked,
            }).then(function(confirmed) {
                if (!confirmed) return;

                const btn = document.getElementById('submitBtn');
                btn.disabled = true;
                btn.innerHTML = `
                    <svg class="w-3.5 h-3.5 animate-spin" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="8" cy="8" r="6" stroke-dasharray="28" stroke-dashoffset="10"/>
                    </svg>
                    Menyimpan...
                `;

                axios.post('{{ route('returdata.returItem') }}', {
                        medicine_id,
                        cart_id,
                        transaction_id,
                        qty_retur,
                        total_retur,
                        transfer_id,
                        medicine_code,
                        old_qty,
                        _token: '{{ csrf_token() }}'
                    })
                    .then(response => {
                        iziToast.success({
                            title: 'Berhasil',
                            message: response.data?.message || 'Retur obat berhasil disimpan.',
                            position: 'topRight'
                        });

                        // Update retur code display if returned from server
                        if (response.data.retur_code) {
                            $('#retur_code_display').val(response.data.retur_code);
                        }

                        // Reset form but keep transaction selected so user can retur next item
                        resetReturForm();

                        // Reload medicine table to reflect updated data
                        medicineTable?.ajax.reload(null, false);
                    })
                    .catch(error => {
                        const message = error.response?.data?.message || 'Terjadi kesalahan. Coba lagi.';
                        iziToast.error({
                            title: 'Gagal Retur',
                            message,
                            position: 'topRight'
                        });
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = `
                            <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="2 9 6 13 14 4" />
                            </svg>
                            Simpan
                        `;
                    });
            });
        }

        // ─── DOM-dependent code ───────────────────────────────────────────────────────
        $(document).ready(function() {

            // Init Enter key navigation
            initEnterNavigation();

            // Keyboard nav on search input
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                const rows = document.querySelectorAll('#searchResults tr');
                const dropdown = document.getElementById('searchDropdown');

                if (dropdown.style.display === 'none' || !rows.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, rows.length - 1);
                    updateActiveRow(rows);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    updateActiveRow(rows);
                } else if (e.key === 'Enter' && activeIndex >= 0) {
                    e.preventDefault();
                    selectRow(rows[activeIndex]);
                }
            });

            // Hover highlight
            document.getElementById('searchResults').addEventListener('mouseover', function(e) {
                const row = e.target.closest('tr');
                if (!row) return;
                const rows = [...this.children];
                rows.forEach(r => r.classList.remove('active'));
                row.classList.add('active');
                activeIndex = rows.indexOf(row);
            });

            // Click to select
            document.getElementById('searchResults').addEventListener('click', function(e) {
                const row = e.target.closest('tr');
                if (row) {
                    selectRow(row);
                    e.stopPropagation();
                }
            });

            // Close on outside click
            document.addEventListener('click', function(e) {
                const searchInput = document.getElementById('searchInput');
                const dropdown = document.getElementById('searchDropdown');
                if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                    hideDropdown();
                }
            });

            // Back button
            $('#back').on('click', () => window.location.href = "{{ route('home') }}");

            // DataTable
            medicineTable = $('#medicineTable').DataTable({
                processing: true,
                paging: true,
                searching: true,
                ordering: false,
                info: true,
                lengthChange: true,
                autoWidth: false,
                deferLoading: 0,
                ajax: {
                    url: '{{ route('returdata.medicines') }}',
                    data: d => {
                        d.transaction_code = selectedTransactionCode;
                    },
                    dataSrc: ''
                },

                columns: [{
                        data: null,
                        render: (d, t, r, m) => m.row + 1
                    },
                    {
                        data: 'medicine.name',
                        render: function(data, type, row) {
                            if (row.is_locked) {
                                return `
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-gray-400 line-through select-none">${data || '—'}</span>
                                        <span class="badge-locked" title="Seluruh quantity obat ini sudah diretur penuh dan dikunci">
                                            <svg class="w-3 h-3 text-red-500 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                            </svg>
                                            Sudah Diretur Penuh
                                        </span>
                                    </div>
                                `;
                            }
                            if (row.returned_qty > 0) {
                                return `
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-gray-800">${data || '—'}</span>
                                        <span class="badge-partial" title="Sebagian quantity obat ini sudah diretur">
                                            Diretur ${row.returned_qty}
                                        </span>
                                    </div>
                                `;
                            }
                            return `<span class="font-medium text-gray-800">${data || '—'}</span>`;
                        }
                    },
                    {
                        data: 'quantity',
                        className: 'text-end',
                        render: function(d, t, r) {
                            if (r.is_locked) {
                                return `<span class="text-gray-400 font-medium">0 / ${d}</span>`;
                            }
                            if (r.returned_qty > 0) {
                                return `<span class="font-bold text-blue-600">${r.remaining_qty}</span> <span class="text-[11px] text-gray-400">/ ${d}</span>`;
                            }
                            return `<span class="font-medium text-gray-800">${d}</span>`;
                        }
                    },
                    {
                        data: 'final_price',
                        className: 'text-end',
                        render: function(d, t, r) {
                            if (r.is_locked) {
                                return `<span class="text-gray-400">Rp 0</span>`;
                            }
                            const val = (r.returned_qty > 0) ? r.remaining_price : r.final_price;
                            const formatted = new Intl.NumberFormat('id-ID').format(Math.round(val || 0));
                            return `<span class="font-medium text-gray-800">Rp ${formatted}</span>`;
                        }
                    },
                ],

                createdRow: function(row, data, dataIndex) {
                    if (data.is_locked) {
                        $(row).addClass('row-locked text-gray-400 select-none');
                    }
                },

                drawCallback: function() {
                    const api = this.api();
                    const rows = api.rows().data().toArray();
                    const count = rows.length;
                    document.getElementById('itemCount').textContent = count + ' item';

                    const allLocked = count > 0 && rows.every(r => r.is_locked);
                    const banner = document.getElementById('allLockedBanner');
                    const badge = document.getElementById('transStatusBadge');

                    if (allLocked) {
                        banner?.classList.remove('hidden');
                        badge?.classList.remove('hidden');
                        $('#submitBtn').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                    } else {
                        banner?.classList.add('hidden');
                        badge?.classList.add('hidden');
                        $('#submitBtn').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    }
                }
            });

            function loadMedicineForRetur(data) {
                $('#medicine_code').val(data.medicine.code);
                $('#medicine_id').val(data.medicine.id);
                $('#medicine_name').val(data.medicine.name);
                $('#transaction_id').val(data.transaction_id);
                $('#unit').val(data.medicine.unit);
                $('#price').val(data.remaining_price);
                $('#item_price').val(data.item_price);
                $('#old_qty').val(data.remaining_qty);
                $('#cart_id').val(data.id);

                // Set input qty limits & hint
                $('#qty').val('').attr('max', data.remaining_qty).attr('placeholder', 'Maks: ' + data.remaining_qty);
                if (data.returned_qty > 0) {
                    $('#qty_max_hint').html('<span class="text-amber-600 font-medium">Sisa dapat diretur: ' + data.remaining_qty + ' ' + (data.medicine.unit || '') + ' (Sudah diretur: ' + data.returned_qty + ')</span>');
                } else {
                    $('#qty_max_hint').html('<span class="text-gray-500">Maksimal retur: ' + data.remaining_qty + ' ' + (data.medicine.unit || '') + '</span>');
                }

                // Load batches
                loadBatches(data.medicine.id, data.transaction_id);
                $('#qty').trigger('focus');
            }

            $('#medicineTable tbody').on('click', 'tr', function() {
                const data = medicineTable.row(this).data();
                if (!data) return;

                if (data.is_locked) {
                    iziToast.warning({
                        title: 'Terkunci',
                        message: 'Seluruh quantity obat "' + (data.medicine?.name || 'ini') + '" pada transaksi ini sudah diretur dan telah dikunci.',
                        position: 'topRight'
                    });
                    return;
                }

                $('#medicineTable tbody tr').removeClass('active');
                $(this).addClass('active');
                loadMedicineForRetur(data);
            });
        });



        let batchRequestSeq = 0;

        function appendFallbackBatchOption(select) {
            const option = document.createElement('option');
            option.value = 'fallback';
            option.textContent = '⚠️ [PENYESUAIAN] NON-BATCH (Penyesuaian Salah Input) — (Retur Finansial)';
            option.classList.add('text-amber-600', 'font-semibold');
            option.dataset.batchName = 'NON-BATCH (Penyesuaian Salah Input)';
            option.dataset.expiredDate = '-';
            option.dataset.transferId = 'fallback';
            option.dataset.isFallback = '1';
            select.appendChild(option);
        }

        function loadBatches(medicine_id, transaction_id) {
            const select = document.getElementById('batch_select');
            const requestId = ++batchRequestSeq;

            select.innerHTML = '<option value="">Memuat batch...</option>';
            syncBatchFields(); // kosongkan transfer_id lama selama loading

            if (!medicine_id || !transaction_id) {
                select.innerHTML = '<option value="">Data obat/transaksi tidak valid</option>';
                syncBatchFields();
                return;
            }

            const url = `{{ route('returdata.batches') }}?medicine_id=${encodeURIComponent(medicine_id)}&transaction_id=${encodeURIComponent(transaction_id)}`;

            fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(async res => {
                    if (!res.ok) {
                        let msg = `HTTP ${res.status}`;
                        try {
                            const body = await res.json();
                            if (body?.message) msg = body.message;
                        } catch (_) {}
                        throw new Error(msg);
                    }
                    return res.json();
                })
                .then(data => {
                    // Abaikan respons basi (user sudah klik obat lain)
                    if (requestId !== batchRequestSeq) return;

                    if (!Array.isArray(data) || !data.length) {
                        select.innerHTML = '<option value="">Tidak ada batch tersedia</option>';
                        syncBatchFields();
                        return;
                    }

                    select.innerHTML = '<option value="">— Pilih batch/etalase —</option>';

                    data.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.transfer_id;
                        if (item.is_fallback) {
                            option.textContent = `⚠️ [PENYESUAIAN] ${item.batch_name} — (${item.etalase_name})`;
                            option.classList.add('text-amber-600', 'font-semibold');
                        } else {
                            option.textContent =
                                `${item.batch_name} — Exp: ${item.expired_date} (Stok: ${item.counter_stock}) (Etalase: ${item.etalase_name})`;
                        }

                        option.dataset.batchName = item.batch_name;
                        option.dataset.expiredDate = item.expired_date;
                        option.dataset.transferId = item.transfer_id;
                        option.dataset.isFallback = item.is_fallback ? '1' : '0';

                        select.appendChild(option);
                    });

                    // Auto-select first (FEFO — already ordered by earliest expiry)
                    select.selectedIndex = 1;
                    syncBatchFields();
                })
                .catch(err => {
                    if (requestId !== batchRequestSeq) return;
                    console.error('Gagal memuat batch:', err);

                    // Jangan blokir kasir: sediakan opsi penyesuaian, tapi TIDAK dipilih otomatis
                    select.innerHTML = '<option value="">⚠ Gagal memuat batch — pilih penyesuaian di bawah</option>';
                    appendFallbackBatchOption(select);
                    select.selectedIndex = 0;
                    syncBatchFields();

                    iziToast.warning({
                        title: 'Batch gagal dimuat',
                        message: 'Data batch tidak dapat dimuat (' + (err?.message || 'error') + '). Anda tetap bisa memilih opsi PENYESUAIAN.',
                        position: 'topRight'
                    });
                });
        }

        // Sync hidden fields whenever select changes
        function syncBatchFields() {
            const select = document.getElementById('batch_select');
            const opt = select.options[select.selectedIndex];

            if (!opt || !opt.value) {
                $('#batch').val('');
                $('#expired_date').val('');
                $('#transfer_id').val('');
                return;
            }

            $('#batch').val(opt.dataset.batchName);
            $('#expired_date').val(opt.dataset.expiredDate);
            $('#transfer_id').val(opt.dataset.transferId);
        }

        // Attach change listener
        document.getElementById('batch_select').addEventListener('change', syncBatchFields);
    </script>
@endsection
