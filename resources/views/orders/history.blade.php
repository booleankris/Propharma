@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .select2-container .select2-selection--single {
            height: 46px !important;
            /* match your Tailwind input height */
            padding: 7px 10px !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
        }

        .select2-selection__choice {
            background: #e5e7eb !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            font-size: 13px;
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
                        <div class="flex items-end justify-between md:block">
                            <h1 class="text-2xl font-semibold tracking-tight font-poppins text-[#1c1c1c]">History Harga Beli
                            </h1>
                        </div>
                        <div class="flex py-2 gap-1">

                            <div>
                                <div class="py-1 text-[13px] font-bold">Tanggal</div>

                                <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    autocomplete="off">
                            </div>
                            <div>
                                <div class="py-1 text-[13px] font-bold">Cari Obat...</div>

                                <input type="text" onkeyup="searchMedicines(this.value)" id="medicine"
                                    placeholder="Ketik Nama atau Kode Obat..."
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    autocomplete="off">
                            </div>
                            <div>
                                <div class="py-1 text-[13px] font-bold">Nama Obat</div>

                                <input type="text" readonly id="medicine_name" placeholder="Ketik Nama Obat..."
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3 relative w-full p-[24px] bg-[#ffffff] rounded-[22px]">
                {{-- <div class="flex flex-wrap items-center gap-3">
                    <button onclick="completeOrder()"
                        class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-blue-600 !shadow-[0_2px_6px_#2563eb] px-6 py-4 text-sm font-xl text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            class="w-5 h-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                            <polyline points="17 21 17 13 7 13 7 21" />
                            <polyline points="7 3 7 8 15 8" />
                        </svg>
                        Simpan
                    </button>

                    <button onclick="printReceiving()"
                        class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-gray-700 !shadow-[0_2px_6px_#374151] px-6 py-4 text-sm font-xl text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 9V4h12v5M6 18h12v-5H6v5zM6 14h12" />
                        </svg>
                        Cetak
                    </button>
                </div> --}}

                <table id="orderItemsTable" class="w-full">
                    <thead>
                        <tr>
                            <th>No. Terima</th>
                            <th>Tanggal Faktur</th>
                            <th>Kreditur</th>
                            <th>Jumlah</th>
                            <th>Satuan</th>
                            <th>Harga PPN</th>
                        </tr>
                    </thead>
                    <tbody class="text-[12px]"></tbody>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let page = 1;
        let keyword = '';
        let loading = false;
        let hasMore = true;
        let activeIndex = -1;
        let selectedTransactionCode = null;
        let medicineTable = null;
        let medicineSelectedId = '';
        let startDate = '';
        let endDate = '';
        var searchMedicine = '';

        var pack = document.getElementById('pack');
        let orderItemsTable;
        let selectedRowData = null;
        let selectedRowIndex = null;

        document.addEventListener('DOMContentLoaded', function() {
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


            // DATATABLE INIT
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.gethistory') }}",
                    data: function(d) {
                        d.searchMedicine = searchMedicine;
                        d.start_date = startDate;
                        d.end_date = endDate;

                    }
                },
                columns: [{
                        data: 'receiving_code'
                    },
                    {
                        data: 'invoice_date'
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'qty'
                    },
                    {
                        data: 'unit'
                    },

                    {
                        data: 'total'
                    },

                ],
                paging: false,
                searching: false,
                info: false,

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

        function searchMedicines(medicine) {
            if (!medicine || medicine.trim() === '') {
                $('#medicine_name').val('');
                searchMedicine = '';
                orderItemsTable.ajax.reload();
                return;
            }

            searchMedicine = medicine;

            orderItemsTable.ajax.reload(function() {
                let filteredData = orderItemsTable.rows({
                    search: 'applied'
                }).data();

                if (filteredData.length > 0) {
                    let firstRow = filteredData[0];
                    let firstMedicineName = firstRow.order_items?.medicines?.name ?? '-';
                    $('#medicine_name').val(firstMedicineName);
                    console.log('First medicine name:', firstMedicineName);
                } else {
                    console.log('No rows found for this medicine.');
                    $('#medicine_name').val('');
                }
            });
        }


        // Fetch   
        function fetchData() {
            if (loading || !hasMore) return;

            loading = true;

            fetch(`{{ route('receiving.searchbpba') }}?search=${keyword}&page=${page}`)
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
                                <td>${item.code}</td>
                            </tr>
                        `);
                    });

                    hasMore = res.current_page < res.last_page;
                    page++;

                    document.getElementById('searchDropdown').style.display = 'block';
                })
                .finally(() => loading = false);
        }

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
            document.getElementById('invoice_number').focus();
            console.log(item.items);
            ordersid = item.code;
            loadItems(ordersid);
        }

        console.log(ordersid);
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
            let qty = parseInt(document.getElementById('qty_received').value) || 0;
            itemqty = qty;

            if (pack.checked) {
                itemtotal = qty * itemcontent * itemprice;
                total_transaction = itemtotal;
                document.getElementById('total_price').value = formatRupiah(itemtotal);

            } else {
                itemtotal = qty * itemprice;
                total_transaction = itemtotal;
                document.getElementById('total_price').value = formatRupiah(itemtotal);


            }
        }

        function counttotalreceived() {
            let qty = parseInt(document.getElementById('qty_received').value) || 0;
            itemqty = qty;

            if (pack.checked) {
                itemtotal = qty * itemcontent * itemprice;
                total_transaction = itemtotal;
                document.getElementById('total_price').value = formatRupiah(itemtotal);

            } else {
                itemtotal = qty * itemprice;
                total_transaction = itemtotal;
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

            // alert(itemlocation.value);
            // alert(discount.value);
            // alert(batch.value);
            // alert(itemlocation.value);
            // alert(qty_received.value);

            const payload = {
                creditor_code: creditor.value,
                receiving_items_id: receiving_items_id.value,
                receiving_id: receiving_id,
                order_items_id: order_items_id,
                order_id: order_id,
                qty_received: qty_received.value,
                discount: discount.value,
                expired_date: expired_date.value,
                batch: batch.value,
                location: itemlocation.value,
                etalase: etalase.value,
                status: itemstatus.value,
                total: total_transaction,
                invoice_payment: invoice_payment.value,
                invoice_number: invoice_number.value,
                invoice_date: invoice_date.value,
                invoice_times: invoice_times.value,
                invoice_due: invoice_due.value,
                invoice_ppn: invoice_ppn.value,
            };

            axios.post("{{ route('receiving.addreceivingitem') }}", payload, {
                    headers: {
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            .content
                    }
                })
                .then(res => {
                    if (res.data.success) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: res.message ??
                                'Item Berhasil di-Update!',
                            position: 'topRight'
                        });
                        orderItemsTable.ajax.reload(null, false);
                        document.getElementById("searchInput").readOnly = true;
                        resetInputs();

                    }
                })
                .catch(err => {
                    let message = 'Data gagal disimpan';
                    if (err.response) {
                        if (err.response.data.errors) {
                            message = Object.values(err.response.data.errors)
                                .map(e => e[0])
                                .join('<br>');
                        }
                        if (err.response.data.message) {
                            message = err.response.data.message;
                        }
                    }
                    iziToast.error({
                        title: 'Gagal',
                        message: message,
                        position: 'topRight'
                    });
                });
        }

        function completeOrder() {

            axios.post("{{ route('receiving.completeOrder') }}", {
                receivingid: receiving_id,
                orderid: ordersid,
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

        let itemsTable;

        function loadItems(transactionId) {

            if (!orderItemsTable) {
                console.warn('DataTable belum siap');
                return;
            }

            ordersid = transactionId;

            document.getElementById('searchDropdown').style.display = 'none';
            document.getElementById('searchInput').value = orderscode;

            orderItemsTable.ajax.reload(null, false);
        }


        // Count Due
        function count_due() {

            const days = parseInt(invoice_times.value);
            const baseDateValue = invoice_date.value;

            if (!days || days <= 0 || !baseDateValue) {
                invoice_due.value = '';
                return;
            }

            const dueDate = new Date(baseDateValue);
            dueDate.setDate(dueDate.getDate() + days);

            const yyyy = dueDate.getFullYear();
            const mm = String(dueDate.getMonth() + 1).padStart(2, '0');
            const dd = String(dueDate.getDate()).padStart(2, '0');

            invoice_due.value = `${yyyy}-${mm}-${dd}`;
        }
        // Count Items Left
        function count_itemsleft() {
            const qtyOrder = parseFloat(document.getElementById('qty').value) || 0;
            const input = document.getElementById('qty_received');
            let value = parseFloat(input.value) || 0;

            if (value > qtyOrder) {
                input.value = qtyOrder;
            }

            if (value < 0) {
                input.value = 0;
            }
        }

        // ENTER REDIRECTION AND SUBMIT
        document.getElementById('qty_received').addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                batch.focus();
            }
            const qtyOrder = parseFloat(document.getElementById('qty').value) || 0;
            const input = document.getElementById('qty_received');
            let value = parseFloat(input.value) || 0;

            if (value > qtyOrder) {
                input.value = qtyOrder;
                counttotal();
            }

            if (value < 0) {
                input.value = 0;
            }
        });

        $("#invoice_payment").on("select2:select", () => {
            setTimeout(() => $("#invoice_number").focus(), 100);
        });

        discount.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                etalase.focus();
            }
        });
        etalase.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                itemlocation.focus();
            }
        });
        itemlocation.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                itemstatus.focus();
            }
        });
        itemstatus.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                addItem();
            }
        });
        invoice_number.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                invoice_date.focus();
            }
        });
        invoice_date.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                invoice_times.focus();
            }
        });
        invoice_times.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                invoice_ppn.focus();
            }
        });
        batch.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                discount.focus();
            }
        });
        discount.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                expired_date.focus();
            }
        });
        expired_date.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                etalase.focus();
            }
        });
        etalase.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                location.focus();
            }
        });

        function printReceiving() {

            if (!receiving_id) {
                iziToast.error({
                    title: 'Error',
                    message: 'Data penerimaan belum ada',
                    position: 'topRight'
                });
                return;
            }

            const url = `/receiving/print/${receiving_id}`;

            window.open(url, '_blank');
            setTimeout(() => {
                window.location.reload();

            }, 300);

        }
    </script>


@endsection
