@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .flatpickr-input {
            background: white !important;
        }

        .flatpickr-calendar {
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

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




        .text-end {
            text-align: right !important;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 py-2 font-poppins text-[#1c1c1c]">
        <div class="section-body flex flex-col gap-5">

            <div
                class="flex flex-col gap-4 p-4 bg-white border border-slate-200/80 rounded-xl shadow-xs md:flex-row md:items-center md:justify-between">

                {{-- LEFT: JUDUL & IKON HEADER --}}
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 leading-tight">Daftar Pesanan</h2>
                        <p class="text-xs text-slate-400">Daftar dan riwayat pesanan apotek</p>
                    </div>
                </div>

                {{-- RIGHT: GRUP TOMBOL AKSI (RESPONSIF) --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">

                    {{-- 1. TOMBOL KEMBALI (Secondary / Ghost Outline) --}}
                    <button id="back" type="button"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300 transition-all duration-150">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        <span>Kembali</span>
                    </button>

                    {{-- 2. TOMBOL TRACKING PESANAN (Soft Emerald + Ikon Truk Pengiriman) --}}
                    <a href="{{ route('orders-tracking.index') }}" class="w-full sm:w-auto">
                        <button type="button"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300/80 hover:bg-emerald-100/80 hover:border-emerald-400 rounded-lg shadow-[0_0_8px_rgba(16,185,129,0.15)] hover:shadow-[0_0_12px_rgba(16,185,129,0.3)] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                          
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-truck-loading">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M2 3h1a2 2 0 0 1 2 2v10a2 2 0 0 0 2 2h15" />
                                <path d="M9 9a3 3 0 0 1 3 -3h4a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-4a3 3 0 0 1 -3 -3l0 -2" />
                                <path d="M7 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M16 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            </svg>
                            <span>Tracking Pesanan</span>
                        </button>
                    </a>

                    {{-- 3. TOMBOL BUAT BARU (Primary Blue + Ikon Plus + Soft Blue Glow) --}}
                    <a href="{{ route('orders.create') }}" class="w-full sm:w-auto">
                        <button type="button"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-[0_0_8px_rgba(37,99,235,0.25)] hover:shadow-[0_0_12px_rgba(37,99,235,0.4)] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Buat Baru</span>
                        </button>
                    </a>

                </div>
            </div>

            <div class="w-full p-5 bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label
                            class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</label>
                        <input type="text" id="returdate"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition-all focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="{{ $now }}" readonly onkeyup="searchMedicineData(this.value)"
                            autocomplete="off">
                    </div>
                    <div>
                        <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cari Nomor
                            SPB</label>
                        <input type="text" id="searchInput"
                            class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm transition-all focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ketik nomor SPB / Nomor Terima..." oninput="searchOrderCode(this.value)" autocomplete="off">
                    </div>
                    <div>
                        <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Filter
                            Tanggal</label>
                        <input type="text" id="dateRange"
                            class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm transition-all focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Pilih rentang tanggal..." autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="w-full p-5 bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
                <table id="orderItemsTable" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wider">
                            <th class="pb-3 font-semibold">#</th>
                            <th class="pb-3 font-semibold">Date</th>
                            <th class="pb-3 font-semibold">SPB & NOMOR TERIMA</th>
                            <th class="pb-3 font-semibold text-center">Status</th>
                            <th class="pb-3 font-semibold">Total</th>
                            <th class="pb-3 font-semibold">Total PPN</th>
                            <th class="pb-3 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm"></tbody>
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
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        let startDate = '';
        let endDate = '';

        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "Y-m-d",
            onClose: function(selectedDates, dateStr) {

                if (selectedDates.length === 2) {
                    startDate = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                    endDate = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                } else {
                    startDate = '';
                    endDate = '';
                }

                orderItemsTable.ajax.reload();
            }
        });
        $('#back').click(function() {
            window.location.href = "{{ route('home') }}";
        });
    </script>
    <script>
        let page = 1;
        let keyword = '';
        let loading = false;
        let hasMore = true;
        let activeIndex = -1;
        let selectedTransactionCode = null;
        let medicineTable = null;
        let medicineSelectedId = '';
        let orderid = null;
        let itemcode = '';
        let itemprice = '';
        let itemcontent = '';
        let itemqty = '';
        let itemtotal = '';
        let total_transaction = '';
        let itempack = '0';
        var itemcreditor = '';


        var pack = document.getElementById('pack');
        let orderItemsTable;
        let selectedRowData = null;
        let selectedRowIndex = null;


        document.addEventListener('DOMContentLoaded', function() {
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.orderlist') }}",
                    data: function(d) {
                        d.order_code = $('#searchInput').val();
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'date',
                        name: 'date'
                    },
                    {
                        data: 'code',
                        name: 'code',
                        defaultContent: '-'
                    },

                    {
                        data: 'status_order'
                    },
                    {
                        data: 'total',
                        name: 'total'
                    },
                    {
                        data: 'total_ppn',
                        name: 'total_ppn'
                    },
                    {
                        data: 'action',
                        name: 'action'
                    },
                ],
                searching: false,
                info: false,
            });
        });
        // $('#orderItemsTable').on('click', 'tbody tr', function() {
        //     $('#orderItemsTable tbody tr').removeClass('selected');
        //     $(this).addClass('selected');

        //     selectedRowData = orderItemsTable.row(this).data();

        //     console.log('ROW SELECTED:', selectedRowData);
        // });

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

        function searchOrderCode(data) {
            orderItemsTable.ajax.reload();
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
