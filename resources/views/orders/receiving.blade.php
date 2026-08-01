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
                        <div class="flex items-end justify-between md:block">
                            <h1 class="text-2xl font-semibold tracking-tight font-poppins text-[#1c1c1c]">Penerimaan
                            </h1>
                        </div>
                        <div class="flex flex-wrap py-2 gap-1">

                            <div>
                                <div class="py-1 text-[13px] font-bold" for="returnumber">Cari Kreditur</div>
                                <select autofocus id="creditor" name="creditor_id"
                                    class="w-full rounded-lg border border-gray-300  text-center py-[12.4px] !px-20 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                                    <option value="" required>---- Pilih Kreditur ----</option>
                                    @foreach ($creditorOption as $creditor)
                                        <option value="{{ $creditor->code }}" required>{{ $creditor->name }}</option>
                                    @endforeach
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
                                    placeholder="" value="{{ $receiving_code }}" readonly onkeyup="SearchBPBA(this.value)"
                                    autocomplete="off">
                            </div>

                            <div>
                                <div class="py-1 text-[13px] font-bold" for="returnumber">Nomor BPBA</div>
                                <input type="text" id="searchInput" autofocus
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Cari Nomor BPBA..." oninput="SearchBPBA(this.value)" readonly
                                    autocomplete="off">
                            </div>
                            <div class="">
                                <div class="py-1 text-[13px] font-bold">Jenis Bayar</div>
                                <select id="invoice_payment" name="invoice_payment"
                                    class="select2 w-full rounded-lg border border-gray-300 px-12 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Pembayaran --</option>
                                    <option value="KREDIT">Kredit</option>
                                    <option value="TUNAI">Tunai</option>
                                    <option value="KONSINYASI">Konsinyasi</option>

                                </select>
                            </div>

                        </div>


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
                                <input id="invoice_number" value="{{ $transaction->invoice_number }}" name="invoice_number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                            </div>
                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">Tanggal Faktur</div>
                                <input id="invoice_date" type="date" name="invoice_date"
                                    value="{{ $transaction->invoice_date ? \Carbon\Carbon::createFromFormat('d/m/Y', $transaction->invoice_date)->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Tanggal Faktur">
                            </div>
                            <div class="flex-1 min-w-[200px]">
                                <div class="py-1 text-[13px] font-bold">Waktu Kredit (Hari)</div>
                                <input id="invoice_times" type="number" value="{{ $transaction->invoice_times }}"
                                    oninput="count_due()" name="invoice_times"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                            </div>
                            <div class="flex-1 min-w-[200px]">
                                <div class="py-1 text-[13px] font-bold">Jatuh Tempo</div>
                                <input id="invoice_due" readonly name="invoice_due" value="{{ $transaction->invoice_due }}"
                                    type="date"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="Jumlah">
                            </div>
                            <div class="w-full sm:w-40">
                                <div class="py-1 text-[13px] font-bold">Jenis PPN</div>
                                <select id="invoice_ppn" name="invoice_ppn"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="PPN">

                                    <option value="INCLUDE">Include
                                    </option>
                                    <option value="EXCLUDE">Exclude
                                    </option>
                                    <option value="TANPA">Tanpa
                                    </option>
                                </select>
                            </div>
                        </div>
                        <form method="post" id="checkout_detail" action="{{ route('orders.addItemOrder') }}">
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
                                <div class="hidden">
                                    <input type="text" id="receiving_details_id">
                                </div>
                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Kode Obat</div>
                                    <input id="medicine_code" type="text" readonly
                                        class="w-full rounded-lg border bg-[#eaeaea] border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Kode Obat">
                                </div>

                                <div class="flex-1 min-w-[200px]">
                                    <div class="py-1 text-[13px] font-bold">Nama Obat</div>
                                    <input id="medicine_name" type="text" readonly
                                        class="w-full rounded-lg border bg-[#eaeaea] border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Nama Obat">
                                </div>

                                <div class="w-full sm:w-32">
                                    <div class="py-1 text-[13px] font-bold">Satuan</div>
                                    <input id="unit" type="text" readonly
                                        class="w-full rounded-lg border bg-[#eaeaea] border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Satuan">
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 py-2 w-full">
                                <div class="w-full sm:w-32">
                                    <div class="py-1 text-[13px] font-bold">Kemasan</div>
                                    <label class="flex items-center gap-2 mt-2">
                                        <input type="checkbox" disabled id="pack" name="is_active"
                                            class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm">Utuh</span>
                                    </label>
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">QTY Beli</div>
                                    <input id="qty" type="number" readonly name="qty"
                                        class="w-full rounded-lg border bg-[#eaeaea] border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
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
                                    <input id="content" type="text" readonly
                                        class="w-full rounded-lg border bg-[#eaeaea] border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Isi Obat">
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Hrg HNA</div>
                                    <input id="item_price" type="text" readonly
                                        class="w-full rounded-lg border bg-[#eaeaea] border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Harga Satuan">
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Batch</div>
                                    <input id="batch" name="batch" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Batch">
                                </div>

                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Diskon</div>
                                    <input id="discount" value="0" type="number"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Diskon">
                                </div>
                                <div class="w-full sm:w-40">
                                    <div class="py-1 text-[13px] font-bold">Ekstra Diskon</div>
                                    <input id="extra_discount" value="0" type="number" required value="0"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Extra Diskon">
                                </div>
                                <div class="w-[10%]">
                                    <div class="py-1 text-[13px] font-bold">Exp Date</div>
                                    <input id="expired_date" type="date" name="expired_date"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Tanggal Faktur">
                                </div>
                                <div class="flex-1 w-[40%] min-w-[200px]">
                                    <div class="py-1 text-[13px] font-bold">Jumlah</div>
                                    <input id="total_price" type="text" readonly name="total_price"
                                        class="w-full rounded-lg border bg-[#eaeaea] border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Jumlah">
                                </div>
                                <div class="w-[20%]">
                                    <div class="py-1 text-[13px] font-bold">Status Barang</div>
                                    <input id="status" name="status" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                        placeholder="Status Barang">
                                </div>
                                <div class="flex flex-wrap w-full gap-3">

                                    <div class="w-[20%] hidden">
                                        <div class="py-1 text-[13px] font-bold">Etalase</div>
                                        <select id="items" name="etalase" required
                                            class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                            <option value="">-- Pilih Etalase --</option>

                                        </select>
                                    </div>

                                    <div class="w-[20%] hidden">
                                        <div class="py-1 text-[13px] font-bold">Lokasi</div>
                                        <select name="location"
                                            class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px"
                                            id="location">
                                            <option value="">-- Pilih Lokasi --</option>

                                        </select>
                                    </div>


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
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M6 9V4h12v5M6 18h12v-5H6v5zM6 14h12" />
                        </svg>
                        Cetak
                    </button>

                    <button onclick="printSPB()"
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
                            <th>QTY Beli</th>
                            <th>QTY Diterima</th>
                            <th>HNA</th>
                            <th>Harga PPN</th>
                            <th>Diskon</th>
                            <th>Extra Diskon</th>
                            <th>Lokasi</th>
                            <th>Total</th>
                            <th>Status Barang</th>

                        </tr>
                    </thead>
                    <tbody class="text-[12px]"></tbody>
                </table>
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
        var ordersid = @json($order_id ?? '');
        var orderscode = @json($order_code ?? '');
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
        let d_price = {{ $d_price }};
        let d_ppn = {{ $d_ppn }};
        let d_total = {{ $d_total }};

        // Setting Initial Transaction Value
        $('#d_price').val(formatRupiah(d_price));
        $('#d_ppn').val(formatRupiah(d_ppn));
        $('#d_total').val(formatRupiah(d_total));


        const qty_received = document.getElementById('qty_received');
        const expired_date = document.getElementById('expired_date');
        const discount = document.getElementById('discount');
        const itemlocation = document.getElementById('location');
        const itemstatus = document.getElementById('status');
        const etalase = document.getElementById('items');
        const batch = document.getElementById('batch');
        const extra_discount = document.getElementById('extra_discount');
        const invoice_payment = document.getElementById('invoice_payment');
        const invoice_number = document.getElementById('invoice_number');
        const invoice_date = document.getElementById('invoice_date');
        const invoice_times = document.getElementById('invoice_times');
        const invoice_due = document.getElementById('invoice_due');
        const invoice_ppn = document.getElementById('invoice_ppn');
        const receiving_items_id = document.getElementById('receiving_items_id');
        const receiving_details_id = document.getElementById('receiving_details_id');
        let date_now = "{{ \Carbon\Carbon::now()->format('Y-m-d') }}";

        const itemPriceInput = document.getElementById('item_price');
        const discountInput = document.getElementById('discount');
        const extraDiscountInput = document.getElementById('extra_discount');

        function handleDiscount(inputElement) {
            const basePrice = parseFloat(total_transaction.value) || 0;
            let value = parseFloat(inputElement.value.replace(',', '.')) || 0;

            let realDiscount = 0;
            let percentage = 0;

            if (value > 100) {
                realDiscount = value;
                percentage = basePrice > 0 ? Math.round((value / basePrice) * 100) : 0;

            } else {
                percentage = value;
                realDiscount = Math.round((basePrice * value) / 100);
                document.getElementById('total_price').value = data.total
            }

            console.log(`Input: ${value}, Real Discount: ${realDiscount}, Percentage: ${percentage}%`);

            return {
                realDiscount,
                percentage
            };
        }

        // Event listeners
        discountInput.addEventListener('input', () => handleDiscount(discountInput));
        extraDiscountInput.addEventListener('input', () => handleDiscount(extraDiscountInput));


        var pack = document.getElementById('pack');
        let orderItemsTable;
        let selectedRowData = null;
        let selectedRowIndex = null;

        // Functions
        function resetInputs() {

            $('#searchWrapper')
                .find('input:not([type="hidden"]):not([readonly]):not([disabled])')
                .val('');

            $('#searchWrapper')
                .find('textarea:not([readonly]):not([disabled])')
                .val('');

            $('#searchWrapper')
                .find('select')
                .val('')
                .trigger('change');

            $('#searchWrapper')
                .find('input[type="checkbox"]')
                .prop('checked', false);
            $('#checkout_detail')
                .find('input[type="text"]')
                .val('')
                .trigger('change');
            $('#total_price').val('');
            $('#qty_received').val('');

            $('#items').val(null).trigger('change');
            $('#location').val(null).trigger('change');
            $('#invoice_due').val('');
            itemcode = null;
            itemprice = null;
            itemqty = null;
            itemcontent = null;
            document.getElementById('creditor').focus();

        }

        function printSPB() {
            window.open(`/orders/${ordersid}/printspb`, "_blank");
        }

        function setSelect2AjaxValue(selector, id, text) {
            const $select = $(selector);

            if (!id) {
                $select.val(null).trigger("change");
                return;
            }

            if ($select.find("option[value='" + id + "']").length) {
                $select.val(id).trigger("change");
            } else {
                const option = new Option(text, id, true, true);
                $select.append(option).trigger("change");
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            const factorDateInput = document.getElementById('invoice_date');
            // if (factorDateInput && !factorDateInput.value) {
            //     const today = new Date();
            //     const year = today.getFullYear();
            //     const month = String(today.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
            //     const day = String(today.getDate()).padStart(2, '0');
            //     console.log(day);
            //     factorDateInput.value = `${year}-${month}-${day}`;
            // }

            $('#invoice_payment').select2({
                placeholder: 'Pilih Pembayaran...',
                allowClear: true,
            });
            $("#location").select2({
                placeholder: "Cari Lokasi...",
                allowClear: true,
                ajax: {
                    url: "{{ route('locations.select') }}",
                    dataType: "json",
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true
                }
            });
            $("#items").select2({
                placeholder: "Cari Lokasi...",
                allowClear: true,
                ajax: {
                    url: "{{ route('items.select') }}",
                    dataType: "json",
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true
                }
            });
            // DATATABLE INIT
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                deferLoading: 0,
                ajax: {
                    url: "{{ route('receiving.getorderitems') }}",
                    data: function(d) {
                        d.order_id = ordersid;
                        d.creditor_code = $('#creditor').val();
                    }
                },
                columns: [{
                        data: 'medicines.name'
                    },
                    {
                        data: 'quantity'
                    },
                    {
                        data: 'qty_received'
                    },
                    {
                        data: 'price'
                    },
                    {
                        data: 'price_ppn'
                    },
                    {
                        data: 'receiving_items.discount'
                    },
                    {
                        data: 'receiving_items.extra_discount'
                    },
                    {
                        data: 'receiving_items.locations.name'
                    },

                    {
                        data: 'total'
                    },
                    {
                        data: 'receiving_items.status'
                    },
                ],
                paging: false,
                searching: false,
                info: false,
                language: {
                    emptyTable: "Silakan pilih kreditur terlebih dahulu",
                }
            });

            // Load Datatable
            loadItems(ordersid);


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

        });

        $('#orderItemsTable tbody').on('dblclick', 'tr', function() {

            const data = orderItemsTable.row(this).data();
            if (!data) return;
            console.log(data.receiving_items?.locations?.name);
            console.log(data.receiving_items?.etalases?.name);
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

            document.getElementById('discount').value = data.receiving_items?.discount ?? 0;
            document.getElementById('extra_discount').value = data.receiving_items?.extra_discount ?? 0;
            document.getElementById('status').value = data.receiving_items?.status ?? '';
            document.getElementById('expired_date').value = data.receiving_items?.expired_date ?? '';
            document.getElementById('receiving_items_id').value = data.receiving_items?.id ?? '';
            document.getElementById('receiving_details_id').value = data.receiving_items?.id ?? '';
            setSelect2AjaxValue(
                "#location",
                data.receiving_items?.locations?.id,
                data.receiving_items?.locations?.name
            );
            setSelect2AjaxValue(
                "#items",
                data.receiving_items?.etalases?.id,
                data.receiving_items?.etalases?.name
            );
            if (data.pack == "1") {
                pack.checked = true;
            }
            axios.get('/searchreceivingdetails', {
                    params: {
                        detail_id: data.receiving_items?.receiving_details_id ?? '',
                    }
                })
                .then(function(response) {
                    // console.log(response);
                    // console.log(invoice_times);
                    // console.log(response.data.creditor.credit_time);

                    // invoice_times.value = 30;

                    // console.log(invoice_times.value);

                    invoice_number.value = response.data.query?.invoice_number || '';
                    invoice_times.value = response.data.query?.invoice_times || response.data.creditor
                        .credit_time || '';
                    $('#invoice_payment')
                        .val(response.data.query?.invoice_payment || '')
                        .trigger('change');
                    invoice_due.value = response.data.query?.invoice_due || '';
                    invoice_date.value = response.data.query?.invoice_date ?? invoice_date.value;

                    const ppn =
                        response.data.query?.invoice_ppn?.trim() ||
                        response.data.creditor?.ppn_type?.trim() ||
                        'TANPA';

                    document.getElementById('invoice_ppn').value = ppn;

                    count_due();
                    // invoice_number.value = response.data.query?.invoice_number || '';

                })
                .catch(function(error) {
                    console.error(error);
                });
            console.log(data.receiving_items?.receiving_details_id ?? '');
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
                addItem();
            }
        });

        // Creditor select
        $('#creditor').on('change', function() {
            console.log(document.getElementById('invoice_ppn'));

            let creditor = $(this).val();
            if (creditor) {
                axios.get('/searchselectcreditors', {
                        params: {
                            creditor_code: creditor,
                            orderid: ordersid
                        }
                    })
                    .then(function(response) {
                        console.log(response);
                        // console.log(invoice_times);
                        // console.log(response.data.creditor.credit_time);

                        // invoice_times.value = 30;

                        // console.log(invoice_times.value);

                        invoice_times.value = response.data.query?.invoice_times || response.data.creditor
                            .credit_time || '';
                        $('#invoice_payment')
                            .val(response.data.query?.invoice_payment || '')
                            .trigger('change');
                        invoice_due.value = response.data.query?.invoice_due || '';
                        invoice_date.value = response.data.query?.invoice_date ?? invoice_date.value;

                        const ppn =
                            response.data.query?.invoice_ppn?.trim() ||
                            response.data.creditor?.ppn_type?.trim() ||
                            'TANPA';

                        document.getElementById('invoice_ppn').value = ppn;

                        count_due();
                        // invoice_number.value = response.data.query?.invoice_number || '';

                    })
                    .catch(function(error) {
                        console.error(error);
                    });
                $('#invoice_payment').select2('open');
                orderItemsTable.ajax.reload();
            } else {
                orderItemsTable.clear().draw();
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
            document.getElementById('invoice_number').focus();
            ordersid = item.code;
            loadItems(ordersid);
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
        });
        invoice_ppn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                addItem();
            }
        });

        function counttotal() {
            let qty = parseInt(document.getElementById('qty_received').value) || 0;
            itemqty = qty;

            const qtyOrder = parseFloat(document.getElementById('qty').value) || 0;
            itemstatus.value = "Diterima Lengkap"

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

            const qtyOrder = parseFloat(document.getElementById('qty').value) || 0;
            if (qty == qtyOrder) {
                itemstatus.value = "Diterima Lengkap"
            } else if (qty < qtyOrder) {
                itemstatus.value = "Diterima Kurang";
            }

            if (qty == 0) {
                itemstatus.value = "Tidak Diterima";
            }

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



        function addItem() {

            const payload = {
                creditor_code: creditor.value,
                receiving_items_id: receiving_items_id.value,
                receiving_id: receiving_id,
                order_items_id: order_items_id,
                order_id: order_id,
                qty_received: qty_received.value,
                discount: discount.value,
                extra_discount: extra_discount.value,
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

                        let item = res.data.summary;
                        $('#d_price').val(formatRupiah(item.price_item));
                        $('#d_ppn').val(formatRupiah(item.price_ppn));
                        $('#d_total').val(formatRupiah(item.price_total));

                        orderItemsTable.ajax.reload(null, false);
                        document.getElementById("searchInput").readOnly = true;

                        // Reset hanya jika sukses
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

                    // Jangan reset input jika gagal
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
        // // Count Items Left
        // function count_itemsleft() {
        //     const qtyOrder = parseFloat(document.getElementById('qty').value) || 0;
        //     const input = document.getElementById('qty_received');
        //     let value = parseFloat(input.value) || 0;

        //     if (value > qtyOrder) {
        //         input.value = qtyOrder;
        //     }

        //     if (value < 0) {
        //         input.value = 0;
        //     }
        // }

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

        $("#items").on("select2:select", () => {
            setTimeout(() => $("#location").focus(), 100);
        });

        $("#location").on("select2:select", () => {
            setTimeout(() => $("#status").focus(), 100);
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
                extra_discount.focus();
            }
        });
        extra_discount.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                expired_date.focus();
            }
        });
        expired_date.addEventListener('keydown', function(e) {
            if (e.key == 'Enter') {
                itemstatus.focus();
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
