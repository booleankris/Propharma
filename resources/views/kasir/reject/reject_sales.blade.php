@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <style>
        .dropdown-table {
            width: 100%;
            position: absolute;
            z-index: 999999;
            margin-top: 0;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
            border: 1px solid #e5e7eb;
            max-height: 320px;
            overflow-y: auto;
            display: none;
            z-index: 9999;
        }

        .dropdown-table tbody tr.active {
            background-color: #dbeafe;
        }

        .dropdown-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
        }

        .dropdown-table thead th {
            position: sticky;
            top: 0;
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: .04em;
        }

        .dropdown-table tbody tr {
            transition: background-color .15s ease, transform .05s ease;
            cursor: pointer;
        }

        .dropdown-table tbody tr:hover {
            background-color: #f3f4f6;
        }

        .dropdown-table tbody tr:active {
            transform: scale(0.995);
        }

        .dropdown-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #111827;
            vertical-align: middle;
        }

        .dropdown-table tbody tr:last-child td {
            border-bottom: none;
        }

        .dropdown-table td:first-child {
            width: 40px;
            color: #6b7280;
            font-size: 13px;
        }

        .dropdown-table td:nth-child(4) {
            font-weight: 600;
            color: #16a34a;
        }

        .dropdown-table td:last-child {
            color: #6b7280;
            font-size: 13px;
        }

        .dropdown-table .empty-row {
            text-align: center;
            padding: 16px;
            color: #9ca3af;
            font-style: italic;
        }


        .dataTables_wrapper .top {
            font-family: "Poppins";
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter {
            display: block !important;
        }

        .dataTables_filter label {
            font-weight: 600 !important;
        }

        .dataTables_filter input {
            width: 260px !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            outline: none !important;
        }

        .dataTables_length {
            display: block !important;
        }

        .dataTables_length select {
            padding: 4px 23px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }


        #medicineTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
        }


        #medicineTable tbody td {
            padding: 12px 10px !important;
            font-size: 14px !important;
            vertical-align: middle !important;
        }

        #medicineTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        #orderItemsTable tr.selected {
            background-color: #e0f2fe !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 6px 12px !important;
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

            <div class="relative w-full p-[24px] bg-[#ffffff] rounded-[22px]">
                <div id="searchWrapper" class="flex gap-5" style="position: relative; width: 100%;">
                    <div class="w-11/12">
                        <div class="flex items-center justify-between mb-4">
                            <h1 class="text-2xl font-semibold tracking-tight font-poppins text-[#1c1c1c]">Penolakan Barang
                            </h1>

                            <!-- Export Controls -->
                            <div class="flex items-end gap-3">
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Mulai Tanggal</label>
                                    <input type="date" id="export_start_date"
                                        class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 outline-none focus:border-blue-500 transition">
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Sampai Tanggal</label>
                                    <input type="date" id="export_end_date"
                                        class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 outline-none focus:border-blue-500 transition">
                                </div>
                                <div>
                                    <button type="button" id="export-reject-btn"
                                        class="flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition shadow-sm h-[32px]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24"
                                            stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                            <path
                                                d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                            <path d="M8 11h8v7h-8z" />
                                            <path d="M8 15h8" />
                                            <path d="M11 11v7" />
                                        </svg>
                                        <span>Export Excel</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Progress Bar --}}
                        <div id="progressContainer"
                            class="hidden mb-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm w-full">
                            <p class="font-semibold text-slate-700 mb-2 text-sm">Export Progress</p>
                            <div class="w-full bg-slate-200 rounded-full h-3">
                                <div id="progressBar" class="h-3 bg-emerald-500 rounded-full transition-all duration-300"
                                    style="width: 0%;"></div>
                            </div>
                            <p id="progressText" class="text-xs mt-2 text-slate-500 font-medium">0%</p>
                        </div>
                        <div class="flex py-2 gap-1">
                            <div>
                                <div class="py-1 text-[13px] font-bold">Tanggal Penolakan</div>
                                <input type="text" id="reject_date"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $now }}" readonly
                                    onkeyup="searchMedicineData(this.value)" autocomplete="off">
                            </div>
                            <div>
                                <div class="py-1 text-[13px] font-bold" for="reject_number">Nomor Penolakan</div>
                                <input type="text" id="reject_number"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $rejection_code }}" readonly
                                    onkeyup="searchMedicineData(this.value)" autocomplete="off">
                            </div>
                        </div>
                        <div class="relative flex items-center gap-2">
                            <div class="relative flex-1">
                                <input autofocus type="text" id="searchInput"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Cari Obat..." oninput="searchMedicineData(this.value)" autocomplete="off">

                                <div id="searchDropdown" class="dropdown-table" style="display:none;">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Code</th>
                                                <th>Name Pelanggan</th>
                                                <th>Total</th>
                                            </tr>
                                        </thead>
                                    </table>
                                    <div id="tableScroll" style="max-height: 250px; overflow-y: auto;"
                                        onscroll="handleScroll()">
                                        <table class="table table-sm table-bordered mb-0">
                                            <tbody id="searchResults"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <button type="button" id="customMedicineBtn" onclick="toggleCustomMedicine()"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 w-full sm:w-auto"
                                title="Tambah Obat Custom">

                                <svg id="customMedicineIcon" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 5v14" />
                                    <path d="M5 12h14" />
                                </svg>
                                <span>Custom Obat</span>
                            </button>
                        </div>

                        <div class="flex flex-wrap gap-3 py-2 w-full">

                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">Kode Obat</div>
                                <input id="medicine_code" readonly
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Kode Obat">
                            </div>

                            <div class="flex-1 min-w-[200px]">
                                <div class="py-1 text-[13px] font-bold">Nama Obat</div>
                                <input id="medicine_name" readonly
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Nama Obat (pilih dari pencarian)">
                            </div>

                            <div class="w-full sm:w-32">
                                <div class="py-1 text-[13px] font-bold">Satuan</div>
                                <input id="unit"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Satuan">
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 py-2 w-full">
                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">QTY Tolak</div>
                                <input id="qty" type="number" name="qty"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="QTY Tolak" onkeyup="counttotal()">
                            </div>
                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">Alasan Penolakan</div>
                                <select
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    name="reason" id="reason">
                                    <option selected value="">-- Pilih Alasan --</option>
                                    <option value="Stok Kurang">Stok Kurang</option>
                                    <option value="⁠Kosong Distributor">⁠Kosong Distributor</option>
                                    <option value="Tidak Ada / Habis">Tidak Ada / Habis</option>
                                    <option value="Belum pernah tersedia">Belum pernah tersedia</option>
                                    <option value="Harga">Harga</option>
                                    <option value="Pelayanan">Pelayanan</option>
                                    <option value="Hanya Bertanya">Hanya Bertanya</option>
                                    <option value="Wajib Resep">Wajib Resep</option>
                                    <option value="Lain-lain">Lain-lain</option>
                                </select>
                            </div>
                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">Hrg HNA</div>
                                <input id="item_price" readonly
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Harga Satuan">
                            </div>

                            <div class="flex-1 min-w-[200px]">
                                <div class="py-1 text-[13px] font-bold">Jumlah</div>
                                <input id="total" readonly name="total"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                            </div>

                        </div>
                        <div>
                            <div class="flex items-center gap-3 mt-4">
                                <div class="flex gap-2 items-center flex-wrap">
                                    <div>
                                        <button onclick="submit_data()"
                                            class="inline-flex items-center gap-2 rounded-lg font-poppins btn-pharma !bg-blue-600 !shadow-[0_2px_6px_#2563eb] px-6 py-4 text-sm font-xl text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" class="w-5 h-5" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                                <polyline points="17 21 17 13 7 13 7 21" />
                                                <polyline points="7 3 7 8 15 8" />
                                            </svg>
                                            Simpan
                                        </button>
                                    </div>
                                    <div>
                                        <button id="back"
                                            class="inline-flex items-center gap-2 rounded-lg font-poppins 
                                                               px-6 py-4 text-sm font-semibold 
                                                               transition-all hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2"
                                            style="box-shadow: 0 0px 7px -1px #1770ec; background: transparent; color: #2aa0ff;">

                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" class="w-5 h-5" style="color: #2aa0ff;">
                                                <path d="M15 18l-6-6 6-6" />
                                            </svg>

                                            Kembali
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 relative w-full p-[24px] bg-[#ffffff] rounded-[22px]">
                    <table id="orderItemsTable" class="w-full">
                        <thead>
                            <tr>
                                <th>Kode Penolakan</th>
                                <th>Tanggal</th>
                                <th>Nama Item</th>
                                <th>Jumlah Ditolak</th>
                                <th>Total</th>
                                <th>Alasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-[11px]"></tbody>
                    </table>
                    <div class="flex justify-end w-full shadow-[0_0_20px_rgba(0,0,0,0.2)] bg-white fixed left-0 bottom-0">
                        <div class="p-4 rounded-t-2xl gap-2 flex">
                            <div class="flex items-center">
                                <p class="font-bold pr-2 font-poppins">TOTAL DITOLAK</p>

                                <input id="d_total" readonly
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Input">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end w-full shadow-[0_0_20px_rgba(0,0,0,0.2)] bg-white fixed left-0 bottom-0">
                    <div class="p-4 rounded-t-2xl gap-2 flex">
                        <div class="flex items-center">
                            <p class="font-bold pr-2 font-poppins">HARGA HNA</p>
                            <input id="d_price" readonly
                                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                placeholder="Input">
                        </div>
                        <div class="flex items-center">
                            <p class="font-bold pr-2 font-poppins">PPN</p>
                            <input id="d_ppn" readonly
                                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                placeholder="Input">
                        </div>
                        <div class="flex items-center">
                            <p class="font-bold pr-2 font-poppins">TOTAL</p>

                            <input id="d_total" readonly
                                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                placeholder="Input">
                        </div>
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
        let page = 1;
        let keyword = '';
        let loading = false;
        let hasMore = true;
        let activeIndex = -1;
        let selectedTransactionCode = null;
        let medicineTable = null;
        let medicineSelectedId = '';
        let itemcode = '';
        let itemprice = '';
        let itemcontent = '';
        let itemqty = '';
        let itemtotal = '';
        let total_transaction = '';
        let itempack = '0';
        var itemcreditor = '';
        var date = @json($now);


        var pack = document.getElementById('pack');
        let orderItemsTable;
        let selectedRowData = null;
        let selectedRowIndex = null;
        let d_total = {{ $d_total }};
        let rejection_code = @json($rejection_code);
        let isCustomMode = false;
        let editingId = null;
        // Setting Initial Transaction Value

        $('#d_total').val(formatRupiah(d_total));



        // Datatable
        document.addEventListener('DOMContentLoaded', function() {
            exitCustomMode();

            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('sales.getreject') }}",
                },
                columns: [{
                        data: 'code',
                        name: 'code'
                    },
                    {
                        data: 'date',
                        name: 'date',
                        defaultContent: '-'
                    },
                    {
                        data: null,
                        name: 'medicine_name',
                        render: function(data, type, row) {
                            // If standard medicine exists, show it. Otherwise, show custom name.
                            return row.medicines ? row.medicines.name : (row.medicine_name || '-');
                        }
                    },
                    {
                        data: 'quantity',
                        name: 'quantity',
                        render: function(data, type, row) {
                            const unitStr = row.unit || (row.medicines ? row.medicines.unit : '');
                            return data + (unitStr ? ' ' + unitStr : '');
                        }
                    },
                    {
                        data: 'total',
                        name: 'total',
                        render: function(data, type, row) {
                            return data ? data : 'Rp. 0'; // Handle visual for 0/null
                        }
                    },
                    {
                        data: 'reason',
                        name: 'reason'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    },
                ],
                paging: false,
                searching: false,
                info: false,
            });
        });
        document.getElementById('qty').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            document.getElementById('reason').focus();
        });
        document.getElementById('reason').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            addItem();
        });


        function empyCreditorOption() {
            let select = $("#creditor");
            select.empty();
        }

        function submit_data() {

            addItem();

        }

        function formatRupiah(value) {
            const number = Number(value) || 0;
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: 0
            }).format(number);
        }

        function searchMedicineData(value) {
            keyword = value.trim();
            page = 1;
            hasMore = true;
            activeIndex = -1;

            const tbody = document.getElementById('searchResults');
            tbody.innerHTML = '';
            clearFormFields();

            if (keyword.length < 1) {
                document.getElementById('searchDropdown').style.display = 'none';
                return;
            }

            fetchData();
        }

        // Fetch   
        function fetchData() {
            if (loading || !hasMore) return;

            loading = true;

            fetch(`{{ route('sales.searchmedicine') }}?search=${keyword}&page=${page}`)
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('searchResults');

                    if (page === 1 && res.data.length === 0) {
                        tbody.innerHTML = `
                                        <tr>
                                            <td colspan="4" class="text-center">No data found</td>
                                        </tr>`;
                        hasMore = false;
                        return;
                    }

                    res.data.forEach((item, index) => {
                        tbody.insertAdjacentHTML('beforeend', `
                                        <tr 
                                            data-item='${JSON.stringify(item)}'
                                            tabindex="0"
                                        >
                                            <td>${((page - 1) * res.per_page) + index + 1}</td>
                                            <td>${item.name}</td>
                                        </tr>
                                    `);
                    });

                    hasMore = res.current_page < res.last_page;
                    page++;

                    document.getElementById('searchDropdown').style.display = 'block';
                })
                .finally(() => loading = false);
        }

        // Scroll
        function handleScroll() {
            const container = document.getElementById('tableScroll');
            if (container.scrollTop + container.clientHeight >= container.scrollHeight - 5) {
                fetchData();
            }
        }

        function selectRow(row) {
            const item = JSON.parse(row.dataset.item);
            itemprice = item.raw_price;
            itemcontent = item.content;
            itemcreditor = item.creditors_id;
            itemcode = item.id;
            medicineSelectedId = item.id;
            console.log('Obat id' + medicineSelectedId);
            document.getElementById('medicine_code').value = item.code ?? '';
            document.getElementById('medicine_name').value = item.name ?? '';
            document.getElementById('unit').value = item.unit ?? '';
            document.getElementById('item_price').value = formatRupiah(item.raw_price ?? 0);
            document.getElementById('searchDropdown').style.display = 'none';
            document.getElementById('searchInput').value = "";

            document.getElementById('qty')?.focus();
        }
        // Nav
        document.addEventListener('keydown', function(e) {
            const dropdown = document.getElementById('searchDropdown');
            if (!dropdown || dropdown.offsetParent === null) return;

            const rows = document.querySelectorAll('#searchResults tr');
            if (!rows.length) return;

            // allow arrow navigation even when input is focused
            if (['ArrowDown', 'ArrowUp', 'Enter'].includes(e.key)) {
                e.preventDefault();
            }

            if (e.key === 'ArrowDown') {
                activeIndex = Math.min(activeIndex + 1, rows.length - 1);
                updateActiveRow(rows);
            }

            if (e.key === 'ArrowUp') {
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActiveRow(rows);
            }

            if (e.key === 'Enter' && activeIndex >= 0) {
                selectRow(rows[activeIndex]);
            }
        });

        function updateActiveRow(rows) {
            rows.forEach(r => r.classList.remove('active'));
            if (activeIndex >= 0) {
                rows[activeIndex].classList.add('active');
                rows[activeIndex].scrollIntoView({
                    block: 'nearest'
                });
            }
        }
        document.addEventListener('click', function(e) {
            const wrapper = document.getElementById('searchWrapper');
            if (!wrapper.contains(e.target)) {
                document.getElementById('searchDropdown').style.display = 'none';
            }
        }, true);


        function calculateReturTotal() {
            const oldQty = parseFloat(document.getElementById('old_qty').value) || 0;
            const returQty = parseFloat(document.getElementById('qty').value) || 0;

            // item_price may contain formatting, strip non-numeric
            const priceRaw = document.getElementById('item_price').value || '0';
            const itemPrice = parseFloat(priceRaw.replace(/[^\d.-]/g, '')) || 0;



            const total = (oldQty - returQty) * itemPrice;

            document.getElementById('total_retur').value = total.toFixed(0);
        }
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

        // Select

        document.addEventListener('click', function(e) {
            const wrapper = document.getElementById('searchWrapper');
            if (!wrapper.contains(e.target)) {
                document.getElementById('searchDropdown').style.display = 'none';
            }
        }, true);



        function counttotal() {
            let qty = parseFloat(document.getElementById('qty').value) || 0;
            let price = parseFloat(itemprice) || 0;

            itemqty = qty;
            itemtotal = qty * price;

            document.getElementById('total').value = formatRupiah(itemtotal);
        }

        function clearFormFields() {
            document.getElementById('medicine_code').value = '';
            document.getElementById('medicine_name').value = '';
            document.getElementById('unit').value = '';
            document.getElementById('qty').value = '';
            document.getElementById('total').value = '';
            document.getElementById('item_price').value = '';
            document.getElementById('reason').value = '';

            itemcode = '';
            itemprice = '';
            itemqty = '';
            itemtotal = '';
            itemcreditor = null;
            medicineSelectedId = '';
            selectedRowData = null;
            empyCreditorOption();
        }

        function resetInputs() {
            clearFormFields();
            exitCustomMode();
            document.getElementById('searchInput').value = '';
            document.getElementById('searchInput').focus();
        }

        function setPlusIconState(active) {
            const icon = document.getElementById('customMedicineIcon');
            const btn = document.getElementById('customMedicineBtn');

            if (active) {
                // Tabler "x" icon — cancel custom mode
                icon.innerHTML = `
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M18 6l-12 12" />
                        <path d="M6 6l12 12" />
                    `;
                btn.title = 'Batal Mode Custom';
                btn.classList.remove('bg-[#1678df]', 'hover:bg-blue-600');
                btn.classList.add('bg-red-500', 'hover:bg-red-600');
            } else {
                // Tabler "plus" icon — enter custom mode
                icon.innerHTML = `
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    `;
                btn.title = 'Tambah Obat Custom';
                btn.classList.remove('bg-red-500', 'hover:bg-red-600');
                btn.classList.add('bg-[#1678df]', 'hover:bg-blue-600');
            }
        }

        function enterCustomMode() {
            isCustomMode = true;
            clearFormFields();

            const nameInput = document.getElementById('medicine_name');
            const searchInput = document.getElementById('searchInput');

            nameInput.readOnly = false;
            nameInput.placeholder = 'Ketik Nama Obat Manual';
            nameInput.style.backgroundColor = '#ffffff';
            nameInput.style.cursor = 'text';

            searchInput.value = '';
            searchInput.disabled = true;
            searchInput.placeholder = 'Nonaktif (mode custom)';
            searchInput.style.backgroundColor = '#f3f4f6';

            document.getElementById('searchDropdown').style.display = 'none';

            setPlusIconState(true);
            nameInput.focus();
        }

        function exitCustomMode() {
            isCustomMode = false;

            const nameInput = document.getElementById('medicine_name');
            const searchInput = document.getElementById('searchInput');

            nameInput.readOnly = true;
            nameInput.placeholder = 'Nama Obat (pilih dari pencarian)';
            nameInput.style.backgroundColor = '#f3f4f6';
            nameInput.style.cursor = 'not-allowed';

            searchInput.disabled = false;
            searchInput.placeholder = 'Cari Obat...';
            searchInput.style.backgroundColor = '#ffffff';

            setPlusIconState(false);
        }

        function toggleCustomMedicine() {
            if (isCustomMode) {
                exitCustomMode();
                clearFormFields();
                document.getElementById('searchInput').focus();
            } else {
                enterCustomMode();
            }
        }

        function editReject(id) {
            const row = orderItemsTable.rows().data().toArray().find(r => r.id === id);
            if (!row) return;

            editingId = id;
            medicineSelectedId = row.medicine_id || '';
            itemcode = row.medicine_id || '';
            itemprice = row.raw_price || 0;

            document.getElementById('medicine_code').value = row.medicines?.code ?? '';
            document.getElementById('medicine_name').value = row.medicines ? row.medicines.name : (row.medicine_name || '');
            document.getElementById('unit').value = row.unit || (row.medicines ? row.medicines.unit : '');
            document.getElementById('item_price').value = formatRupiah(row.raw_price || 0);
            document.getElementById('qty').value = row.quantity ?? '';
            document.getElementById('total').value = formatRupiah(row.raw_total || 0);
            document.getElementById('reason').value = row.reason ?? '';

            document.getElementById('medicine_name').scrollIntoView({
                block: 'center',
                behavior: 'smooth'
            });
            document.getElementById('qty').focus();

            iziToast.info({
                title: 'Edit Mode',
                position: 'topRight',
                message: 'Ubah data lalu klik Simpan untuk memperbarui.',
            });
        }

        function deleteReject(id) {
            Swal.fire({
                title: 'Hapus Penolakan?',
                text: 'Data ini akan dihapus permanen.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#e11d48',
            }).then((result) => {
                if (!result.isConfirmed) return;

                axios.delete(`{{ url('reject/deleteitemreject') }}/${id}`, {
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(res => {
                        if (res.data.success) {
                            orderItemsTable.ajax.reload(null, false);
                            d_total = res.data.total;
                            $('#d_total').val(formatRupiah(d_total));
                            iziToast.success({
                                title: 'Berhasil',
                                position: 'topRight',
                                message: 'Penolakan berhasil dihapus.',
                            });
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        iziToast.error({
                            title: 'Gagal',
                            position: 'topRight',
                            message: 'Gagal menghapus data.'
                        });
                    });
            });
        }

        function addItem() {
            const payload = {
                code: rejection_code,
                date: date,
                medicine_id: medicineSelectedId || null,
                medicine_name: document.getElementById('medicine_name').value,
                quantity: itemqty,
                unit: document.getElementById('unit').value,
                total: itemtotal || 0, // raw number now, not formatted string
                reason: document.getElementById('reason').value,
            };

            const url = editingId ?
                `{{ url('reject/updateitemreject') }}/${editingId}` :
                "{{ route('sales.addItemReject') }}";

            const method = editingId ? 'put' : 'post';

            axios({
                    method: method,
                    url: url,
                    data: payload,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(res => {
                    if (res.data.success) {
                        const wasEditing = editingId !== null;
                        orderItemsTable.ajax.reload(null, false);
                        d_total = res.data.total ?? (d_total + (res.data.summary?.price_item || 0));
                        $('#d_total').val(formatRupiah(d_total));
                        resetInputs();
                        editingId = null;
                        iziToast.success({
                            title: 'Berhasil',
                            position: 'topRight',
                            message: wasEditing ? "Penolakan berhasil diperbarui" :
                                "Penolakan Berhasil Dicatat",
                        });
                    }
                })
                .catch(err => {
                    console.error(err);
                    // Show the actual validation errors instead of a generic alert
                    if (err.response?.data?.errors) {
                        console.log('Validation errors:', err.response.data.errors);
                        const messages = Object.values(err.response.data.errors).flat().join('\n');
                        alert('Isi Form Dengan Benar!\n\n' + messages);
                    } else {
                        alert('Isi Form Dengan Benar!');
                    }
                });
        }

        function completeOrder() {

            axios.post("{{ route('orders.completeOrder') }}", {
                order_id: orderid,
            }, {
                headers: {
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .content
                }
            }).then(res => {
                if (res.data.success) {
                    orderItemsTable.ajax.reload(null, false);
                    resetInputs();
                    selectedRowData = null;
                    selectedRowIndex = null;
                    location.reload();

                }
            }).catch(err => {
                console.error(err);
                alert('Update failed');
            });
        }

        function printSPB() {
            window.open(`/orders/${orderid}/printspb`, "_blank");
        }

        function printOrder() {

            const btn = document.getElementById('printorder');
            if (btn.disabled) return;

            btn.disabled = true;

            Swal.fire({
                title: 'Processing...',
                text: 'Sedang menyiapkan file',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                    window.location.href = `/orders/printorder/${orderid}`;
                }
            });

            setTimeout(() => {
                Swal.close();
                btn.disabled = false;
            }, 4000);
        }
        document.getElementById('medicine_name').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            document.getElementById('qty').focus();
        });
        $('#back').click(function() {
            window.location.href = "{{ route('home') }}";
        });

        // --- EXPORT QUEUE LOGIC ---
        document.getElementById('export-reject-btn').addEventListener('click', async function(e) {
            e.preventDefault();

            const startDate = document.getElementById('export_start_date').value;
            const endDate = document.getElementById('export_end_date').value;

            const exportBtn = this;
            const originalContent = exportBtn.innerHTML;

            exportBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Menyiapkan...</span>
            `;
            exportBtn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');

            let queryParams = new URLSearchParams();
            if (startDate) queryParams.append('start_date', startDate);
            if (endDate) queryParams.append('end_date', endDate);

            try {
                const response = await fetch(`{{ route('sales.reject.export') }}?${queryParams.toString()}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Gagal memulai export');
                const data = await response.json();

                if (data.job_id) {
                    iziToast.info({
                        title: 'Memproses',
                        message: 'Export sedang disiapkan...',
                        position: 'topRight'
                    });
                    document.getElementById('progressContainer').classList.remove('hidden');
                    pollRejectExportStatus(data.job_id);
                }
            } catch (error) {
                iziToast.error({
                    title: 'Gagal',
                    message: 'Terjadi kesalahan saat memulai export.',
                    position: 'topRight'
                });
                resetRejectExportButton();
            }
        });

        function resetRejectExportButton() {
            const exportBtn = document.getElementById('export-reject-btn');
            exportBtn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                    <path d="M8 11h8v7h-8z" />
                    <path d="M8 15h8" />
                    <path d="M11 11v7" />
                </svg>
                <span>Export Excel</span>
            `;
            exportBtn.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
        }

        function pollRejectExportStatus(jobId) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');

            let interval = setInterval(() => {
                fetch(`/reject/export/status/${jobId}`)
                    .then(res => res.json())
                    .then(data => {
                        progressBar.style.width = data.progress + "%";
                        progressText.innerText = data.progress + "%";

                        if (data.status === "completed") {
                            clearInterval(interval);
                            iziToast.success({
                                title: 'Selesai',
                                message: 'File Excel siap diunduh!',
                                position: 'topRight'
                            });
                            document.getElementById('progressContainer').classList.add('hidden');
                            progressBar.style.width = "0%";
                            progressText.innerText = "0%";
                            resetRejectExportButton();

                            // Trigger download
                            window.location.href = data.file;
                        } else if (data.status === "failed") {
                            clearInterval(interval);
                            iziToast.error({
                                title: 'Gagal',
                                message: 'Gagal men-generate file Excel.',
                                position: 'topRight'
                            });
                            document.getElementById('progressContainer').classList.add('hidden');
                            resetRejectExportButton();
                        }
                    })
                    .catch(err => {
                        clearInterval(interval);
                        document.getElementById('progressContainer').classList.add('hidden');
                        resetRejectExportButton();
                    });
            }, 2000);
        }

        document.getElementById('export_start_date').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            document.getElementById('export_end_date').focus();
        });

        document.getElementById('export_end_date').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            document.getElementById('export-reject-btn').click();
        });
    </script>


@endsection
