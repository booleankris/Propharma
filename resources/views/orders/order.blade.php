@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

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

            <div
                class="relative w-full p-6 bg-white rounded-xl shadow-sm border border-gray-100 font-poppins text-[#1c1c1c]">

                <!-- HEADER & TOP INFO -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6 pb-4 border-b border-gray-100">
                    <div>
                        <div class="flex items-center gap-3">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-800">Form Pemesanan</h1>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                <i class="fas fa-file-invoice text-[11px]"></i> {{ $order_code }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Cari dan tambahkan obat ke dalam daftar pesanan Anda.</p>
                    </div>

                    <div class="flex items-center flex-wrap md:flex-nowrap gap-3 w-full md:w-auto">
                        @if(isset($otherDrafts) && $otherDrafts->count() > 0)
                            <div class="relative">
                                <select onchange="if(this.value) window.location.href=this.value;" 
                                    class="rounded-lg border border-amber-300 bg-amber-50/80 text-amber-900 px-3 py-2 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    <option value="" disabled selected>📁 {{ $otherDrafts->count() }} Draft Lain</option>
                                    @foreach($otherDrafts as $draft)
                                        <option value="{{ route('orders.create', ['order_id' => $draft->id]) }}">
                                            {{ $draft->code }} ({{ $draft->date }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <a href="{{ route('orders.create', ['new' => 1]) }}" 
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition-all hover:shadow hover:-translate-y-0.5 focus:ring-2 focus:ring-emerald-400">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>+ Pesanan Baru</span>
                        </a>

                        <div class="flex gap-2">
                            <div class="w-28">
                                <input type="text" id="returdate"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 text-xs text-gray-600 cursor-not-allowed focus:outline-none text-center font-medium"
                                    value="{{ $now }}" readonly autocomplete="off">
                            </div>
                            <div class="w-36">
                                <input type="text" id="returnumber"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-2 text-xs text-gray-700 font-bold cursor-not-allowed focus:outline-none text-center"
                                    value="{{ $order_code }}" readonly autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MAIN SEARCH BAR -->
                <div class="relative mb-6">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <input type="text" autofocus id="searchInput"
                        class="w-full py-[9px] px-[35px] rounded-lg border border-gray-300 bg-white text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
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
                        <div id="tableScroll" style="max-height: 250px; overflow-y: auto;" onscroll="handleScroll()">
                            <table class="table table-sm table-bordered mb-0">
                                <tbody id="searchResults"></tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <!-- FORM ENTRY -->
                <form method="post" action="{{ route('orders.addItemOrder') }}"
                    class="bg-gray-50/50 rounded-xl p-5 border border-gray-100">
                    @csrf

                    {{-- Row 1: Kode, Nama, Satuan --}}
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                        <div class="md:col-span-3">
                            <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Kode
                                Obat</label>
                            <input id="medicine_code" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600 focus:outline-none"
                                placeholder="Kode Obat">
                        </div>
                        <div class="md:col-span-6">
                            <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama
                                Obat</label>
                            <input id="medicine_name" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600 focus:outline-none"
                                placeholder="Nama Obat">
                        </div>
                        <div class="md:col-span-3">
                            <label
                                class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Kemasan</label>
                            <input id="unit" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600 focus:outline-none"
                                placeholder="Kemasan">
                        </div>
                    </div>

                    {{-- Row 2: Utuh, QTY, Isi, Harga, Jumlah --}}
                    <div class="grid grid-cols-2 md:grid-cols-12 gap-4 mb-6">
                        <div class="md:col-span-2 flex flex-col justify-center">
                            <label
                                class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Kemasan</label>
                            <label class="flex items-center gap-2 h-[42px] cursor-pointer">
                                <input type="checkbox" id="pack" name="is_active"
                                    class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 transition-all cursor-pointer">
                                <span class="text-sm font-medium text-gray-700">Utuh</span>
                            </label>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">QTY
                                BPBA</label>
                            <input id="qty" type="number" name="qty"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="0" onkeyup="counttotal()">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Isi
                                Obat</label>
                            <input id="content" readonly
                                class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600 focus:outline-none"
                                placeholder="0">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Hrg
                                HNA <span id="box_price_info"
                                    class="text-[11px] text-blue-600 font-medium normal-case"></span></label>
                            <div class="relative">

                                <input id="item_price" readonly
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-4 pr-4 py-2.5 text-sm text-gray-600 focus:outline-none"
                                    placeholder="0">
                            </div>
                        </div>
                        <div class="md:col-span-3">
                            <label
                                class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Jumlah</label>
                            <div class="relative">

                                <input id="total_price" readonly name="total_price"
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 pl-4 pr-4 py-2.5 text-sm font-semibold text-gray-800 focus:outline-none"
                                    placeholder="0">
                            </div>
                        </div>
                    </div>

                    {{-- Divider --}}
                    <hr class="border-gray-200 my-5">

                    {{-- Row 3: Kreditur & Buttons --}}
                    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">

                        <div class="w-full flex flex-col gap-3">
                            {{-- Kreditur select --}}
                            <div>
                                <label
                                    class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Kreditur</label>
                                <select id="creditor" name="creditor_id"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                    <option value="">-- Pilih Kreditur --</option>
                                </select>
                            </div>

                            <div>
                                <label
                                    class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Keterangan
                                    (Opsional)</label>
                                <input id="note" type="text" name="note"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                    placeholder="Catatan untuk item ini...">
                            </div>

                            {{-- Pill editor (hidden by default) --}}
                            <div id="creditorPillEditor" class="w-full hidden">
                                <label
                                    class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Kelola
                                    Kreditur</label>
                                <div id="pillContainer"
                                    class="flex flex-wrap gap-2 mb-2 min-h-[42px] p-2 bg-white rounded-lg border border-dashed border-gray-300">
                                    <!-- Pills spawn here -->
                                </div>
                                <div class="relative">
                                    <input id="creditorPillSearch" type="text" placeholder="Cari & tambah kreditur..."
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        autocomplete="off">
                                    <ul id="creditorPillDropdown"
                                        class="absolute z-[99999] mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-xl max-h-48 overflow-y-auto hidden">
                                    </ul>
                                </div>
                            </div>
                        </div>


                    </div>
                    <div class="flex pt-5 flex-wrap md:flex-nowrap gap-3 w-full md:w-auto">
                        <button id="back" type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-all hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 w-full md:w-auto order-3 md:order-1">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                            </svg>
                            Kembali
                        </button>

                        <button onclick="openSmartOrder()" type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-1 w-full md:w-auto order-2">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                            </svg>
                            Smart Order
                        </button>

                        <button onclick="submit_data()" type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 w-full md:w-auto order-1 md:order-3">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Tambahkan
                        </button>
                    </div>
                </form>
            </div>
            <div class="mt-3 relative w-full p-[24px] bg-[#ffffff] rounded-[22px]">
                <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
                    <div class="flex items-center gap-3 flex-wrap">
                        <button onclick="completeOrder()"
                            class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-blue-600 !shadow-[0_2px_6px_#2563eb] px-6 py-4 text-sm font-xl text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" class="w-5 h-5" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                                <polyline points="17 21 17 13 7 13 7 21" />
                                <polyline points="7 3 7 8 15 8" />
                            </svg>
                            Simpan
                        </button>

                        <button onclick="printOrder()" id='printorder'
                            class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-gray-700 !shadow-[0_2px_6px_#374151] px-6 py-4 text-sm font-xl text-white hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6 9V4h12v5M6 18h12v-5H6v5zM6 14h12" />
                            </svg>
                            Cetak
                        </button>

                        <button onclick="printSPB()"
                            class="inline-flex items-center gap-2 rounded-lg  btn-pharma !bg-purple-600 !shadow-[0_2px_6px_#9333ea] px-6 py-4 text-sm font-xl text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 3h10v18l-2-1-2 1-2-1-2 1-2-1V3z" />
                            </svg>
                            SPB
                        </button>

                        <button onclick="printSPBDotMatrix()"
                            class="inline-flex items-center gap-2 rounded-lg btn-pharma !bg-teal-600 !shadow-[0_2px_6px_#0d9488] px-6 py-4 text-sm font-xl text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6 9V4h12v5M6 18h12v-5H6v5zM6 14h12" />
                            </svg>
                            SPB Dot Matrix
                        </button>
                    </div>

                    <!-- Live Sync Indicator -->

                </div>

                <table id="orderItemsTable" class="w-full">
                    <thead>
                        <tr>
                            <th>Nama Obat</th>
                            <th>Pabrik</th>
                            <th>Status PBF</th>
                            <th>Diskon</th>
                            <th>Satuan</th>
                            <th>Harga</th>
                            <th>Qty</th>
                            <th>Sisa</th>
                            <th>Keterangan</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody class="text-[11px]"></tbody>
                </table>
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
        </div>
    </section>
    <div id="smartOrderModal" class="fixed inset-0 z-[9999] hidden">
        <div class="absolute inset-0 bg-black/40" onclick="closeSmartOrder()"></div>

        <div
            class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-[90%] bg-white rounded-2xl shadow-2xl flex flex-col max-h-[85vh]">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <div>
                    <h3 class="text-lg font-semibold text-[#1c1c1c]">Smart Order</h3>
                    <p class="text-[12px] text-gray-500">Obat terlaris berdasarkan riwayat transaksi</p>
                </div>
                <button onclick="closeSmartOrder()"
                    class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
            </div>

            <div class="px-6 py-3 flex gap-2 border-b border-gray-100">
                <input type="text" id="smartSearch" placeholder="Cari obat..."
                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-purple-200">
                <input type="text" id="smartDateRange" placeholder="Rentang tanggal"
                    class="w-[200px] rounded-lg border border-gray-300 px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-purple-200">
            </div>

            <div id="smartList" class="flex-1 overflow-y-auto px-6 py-2 divide-y divide-gray-50"></div>

            <div class="flex items-center justify-between border-t border-gray-100 px-6 py-4">
                <!-- Status Jumlah Item -->
                <span id="smartSelectedCount" class="text-sm font-medium text-gray-600">
                    0 obat dipilih
                </span>

                <!-- Pembungkus Tombol Aksi -->
                <div class="flex items-center gap-3">
                    <button type="button" onclick="closeSmartOrder()"
                        class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Batal
                    </button>

                    <button type="button" onclick="confirmSmartOrder()"
                        class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                        Tambahkan Item
                    </button>
                </div>
            </div>
        </div>
    </div>
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
        var ordercode = @json($order_code);
        var orderid = @json($order_id);
        let itemcode = '';
        let itemrawprice = 0;
        let itemprice = '';
        let itemcontent = '';
        let itemqty = '';
        let itemtotal = '';
        let total_transaction = '';
        let itempack = '0';
        var itemcreditor = '';
        let allSystemCreditors = []; // all creditors from GET /creditors/all
        let medicineCreditors = []; // creditors currently linked to selected medicine
        let currentMedicineId = null;

        var pack = document.getElementById('pack');
        let orderItemsTable;
        let selectedRowData = null;
        let selectedRowIndex = null;
        let d_price = {{ $d_price }};
        let d_ppn = {{ $d_ppn }};
        let d_total = {{ $d_total }};
        let smartSelected = {}; // medicine_id -> { medicine, quantity }
        let smartPage = 1;
        let smartHasMore = true;
        let smartLoading = false;
        let smartRange;
        let isSubmitting = false;


        // Setting Initial Transaction Value
        $('#d_price').val(formatRupiah(d_price));
        $('#d_ppn').val(formatRupiah(d_ppn));
        $('#d_total').val(formatRupiah(d_total));


        $('#back').click(function() {
            window.location.href = "{{ route('receiving.index') }}";
        });
        // Datatable
        document.addEventListener('DOMContentLoaded', function() {
            loadAllSystemCreditors();
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                order: [
                    [2, 'asc']
                ],
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
                        data: 'creditors',
                        name: 'creditors.name'
                    },
                    {
                        data: 'discount',
                        name: 'discount',
                        defaultContent: '0%',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'medicines.packaging',
                        name: 'medicines.packaging'
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
                        data: 'medicines.stock',
                        name: 'medicines.stock'
                    },
                    {
                        data: 'note',
                        name: 'note',
                        defaultContent: '-'
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

        // Table click
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

            itemcode = data.medicine_id;
            itemrawprice = parseFloat(data.medicines.raw_price) || 0;
            itemcontent = parseFloat(data.medicines.content) || 1;
            itemqty = data.quantity;

            // Fill inputs
            document.getElementById('medicine_name').value = data.medicines.name ?? '';
            document.getElementById('unit').value = data.medicines.packaging ?? '';
            document.getElementById('content').value = data.medicines.content ?? '';
            document.getElementById('item_price').value = formatRupiah(itemrawprice);
            document.getElementById('qty').value = data.quantity;
            document.getElementById('medicine_code').value = data.medicines.code;
            document.getElementById('note').value = data.note ?? '';
            loadMedicineCreditors(data.medicine_id, data.creditor_code);

            if (data.pack == "1") {
                pack.checked = true;
                itempack = 1;
            } else {
                pack.checked = false;
                itempack = 0;
            }

            updateBoxPriceInfo();
            counttotal();
            document.getElementById('qty').focus();
        });

        document.getElementById('qty').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            document.getElementById('creditor').focus();
        });
        document.getElementById('creditor').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            document.getElementById('note').focus();
        });

        document.getElementById('note').addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            submit_data();
        });

        function empyCreditorOption() {
            let select = $("#creditor");
            select.empty();
        }

        function submit_data() {
            if (isSubmitting) return;
            isSubmitting = true;

            if (selectedRowData) {
                updateItem();
            } else {
                addItem();
            }
        }

        function updateItem() {
            counttotal();
            syncMedicineCreditors().then(() => {

                axios.post("{{ route('orders.updateOrderItem') }}", {
                    order_id: selectedRowData.order_item_id,
                    creditor_code: document.getElementById('creditor')?.value ?? null,
                    medicine_id: selectedRowData.medicines.id,
                    pack: itempack,
                    price: itemprice,
                    quantity: itemqty,
                    total: itemtotal,
                    note: document.getElementById('note').value,
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
                        let item = res.data.summary;
                        orderItemsTable.ajax.reload(null, false);
                        console.log(item.price);

                        d_price = item.price_item;
                        d_ppn = item.price_ppn
                        d_total = item.price_total;

                        $('#d_price').val(formatRupiah(d_price));
                        $('#d_ppn').val(formatRupiah(d_ppn));
                        $('#d_total').val(formatRupiah(d_total));
                    }
                }).catch(err => {
                    console.error(err);
                    console.log(err.response?.data);

                    alert('Update failed');
                }).finally(() => {
                    isSubmitting = false;
                });
            }).catch(err => {
                isSubmitting = false;
                console.error(err);
                alert('Gagal menyimpan kreditur!');
            });
        }

        document.addEventListener('keydown', function(e) {
            const isDeleteKey =
                e.key === 'Delete' ||
                e.key === 'Del' ||
                e.key === 'Backspace';

            if (!isDeleteKey) return;
            if (!selectedRowData) return;

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
                    let item = res.data.summary;
                    orderItemsTable.ajax.reload(null, false);
                    console.log(item.price);

                    d_price = item.price_item;
                    d_ppn = item.price_ppn
                    d_total = item.price_total;

                    $('#d_price').val(formatRupiah(d_price));
                    $('#d_ppn').val(formatRupiah(d_ppn));
                    $('#d_total').val(formatRupiah(d_total));
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

            fetch(`{{ route('orders.searchmedicine') }}?search=${keyword}&page=${page}&orderid=${orderid}`)
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('searchResults');

                    if (page === 1 && res.data.length === 0) {
                        tbody.innerHTML =
                            `
                                                                                                                                                <tr>
                                                                                                                                                    <td colspan="4" class="text-center">No data found</td>
                                                                                                                                                </tr>`;
                        hasMore = false;
                        return;
                    }

                    res.data.forEach((item, index) => {
                        tbody.insertAdjacentHTML('beforeend',
                            `
                                                                                                                                                <tr 
                                                                                                                                                    data-item='${JSON.stringify(item)}'
                                                                                                                                                    tabindex="0"
                                                                                                                                                >
                                                                                                                                                    <td>${((page - 1) * res.per_page) + index + 1}</td>
                                                                                                                                                    <td>${item.name}</td>
                                                                                                                                                </tr>
                                                                                                                                            `
                        );
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

        // Load medicine creditors


        function loadAllSystemCreditors() {
            axios.get("{{ route('creditors.all') }}")
                .then(res => {
                    allSystemCreditors = res.data.creditors;
                })
                .catch(err => console.error(err));
        }

        function loadMedicineCreditors(medicineId, preselectedCode = null) {
            currentMedicineId = medicineId;
            axios.get(`/orders/${medicineId}/creditors`)
                .then(res => {
                    medicineCreditors = res.data.creditors;
                    renderPills();
                    renderCreditorSelect(preselectedCode);
                    document.getElementById('creditorPillEditor').classList.remove('hidden');
                })
                .catch(err => console.error(err));
        }

        function renderPills() {
            const container = document.getElementById('pillContainer');
            container.innerHTML = '';

            medicineCreditors.forEach(c => {
                const pill = document.createElement('span');
                pill.className =
                    'flex items-center gap-1 bg-blue-100 text-blue-800 text-[12px] font-medium px-2.5 py-1 rounded-full';
                pill.innerHTML =
                    `
                                                                                                                                ${c.name}
                                                                                                                                <span class="text-blue-600 font-semibold">${c.discount ?? 0}%</span>
                                                                                                                                <button type="button" data-code="${c.code}" class="ml-1 text-blue-500 hover:text-red-500 font-bold">&times;</button>
                                                                                                                            `;
                pill.querySelector('button').addEventListener('click', () => {
                    medicineCreditors = medicineCreditors.filter(x => x.code !== c.code);
                    renderPills();
                    renderCreditorSelect();
                });
                container.appendChild(pill);
            });
        }

        function renderCreditorSelect(preselectedCode = null) {
            const select = document.getElementById('creditor');
            select.innerHTML = '<option value="">--Pilih Kreditur--</option>';
            medicineCreditors.forEach(c => {
                const opt = new Option(c.name, c.code);
                select.appendChild(opt);
            });
            if (preselectedCode) select.value = preselectedCode;
        }

        document.getElementById('creditorPillSearch').addEventListener('input', function() {
            const keyword = this.value.toLowerCase().trim();
            const ul = document.getElementById('creditorPillDropdown');

            if (!keyword) {
                ul.classList.add('hidden');
                return;
            }

            const filtered = allSystemCreditors.filter(c =>
                c.name.toLowerCase().includes(keyword) &&
                !medicineCreditors.find(m => m.code === c.code)
            );

            if (!filtered.length) {
                ul.classList.add('hidden');
                return;
            }

            ul.innerHTML = '';
            filtered.forEach(c => {
                const li = document.createElement('li');
                li.className = 'px-4 py-2 text-[13px] hover:bg-blue-50 cursor-pointer';
                li.textContent = c.name;
                li.addEventListener('mousedown', e => {
                    e.preventDefault();
                    medicineCreditors.push(c);
                    renderPills();
                    renderCreditorSelect();
                    document.getElementById('creditorPillSearch').value = '';
                    ul.classList.add('hidden');
                });
                ul.appendChild(li);
            });
            ul.classList.remove('hidden');
        });

        document.getElementById('creditorPillSearch').addEventListener('blur', function() {
            setTimeout(() => document.getElementById('creditorPillDropdown').classList.add('hidden'), 150);
        });

        // ── Sync creditors to medicine master before add/update ───────────
        function syncMedicineCreditors() {
            if (!currentMedicineId) return Promise.resolve();
            return axios.put(`/ordercreditors/${currentMedicineId}/sync-creditors`, {
                creditors: medicineCreditors.map(c => ({
                    code: c.code,
                    discount: c.discount ?? 0
                }))
            }, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
        }

        // ── Reset pill editor ─────────────────────────────────────────────
        function resetCreditorPills() {
            medicineCreditors = [];
            currentMedicineId = null;
            renderPills();
            document.getElementById('creditor').innerHTML = '<option value="">--Pilih Kreditur--</option>';
            document.getElementById('creditorPillSearch').value = '';
            document.getElementById('creditorPillDropdown').classList.add('hidden');
            document.getElementById('creditorPillEditor').classList.add('hidden');
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

        function updateBoxPriceInfo() {
            const boxInfo = document.getElementById('box_price_info');
            if (!boxInfo) return;
            if (pack.checked && itemcontent > 1 && itemrawprice > 0) {
                const boxPrice = itemrawprice * itemcontent;
                boxInfo.textContent = `(Hrg Box: ${formatRupiah(boxPrice)})`;
            } else {
                boxInfo.textContent = '';
            }
        }

        // Select
        function selectRow(row) {
            const item = JSON.parse(row.dataset.item);
            itemrawprice = parseFloat(item.raw_price) || 0;
            itemcontent = parseFloat(item.content) || 1;
            itemcreditor = item.creditors_id;
            itemcode = item.id;

            document.getElementById('medicine_code').value = item.code ?? '';
            document.getElementById('medicine_name').value = item.name ?? '';
            document.getElementById('unit').value = item.packaging ?? '';
            document.getElementById('content').value = item.content ?? '';
            document.getElementById('item_price').value = formatRupiah(itemrawprice);

            document.getElementById('searchDropdown').style.display = 'none';
            document.getElementById('searchInput').value = "";
            document.getElementById('creditor').value = "";

            updateBoxPriceInfo();
            counttotal();

            document.getElementById('qty')?.focus();
            loadMedicineCreditors(item.id);
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

            updateBoxPriceInfo();
            counttotal();
        });

        pack.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('qty').focus();
            }
        })

        function counttotal() {
            let qty = parseFloat(document.getElementById('qty').value) || 0;
            itemqty = qty;

            if (pack.checked) {
                itemprice = itemrawprice * itemcontent;
                itemtotal = qty * itemprice;
            } else {
                itemprice = itemrawprice;
                itemtotal = qty * itemprice;
            }
            document.getElementById('total_price').value = formatRupiah(itemtotal);
        }

        function resetInputs() {
            document.getElementById('medicine_code').value = '';
            document.getElementById('medicine_name').value = '';
            document.getElementById('unit').value = '';
            document.getElementById('qty').value = '';
            document.getElementById('content').value = '';
            document.getElementById('item_price').value = '';
            document.getElementById('total_price').value = '';
            document.getElementById('note').value = '';
            const isActive = document.getElementById('is_active');
            pack.checked = false;
            itempack = 0;

            if (isActive && isActive.checked) {
                isActive.checked = false;
            }

            // reset JS
            itemcode = '';
            itemrawprice = 0;
            itemprice = '';
            itemqty = '';
            itemcontent = 1;
            itemtotal = '';
            itemcreditor = null;
            selectedRowData = null;
            updateBoxPriceInfo();
            document.getElementById('searchInput').focus();
            resetCreditorPills();

        }

        function addItem() {
            syncMedicineCreditors().then(() => {
                const payload = {
                    order_id: orderid,
                    medicine_id: itemcode,
                    creditor_code: document.getElementById('creditor').value ?? null,
                    pack: itempack,
                    price: itemprice,
                    quantity: itemqty,
                    total: itemtotal,
                    note: document.getElementById('note').value,
                };
                axios.post("{{ route('orders.addItemOrder') }}", payload, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                }).then(res => {
                    if (res.data.success) {
                        let item = res.data.summary;
                        orderItemsTable.ajax.reload(null, false);
                        d_price = item.price_item;
                        d_ppn = item.price_ppn;
                        d_total = item.price_total;
                        $('#d_price').val(formatRupiah(d_price));
                        $('#d_ppn').val(formatRupiah(d_ppn));
                        $('#d_total').val(formatRupiah(d_total));
                        resetInputs();
                        empyCreditorOption();
                    }
                }).catch(err => {
                    console.error(err);
                    empyCreditorOption();
                    alert('Isi Form Dengan Benar!');
                }).finally(() => {
                    isSubmitting = false;
                });
            }).catch(err => {
                isSubmitting = false;
                console.error(err);
                alert('Gagal menyimpan kreditur!');
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
                    const data = res.data;

                    // SweetAlert
                    Swal.fire({
                        icon: data.status, // success or error
                        title: data.status === 'success' ? 'Berhasil' : 'Gagal',
                        text: data.message,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        if (data.redirect) {
                            window.location.href = data.redirect; // redirect after OK
                        } else {
                            // optional fallback: reload table
                            orderItemsTable.ajax.reload(null, false);
                        }

                        // reset inputs only if needed
                        resetInputs();
                        selectedRowData = null;
                        selectedRowIndex = null;
                    });

                })
                .catch(err => {
                    let message = 'Terjadi kesalahan sistem!';

                    if (err.response) {
                        if (err.response.status === 422) {
                            message = err.response.data.message;
                        } else if (err.response.data?.message) {
                            message = err.response.data.message;
                        }
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: message
                    });
                });

        }

        function validateOrderCreditorsBeforePrint() {
            if (typeof orderItemsTable === 'undefined' || !orderItemsTable) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Tabel order belum dimuat!'
                });
                return false;
            }

            const data = orderItemsTable.rows().data().toArray();

            if (!data || data.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Tidak ada item obat dalam order ini untuk dicetak.'
                });
                return false;
            }

            const invalidItems = data.filter(item => {
                const code = item.creditor_code;
                const creditorName = item.creditors;
                return !code || String(code).trim() === '' || creditorName === 'Belum Dipilih' || !creditorName;
            });

            if (invalidItems.length > 0) {
                const itemNames = invalidItems.map(item => item.medicines?.name || 'Obat').join(', ');
                Swal.fire({
                    icon: 'warning',
                    title: 'PBF Belum Dipilih',
                    text: `Terdapat item obat yang PBF/Krediturnya belum dipilih (${itemNames}). Silakan pilih PBF terlebih dahulu sebelum mencetak.`
                });
                return false;
            }

            return true;
        }

        function printSPB() {
            if (!validateOrderCreditorsBeforePrint()) return;
            window.open(`/orders/${orderid}/printspb`, "_blank");
        }

        function printSPBDotMatrix() {
            if (!validateOrderCreditorsBeforePrint()) return;

            Swal.fire({
                title: 'Mengirim ke printer...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/orders/${orderid}/printspb-dotmatrix`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        Swal.fire('Gagal', data.error, 'error');
                    } else {
                        Swal.fire('Berhasil', data.message ?? 'SP berhasil dikirim.', 'success');
                    }
                })
                .catch(() => {
                    Swal.fire('Gagal', 'Terjadi kesalahan saat mengirim ke printer.', 'error');
                });
        }

        function printOrder() {
            if (typeof orderItemsTable === 'undefined' || !orderItemsTable) {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'Tabel order belum dimuat!'
                });
                return;
            }

            const data = orderItemsTable.rows().data().toArray();
            if (!data || data.length === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Tidak ada item obat dalam order ini untuk dicetak.'
                });
                return;
            }

            const btn = document.getElementById('printorder');
            if (btn.disabled) return;

            btn.disabled = true;

            Swal.fire({
                title: 'Processing...',
                text: 'Sedang menyiapkan file Excel',
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

        // Smart Order
        function openSmartOrder() {
            smartSelected = {};
            smartPage = 1;
            smartHasMore = true;
            document.getElementById('smartList').innerHTML = '';
            document.getElementById('smartSearch').value = '';
            updateSmartSelectedCount();

            if (!smartRange) {
                const yesterday = new Date();
                yesterday.setDate(yesterday.getDate() - 1);

                smartRange = flatpickr('#smartDateRange', {
                    mode: 'range',
                    dateFormat: 'Y-m-d',
                    defaultDate: yesterday,
                    onClose: () => fetchSmartMedicines(true)
                });
            }

            document.getElementById('smartOrderModal').classList.remove('hidden');
            fetchSmartMedicines(true);
        }

        function closeSmartOrder() {
            document.getElementById('smartOrderModal').classList.add('hidden');
        }

        document.getElementById('smartSearch').addEventListener('input', debounce(() => {
            fetchSmartMedicines(true);
        }, 300));

        document.getElementById('smartList').addEventListener('scroll', function() {
            if (this.scrollTop + this.clientHeight >= this.scrollHeight - 40) {
                fetchSmartMedicines(false);
            }
        });

        function debounce(fn, delay) {
            let t;
            return (...args) => {
                clearTimeout(t);
                t = setTimeout(() => fn(...args), delay);
            };
        }

        function fetchSmartMedicines(reset) {
            if (smartLoading || (!reset && !smartHasMore)) return;
            smartLoading = true;

            if (reset) {
                smartPage = 1;
                smartHasMore = true;
                document.getElementById('smartList').innerHTML = '';
            }

            const dates = smartRange.selectedDates;
            const params = new URLSearchParams({
                page: smartPage,
                order_id: orderid,
                search: document.getElementById('smartSearch').value,
                date_from: dates[0] ? flatpickr.formatDate(dates[0], 'Y-m-d') : '',
                date_to: dates[1] ? flatpickr.formatDate(dates[1], 'Y-m-d') : (dates[0] ? flatpickr.formatDate(
                    dates[0], 'Y-m-d') : ''),
            });

            axios.get(`{{ route('orders.smartMedicines') }}?${params}`)
                .then(res => {
                    const data = res.data;
                    const list = document.getElementById('smartList');

                    if (smartPage === 1 && data.data.length === 0) {
                        list.innerHTML =
                            `<div class="text-center text-gray-400 text-[13px] py-10">Tidak ada data terjual pada rentang ini</div>`;
                    }

                    data.data.forEach(med => {
                        const row = document.createElement('div');
                        row.className = 'flex items-center gap-3 py-3';
                        row.innerHTML = `
                                    <input type="checkbox" data-id="${med.medicine_id}"
                                        class="smart-checkbox h-4 w-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-[13px] font-medium text-gray-900 truncate">${med.name}</div>
                                        <div class="text-[12px] text-gray-500">${med.code} · ${med.packaging ?? '-'}</div>
                                    </div>
                                    <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full whitespace-nowrap">
                                        Terjual ${med.total_sold}
                                    </span>
                                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full whitespace-nowrap">
                                        Minimal Stok : ${med.min_stock}
                                    </span>
                                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-full whitespace-nowrap">
                                        Stok : ${med.stocks}
                                    </span>
                                    <input type="number" min="1" value="1" data-id="${med.medicine_id}"
                                        class="smart-qty w-16 rounded-lg border border-gray-300 px-2 py-1.5 text-[13px] text-center hidden">
                                `;

                        const checkbox = row.querySelector('.smart-checkbox');
                        const qtyInput = row.querySelector('.smart-qty');

                        checkbox.addEventListener('change', function() {
                            qtyInput.classList.toggle('hidden', !this.checked);
                            if (this.checked) {
                                smartSelected[med.medicine_id] = {
                                    medicine: med,
                                    quantity: parseInt(qtyInput.value) || 1
                                };
                            } else {
                                delete smartSelected[med.medicine_id];
                            }
                            updateSmartSelectedCount();
                        });

                        qtyInput.addEventListener('input', function() {
                            if (smartSelected[med.medicine_id]) {
                                smartSelected[med.medicine_id].quantity = parseInt(this.value) || 1;
                            }
                        });

                        list.appendChild(row);
                    });

                    smartHasMore = data.current_page < data.last_page;
                    smartPage++;
                })
                .finally(() => smartLoading = false);
        }

        function updateSmartSelectedCount() {
            const count = Object.keys(smartSelected).length;
            document.getElementById('smartSelectedCount').textContent = `${count} obat dipilih`;
        }

        function confirmSmartOrder() {
            const items = Object.values(smartSelected).map(s => ({
                medicine_id: s.medicine.medicine_id,
                quantity: s.quantity
            }));

            if (!items.length) return;

            axios.post("{{ route('orders.addItemsBulk') }}", {
                order_id: orderid,
                items: items
            }, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).then(res => {
                if (res.data.success) {
                    orderItemsTable.ajax.reload(null, false);
                    const item = res.data.summary;
                    d_price = item.price_item;
                    d_ppn = item.price_ppn;
                    d_total = item.price_total;
                    $('#d_price').val(formatRupiah(d_price));
                    $('#d_ppn').val(formatRupiah(d_ppn));
                    $('#d_total').val(formatRupiah(d_total));
                    closeSmartOrder();
                }
            }).catch(err => {
                console.error(err);
                alert('Gagal menambahkan item!');
            });
        }

        // =================== LIVE SYNC & HO REAL-TIME DETECTOR ===================
        let currentOrderHash = null;
        let isPollingSync = false;
        let knownHoItemsMap = {}; // item_id -> { creditor_name, updated_at }

        function pollOrderChanges() {
            if (isPollingSync || (typeof isSubmitting !== 'undefined' && isSubmitting)) return;

            // Do not reload table if edit modal or smart order modal is open to avoid interrupting active user edits
            const editModal = document.getElementById('editModal');
            if (editModal && !editModal.classList.contains('hidden')) return;

            const smartModal = document.getElementById('smartOrderModal');
            if (smartModal && !smartModal.classList.contains('hidden')) return;

            isPollingSync = true;

            axios.get("{{ route('orders.checkUpdates') }}", {
                    params: {
                        order_id: orderid,
                        last_hash: currentOrderHash
                    }
                })
                .then(res => {
                    if (res.data && res.data.success) {
                        const newHash = res.data.hash;
                        const hoItems = res.data.ho_items || [];

                        if (currentOrderHash !== null && res.data.changed) {
                            // Detect specific new changes made by HO
                            const newlyChangedHoMedicines = [];
                            hoItems.forEach(item => {
                                const prev = knownHoItemsMap[item.id];
                                if (!prev || prev.creditor_name !== item.creditor_name || prev.updated_at !==
                                    item.updated_at) {
                                    newlyChangedHoMedicines.push(
                                        `<b>${item.medicine_name}</b> &rarr; <span style="color:#4f46e5;font-weight:700;">${item.creditor_name}</span>`
                                    );
                                }
                            });

                            // Show toast notification if HO made changes
                            if (newlyChangedHoMedicines.length > 0) {
                                if (typeof iziToast !== 'undefined') {
                                    iziToast.info({
                                        title: '🔔 PBF Diperbarui oleh HO',
                                        message: newlyChangedHoMedicines.slice(0, 4).join('<br>') + (
                                            newlyChangedHoMedicines.length > 4 ?
                                            `<br><i>+${newlyChangedHoMedicines.length - 4} obat lainnya</i>` :
                                            ''),
                                        position: 'topRight',
                                        timeout: 8000,
                                        transitionIn: 'fadeInDown',
                                        transitionOut: 'fadeOutUp',
                                    });
                                }
                            }

                            // Reload table without resetting user scroll/page
                            if (typeof orderItemsTable !== 'undefined' && orderItemsTable) {
                                orderItemsTable.ajax.reload(null, false);
                            }

                            // Update summary values smoothly
                            if (res.data.summary) {
                                $('#d_price').val(formatRupiah(res.data.summary.price_item));
                                $('#d_ppn').val(formatRupiah(res.data.summary.price_ppn));
                                $('#d_total').val(formatRupiah(res.data.summary.price_total));
                            }
                        }

                        // Update known map and hash
                        currentOrderHash = newHash;
                        knownHoItemsMap = {};
                        hoItems.forEach(item => {
                            knownHoItemsMap[item.id] = {
                                creditor_name: item.creditor_name,
                                updated_at: item.updated_at
                            };
                        });
                    }
                })
                .catch(err => {
                    // Silently ignore background polling network errors
                    console.warn('Order sync polling:', err);
                })
                .finally(() => {
                    isPollingSync = false;
                });
        }

        function manualRefreshOrderItems() {
            if (typeof orderItemsTable !== 'undefined' && orderItemsTable) {
                orderItemsTable.ajax.reload(null, false);
            }
            pollOrderChanges();
            if (typeof iziToast !== 'undefined') {
                iziToast.success({
                    title: 'Disinkronkan',
                    message: 'Data order telah disinkronkan dengan server.',
                    position: 'topRight',
                    timeout: 2500
                });
            }
        }

        // Start live polling every 5 seconds
        setInterval(pollOrderChanges, 5000);
        setTimeout(pollOrderChanges, 1200);
    </script>


@endsection
