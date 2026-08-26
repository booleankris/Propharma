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
    <section class="section py-4 px-[18px] bg-gray-50 min-h-screen pb-28">
        <div class="mx-auto space-y-6">

            <!-- HEADER HALAMAN -->
            <div class="flex items-center justify-between bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div>
                    <h1 class="text-2xl font-bold uppercase font-poppins text-gray-800">Penerimaan Barang</h1>
                    <p class="text-xs text-gray-500 mt-1">Kelola penerimaan obat dari kreditur dan input detail faktur</p>
                </div>
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                    Penerimaan Baru
                </span>
            </div>

            <!-- BLOK 1: INFORMASI FAKTUR & SUPPLIER -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-100 text-blue-600 text-xs font-bold">1</span>
                        Informasi Faktur & Supplier
                    </h2>
                    <span class="text-xs text-gray-400">* Wajib diisi sebelum konfirmasi item</span>
                </div>

                <!-- Grid Form Informasi Utama -->
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">

                    <!-- Cari Kreditur -->
                    <div class="lg:col-span-2">
                        <label for="creditor" class="block text-xs font-semibold text-gray-700 mb-1">
                            Cari Kreditur <span class="text-red-500">*</span>
                        </label>
                        <select autofocus id="creditor" name="creditor_id"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                            <option value="" required>---- Pilih Kreditur ----</option>
                            @foreach ($creditorOption as $creditor)
                                <option value="{{ $creditor->code }}" required>{{ $creditor->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Tanggal Terima (Readonly) -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tanggal Terima</label>
                        <input type="text" id="returdate" readonly value="{{ $now }}" onkeyup="SearchBPBA(this.value)"
                            autocomplete="off"
                            class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3.5 py-2.5 text-xs text-gray-500 cursor-not-allowed">
                    </div>

                    <!-- Nomor Terima (Readonly) -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nomor Terima</label>
                        <input type="text" id="returnumber" name="receiving_number" readonly
                            value="(Otomatis saat simpan faktur)" autocomplete="off"
                            class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3.5 py-2.5 text-xs text-gray-500 cursor-not-allowed font-mono italic">
                    </div>

                    <!-- Nomor BPBA -->
                    <div class="relative">
                        <label for="searchInput" class="block text-xs font-semibold text-gray-700 mb-1">Cari Nomor
                            BPBA</label>
                        <input type="text" id="searchInput" autofocus readonly placeholder="Cari Nomor BPBA..."
                            oninput="SearchBPBA(this.value)" autocomplete="off"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

                        <!-- Search Dropdown Table -->
                        <div id="searchDropdown"
                            class="dropdown-table absolute left-0 top-full mt-1 w-full z-50 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                            style="display:none;">
                            <table class="table table-sm table-bordered mb-0 w-full text-xs">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="p-2 border-b text-left">#</th>
                                        <th class="p-2 border-b text-left">Code</th>
                                    </tr>
                                </thead>
                            </table>
                            <div id="tableScroll" style="max-height: 200px; overflow-y: auto;" onscroll="handleScroll()">
                                <table class="table table-sm table-bordered mb-0 w-full text-xs">
                                    <tbody id="searchResults"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Nomor Faktur -->
                    <div>
                        <label for="invoice_number" class="block text-xs font-semibold text-gray-700 mb-1">Nomor Faktur
                            <span class="text-red-500">*</span></label>
                        <input id="invoice_number" name="invoice_number" value="{{ $transaction->invoice_number }}"
                            placeholder="Nomor Faktur"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Tanggal Faktur -->
                    <div>
                        <label for="invoice_date" class="block text-xs font-semibold text-gray-700 mb-1">Tanggal
                            Faktur</label>
                        <input id="invoice_date" type="date" name="invoice_date"
                            value="{{ $transaction->invoice_date ? \Carbon\Carbon::createFromFormat('d/m/Y', $transaction->invoice_date)->format('Y-m-d') : \Carbon\Carbon::now()->format('Y-m-d') }}"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Jenis Bayar -->
                    <div>
                        <label for="invoice_payment" class="block text-xs font-semibold text-gray-700 mb-1">Jenis
                            Bayar</label>
                        <select id="invoice_payment" name="invoice_payment"
                            class="select2 w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Pilih --</option>
                            <option value="KREDIT">Kredit</option>
                            <option value="TUNAI">Tunai</option>
                            <option value="KONSINYASI">Konsinyasi</option>
                        </select>
                    </div>

                    <!-- Waktu Kredit (Hari) -->
                    <div>
                        <label for="invoice_times" class="block text-xs font-semibold text-gray-700 mb-1">Tempo
                            (Hari)</label>
                        <input id="invoice_times" type="number" value="{{ $transaction->invoice_times }}"
                            oninput="count_due()" name="invoice_times" placeholder="0"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>

                    <!-- Jatuh Tempo (Readonly) -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tgl Jatuh Tempo</label>
                        <input id="invoice_due" readonly name="invoice_due" value="{{ $transaction->invoice_due }}"
                            type="date"
                            class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3.5 py-2.5 text-xs text-gray-500 cursor-not-allowed">
                    </div>

                    <!-- Jenis PPN -->
                    <div>
                        <label for="invoice_ppn" class="block text-xs font-semibold text-gray-700 mb-1">Jenis PPN</label>
                        <select id="invoice_ppn" name="invoice_ppn"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="INCLUDE">Include</option>
                            <option value="EXCLUDE">Exclude</option>
                            <option value="TANPA">Tanpa</option>
                        </select>
                    </div>

                    <!-- Pilih Faktur untuk dicetak SP -->
                    <div>
                        <label for="print_faktur" class="block text-xs font-semibold text-gray-700 mb-1">Pilih Faktur (Cetak
                            SP)</label>
                        <select id="print_faktur" name="print_faktur"
                            class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all">
                            <option value="">-- Pilih Faktur --</option>
                            @if(isset($allFakturs) && $allFakturs->count() > 0)
                                @foreach($allFakturs as $detail)
                                    <option value="{{ $detail->id }}" data-creditor="{{ $detail->creditor_code }}">Faktur:
                                        {{ $detail->invoice_number ?: $detail->receiving_details_code }}
                                        ({{ $detail->sp_code ?? 'Belum Disimpan' }})</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <!-- Cetak SP Faktur Ini -->
                    <div class="flex items-end">
                        <button type="button" onclick="printSPBSelectedFaktur()"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 text-[12px] font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition-all duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 9V2h12v7" />
                                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
                                <rect x="6" y="14" width="12" height="8" />
                            </svg>
                            Cetak SP Faktur Ini
                        </button>
                    </div>

                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 space-y-4">

                <!-- Baris Tombol Aksi Utama -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                    <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        Pilih Item Yang Diterima
                    </h2>
                </div>

                <!-- Tabel Produk Responsive -->
                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table id="orderItemsTable" class="w-full text-left text-xs text-gray-700">
                        <thead
                            class="bg-gray-50 text-[11px] uppercase font-semibold text-gray-600 border-b border-gray-200">
                            <tr>
                                <th class="px-4 py-3">Nama Obat</th>
                                <th class="px-3 py-3 text-center">QTY Beli</th>
                                <th class="px-3 py-3 text-center">QTY Diterima</th>
                                <th class="px-4 py-3 text-right">HNA</th>
                                <th class="px-4 py-3 text-right">Harga PPN</th>
                                <th class="px-3 py-3 text-center">Diskon</th>
                                <th class="px-3 py-3 text-center">Extra Diskon</th>
                                <th class="px-3 py-3 text-center">Diskon Diterima</th>
                                <th class="px-3 py-3">Lokasi</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-3 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white text-[12px]">
                            <!-- Content via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- BLOK 2: INPUT ITEM OBAT (ZONA INPUT AKTIF) -->
            <div class="bg-blue-50/40 p-6 rounded-2xl shadow-sm border border-blue-200 space-y-4">
                <div class="flex items-center justify-between border-b border-blue-100 pb-3">
                    <h2 class="text-base font-semibold text-blue-900 flex items-center gap-2">
                        <span
                            class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">2</span>
                        Input Detail Item Obat
                    </h2>
                    <span class="text-xs bg-blue-100 text-blue-800 font-medium px-2.5 py-1 rounded-md">Isi parameter item
                        lalu klik Konfirmasi</span>
                </div>

                <form method="post" id="checkout_detail" action="{{ route('orders.addItemOrder') }}" class="space-y-4">
                    @csrf
                    <div class="hidden">
                        <input type="text" id="receiving_items_id">
                        <input type="text" id="receiving_details_id">
                    </div>

                    <!-- Sub-bagian A: Informasi Referensi Obat (Auto-filled / Readonly) -->
                    <div class="bg-white p-4 rounded-xl border border-blue-100 space-y-2">
                        <span class="text-[11px] font-bold tracking-wider uppercase text-gray-400">Data Referensi Obat
                            (Otomatis)</span>
                        <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-1">Kode Obat</label>
                                <input id="medicine_code" type="text" readonly placeholder="-"
                                    class="w-full rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-xs text-gray-600 font-mono">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-[11px] text-gray-500 mb-1">Nama Obat</label>
                                <input id="medicine_name" type="text" readonly placeholder="Pilih obat dari daftar..."
                                    class="w-full rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-xs text-gray-700 font-medium">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-1">Satuan</label>
                                <input id="unit" type="text" readonly placeholder="-"
                                    class="w-full rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-xs text-gray-600">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-1">Isi Obat</label>
                                <input id="content" type="text" readonly placeholder="-"
                                    class="w-full rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-xs text-gray-600">
                            </div>
                            <div>
                                <label class="block text-[11px] text-gray-500 mb-1">QTY Beli</label>
                                <input id="qty" type="number" readonly name="qty" placeholder="0" oninput="counttotal()"
                                    class="w-full rounded-lg bg-gray-100 border border-gray-200 px-3 py-2 text-xs text-gray-700 font-bold">
                            </div>
                        </div>
                    </div>

                    <!-- Sub-bagian B: Input Penerimaan Real (Active Fields) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

                        <!-- Kemasan -->
                        <div class="flex flex-col justify-end">
                            <label class="block text-xs font-semibold text-gray-700 mb-2">Kemasan Utuh</label>
                            <label
                                class="flex items-center gap-2 bg-white px-3 py-2 rounded-xl border border-gray-300 cursor-pointer hover:bg-gray-50">
                                <input type="checkbox" disabled id="pack" name="is_active"
                                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-xs font-medium text-gray-700">Utuh</span>
                            </label>
                        </div>

                        <!-- QTY Diterima -->
                        <div>
                            <label for="qty_received" class="block text-xs font-semibold text-blue-900 mb-1">QTY Diterima
                                <span class="text-red-500">*</span></label>
                            <input id="qty_received" type="number" name="qty_received" placeholder="0"
                                oninput="counttotalreceived()"
                                class="w-full rounded-xl border border-blue-400 bg-white px-3.5 py-2.5 text-xs text-gray-900 font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Hrg HNA -->
                        <div>
                            <label for="item_price" class="block text-xs font-semibold text-blue-900 mb-1">Harga HNA <span id="box_price_info" class="text-[10px] text-blue-600 font-normal"></span> <span
                                    class="text-red-500">*</span></label>
                            <input id="item_price" type="text" placeholder="Rp 0"
                                class="w-full rounded-xl border border-blue-400 bg-white px-3.5 py-2.5 text-xs text-gray-900 font-bold focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Batch -->
                        <div>
                            <label for="batch" class="block text-xs font-semibold text-gray-700 mb-1">No. Batch <span
                                    class="text-red-500">*</span></label>
                            <input id="batch" name="batch" type="text" placeholder="Masukkan Batch"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>


                        <!-- Diskon -->
                        <div>
                            <label for="discount" class="block text-xs font-semibold text-gray-700 mb-1">Diskon
                                (%) <span id="discount_info" class="text-[10px] text-gray-500 font-normal"></span></label>
                            <input id="discount" value="0" type="number" placeholder="0"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Ekstra Diskon -->
                        <div>
                            <label for="extra_discount" class="block text-xs font-semibold text-gray-700 mb-1">Ex. Diskon
                                (%) <span id="extra_discount_info" class="text-[10px] text-gray-500 font-normal"></span></label>
                            <input id="extra_discount" value="0" type="number" required
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Exp Date -->
                        <div>
                            <label for="expired_date" class="block text-xs font-semibold text-gray-700 mb-1">Exp Date
                                <span class="text-red-500">*</span></label>
                            <input id="expired_date" type="date" name="expired_date"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Status Barang -->
                        <div>
                            <label for="status" class="block text-xs font-semibold text-gray-700 mb-1">Status
                                Barang</label>
                            <input id="status" name="status" type="text" placeholder="Baik / Rusak"
                                class="w-full rounded-xl border border-gray-300 bg-white px-3.5 py-2.5 text-xs text-gray-800 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <!-- Total Price (Calculated) -->
                        <div class="col-span-2 sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Subtotal Item</label>
                            <input id="total_price" type="text" readonly name="total_price" placeholder="Rp 0"
                                class="w-full rounded-xl border border-gray-200 bg-gray-100 px-3.5 py-2.5 text-xs text-emerald-700 font-bold cursor-not-allowed">
                        </div>

                        <!-- Hidden Fields (Etalase & Lokasi) -->
                        <div class="hidden">
                            <select id="items" name="etalase" required class="select2">
                                <option value="">-- Pilih Etalase --</option>
                            </select>
                            <select name="location" id="location">
                                <option value="">-- Pilih Lokasi --</option>
                            </select>
                        </div>
                        <div class="col-span-2 sm:col-span-2 flex items-end gap-2">


                            <button onclick="addNewBatch()" type="button"
                                class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-[#2c3862] font-['Poppins'] px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-all focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-package-export">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 21l-8 -4.5v-9l8 -4.5l8 4.5v4.5" />
                                    <path d="M12 12l8 -4.5" />
                                    <path d="M12 12v9" />
                                    <path d="M12 12l-8 -4.5" />
                                    <path d="M15 18h7" />
                                    <path d="M19 15l3 3l-3 3" />
                                </svg>
                                Tambah Batch
                            </button>
                            <button onclick="resetInputs()" type="button" id="back"
                                class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl text-center !text-[#000] bg-[#f6d448] px-4 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-300 transition-all">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-restore">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M3.06 13a9 9 0 1 0 .49 -4.087" />
                                    <path d="M3 4.001v5h5" />
                                    <path d="M11 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                </svg>
                                Reset
                            </button>
                        </div>
                    </div>
                    <!-- Tombol Aksi Form Item -->
                    <div class="col-span-2 sm:col-span-2 flex items-end gap-2">
                        <button onclick="addItem()" type="button"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-all focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Konfirmasi Item
                        </button>

                    </div>
                </form>
            </div>
        </div>
        <div class="bg-blue-50/40 p-6 my-3 rounded-2xl shadow-sm border border-blue-200 space-y-4">
            <div class="flex items-center justify-between border-b border-blue-100 pb-3">
                <h2 class="text-base font-semibold text-blue-900 flex items-center gap-2">
                    <span
                        class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-600 text-white text-xs font-bold">3</span>
                    Finalisasi Penerimaan
                </h2>
                <span class="text-xs bg-blue-100 text-blue-800 font-medium px-2.5 py-1 rounded-md">Konfirmasi
                    Penerimaan</span>
            </div>
            <div class="flex flex-wrap py-3 px-2 items-center justify-between gap-3 border-b border-gray-100 pb-4">

                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="saveOrder()" type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-blue-50 border border-blue-200 px-4 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        Simpan Draft
                    </button>
                    <button onclick="printReceiving()" type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-gray-50 border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Cetak
                    </button>
                    <button onclick="printSPB()" type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-purple-50 border border-purple-200 px-4 py-2 text-xs font-semibold text-purple-700 hover:bg-purple-100 transition-all">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        SPB
                    </button>
                    <button onclick="completeOrder()" type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-all focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                        </svg>
                        Selesaikan Pesanan
                    </button>
                </div>
            </div>

        </div>

        <!-- FIXED BOTTOM BAR (TOTAL SUDAH DIBERSIHKAN) -->
        <div
            class="fixed right-0 bottom-0 md:w-[70%] w-full rounded-tl-2xl bg-white border-t border-gray-200 shadow-[0_-4px_20px_rgba(0,0,0,0.08)] z-40">
            <div class="max-w-7xl mx-auto px-6 py-3 flex flex-wrap items-center justify-between gap-4">
                <div class="text-xs text-gray-500 font-medium">
                    Ringkasan Transaksi Penerimaan
                </div>

                <div class="flex items-center gap-6">
                    <!-- HARGA HNA -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase">Harga (HNA):</span>
                        <input id="d_price" readonly placeholder="Rp 0"
                            class="w-32 rounded-lg bg-gray-50 border border-gray-200 px-3 py-1.5 text-xs text-gray-800 font-semibold text-right cursor-default focus:outline-none">
                    </div>

                    <!-- PPN -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase">PPN:</span>
                        <input id="d_ppn" readonly placeholder="Rp 0"
                            class="w-32 rounded-lg bg-gray-50 border border-gray-200 px-3 py-1.5 text-xs text-gray-800 font-semibold text-right cursor-default focus:outline-none">
                    </div>

                    <!-- TOTAL GRAND -->
                    <div class="flex items-center gap-2 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-100">
                        <span class="text-xs font-bold text-emerald-800 uppercase">Total:</span>
                        <input id="d_total" readonly placeholder="Rp 0"
                            class="w-36 bg-transparent border-none text-sm text-emerald-700 font-extrabold text-right focus:outline-none cursor-default">
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
        let isEditMode = false;
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
        let itemrawprice = 0;
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
        let total_received = '';

        function updateBoxPriceInfo() {
            const boxInfo = document.getElementById('box_price_info');
            if (!boxInfo) return;
            if (pack.checked && itemcontent > 1 && itemrawprice > 0) {
                const boxPrice = itemrawprice * itemcontent;
                boxInfo.textContent = `(Box: Rp ${formatRupiah(boxPrice)})`;
            } else {
                boxInfo.textContent = '';
            }
        }
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
        itemPriceInput.addEventListener('keyup', function (e) {
            let rawVal = this.value.replace(/[^\d-]/g, '');
            itemprice = parseFloat(rawVal) || 0;
            this.value = formatRupiah(rawVal);
            counttotalreceived();
        });
        itemPriceInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                batch.focus();
            }
        });
        const discountInput = document.getElementById('discount');
        const extraDiscountInput = document.getElementById('extra_discount');

        function calculateVisualTotal() {
            let grossTotal = parseFloat(total_transaction) || 0;
            let discVal = parseFloat(discountInput.value.replace(',', '.')) || 0;
            let extraDiscVal = parseFloat(extraDiscountInput.value.replace(',', '.')) || 0;

            let nomDisc = discVal <= 100 && discVal > 0 ? Math.round(grossTotal * discVal / 100) : discVal;
            let nomExtraDisc = extraDiscVal <= 100 && extraDiscVal > 0 ? Math.round(grossTotal * extraDiscVal / 100) : extraDiscVal;

            let discInfo = document.getElementById('discount_info');
            let extraDiscInfo = document.getElementById('extra_discount_info');
            
            if (discInfo) {
                discInfo.textContent = discVal <= 100 && discVal > 0 ? `(Rp ${formatRupiah(nomDisc)})` : '';
            }
            if (extraDiscInfo) {
                extraDiscInfo.textContent = extraDiscVal <= 100 && extraDiscVal > 0 ? `(Rp ${formatRupiah(nomExtraDisc)})` : '';
            }

            let netBeforePpn = Math.max(0, grossTotal - nomDisc - nomExtraDisc);
            let ppnType = (invoice_ppn && invoice_ppn.value) ? invoice_ppn.value.toUpperCase().trim() : 'TANPA';
            let finalSubtotal = netBeforePpn;

            if (ppnType === 'EXCLUDE') {
                let ppnNominal = Math.round(netBeforePpn * 0.11);
                finalSubtotal = netBeforePpn + ppnNominal;
            } else if (ppnType === 'INCLUDE') {
                finalSubtotal = netBeforePpn;
            } else { // TANPA
                finalSubtotal = netBeforePpn;
            }

            document.getElementById('total_price').value = formatRupiah(Math.max(0, finalSubtotal));
            return finalSubtotal;
        }

        function handleDiscount(inputElement) {
            calculateVisualTotal();
        }

        function addNewBatch() {
            isEditMode = false;
            document.getElementById('receiving_items_id').value = '';

            // Clear batch-specific fields only
            document.getElementById('qty_received').value = '';
            document.getElementById('batch').value = '';
            document.getElementById('discount').value = '0';
            document.getElementById('extra_discount').value = '0';
            document.getElementById('expired_date').value = '';
            document.getElementById('total_price').value = '';
            document.getElementById('status').value = '';

            // Keep medicine details filled
            document.getElementById('qty_received').focus();

            iziToast.info({
                title: 'Tambah Batch Baru',
                message: 'Siap memasukkan batch baru untuk obat ini',
                position: 'topRight'
            });
        }
        // Event listeners
        discountInput.addEventListener('input', () => handleDiscount(discountInput));
        extraDiscountInput.addEventListener('input', () => handleDiscount(extraDiscountInput));
        invoice_ppn.addEventListener('change', () => calculateVisualTotal());


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

            document.getElementById('receiving_items_id').value = '';

            itemcode = null;
            itemprice = null;
            itemqty = null;
            itemcontent = null;
            document.getElementById('creditor').focus();
        }

        function printSPB() {
            window.open(`/orders/${ordersid}/printspb`, "_blank");
        }

        function printSPBSelectedFaktur() {
            const selectedFaktur = $('#print_faktur').val();
            if (!selectedFaktur) {
                iziToast.warning({ title: 'Peringatan', message: 'Pilih faktur terlebih dahulu!', position: 'topRight' });
                return;
            }
            if (!ordersid) {
                iziToast.warning({ title: 'Peringatan', message: 'Pilih order terlebih dahulu!', position: 'topRight' });
                return;
            }
            window.open(`/receiving/${ordersid}/printspbfinal/faktur/${selectedFaktur}`, "_blank");
        }

        $('#creditor').on('change', function () {
            const selectedCreditor = $(this).val();
            const $printFaktur = $('#print_faktur');
            $printFaktur.val(''); // reset selection
            let lastVisibleOptionVal = '';
            $printFaktur.find('option').each(function () {
                if ($(this).val() === "") return; // keep default
                if ($(this).data('creditor') === selectedCreditor) {
                    $(this).removeAttr('disabled').show();
                    lastVisibleOptionVal = $(this).val();
                } else {
                    $(this).attr('disabled', 'disabled').hide();
                }
            });
            if (lastVisibleOptionVal !== '') {
                $printFaktur.val(lastVisibleOptionVal);
            }
        });

        function printSPBItem(orderItemId) {
            if (!ordersid) {
                iziToast.warning({ title: 'Peringatan', message: 'Data order belum tersedia!', position: 'topRight' });
                return;
            }
            window.open(`/receiving/${ordersid}/printspbfinal/item/${orderItemId}`, "_blank");
        }

        function printSPBFaktur(receivingDetailsId, creditorCode) {
            if (!ordersid) {
                iziToast.warning({ title: 'Peringatan', message: 'Data order belum tersedia!', position: 'topRight' });
                return;
            }
            window.open(`/receiving/${ordersid}/printspbfinal/faktur/${receivingDetailsId}`, "_blank");
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
        document.addEventListener('DOMContentLoaded', function () {
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
                    data: function (d) {
                        d.order_id = ordersid;
                        d.creditor_code = $('#creditor').val();
                    }
                },
                columns: [{
                    data: 'medicines.name',
                    defaultContent: '-'
                },
                {
                    data: 'quantity',
                    defaultContent: '0'
                },
                {
                    data: 'qty_received',
                    defaultContent: '0'
                },
                {
                    data: 'price',
                    defaultContent: 'Rp 0'
                },
                {
                    data: 'price_ppn',
                    defaultContent: 'Rp 0'
                },
                {
                    data: 'receiving_items.discount',
                    defaultContent: '0'
                },
                {
                    data: 'receiving_items.extra_discount',
                    defaultContent: '0'
                },
                {
                    data: 'creditor_discount',
                    defaultContent: '0%'
                },
                {
                    data: 'receiving_items.locations.name',
                    defaultContent: '-'
                },
                {
                    data: 'total',
                    defaultContent: 'Rp 0'
                },
                {
                    data: 'receiving_items.status',
                    defaultContent: '-'
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    defaultContent: '',
                    render: function (data, type, row) {
                        if (!row.receiving_items || !row.receiving_items.id) {
                            return '-';
                        }
                        const saved = row.receiving_items.batches_id != null;
                        let html = '';
                        if (row.receiving_items.receiving_details_id) {
                            html += `<button type="button" onclick="printSPBFaktur(${row.receiving_items.receiving_details_id}, '${row.creditor_code}')" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition-all" title="Cetak SP Faktur Ini"><svg xmlns='http://www.w3.org/2000/svg' class='w-3 h-3' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M6 9V2h12v7'/><path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'/><rect x='6' y='14' width='12' height='8'/></svg>SP Faktur</button>`;
                        }
                        html += `<button type="button" onclick="deleteDraftItem(${row.receiving_items.id}, ${saved})" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[10px] font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-all" title="Hapus Item"><svg xmlns='http://www.w3.org/2000/svg' class='w-3 h-3' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M3 6h18'/><path d='M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2'/><path d='M10 11v6'/><path d='M14 11v6'/></svg>Hapus</button>`;
                        return html;
                    }
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


            qty_received.addEventListener('keyup', function (e) {

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
        $('#orderItemsTable').on('click', 'tbody tr', function () {
            $('#orderItemsTable tbody tr').removeClass('selected');
            $(this).addClass('selected');

            selectedRowData = orderItemsTable.row(this).data();
            order_items_id = selectedRowData.id;
            order_id = selectedRowData.order_id;
            
            if (selectedRowData.receiving_items && selectedRowData.receiving_items.receiving_details_id) {
                $('#print_faktur').val(selectedRowData.receiving_items.receiving_details_id);
            }
        });

        $('#orderItemsTable tbody').on('dblclick', 'tr', function () {
            const data = orderItemsTable.row(this).data();
            if (!data) return;

            isEditMode = !!data.receiving_items?.id;

            // Correctly map IDs without duplicating overwrites
            document.getElementById('receiving_items_id').value = data.receiving_items?.id ?? '';
            document.getElementById('receiving_details_id').value = data.receiving_items?.receiving_details_id ??
                '';

            itemcode = data.medicine_id;
            itemrawprice = parseFloat(data.medicines?.raw_price) || 0;
            itemcontent = parseFloat(data.medicines?.content) || 1;
            itemqty = data.quantity;

            // Safely map medicine data
            document.getElementById('medicine_name').value = data.medicines?.name ?? '';
            document.getElementById('unit').value = data.medicines?.unit ?? '';
            document.getElementById('content').value = data.medicines?.content ?? '';
            document.getElementById('medicine_code').value = data.medicines?.code ?? '';

            document.getElementById('qty').value = data.quantity ?? 0;
            document.getElementById('qty_received').value = data.receiving_items?.qty_received ?? '';

            if (data.pack == "1") {
                itemprice = itemrawprice * itemcontent;
                document.getElementById('item_price').value = formatRupiah(itemprice);
            } else {
                itemprice = itemrawprice;
                document.getElementById('item_price').value = formatRupiah(itemprice);
            }

            document.getElementById('total_price').value = data.total ?? '';

            document.getElementById('batch').value = data.receiving_items?.batch ?? '';
            document.getElementById('discount').value = data.receiving_items?.discount ?? 0;
            document.getElementById('extra_discount').value = data.receiving_items?.extra_discount ?? 0;
            document.getElementById('status').value = data.receiving_items?.status ?? '';
            document.getElementById('expired_date').value = data.receiving_items?.expired_date ?? '';

            setSelect2AjaxValue("#location", data.receiving_items?.locations?.id, data.receiving_items?.locations
                ?.name);
            setSelect2AjaxValue("#items", data.receiving_items?.etalases?.id, data.receiving_items?.etalases?.name);

            if (data.pack == "1") {
                pack.checked = true;
                itempack = 1;
            } else {
                pack.checked = false;
                itempack = 0;
            }

            // Only fire the Axios request if a detail_id actually exists
            const detail_id = data.receiving_items?.receiving_details_id;
            if (detail_id) {
                axios.get('/searchreceivingdetails', {
                    params: {
                        detail_id: detail_id
                    }
                })
                    .then(function (response) {
                        invoice_number.value = response.data.query?.invoice_number || '';
                        invoice_times.value = response.data.query?.invoice_times || response.data.creditor
                            ?.credit_time || '';
                        $('#invoice_payment').val(response.data.query?.invoice_payment || '').trigger('change');
                        invoice_due.value = response.data.query?.invoice_due || '';
                        invoice_date.value = response.data.query?.invoice_date ?? invoice_date.value;
                        document.getElementById('invoice_ppn').value = response.data.query?.invoice_ppn
                            ?.trim() || response.data.creditor?.ppn_type?.trim() || 'TANPA';
                        count_due();
                        calculateVisualTotal();
                    })
                    .catch(console.error);
            }

            updateBoxPriceInfo();
            // Recalculate gross total and update visual total
            counttotalreceived();
            document.getElementById('qty_received').focus();
        });
        document.getElementById('qty').addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();

            if (selectedRowData) {
                updateItem();
            } else {
                addItem();
            }
        });

        // Creditor select
        $('#creditor').on('change', function () {
            console.log(document.getElementById('invoice_ppn'));

            let creditor = $(this).val();
            if (creditor) {
                axios.get('/searchselectcreditors', {
                    params: {
                        creditor_code: creditor,
                        orderid: ordersid
                    }
                })
                    .then(function (response) {
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
                        calculateVisualTotal();
                        // invoice_number.value = response.data.query?.invoice_number || '';

                    })
                    .catch(function (error) {
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

        document.addEventListener('keydown', function (e) {
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

            const receivingItem = selectedRowData.receiving_items;
            if (!receivingItem || !receivingItem.id) return;

            deleteDraftItem(receivingItem.id, receivingItem.batches_id != null);
        });

        function deleteDraftItem(id, saved) {
            if (saved) {
                iziToast.warning({
                    title: 'Tidak Bisa Dihapus',
                    message: 'Barang sudah diterima dan disimpan',
                    position: 'topRight'
                });
                return;
            }

            if (!confirm('Hapus item ini ?')) return;

            axios.delete("{{ route('receiving.deleteDraftItem', ['id' => '__ID__']) }}".replace('__ID__', id), {
                headers: {
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        .content
                }
            }).then(res => {
                if (res.data.success) {
                    iziToast.success({
                        title: 'Berhasil',
                        message: res.data.message ?? 'Item berhasil dihapus',
                        position: 'topRight'
                    });
                    orderItemsTable.ajax.reload(null, false);
                    selectedRowData = null;
                    selectedRowIndex = null;
                }
            }).catch(err => {
                const message = err.response?.data?.message ?? 'Gagal menghapus item';
                iziToast.error({
                    title: 'Gagal',
                    message: message,
                    position: 'topRight'
                });
            });
        }



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
        document.addEventListener('keydown', function (e) {
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
        document.getElementById('searchResults').addEventListener('mouseover', function (e) {
            const row = e.target.closest('tr');
            if (!row) return;

            const rows = [...this.children];
            rows.forEach(r => r.classList.remove('active'));

            row.classList.add('active');
            activeIndex = rows.indexOf(row);
        });

        // Click
        document.getElementById('searchResults').addEventListener('click', function (e) {
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
        document.addEventListener('click', function (e) {
            const wrapper = document.getElementById('searchWrapper');
            if (wrapper && !wrapper.contains(e.target)) {
                document.getElementById('searchDropdown').style.display = 'none';
            }
        }, true);

        pack.addEventListener('change', function () {
            if (this.checked) {
                itempack = 1;
            } else {
                itempack = 0;
            }

            counttotal();
        });

        pack.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                document.getElementById('qty').focus();
            }
        });
        invoice_ppn.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                addItem();
            }
        });

        function counttotal() {
            counttotalreceived();
        }

        function counttotalreceived() {
            let qty = parseFloat(document.getElementById('qty_received').value) || 0;
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

            const priceInput = document.getElementById('item_price');
            if (priceInput && priceInput.value) {
                itemprice = parseFloat(priceInput.value.replace(/[^\d-]/g, '')) || 0;
            } else {
                itemprice = pack.checked ? (itemrawprice * itemcontent) : itemrawprice;
            }

            itemtotal = qty * itemprice;
            total_transaction = itemtotal;
            calculateVisualTotal();
        }

        function addItem() {
            let finalItemTotal = calculateVisualTotal();
            const payload = {
                creditor_code: creditor.value,
                receiving_items_id: document.getElementById('receiving_items_id').value,
                receiving_id: receiving_id,
                order_items_id: order_items_id,
                order_id: order_id,
                qty_received: qty_received.value,
                raw_price: itemprice,
                discount: discount.value,
                extra_discount: extra_discount.value,
                expired_date: expired_date.value,
                batch: batch.value,
                location: itemlocation.value,
                etalase: etalase.value,
                status: itemstatus.value,
                total: finalItemTotal,
                invoice_payment: invoice_payment.value,
                invoice_number: invoice_number.value,
                invoice_date: invoice_date.value,
                invoice_times: invoice_times.value,
                invoice_due: invoice_due.value,
                invoice_ppn: invoice_ppn.value,
            };

            console.log('Sending receiving_items_id:', payload.receiving_items_id);

            axios.post("{{ route('receiving.addreceivingitem') }}", payload, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
                .then(res => {
                    if (res.data.success) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: res.message ?? 'Item Berhasil Disimpan!',
                            position: 'topRight'
                        });

                        let item = res.data.summary;
                        $('#d_price').val(formatRupiah(item.price_item));
                        $('#d_ppn').val(formatRupiah(item.price_ppn));
                        $('#d_total').val(formatRupiah(item.price_total));

                        orderItemsTable.ajax.reload(null, false);

                        isEditMode = false;
                        // Don't reset — keep medicine selected so user can add more batches easily
                        document.getElementById('receiving_items_id').value = '';
                        document.getElementById('qty_received').value = '';
                        document.getElementById('batch').value = '';
                        document.getElementById('expired_date').value = '';
                        document.getElementById('qty_received').focus();
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
        // NEW: "Simpan" button — posts items to batches, status → 2
        function saveOrder() {
            axios.post("{{ route('receiving.saveOrder') }}", {
                receivingid: receiving_id,
                orderid: ordersid,
            }, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
                .then(res => {
                    if (res.data.success) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: res.data.message ?? 'Item Tersimpan',
                            position: 'topRight'
                        });

                        orderItemsTable.ajax.reload(null, false);
                        resetInputs();
                        selectedRowData = null;
                        selectedRowIndex = null;
                    }
                })
                .catch(err => {
                    let message = 'Gagal menyimpan';
                    if (err.response?.data?.message) {
                        message = err.response.data.message;
                    }
                    iziToast.error({
                        title: 'Gagal',
                        message: message,
                        position: 'topRight'
                    });
                });
        }

        // UPDATED: "Selesaikan Pesanan" button — now just locks (status → 3)
        function completeOrder() {
            axios.post("{{ route('receiving.completeOrder') }}", {
                receivingid: receiving_id,
                orderid: ordersid,
            }, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
                .then(res => {
                    if (res.data.success) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: res.data.message ?? 'Pesanan Berhasil Diselesaikan',
                            position: 'topRight'
                        });

                        // Redirect back to the receiving index after a short delay
                        // so the user sees the success toast first
                        setTimeout(() => {
                            window.location.href = "{{ route('receiving.index') }}";
                        }, 800);
                    }
                })
                .catch(err => {
                    let message = 'Gagal menyelesaikan pesanan';
                    if (err.response?.data?.message) {
                        message = err.response.data.message;
                    }
                    iziToast.error({
                        title: 'Gagal',
                        message: message,
                        position: 'topRight'
                    });
                });
        }

        // Helper: disable inputs when order is locked (status = 3)
        function disableFormForLockedOrder() {
            const inputs = document.querySelectorAll(
                '#searchWrapper input, #searchWrapper select, #searchWrapper textarea, #checkout_detail input');
            inputs.forEach(input => {
                input.disabled = true;
            });

            // Disable buttons
            document.querySelector('[onclick="addItem()"]').disabled = true;
            document.querySelector('[onclick="saveOrder()"]').disabled = true;
        }

        function saveOrder() {
            axios.post("{{ route('receiving.saveOrder') }}", {
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

            if (isNaN(days) || days < 0 || !baseDateValue) {
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
        document.getElementById('qty_received').addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                itemPriceInput.focus();
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
        discount.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                etalase.focus();
            }
        });
        etalase.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                itemlocation.focus();
            }
        });
        itemlocation.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                itemstatus.focus();
            }
        });
        itemstatus.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                addItem();
            }
        });
        invoice_number.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                invoice_date.focus();
            }
        });
        invoice_date.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                invoice_times.focus();
            }
        });
        invoice_times.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                invoice_ppn.focus();
            }
        });
        batch.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                discount.focus();
            }
        });
        discount.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                extra_discount.focus();
            }
        });
        extra_discount.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                expired_date.focus();
            }
        });
        expired_date.addEventListener('keydown', function (e) {
            if (e.key == 'Enter') {
                itemstatus.focus();
            }
        });
        etalase.addEventListener('keydown', function (e) {
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