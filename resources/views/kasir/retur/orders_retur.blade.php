@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <style>
        .dropdown-table {
            width: 100%;
            position: relative;
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
            <div style="position: relative; width: 100%;">
                <div id="searchWrapper" class="flex flex-wrap p-7 rounded-lg bg-white gap-5"
                    style="position: relative; width: 100%;">
                   
                    <div class="w-[44%]">
                        <div class="flex items-end justify-between md:block">
                            <h1 class="text-2xl font-semibold tracking-tight font-poppins text-[#1c1c1c]">Retur Pembelian
                            </h1>
                        </div>
                        <div class="flex py-2 gap-1">
                            <div class="relative">
                                <div class="py-1 text-[13px] font-bold">Tanggal Retur</div>
                                <input type="text" id="returdate"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $now }}" readonly
                                    onkeyup="searchSalesData(this.value)" autocomplete="off">
                            </div>
                            <div>
                                <div class="py-1 text-[13px] font-bold" for="returnumber">Nomor Retur</div>
                                <input type="text" id="returnumber"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $retur_code }}" readonly
                                    onkeyup="searchSalesData(this.value)" autocomplete="off">
                            </div>
                        </div>

                        <input type="text" id="searchInput"
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="Cari Kode Pembelian..." oninput="searchSalesData(this.value)" autocomplete="off">
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
                        <form method="post" action="{{ route('returdata.returorderitems') }}" class="mt-3">
                            @csrf
                            <div class="flex flex-wrap py-2 w-full gap-1">
                                <input id="medicine_id" type="hidden" name="medicine_id">
                                <input id="cart_id" type="hidden" name="cart_id">
                                <input id="transaction_id" type="hidden" name="transaction_id">
                                <input id="old_qty" type="hidden" name="old_qty">
                                <input id="content" type="hidden" name="content">

                                <div>
                                    <div class="py-1 text-[13px] font-bold">Kode Obat</div>
                                    <input id="medicine_code" name="medicine_code" readonly value="" step="0.01"
                                        class="rounded-lg w-full border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        placeholder="Kode Obat">

                                </div>
                                <div class="w-[100%]">
                                    <div class="py-1 text-[13px] font-bold">Nama Obat</div>
                                    <input id="medicine_name" type="text" readonly
                                        class="rounded-lg w-full border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        placeholder="Nama Obat">
                                </div>
                                <div class="flex gap-2">
                                    <div>
                                        <div class="py-1 text-[13px] font-bold">Satuan</div>
                                        <input id="unit" type="text" readonly
                                            class="rounded-lg w-full border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                            placeholder="Satuan">
                                    </div>
                                    <div>
                                        <div class="py-1 text-[13px] font-bold">Hrg Satuan</div>
                                        <input id="item_price" type="text" readonly
                                            class="rounded-lg  w-full border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                            placeholder="Harga Satuan">
                                    </div>
                                    <div>
                                        <div class="py-1 text-[13px] font-bold">Isi</div>
                                        <input id="content_display" type="text" readonly
                                            class="rounded-lg  w-full border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                            placeholder="Isi">
                                    </div>
                                </div>

                            </div>

                            <div class="flex flex-wrap py-2 w-full gap-1">
                                <div>
                                    <div class="py-1 text-[13px] font-bold">QTY Beli</div>
                                    <input id="qty_in" required type="number" name="qty_in"
                                        class="rounded-lg w-60 border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        placeholder="QTY Retur" >

                                </div>
                                <div>
                                    <div class="py-1 text-[13px] font-bold">QTY Retur</div>
                                    <input id="qty" required type="number" name="qty_retur"
                                        class="rounded-lg w-60 border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        placeholder="QTY Retur" oninput="calculateReturTotal()">

                                </div>
                                <div class="w-[90%]">
                                    <div class="py-1 text-[13px] font-bold">Harga Resep</div>
                                    <input id="price" type="number" readonly
                                        class="rounded-lg  w-full border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        placeholder="Credit">
                                </div>
                                <div class="w-[90%]">
                                    <div class="py-1 text-[13px] font-bold">Jml Retur</div>
                                    <input id="total_retur" type="text" name="total_retur" readonly
                                        class="rounded-lg  w-full border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        placeholder="Jumlah Retur">
                                </div>
                            </div>
                            <div class="flex gap-2 mb-1">
                                <div>
                                    <button type="submit" class="btn btn-pharma !bg-[#2196F3] btn-lg btn-icon icon-right"
                                        tabindex="4">
                                        Simpan
                                    </button>
                                </div>
                                <div>
                                    <button type="button" id="back"
                                        class="btn btn-pharma !bg-[#b72929] !shadow-[0_2px_6px_#7e052e] btn-lg btn-icon icon-right"
                                        tabindex="4">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="w-1/2">
                        <div class="card p-10 md:p-14 mt-2 bg-[#fff] shadow-sm">
                            <div class="card-body">
                                <table id="medicineTable" class="table table-hover align-middle w-100">
                                    <thead>
                                        <tr>
                                            <th class="text-center">#</th>
                                            <th class="text-center">Medicine</th>
                                            <th class="text-center" class="text-end">Qty</th>
                                            <th class="text-center" class="text-end">Price</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
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

        function searchSalesData(value) {
            keyword = value.trim();
            page = 1;
            hasMore = true;
            activeIndex = -1;

            const tbody = document.getElementById('searchResults');
            tbody.innerHTML = '';

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

            fetch(`{{ route('returdata.returorderdata') }}?search=${keyword}&page=${page}`)
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
                            <tr>
                                <td>${((page - 1) * res.per_page) + index + 1}</td>
                                <td>${item.transaction_code}</td>
                                <td>${item.name}</td>
                                <td class="text-end">${item.final_price}</td>
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
        document.getElementById('searchInput').addEventListener('keydown', function(e) {
            const rows = document.querySelectorAll('#searchResults tr');
            const dropdown = document.getElementById('searchDropdown');

            if (dropdown.style.display === 'none' || rows.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, rows.length - 1);
                updateActiveRow(rows);
            }

            if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActiveRow(rows);
            }

            if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                selectRow(rows[activeIndex]);
            }
        }); 
        // Count Total
        function calculateReturTotal() {
            const oldQty = parseFloat(document.getElementById('old_qty').value) || 0;
            const returQty = parseFloat(document.getElementById('qty').value) || 0;
            const content = document.getElementById('content').value;
            const priceRaw = document.getElementById('item_price').value || '0';
            const itemPrice = parseFloat(priceRaw.replace(/[^\d.-]/g, '')) || 0;


            console.log("awal     : " + oldQty);
            console.log("kembali  : " + returQty);
            console.log("total    : " + priceRaw);
            console.log("per item : " + itemPrice);



            const total = (oldQty - returQty) * itemPrice * content;

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
            selectedTransactionCode = row.children[1].innerText;

            document.getElementById('searchInput').value = selectedTransactionCode;
            document.getElementById('searchDropdown').style.display = 'none';

            if (medicineTable) {
                medicineTable.ajax.reload();
            }
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
        // Outside Click
        document.addEventListener('click', function(e) {
            const searchInput = document.getElementById('searchInput');
            const dropdown = document.getElementById('searchDropdown');
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Datatavle
        $(document).ready(function() {
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });
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
                    data: function(d) {
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
                    }
                ]
            });

            function loadMedicineForRetur(data) {
                var medicinedata = data.order_items.medicines;
                medicineSelectedId = medicinedata.id;
                orderSelectedId = data.receiving_details.receiving.id;
                $("#medicine_code").val(medicinedata.code);
                $("#medicine_id").val(medicinedata.id);
                $("#medicine_name").val(medicinedata.name);
                $("#transaction_id").val(orderSelectedId);
                $("#unit").val(medicinedata.unit);
                $("#price").val(data.total);
                $("#item_price").val(medicinedata.raw_price);
                $("#old_qty").val(data.qty_received);
                $("#cart_id").val(data.id);

                $("#qty_in").val(data.qty_received);
                $("#content_display").val(medicinedata.content);
                $("#content").val(medicinedata.content);

                $("#qty").focus();
                console.log(data.receiving_details.receiving.id);
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
