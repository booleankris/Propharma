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
            font-size: 11px !important;
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
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

                {{-- LEFT: Form Panel --}}
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

                            {{-- Dropdown --}}
                            <div id="searchDropdown" class="dropdown-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Kode</th>
                                            <th>Nama Supplier</th>
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


                    {{-- Detail Obat --}}
                    <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Detail Obat</p>

                    <form method="POST" action="{{ route('returdata.returorderitems') }}">
                        @csrf
                        <input id="medicine_id" type="hidden" name="medicine_id">
                        <input id="cart_id" type="hidden" name="cart_id">
                        <input id="transaction_id" type="hidden" name="transaction_id">
                        <input id="old_qty" type="hidden" name="old_qty">
                        <input id="content" type="hidden" name="content">

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Kode obat</label>
                                <input id="medicine_code" name="medicine_code" type="text" readonly
                                    placeholder="Kode Obat"
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

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Harga satuan</label>
                                <input id="item_price" type="text" readonly placeholder="Rp 0"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Isi</label>
                                <input id="content_display" type="text" readonly placeholder="—"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                            </div>
                        </div>


                        {{-- Rincian Retur --}}
                        <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest mb-3">Rincian Retur</p>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Qty beli</label>
                                <input id="qty_in" type="number" name="qty_in" required placeholder="0"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            </div>
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Qty retur</label>
                                <input id="qty" type="number" name="qty_retur" required placeholder="0"
                                    oninput="calculateReturTotal()"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-5">
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Harga resep</label>
                                <input id="price" type="text" readonly placeholder="Rp 0"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Jumlah retur</label>
                                <input id="total_retur" type="text" name="total_retur" readonly placeholder="Rp 0"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 mb-5">
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Batch</label>
                                <input id="batch" name="batch" type="text" placeholder="No. batch"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            </div>
                            <div>
                                <label class="block text-[12px] font-medium text-gray-500 mb-1">Exp date</label>
                                <input id="expired_date" type="date" name="expired_date"
                                    class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button type="submit"
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
                    </form>
                </div>

                {{-- RIGHT: Item Table --}}
                <div class="bg-white border border-gray-100 rounded-xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <p class="text-[11px] font-medium text-gray-400 uppercase tracking-widest">Item pembelian</p>
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

            fetch(`{{ route('returdata.returorderdata') }}?search=${keyword}&page=${page}`)
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
            const content = parseFloat(document.getElementById('content').value) || 1;
            const itemPrice = parseFloat(
                (document.getElementById('item_price').value || '0').replace(/[^\d.-]/g, '')
            ) || 0;

            document.getElementById('total_retur').value = ((oldQty - returQty) * itemPrice * content).toFixed(0);
        }

        // ─── DOM-dependent code ───────────────────────────────────────────────────────
        $(document).ready(function() {

            // Keyboard nav
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

            // Hover
            document.getElementById('searchResults').addEventListener('mouseover', function(e) {
                const row = e.target.closest('tr');
                if (!row) return;
                const rows = [...this.children];
                rows.forEach(r => r.classList.remove('active'));
                row.classList.add('active');
                activeIndex = rows.indexOf(row);
            });

            // Click
            document.getElementById('searchResults').addEventListener('click', function(e) {
                const row = e.target.closest('tr');
                if (row) {
                    selectRow(row);
                    e.stopPropagation();
                }
            });

            // Outside click
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
                    url: '{{ route('returdata.ordermedicines') }}',
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
                        data: 'order_items.medicines.name'
                    },
                    {
                        data: 'qty_received',
                        className: 'text-end'
                    },
                    {
                        data: 'total',
                        className: 'text-end'
                    },
                ],
                drawCallback: function() {
                    const count = this.api().rows().count();
                    document.getElementById('itemCount').textContent = count + ' item';
                }
            });

            function loadMedicineForRetur(data) {
                const med = data.order_items.medicines;
                const orderId = data.receiving_details.receiving.id;

                $('#medicine_code').val(med.code);
                $('#medicine_id').val(med.id);
                $('#medicine_name').val(med.name);
                $('#transaction_id').val(orderId);
                $('#unit').val(med.unit);
                $('#price').val(data.total);
                $('#item_price').val(med.raw_price);
                $('#old_qty').val(data.qty_received);
                $('#cart_id').val(data.id);
                $('#qty_in').val(data.qty_received);
                $('#content_display').val(med.content);
                $('#content').val(med.content);
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
    </script>
@endsection
