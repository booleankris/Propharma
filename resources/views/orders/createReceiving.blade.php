@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

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
                        <div class="flex flex-wrap py-2 gap-1">


                            <div class="">
                                <div class="py-1 text-[13px] font-bold">Jenis Bayar</div>
                                <select id="factor_payment" name="factor_payment"
                                    class="select2 w-full rounded-lg border border-gray-300 px-12 py-2.5 text-[13px]">
                                    <option value="KREDIT">Kredit</option>
                                    <option value="TUNAI">Tunai</option>
                                </select>
                            </div>
                            <div>
                                <div class="py-1 text-[13px] font-bold">Tanggal Terima</div>
                                <input type="text" id="returdate"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $now }}" readonly onkeyup="SearchBPBA(this.value)"
                                    autocomplete="off">
                            </div>
                            <div>
                                <div class="py-1 text-[13px] font-bold" for="returnumber">Nomor Terima</div>
                                <input type="text" id="returnumber" name="receiving_number"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="" value="{{ $order_code }}" readonly onkeyup="SearchBPBA(this.value)"
                                    autocomplete="off">
                            </div>
                        </div>

                        <input type="text" id="searchInput" autofocus
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                            placeholder="Cari Nomor BPBA..." oninput="SearchBPBA(this.value)"
                            @if ($order_exist) readonly @endif autocomplete="off">
                        <div id="searchDropdown" class="dropdown-table" style="display:none;">
                            <table class="table table-sm table-bordered mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Code</th>
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
                        <div class="flex flex-wrap items-center gap-3 py-2 w-full">
                            <div class="flex-1 min-w-[200px]">
                                <div class="py-1 text-[13px] font-bold">Nomor Faktur</div>
                                <input id="factor_number" value="{{ $transaction->factor_number }}" name="factor_number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                            </div>
                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">Tanggal Faktur</div>
                                <input id="factor_date" type="date" name="factor_date"
                                    value="{{ $transaction->date ? \Carbon\Carbon::createFromFormat('d/m/Y', $transaction->date)->format('Y-m-d') : '' }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Tanggal Faktur">
                            </div>
                            <div class="flex-1 min-w-[200px]">
                                <div class="py-1 text-[13px] font-bold">Waktu Kredit (Hari)</div>
                                <input id="factor_times" type="number" value="{{ $transaction->factor_times }}"
                                    oninput="count_due()" name="factor_times"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                            </div>
                            <div class="flex-1 min-w-[200px]">
                                <div class="py-1 text-[13px] font-bold">Jatuh Tempo</div>
                                <input id="factor_due" readonly name="factor_due" value="{{ $transaction->factor_due }}"
                                    type="date"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                            </div>
                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">Jenis PPN</div>
                                <select id="factor_ppn" name="factor_ppn"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="PPN">

                                    <option @if ($transaction->factor_ppn == 'INCLUDE') selected @endif value="INCLUDE">Include
                                    </option>
                                    <option @if ($transaction->factor_ppn == 'EXCLUDE') selected @endif value="EXCLUDE">Exclude
                                    </option>
                                </select>
                            </div>
                        </div>
                        <form method="post" action="{{ route('orders.addItemOrder') }}">
                            @csrf

                            <div class="flex flex-wrap gap-3 py-2 w-full">
                                {{-- <input type="hidden" name="medicine_id">
                                <input type="hidden" name="order_id">
                                <input type="hidden" name="transaction_id">
                                <input type="hidden" name="total_price">
                                <input type="hidden" name="total_qty"> --}}
                                <div class="hidden">
                                    <input type="text" id="receiving_items_id">
                                </div>
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
                                        <input type="checkbox" readonly id="pack" name="is_active"
                                            class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm">Utuh</span>
                                    </label>
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">QTY Beli</div>
                                    <input id="qty" type="number" readonly name="qty"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="QTY Retur" oninput="counttotal()">
                                </div>
                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">QTY Diterima</div>
                                    <input id="qty_received" type="number" name="qty_received"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="QTY Diterima" oninput="counttotalreceived()">
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

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Batch</div>
                                    <input id="batch" name="batch"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Batch">
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Diskon</div>
                                    <input id="discount"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Diskon">
                                </div>
                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Exp Date</div>
                                    <input id="expired_date" type="date" name="expired_date"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Tanggal Faktur">
                                </div>
                                <div class="flex-1 min-w-[200px]">
                                    <div class="py-1 text-[13px] font-bold">Jumlah</div>
                                    <input id="total_price" readonly name="total_price"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Jumlah">
                                </div>
                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Etalase</div>
                                    <input id="etalase" name="etalase"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Etalase">
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Lokasi</div>
                                    <input id="location" name="location"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Lokasi">
                                </div>


                                <div class="flex gap-3 mt-4">
                                    <button onclick="addItem()" type="button"
                                        class="btn btn-pharma !bg-[#2196F3] btn-lg">Konfirmasi
                                    </button>
                                    <button onclick="resetInputs()" type="button" id="back"
                                        class="btn btn-pharma !bg-[#b72929] btn-lg">
                                        Reset
                                    </button>
                                </div>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
            <div class="mt-3 relative w-full p-[24px] bg-[#ffffff] rounded-[22px]">
                <div class="flex flex-wrap items-center gap-3">
                    <button onclick="completeOrder()"
                        class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-blue-600 !shadow-[0_2px_6px_#2563eb] px-6 py-4 text-sm font-xl text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Simpan & Buat Baru
                    </button>

                    <button onclick="printReceiving()"
                        class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-gray-700 !shadow-[0_2px_6px_#374151] px-6 py-4 text-sm font-xl text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 9V4h12v5M6 18h12v-5H6v5zM6 14h12" />
                        </svg>
                        Cetak
                    </button>
                </div>

                <table id="orderItemsTable" class="w-full">
                    <thead>
                        <tr>
                            <th>Nama Obat</th>
                            <th>QTY Beli</th>
                            <th>Satuan</th>
                            <th>HNA</th>
                            <th>Harga PPN</th>
                            <th>Diskon</th>
                            <th>Total</th>
                            <th>Lokasi</th>
                            <th>Etalase</th>
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

    <script>
        let page = 1;
        let keyword = '';
        let loading = false;
        let hasMore = true;
        let activeIndex = -1;
        let selectedTransactionCode = null;
        let medicineTable = null;
        let medicineSelectedId = '';
        var ordercode = @json($order_exist?->orders?->code ?? '');
        var orderid = @json($receiving_id);
        var order_id = '';
        let itemcode = '';
        let itemprice = '';
        let itemcontent = '';
        let itemqty = '';
        let itemtotal = '';
        let total_transaction = 0;
        let itempack = '0';
        var itemcreditor = '';
        var order_items_id = 0;
        var receiving_id = @json($receiving_id);

        const orderExist = @json($order_exist ? true : false);
        const qty_received = document.getElementById('qty_received');
        const expired_date = document.getElementById('expired_date');
        const discount = document.getElementById('discount');
        const itemlocation = document.getElementById('location');
        const etalase = document.getElementById('etalase');
        const batch = document.getElementById('batch');
        const factor_payment = document.getElementById('factor_payment');
        const factor_number = document.getElementById('factor_number');
        const factor_date = document.getElementById('factor_date');
        const factor_times = document.getElementById('factor_times');
        const factor_due = document.getElementById('factor_due');
        const factor_ppn = document.getElementById('factor_ppn');
        const receiving_items_id = document.getElementById('receiving_items_id');

        var pack = document.getElementById('pack');
        let orderItemsTable;
        let selectedRowData = null;
        let selectedRowIndex = null;

        document.addEventListener('DOMContentLoaded', function() {
       
            // SELECT2
            $('#factor_payment').select2({
                placeholder: 'Pilih Pembayaran...',
                allowClear: true,
            });

            // DATATABLE INIT
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.getorderitems') }}",
                    data: d => {
                        d.order_id = ordercode;
                    }
                },
                columns: [{
                        data: 'medicines.name'
                    },
                    {
                        data: 'quantity',
                        title: 'Qty Order'
                    },
                    {
                        data: 'qty_received',
                        title: 'Qty Diterima'
                    },
                    {
                        data: 'price'
                    },
                    {
                        data: 'price_ppn',
                        title: 'Harga PPN'
                    },
                    {
                        data: 'receiving_items.discount'
                    },
                    {
                        data: 'total'

                    },
                    {
                        data: 'receiving_items.location'

                    },
                    {
                        data: 'receiving_items.etalase'

                    },
                ],
                paging: false,
                searching: false,
                info: false,
            });

            // LOAD DATA AWAL (AMAN)
            if (orderExist && ordercode) {
                loadItems(ordercode);
            }

            // EVENT QTY RECEIVED
            qty_received.addEventListener('keyup', function(e) {

                if (e.key === 'Enter') {
                    batch.focus();
                }

                const qtyOrder = parseFloat(document.getElementById('qty').value) || 0;
                let value = parseFloat(this.value) || 0;

                if (value > qtyOrder) {
                    this.value = qtyOrder;
                    counttotal();
                }

                if (value < 0) {
                    this.value = 0;
                }
            });

        });
        $('#orderItemsTable').on('click', 'tbody tr', function() {
            $('#orderItemsTable tbody tr').removeClass('selected');
            $(this).addClass('selected');

            selectedRowData = orderItemsTable.row(this).data();
            order_items_id = selectedRowData.id;
            order_id = selectedRowData.order_id;

            console.log('ROW SELECTED:', selectedRowData);
        });

        $('#orderItemsTable tbody').on('dblclick', 'tr', function() {

            const data = orderItemsTable.row(this).data();
            if (!data) return;

            selectedRowIndex = orderItemsTable.row(this).index();
            selectedRowData = data;

            document.getElementById('medicine_name').value = data.medicines.name ?? '';
            document.getElementById('unit').value = data.medicines.unit ?? '';
            document.getElementById('content').value = data.medicines.content ?? '';
            document.getElementById('item_price').value = formatRupiah(data.medicines.raw_price);
            document.getElementById('qty').value = data.quantity;
            document.getElementById('medicine_code').value = data.medicines.code;
            document.getElementById('total_price').value = data.total;
            document.getElementById('qty_received').focus();
            document.getElementById('qty_received').value = data.qty_received ?? '';
            document.getElementById('batch').value = data.receiving_items?.batch ?? '';
            document.getElementById('location').value = data.receiving_items?.location ?? '';
            document.getElementById('etalase').value = data.receiving_items?.etalase ?? '';
            document.getElementById('discount').value = data.receiving_items?.discount ?? '';
            document.getElementById('expired_date').value = data.receiving_items?.expired_date ?? '';
            document.getElementById('receiving_items_id').value = data.receiving_items?.id ?? '';

            if (data.pack == "1") {
                pack.checked = true;
            }
            console.log(data.receiving_items?.id ?? '');
            itemcode = data.medicine_id;
            itemprice = data.medicines.raw_price;
            itemqty = data.quantity;
            itemcontent = data.medicines.content;
            // totalprice = data.total;
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

        // function updateItem() {
        //     const qty = parseInt(document.getElementById('qty').value);
        //     const total = qty * itemprice;
        //     axios.post("{{ route('orders.updateOrderItem') }}", {
        //         order_id: selectedRowData.order_item_id,
        //         medicine_id: selectedRowData.medicines.id,
        //         pack: itempack,
        //         price: itemprice,
        //         quantity: itemqty,
        //         total: itemtotal,
        //     }, {
        //         headers: {
        //             'X-CSRF-TOKEN': document
        //                 .querySelector('meta[name="csrf-token"]')
        //                 .content
        //         }
        //     }).then(res => {
        //         if (res.data.success) {
        //             orderItemsTable.ajax.reload(null, false);
        //             resetInputs();
        //             selectedRowData = null;
        //             selectedRowIndex = null;
        //         }
        //     }).catch(err => {
        //         console.error(err);
        //         alert('Update failed');
        //     });
        // }

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

        function SearchBPBA(value) {
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
            document.getElementById('factor_number').focus();
            console.log(item.items);
            ordercode = item.code;
            loadItems(ordercode);
        }

        if (orderExist) {
            console.log(ordercode);
            loadItems(ordercode);
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
            const requiredFields = [{
                    el: receiving_items_id,
                    name: "Receiving Items ID"
                },
                {
                    el: qty_received,
                    name: "QTY Diterima"
                },
                {
                    el: batch,
                    name: "Batch"
                },
                {
                    el: expired_date,
                    name: "Exp Date"
                },
                {
                    el: itemlocation,
                    name: "Lokasi"
                },
                {
                    el: etalase,
                    name: "Etalase"
                },
                {
                    el: factor_payment,
                    name: "Jenis Bayar"
                },
                {
                    el: factor_number,
                    name: "Nomor Faktur"
                },
                {
                    el: factor_date,
                    name: "Tanggal Faktur"
                },
                {
                    el: factor_times,
                    name: "Waktu Kredit (Hari)"
                },
                {
                    el: factor_due,
                    name: "Jatuh Tempo"
                },
                {
                    el: factor_ppn,
                    name: "Jenis PPN"
                }
            ];

            let isValid = true;

            document.querySelectorAll(".error-msg").forEach(e => e.remove());

            requiredFields.forEach(field => {
                const input = field.el;
                if (!input.value || input.value.trim() === "") {
                    isValid = false;

                    const error = document.createElement("span");
                    error.classList.add("error-msg");
                    error.style.color = "red";
                    error.style.fontSize = "12px";
                    error.innerText = `${field.name} wajib diisi`;

                    input.parentNode.appendChild(error);
                }
            });

            if (!isValid) {
                return;
            }

            const payload = {
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
                total: total_transaction,
                factor_payment: factor_payment.value,
                factor_number: factor_number.value,
                factor_date: factor_date.value,
                factor_times: factor_times.value,
                factor_due: factor_due.value,
                factor_ppn: factor_ppn.value,
            };

            axios.post("{{ route('receiving.addreceivingitem') }}", payload, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(res => {
                    if (res.data.success) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: res.message ?? 'Item Berhasil di-Update!',
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

            ordercode = transactionId;

            document.getElementById('searchDropdown').style.display = 'none';
            document.getElementById('searchInput').value = ordercode;

            orderItemsTable.ajax.reload(null, false);
        }


        // Count Due
        function count_due() {

            const days = parseInt(factor_times.value);
            const baseDateValue = factor_date.value;

            if (!days || days <= 0 || !baseDateValue) {
                factor_due.value = '';
                return;
            }

            const dueDate = new Date(baseDateValue);
            dueDate.setDate(dueDate.getDate() + days);

            const yyyy = dueDate.getFullYear();
            const mm = String(dueDate.getMonth() + 1).padStart(2, '0');
            const dd = String(dueDate.getDate()).padStart(2, '0');

            factor_due.value = `${yyyy}-${mm}-${dd}`;
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

        // ENTER REDIRECTION
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
                addItem();
            }
        });
        factor_number.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                factor_date.focus();
            }
        });
        factor_date.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                factor_times.focus();
            }
        });
        factor_times.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                factor_ppn.focus();
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
