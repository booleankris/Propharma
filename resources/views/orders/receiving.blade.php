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
            {{-- <div class="flex flex-col lg:flex-row gap-4">

                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Retur Penjualan</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Nomor</th>
                                    <th class="px-4 py-3">Nama Pelanggan/Pasien</th>
                                    <th class="px-4 py-3">Harga</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[50%] mx-auto">
                    <table id="items-table" class="table table-bordered w-full mt-4">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Medicine</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Disc</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                    </table>

                </div>

            </div> --}}
            <div class="relative w-full p-[24px] bg-[#ffffff] rounded-[22px]">
                <div id="searchWrapper" class="flex gap-5" style="position: relative; width: 100%;">
                    <div class="w-11/12">
                        <div class="flex py-2 gap-1">
                            <div>
                                <div class="py-1 text-[13px] font-bold">Tanggal Order</div>
                                <input type="text" id="returdate"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $now }}" readonly
                                    onkeyup="searchMedicineData(this.value)" autocomplete="off">
                            </div>
                            <div>
                                <div class="py-1 text-[13px] font-bold" for="returnumber">Nomor Order</div>
                                <input type="text" id="returnumber"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $receiving_code }}" readonly
                                    onkeyup="searchMedicineData(this.value)" autocomplete="off">
                            </div>
                        </div>

                        <input type="text" id="searchInput"
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
                            <!-- scroll container -->
                            <div id="tableScroll" style="max-height: 250px; overflow-y: auto;" onscroll="handleScroll()">
                                <table class="table table-sm table-bordered mb-0">
                                    <tbody id="searchResults"></tbody>
                                </table>
                            </div>
                        </div>
                        <form method="post" action="{{ route('orders.addItemOrder') }}" class="mt-3">
                            @csrf

                            <div class="flex flex-wrap gap-3 py-2 w-full">
                                {{-- <input type="hidden" name="medicine_id">
                                <input type="hidden" name="order_id">
                                <input type="hidden" name="transaction_id">
                                <input type="hidden" name="total_price">
                                <input type="hidden" name="total_qty"> --}}

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Kode Obat</div>
                                    <input id="medicine_code" readonly
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="OTC">
                                </div>

                                <div class="flex-1 min-w-[200px]">
                                    <div class="py-1 text-[13px] font-bold">Nama Obat</div>
                                    <input id="medicine_name" readonly
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Nama Obat">
                                </div>

                                <div class="w-full sm:w-32">
                                    <div class="py-1 text-[13px] font-bold">Satuan</div>
                                    <input id="unit" readonly
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Satuan">
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 py-2 w-full">
                                <div class="w-full sm:w-32">
                                    <div class="py-1 text-[13px] font-bold">Kemasan</div>
                                    <label class="flex items-center gap-2 mt-2">
                                        <input type="checkbox" id="pack" name="is_active"
                                            class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm">Utuh</span>
                                    </label>
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">QTY BPBA</div>
                                    <input id="qty" type="number" name="qty"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="QTY Retur" oninput="counttotal()">
                                </div>
                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Isi Obat</div>
                                    <input id="content" readonly
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Isi Obat">
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Hrg HNA</div>
                                    <input id="item_price" readonly
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Harga Satuan">
                                </div>

                                <div class="flex-1 min-w-[200px]">
                                    <div class="py-1 text-[13px] font-bold">Jumlah</div>
                                    <input id="total_price" readonly name="total_price"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Jumlah">
                                </div>
                                <div class="flex gap-3 mt-4">
                                    <button onclick="addItem()" type="button" class="btn btn-pharma !bg-[#2196F3] btn-lg">
                                        Simpan
                                    </button>

                                    <button onclick="resetInputs()" type="button" id="back"
                                        class="btn btn-pharma !bg-[#b72929] btn-lg">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
            <div class="mt-3 relative w-full p-[24px] bg-[#ffffff] rounded-[22px]">
                <div class="flex items-center gap-3">
                    <button onclick="completeOrder()"
                        class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-blue-600 !shadow-[0_2px_6px_#2563eb] px-6 py-4 text-sm font-xl text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Buat Baru
                    </button>

                    <button
                        class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-gray-700 !shadow-[0_2px_6px_#374151] px-6 py-4 text-sm font-xl text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 9V4h12v5M6 18h12v-5H6v5zM6 14h12" />
                        </svg>
                        Cetak
                    </button>

                    <button
                        class="inline-flex items-center gap-2 rounded-lg  btn-pharma !bg-emerald-600 !shadow-[0_2px_6px_#079669] px-6 py-4 text-sm font-xl text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="2" y="5" width="20" height="14" rx="2" ry="2" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2 10h20" />
                        </svg>
                        Kreditur
                    </button>

                    <button
                        class="inline-flex items-center gap-2 rounded-lg  btn-pharma !bg-purple-600 !shadow-[0_2px_6px_#9333ea] px-6 py-4 text-sm font-xl text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h10v18l-2-1-2 1-2-1-2 1-2-1V3z" />
                        </svg>
                        SPB
                    </button>
                </div>

                <table id="orderItemsTable" class="w-full">
                    <thead>
                        <tr>
                            <th>Nama Obat</th>
                            <th>Pabrik</th>
                            <th>Satuan</th>
                            <th>Harga</th>
                            <th>Qty</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
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
        var ordercode = @json($receiving_code);
        var orderid = @json($receiving_id);
        let itemcode = '';
        let itemprice = '';
        let itemcontent = '';
        let itemqty = '';
        let itemtotal = '';
        let total_transaction = '';
        let itempack = '0';
        var itemcreditor = '';

        let totalprice = {{ $total }};

        var pack = document.getElementById('pack');
        let orderItemsTable;
        let selectedRowData = null;
        let selectedRowIndex = null;

        document.addEventListener('DOMContentLoaded', function() {
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('orders.orderitems') }}",
                    data: function(d) {
                        d.order_id = orderid;
                    }
                },
                columns: [{
                        data: 'medicines.name',
                        name: 'medicines.name'
                    },
                    {
                        data: 'medicines.factory.name',
                        name: 'medicines.factory.name',
                        defaultContent: '-'

                    },
                    {
                        data: 'medicines.unit',
                        name: 'medicines.unit'
                    },
                    {
                        data: 'item_price',
                        name: 'item_price'
                    },
                    {
                        data: 'quantity',
                        name: 'quantity'
                    },
                    {
                        data: 'item_total',
                        name: 'item_total'
                    },
                ],
                paging: false,
                searching: false,
                info: false,
            });
        });
        $('#orderItemsTable').on('click', 'tbody tr', function() {
            $('#orderItemsTable tbody tr').removeClass('selected');
            $(this).addClass('selected');

            selectedRowData = orderItemsTable.row(this).data();

            console.log('ROW SELECTED:', selectedRowData);
        });

        $('#orderItemsTable tbody').on('dblclick', 'tr', function() {
            const data = orderItemsTable.row(this).data();
            if (!data) return;

            selectedRowIndex = orderItemsTable.row(this).index();
            selectedRowData = data;

            // Fill inputs
            document.getElementById('medicine_name').value = data.medicines.name ?? '';
            document.getElementById('unit').value = data.medicines.unit ?? '';
            document.getElementById('content').value = data.medicines.content ?? '';
            document.getElementById('item_price').value = formatRupiah(data.medicines.raw_price);
            document.getElementById('qty').value = data.quantity;
            document.getElementById('medicine_code').value = data.medicines.code;
            document.getElementById('total_price').value = formatRupiah(data.total);
            if (data.pack == "1") {
                pack.checked = true;
            }
            console.log(data);
            // IMPORTANT: store for update
            itemcode = data.medicine_id;
            itemprice = data.medicines.raw_price;
            itemqty = data.quantity;
            itemcontent = data.medicines.content;
            totalprice = data.total;
            document.getElementById('qty').focus();
        });
        document.getElementById('qty').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();

            if (selectedRowData) {
                updateItem();
            } else {
                addItem(); // your existing function
            }
        });

        function updateItem() {
            const qty = parseInt(document.getElementById('qty').value);
            const total = qty * itemprice;
            axios.post("{{ route('orders.updateOrderItem') }}", {
                order_id: selectedRowData.order_item_id,
                medicine_id: selectedRowData.medicines.id,
                pack: itempack,
                price: itemprice,
                quantity: itemqty,
                total: itemtotal,
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
                }
            }).catch(err => {
                console.error(err);
                alert('Update failed');
            });
        }

        document.addEventListener('keydown', function(e) {
            const isDeleteKey =
                e.key === 'Delete' ||
                e.key === 'Del' ||
                e.key === 'Backspace';

            if (!isDeleteKey) return;
            if (!selectedRowData) return;

            // prevent deleting while typing
            const tag = document.activeElement.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA') return;

            e.preventDefault();

            const name = selectedRowData.medicines?.name ?? 'item';

            if (!confirm(`Hapus item "${name}" ?`)) return;

            axios.post("{{ route('orders.deleteOrderItem') }}", {
                id: selectedRowData.order_item_id
            }, {
                headers: {
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .content
                }
            }).then(res => {
                if (res.data.success) {
                    orderItemsTable.ajax.reload(null, false);
                    selectedRowData = null;
                    selectedRowIndex = null;
                }
            }).catch(err => {
                console.error(err);
                alert('Delete failed');
            });
        });



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
            resetInputs();

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

            fetch(`{{ route('orders.searchmedicine') }}?search=${keyword}&page=${page}`)
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
        function selectRow(row) {
            const item = JSON.parse(row.dataset.item);
            itemprice = item.raw_price;
            itemcontent = item.content;
            itemcreditor = item.creditors_id;
            itemcode = item.id;

            document.getElementById('medicine_code').value = item.code ?? '';
            document.getElementById('medicine_name').value = item.name ?? '';
            document.getElementById('unit').value = item.unit ?? '';
            document.getElementById('content').value = item.content ?? '';
            document.getElementById('item_price').value = formatRupiah(item.raw_price ?? 0);

            document.getElementById('searchDropdown').style.display = 'none';
            document.getElementById('searchInput').value = "";
            document.getElementById('qty')?.focus();

        }

        // Update
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

        pack.addEventListener('change', function() {
            if (this.checked) {
                itempack = 1;
            } else {
                itempack = 0;
            }

            counttotal();
        });

        pack.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('qty').focus();
            }
        })

        function counttotal() {
            let qty = document.getElementById('qty').value;
            itemqty = qty;

            if (pack.checked) {
                itemtotal = qty * itemcontent * itemprice;
                total_transaction += itemtotal;
                document.getElementById('total_price').value = formatRupiah(itemtotal);

            } else {
                itemtotal = qty * itemprice;
                total_transaction += itemtotal;
                document.getElementById('total_price').value = formatRupiah(itemtotal);


            }
        }

        function resetInputs() {
            document.getElementById('medicine_code').value = '';
            document.getElementById('medicine_name').value = '';
            document.getElementById('unit').value = '';
            document.getElementById('qty').value = '';
            document.getElementById('content').value = '';
            document.getElementById('item_price').value = '';
            document.getElementById('total_price').value = '';
            const isActive = document.getElementById('is_active');
            pack.checked = false;

            if (isActive && isActive.checked) {
                isActive.checked = false;
            }

            // reset JS
            itemcode = '';
            itemprice = '';
            itemqty = '';
            itemtotal = '';
            itemcreditor = null;
            selectedRowData = null;
            document.getElementById('searchInput').focus();
        }

        function addItem() {
            const payload = {
                order_id: orderid,
                medicine_id: itemcode,
                creditor_id: itemcreditor ?? null,
                pack: itempack,
                price: itemprice,
                quantity: itemqty,
                total: itemtotal,
            };

            axios.post("{{ route('orders.addItemOrder') }}", payload, {
                    headers: {
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            .content
                    }
                })
                .then(res => {
                    if (res.data.success) {
                        orderItemsTable.ajax.reload(null, false);
                        resetInputs();

                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error adding item');
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
    </script>


@endsection
