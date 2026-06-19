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

        /* DataTable overrides */
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter input {
            width: 220px !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            outline: none !important;
        }

        .dataTables_length select {
            padding: 4px 8px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }

        #medicineTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
            padding: 10px !important;
        }

        #medicineTable tbody td {
            padding: 11px 10px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
        }

        #medicineTable tbody tr {
            cursor: pointer;
        }

        #medicineTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        #medicineTable tbody tr.active {
            background-color: #dbeafe !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 5px 10px !important;
            border-radius: 6px !important;
        }

        .text-end {
            text-align: right !important;
        }

        /* Focus ring for enter-nav inputs */
        .nav-input:focus {
            outline: none;
            ring: 2px solid #3b82f6;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
        }

        /* Submit button loading state */
        #submitBtn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                {{-- LEFT: Form Panel --}}
                <div class="bg-white border border-gray-100 rounded-xl p-6">

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
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Harga resep</label>
                            <input id="price" type="text" readonly placeholder="Rp 0"
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-[12px] font-medium text-gray-500 mb-1">Jumlah retur</label>
                            <input id="total_retur" type="text" readonly placeholder="Rp 0"
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
                        <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest">Item retur</p>
                        <span id="itemCount"
                            class="text-[11px] font-medium bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full">0
                            item</span>
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
            const oldQty = parseFloat(document.getElementById('old_qty').value) || 0;
            const returQty = parseFloat(document.getElementById('qty').value) || 0;
            const itemPrice = parseFloat(
                (document.getElementById('item_price').value || '0').replace(/[^\d.-]/g, '')
            ) || 0;
            document.getElementById('total_retur').value = ((oldQty - returQty) * itemPrice).toFixed(0);
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
            $('#qty').val('');
            $('#total_retur').val('');
            $('#batch').val('');
            $('#expired_date').val('');

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

            if (!qty_retur || qty_retur <= 0) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Qty retur harus diisi.',
                    position: 'topRight'
                });
                $('#qty').focus();
                return;
            }

            if (!transfer_id) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Pilih batch terlebih dahulu.',
                    position: 'topRight'
                });
                $('#batch_select').focus();
                return;
            }

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
                        message: `Retur obat berhasil disimpan.`,
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
                        title: 'Gagal',
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
                        data: 'medicine.name'
                    },
                    {
                        data: 'quantity',
                        className: 'text-end'
                    },
                    {
                        data: 'final_price',
                        className: 'text-end'
                    },
                ],

                drawCallback: function() {
                    const count = this.api().rows().count();
                    document.getElementById('itemCount').textContent = count + ' item';
                }
            });

            function loadMedicineForRetur(data) {
                $('#medicine_code').val(data.medicine.code);
                $('#medicine_id').val(data.medicine.id);
                $('#medicine_name').val(data.medicine.name);
                $('#transaction_id').val(data.transaction_id);
                $('#unit').val(data.medicine.unit);
                $('#price').val(data.final_price);
                $('#item_price').val(data.item_price);
                $('#old_qty').val(data.quantity);
                $('#cart_id').val(data.id);

                // Load batches
                loadBatches(data.medicine.id);
                $('#qty').trigger('focus');
            }

            $('#medicineTable tbody').on('click', 'tr', function() {
                const data = medicineTable.row(this).data();
                if (!data) return;
                $('#medicineTable tbody tr').removeClass('active');
                $(this).addClass('active');
                loadMedicineForRetur(data);
            });
        });



        function loadBatches(medicine_id) {
            const select = document.getElementById('batch_select');
            select.innerHTML = '<option value="">Memuat batch...</option>';

            fetch(`{{ route('returdata.batches') }}?medicine_id=${medicine_id}`)
                .then(res => res.json())
                .then(data => {
                    select.innerHTML = '<option value="">— Pilih batch/etalase —</option>';

                    if (!data.length) {
                        select.innerHTML = '<option value="">Tidak ada batch tersedia</option>';
                        return;
                    }

                    data.forEach((item) => {
                        const option = document.createElement('option');
                        option.value = item.transfer_id;
                        option.textContent =
                            `${item.batch_name} — Exp: ${item.expired_date} (Stok: ${item.counter_stock}) (Etalase: ${item.etalase_name})`;

                        option.dataset.batchName = item.batch_name;
                        option.dataset.expiredDate = item.expired_date;
                        option.dataset.transferId = item.transfer_id;

                        select.appendChild(option);
                    });

                    // Auto-select first (FEFO — already ordered by earliest expiry)
                    select.selectedIndex = 1;
                    syncBatchFields();
                })
                .catch(err => {
                    console.error(err);
                    select.innerHTML = '<option value="">Gagal memuat batch</option>';
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
