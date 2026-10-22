@extends('layouts.app')

@section('title', 'Data Penjualan')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ── Select2 styling inside modal ── */
        .select2-container {
            width: 100% !important;
        }
        .select2-container .select2-selection--single {
            height: 38px !important;
            border-radius: 8px !important;
            border: 1px solid #d1d5db !important;
            display: flex !important;
            align-items: center !important;
            padding: 4px 10px !important;
            font-size: 12px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px !important;
            color: #1f2937 !important;
            padding-left: 0 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
            right: 8px !important;
        }
        .select2-dropdown {
            border-radius: 10px !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            font-size: 12px !important;
            z-index: 99999 !important;
        }
        .select2-results__group {
            background: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 6px 12px !important;
            border-top: 1px solid #f1f5f9;
        }
        .select2-results__option {
            padding: 7px 12px !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #ea580c !important;
            color: white !important;
        }

        /* ── DataTables overrides ── */
        .dataTables_wrapper .dataTables_filter {
            float: none !important;
            margin-bottom: 12px;
        }

        .dataTables_wrapper .dataTables_filter label {
            font-size: 13px;
            color: #6b7280;
        }

        .dataTables_wrapper .dataTables_filter input {
            margin-left: 8px;
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 8px;
            border: 0.5px solid #d1d5db;
            outline: none;
            width: 200px;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length label {
            font-size: 12px;
            color: #9ca3af;
        }

        .dataTables_wrapper .dataTables_length select {
            font-size: 12px;
            padding: 4px 6px;
            border-radius: 6px;
            border: 0.5px solid #d1d5db;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            font-size: 12px !important;
            padding: 4px 9px !important;
            border-radius: 6px !important;
            border: none !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #E6F1FB !important;
            color: #185FA5 !important;
            border: none !important;
        }

        /* ── Tables ── */
        #table-data thead th,
        #items-table thead th {
            font-size: 11px !important;
            font-weight: 500 !important;
            text-transform: uppercase !important;
            color: #9ca3af !important;
            padding: 8px 10px !important;
            background: #fff !important;
            border-bottom: 0.5px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #table-data tbody td,
        #items-table tbody td {
            font-size: 12px !important;
            padding: 9px 2px !important;
            vertical-align: middle !important;
            color: #111827;
            border-bottom: 0.5px solid #f3f4f6 !important;
        }

        #table-data tbody td {
            text-align: center !important;
        }

        #table-data tbody tr,
        #items-table tbody tr {
            cursor: pointer;
            transition: background .1s;
        }

        #table-data tbody tr:hover {
            background: #f8fafc !important;
        }

        #table-data tbody tr.active {
            background: #E6F1FB !important;
        }

        .col-mono {
            font-family: ui-monospace, monospace;
            font-size: 11px !important;
            color: #6b7280 !important;
        }

        .col-price {
            text-align: right !important;
            font-weight: 500 !important;
        }

        .col-right {
            text-align: right !important;
        }

        .col-muted {
            text-align: right !important;
            color: #9ca3af !important;
        }

        /* ── Print button ── */
        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            border: 0.5px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            cursor: pointer;
            white-space: nowrap;
            transition: background .12s;
            line-height: 1.4;
        }

        .status-completed {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            border: 0.5px solid #bfdbfe;
            background: #00940b;
            color: #ffffff;
            cursor: pointer;
            white-space: nowrap;
            transition: background .12s;
            line-height: 1.4;
        }

        .status-pending {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #e29e00;
            color: #ffffff;
            cursor: pointer;
            white-space: nowrap;
            transition: background .12s;
            line-height: 1.4;
        }

        .btn-print:hover {
            background: #dbeafe;
        }

        .btn-kwitansi {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            border: 0.5px solid #a7f3d0;
            background: #ecfdf5;
            color: #059669;
            cursor: pointer;
            white-space: nowrap;
            transition: all .12s;
            line-height: 1.4;
        }

        .btn-kwitansi:hover {
            background: #d1fae5;
            color: #047857;
            border-color: #6ee7b7;
        }

        .btn-kwitansi:disabled,
        .btn-kwitansi:disabled:hover,
        #btn-detail-kwitansi:disabled,
        #btn-detail-kwitansi:disabled:hover {
            background: #e5e7eb !important;
            color: #6b7280 !important;
            border-color: #d1d5db !important;
            cursor: not-allowed;
        }

        .btn-edit-payment {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            border: 0.5px solid #fed7aa;
            background: #fff7ed;
            color: #ea580c;
            cursor: pointer;
            white-space: nowrap;
            transition: all .12s;
            line-height: 1.4;
        }

        .btn-edit-payment:hover {
            background: #ffedd5;
            color: #c2410c;
            border-color: #fdba74;
        }

        /* ── Qty badge ── */
        .qty-badge {
            display: inline-block;
            font-size: 11px;
            padding: 1px 7px;
            border-radius: 20px;
            background: #f3f4f6;
            color: #374151;
            font-weight: 500;
        }

        /* ── Total row ── */
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 10px 0;
            margin-top: 8px;
            border-top: 0.5px solid #e5e7eb;
        }

        .total-row span:first-child {
            font-size: 12px;
            color: #6b7280;
        }

        .total-row span:last-child {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #d1d5db;
            font-size: 12px;
        }

        .empty-state svg {
            width: 28px;
            height: 28px;
            margin: 0 auto 8px;
            display: block;
        }

        .date-filter-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .date-filter-wrap label {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
        }

        .date-filter-wrap input[type="text"] {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 8px;
            border: 0.5px solid #d1d5db;
            outline: none;
            width: 120px;
            cursor: pointer;
        }

        .date-filter-wrap input[type="text"]:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
        }

        .btn-clear-date {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 7px;
            border: 0.5px solid #e5e7eb;
            background: #f9fafb;
            color: #6b7280;
            cursor: pointer;
            transition: background .12s;
        }

        .btn-clear-date:hover {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fca5a5;
        }

        .channel-filter-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .channel-filter-wrap label {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
        }

        .channel-filter-wrap select {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 8px;
            border: 0.5px solid #d1d5db;
            outline: none;
            background: #fff;
            color: #374151;
            cursor: pointer;
        }

        .channel-filter-wrap select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
        }

        .code-copy-wrap {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-copy-code {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            padding: 0;
            border: none;
            background: transparent;
            color: #9ca3af;
            cursor: pointer;
            border-radius: 4px;
            transition: background .12s, color .12s;
        }

        .btn-copy-code:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

                {{-- ─── LEFT: Sales Table (3/5) ─────────────────────────────────── --}}
                <div class="lg:col-span-3 bg-white border border-gray-100 rounded-xl p-5">

                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">

                                    <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-cash-register">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M21 15h-2.5c-.398 0 -.779 .158 -1.061 .439c-.281 .281 -.439 .663 -.439 1.061c0 .398 .158 .779 .439 1.061c.281 .281 .663 .439 1.061 .439h1c.398 0 .779 .158 1.061 .439c.281 .281 .439 .663 .439 1.061c0 .398 -.158 .779 -.439 1.061c-.281 .281 -.663 .439 -1.061 .439h-2.5" />
                                        <path d="M19 21v1m0 -8v1" />
                                        <path
                                            d="M13 21h-7c-.53 0 -1.039 -.211 -1.414 -.586c-.375 -.375 -.586 -.884 -.586 -1.414v-10c0 -.53 .211 -1.039 .586 -1.414c.375 -.375 .884 -.586 1.414 -.586h2m12 3.12v-1.12c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2" />
                                        <path
                                            d="M16 10v-6c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-4c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414v6m8 0h-8m8 0h1m-9 0h-1" />
                                        <path d="M8 14v.01" />
                                        <path d="M8 17v.01" />
                                        <path d="M12 13.99v.01" />
                                        <path d="M12 17v.01" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-[15px] font-semibold text-gray-800 leading-tight">Data Penjualan{{ !empty($restrictedChannelName) ? ' — ' . $restrictedChannelName : '' }}</h2>
                                    <p class="text-[12px] text-gray-400">Data dan riwayat penjualan kasir{{ !empty($restrictedChannelName) ? ' (' . $restrictedChannelName . ')' : '' }}</p>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('home') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200
                                   text-[12px] text-gray-500 hover:bg-gray-50 transition-colors">
                            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round">
                                <path d="M10 12L6 8l4-4" />
                            </svg>
                            Kembali
                        </a>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="date-filter-wrap !mb-0">
                                <label>Periode</label>
                                <input type="text" id="date-range" placeholder="dd/mm/yyyy — dd/mm/yyyy" readonly
                                    style="width: 210px;">
                                <button type="button" class="btn-clear-date" id="btn-clear-date" title="Tampilkan seluruh riwayat tanpa batas tanggal">Semua Waktu</button>
                                <button type="button" class="btn-clear-date" id="btn-today-date" title="Kembali ke hari ini">Hari Ini</button>
                            </div>
                            <div class="channel-filter-wrap !mb-0">
                                <label>Jenis Transaksi</label>
                                @if (!empty($restrictedChannel))
                                    <select id="channel-filter" disabled class="bg-gray-100 cursor-not-allowed text-gray-700 font-medium">
                                        <option value="{{ $restrictedChannel }}" selected>{{ $restrictedChannelName }}</option>
                                    </select>
                                @else
                                    <select id="channel-filter">
                                        <option value="">Semua Jenis Transaksi</option>
                                        <option value="kasir">Kasir (Offline)</option>
                                        <option value="online">Online (Chat WA)</option>
                                        <option value="shopee">Online Shopee</option>
                                        <option value="grab">Online Grab</option>
                                        <option value="digital">Digital (Aplikasi Mobile)</option>
                                    </select>
                                @endif
                            </div>
                        </div>

                        {{-- Export Dropdown --}}
                        <div class="relative inline-block text-left ml-auto">
                            <button type="button" id="btnExportDropdown" onclick="toggleExportMenu(event)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:border-emerald-400 font-medium text-[12px] shadow-xs transition-colors">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                                <span>Export Excel</span>
                                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" class="text-emerald-600">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>

                            <div id="exportMenu" class="hidden absolute right-0 mt-1.5 w-56 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 z-50 text-[12px]">
                                <div class="px-3 py-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 mb-1">
                                    Pilihan Format Ekspor
                                </div>
                                <button type="button" onclick="exportSalesData('all')" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-emerald-50 hover:text-emerald-700 flex items-center justify-between transition-colors">
                                    <div>
                                        <div class="font-medium">Semua (Rekap & Detail)</div>
                                        <div class="text-[10px] text-gray-400">2 Sheet lengkap</div>
                                    </div>
                                    <span class="text-[10px] bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded font-mono font-medium">Lengkap</span>
                                </button>
                                <button type="button" onclick="exportSalesData('rekap')" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50 flex items-center justify-between transition-colors">
                                    <div>
                                        <div class="font-medium">Rekap Nota Penjualan</div>
                                        <div class="text-[10px] text-gray-400">1 baris per struk</div>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-mono">Rekap</span>
                                </button>
                                <button type="button" onclick="exportSalesData('detail')" class="w-full text-left px-3 py-2 text-gray-700 hover:bg-gray-50 flex items-center justify-between transition-colors">
                                    <div>
                                        <div class="font-medium">Detail Obat / Produk</div>
                                        <div class="text-[10px] text-gray-400">Rincian item per transaksi</div>
                                    </div>
                                    <span class="text-[10px] text-gray-400 font-mono">Detail</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="table-data" class="w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Nomor</th>
                                    <th>Jenis</th>
                                    <th>Tipe</th>
                                    <th>Pasien</th>
                                    <th class="col-right">Harga</th>
                                    <th class="col-right">Pembayaran</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- ─── RIGHT: Transaction Detail (2/5) ────────────────────────── --}}
                <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-5">

                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-[14px] font-medium text-gray-800">Detail transaksi</h2>
                            <span id="detail-code" class="text-[11px] font-mono text-gray-400">—</span>
                        </div>
                        <div id="detail-channel-badge"></div>
                    </div>

                    <div id="detail-empty" class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                            <path d="M9 12h6M9 16h6M7 2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8l-6-6z" />
                        </svg>
                        Pilih transaksi untuk melihat detail
                    </div>

                    <div id="detail-wrap" class="hidden">
                        <div class="grid grid-cols-2 gap-2 p-2.5 mb-3 bg-gray-50 rounded-lg text-[12px] border border-gray-100">
                            <div>
                                <span class="text-gray-400 block text-[10px] uppercase font-medium">Pasien:</span>
                                <span id="detail-patient" class="font-semibold text-gray-800 truncate block">—</span>
                            </div>
                            <div>
                                <span class="text-gray-400 block text-[10px] uppercase font-medium">Kasir / Pembuat:</span>
                                <span id="detail-creator" class="font-semibold text-gray-800 truncate block">—</span>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table id="items-table" class="w-full">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Obat</th>
                                        <th class="col-right">Harga</th>
                                        <th class="col-right">Qty</th>
                                        <th class="col-right">Disc</th>
                                        <th class="col-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span id="detail-subtotal">—</span>
                        </div>
                        <div class="total-row">
                            <span>Total Diskon</span>
                            <span id="detail-discount">—</span>
                        </div>
                        <div class="total-row">
                            <span>Total</span>
                            <span id="detail-total">—</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-gray-100">
                            <button type="button" id="btn-detail-struk"
                                class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold transition-all">
                                <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <polyline points="4 6 4 1 12 1 12 6"/>
                                    <path d="M4 12H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1"/>
                                    <rect x="4" y="10" width="8" height="5"/>
                                </svg>
                                <span>Cetak Struk</span>
                            </button>
                            <button type="button" id="btn-detail-kwitansi" disabled
                                class="flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold transition-all">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                </svg>
                                <span>Cetak Kwitansi</span>
                            </button>
                        </div>

                        @if (!empty($canEditPayment))
                            <div class="mt-2.5">
                                <button type="button" id="btn-detail-edit-payment"
                                    class="w-full flex items-center justify-center gap-1.5 py-2 px-3 rounded-lg border border-orange-200 bg-orange-50 hover:bg-orange-100 text-orange-700 text-xs font-semibold transition-all shadow-xs">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                    <span>Edit Pembayaran & Kasir</span>
                                </button>
                            </div>
                        @endif
                    </div>

                </div>

            </div>
        </div>

        @if (!empty($canEditPayment))
            {{-- Modal Edit Pembayaran & Kasir (Khusus General Manager) --}}
            <div id="modalEditPayment" class="fixed inset-0 z-50 hidden overflow-y-auto bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-md overflow-hidden transform transition-all">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-orange-50/50 to-amber-50/50">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center font-bold">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold text-gray-800">Edit Pembayaran & Kasir</h3>
                                <p class="text-[11px] text-gray-500">Otoritas Khusus: General Manager</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeEditPaymentModal()" class="text-gray-400 hover:text-gray-600 p-1 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>

                    <form id="formEditPayment" class="p-5 space-y-4">
                        <input type="hidden" id="edit_trx_id" name="trx_id">

                        <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-[12px] flex justify-between items-center">
                            <div>
                                <span class="text-gray-400 block text-[10px] uppercase font-medium">No. Transaksi</span>
                                <span id="edit_display_code" class="font-mono font-semibold text-gray-700">—</span>
                            </div>
                            <div class="text-right">
                                <span class="text-gray-400 block text-[10px] uppercase font-medium">Total Tagihan</span>
                                <span id="edit_display_total" class="font-semibold text-emerald-600 font-mono">—</span>
                            </div>
                        </div>

                        <div>
                            <label for="edit_user_id" class="block text-xs font-semibold text-gray-700 mb-1.5">Kasir / Pembuat Transaksi</label>
                            <select id="edit_user_id" name="user_id" required class="select2-modal w-full text-xs rounded-lg border border-gray-300 px-3 py-2 bg-white text-gray-800">
                                @foreach ($cashiers ?? [] as $groupName => $userList)
                                    <optgroup label="{{ $groupName }}">
                                        @foreach ($userList as $cashier)
                                            <option value="{{ $cashier->id }}">{{ $cashier->name }} ({{ $cashier->username }})</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <span class="text-[11px] text-gray-400 mt-1 block">Mengubah kasir pada transaksi dan seluruh item keranjang penjualan.</span>
                        </div>

                        <div>
                            <label for="edit_payment_method" class="block text-xs font-semibold text-gray-700 mb-1.5">Metode Pembayaran</label>
                            <select id="edit_payment_method" name="payment_method" required onchange="toggleBankVisibility(this.value)" class="w-full text-xs rounded-lg border border-gray-300 px-3 py-2 bg-white text-gray-800 focus:ring-2 focus:ring-orange-200 focus:border-orange-500 transition-all">
                                <option value="CASH">CASH (Tunai)</option>
                                <option value="QRIS">QRIS</option>
                                <option value="TRANSFER">TRANSFER BANK</option>
                                <option value="DEBIT">KARTU DEBIT</option>
                                <option value="BELUM BAYAR">BELUM BAYAR</option>
                            </select>
                        </div>

                        <div id="wrap_bank_name" class="hidden">
                            <label for="edit_bank_name" class="block text-xs font-semibold text-gray-700 mb-1.5">Rekening Bank Tujuan</label>
                            <select id="edit_bank_name" name="bank_name" class="w-full text-xs rounded-lg border border-gray-300 px-3 py-2 bg-white text-gray-800 focus:ring-2 focus:ring-orange-200 focus:border-orange-500 transition-all">
                                <option value="">-- Pilih Rekening Bank --</option>
                                @foreach ($availableBanks ?? [] as $bank)
                                    <option value="{{ $bank['bank_name'] }}">{{ $bank['name'] }} @if(!empty($bank['account_number']))({{ $bank['account_number'] }})@endif</option>
                                @endforeach
                            </select>
                            <span class="text-[11px] text-gray-400 mt-1 block">Saldo keuangan apotek akan disesuaikan otomatis dengan rekening bank ini.</span>
                        </div>

                        <div class="pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                            <button type="button" onclick="closeEditPaymentModal()" class="px-4 py-2 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                                Batal
                            </button>
                            <button type="submit" id="btnSubmitEditPayment" class="px-4 py-2 text-xs font-semibold text-white bg-orange-600 hover:bg-orange-700 rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                                <span>Simpan Perubahan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let tableData = null;
        let itemsTable = null;

        function copyCode(btn, code) {
            navigator.clipboard.writeText(code).then(() => {
                iziToast.success({
                    title: 'Disalin',
                    message: `Nomor ${code} berhasil disalin`,
                    position: 'topRight',
                    timeout: 1500
                });
            }).catch(() => {
                iziToast.error({
                    title: 'Gagal',
                    message: 'Tidak bisa menyalin nomor',
                    position: 'topRight',
                    timeout: 1500
                });
            });
        }
        $(function() {

            // ── Flatpickr instances ───────────────────────────────────────────
            const today = flatpickr.formatDate(new Date(), 'd/m/Y');

            const fpRange = flatpickr('#date-range', {
                mode: 'range',
                dateFormat: 'd/m/Y',
                locale: {
                    firstDayOfWeek: 1
                },
                defaultDate: [today, today],
                onClose: function(selectedDates) {
                    if (selectedDates.length === 1 || selectedDates.length === 2) {
                        tableData.ajax.reload();
                    }
                },
            });

            $('#btn-clear-date').on('click', function() {
                fpRange.clear();
                tableData.ajax.reload();
            });

            $('#btn-today-date').on('click', function() {
                fpRange.setDate([today, today]);
                tableData.ajax.reload();
            });

            const printedKwitansi = new Set();
            // ── Main sales table ──────────────────────────────────────────────
            tableData = $('#table-data').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('salesdata.index') }}',
                    data: function(d) {
                        const val = $('#date-range').val();
                        const parts = val.split(' to ');
                        d.date_from = parts[0] ?? '';
                        d.date_to = parts[1] ?? '';
                        d.channel = '{{ $restrictedChannel ?? '' }}' || $('#channel-filter').val();
                    }
                },
                language: {
                    search: '',
                    searchPlaceholder: 'Cari nomor, nama pasien, kasir...'
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'col-mono'
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'time',
                        className: 'col-mono'
                    },
                    {
                        data: 'code',
                        className: 'col-mono',
                        render: (data) =>
                            `<span class="code-copy-wrap">
                                <span class="code-text">${data}</span>
                                <button type="button" class="btn-copy-code" onclick="event.stopPropagation(); copyCode(this, '${data}')" title="Salin nomor">
                                    <svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                                        <rect x="5" y="5" width="9" height="9" rx="1.5"/>
                                        <path d="M11 5V3a1 1 0 0 0-1-1H3a1 1 0 0 0-1 1v7a1 1 0 0 0 1 1h2"/>
                                    </svg>
                                </button>
                            </span>`
                    },
                    {
                        data: 'channel',
                        orderable: false,
                        searchable: false,
                    },
                    {
                        data: 'type',
                        className: 'col-mono'
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'final_price',
                        className: 'col-price'
                    },
                    {
                        data: 'payment_method',
                        defaultContent: '-'
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: (d) => {
                            const trxId = d.transactions?.id || d.transaction_id;
                            const trxType = d.type || '';
                            const printed = !!(d.kwitansi_printed_at || d.transactions?.kwitansi_printed_at || printedKwitansi.has(String(trxId)));
                            const canEditPayment = {{ !empty($canEditPayment) ? 'true' : 'false' }};

                            let editBtnHtml = '';
                            if (canEditPayment) {
                                editBtnHtml = `
                                    <button class="btn-edit-payment" onclick="event.stopPropagation(); triggerEditPaymentModal('${trxId}')" title="Edit Pembayaran & Kasir (General Manager)">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>Edit
                                    </button>
                                `;
                            }

                            return `<div class="flex items-center gap-1 justify-center">
                                <button class="btn-print" onclick="event.stopPropagation(); printStruk('${trxId}', '${trxType}')" title="Cetak Struk POS Thermal">
                                    <svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <polyline points="4 6 4 1 12 1 12 6"/>
                                        <path d="M4 12H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1"/>
                                        <rect x="4" y="10" width="8" height="5"/>
                                    </svg>Struk
                                </button>
                                <button class="btn-kwitansi" data-kwitansi-id="${trxId}" ${printed ? 'disabled' : ''} onclick="event.stopPropagation(); if (!this.disabled) window.open('/print/kwitansi/${trxId}','_blank')" title="${printed ? 'Kwitansi sudah dicetak' : 'Cetak Kwitansi Format Apotek'}">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                        <polyline points="14 2 14 8 20 8"/>
                                        <line x1="16" y1="13" x2="8" y2="13"/>
                                        <line x1="16" y1="17" x2="8" y2="17"/>
                                    </svg><span>${printed ? 'Sudah Dicetak' : 'Kwitansi'}</span>
                                </button>
                                ${editBtnHtml}
                            </div>`;
                        }
                    },
                ],
            });

            // ── Channel filter change ─────────────────────────────────────────
            $('#channel-filter').on('change', function() {
                tableData.ajax.reload();
            });

            // ── Row click → load items ────────────────────────────────────────
            let currentSelectedTrxId = null;
            let currentSelectedTrxType = null;

            function updateDetailKwitansi(data) {
                const id = data?.transactions?.id || data?.transaction_id;
                const printed = !!(data?.kwitansi_printed_at || data?.transactions?.kwitansi_printed_at || printedKwitansi.has(String(id)));
                $('#btn-detail-kwitansi').prop('disabled', !id || printed)
                    .attr('title', printed ? 'Kwitansi sudah dicetak' : 'Cetak Kwitansi')
                    .find('span').text(printed ? 'Sudah Dicetak' : 'Cetak Kwitansi');
            }

            function markKwitansiPrinted(message) {
                if (message?.type !== 'kwitansi-printed' || !/^\d+$/.test(String(message.transactionId))) return;
                const id = String(message.transactionId);
                printedKwitansi.add(id);
                document.querySelectorAll('[data-kwitansi-id]').forEach(button => {
                    if (button.dataset.kwitansiId !== id) return;
                    button.disabled = true;
                    button.title = 'Kwitansi sudah dicetak';
                    button.querySelector('span').textContent = 'Sudah Dicetak';
                });
                if (String(currentSelectedTrxId) === id) updateDetailKwitansi({transaction_id: id});
            }

            window.addEventListener('message', event => {
                if (event.origin === window.location.origin) markKwitansiPrinted(event.data);
            });
            window.addEventListener('storage', event => {
                if (event.key !== 'kwitansi-printed') return;
                try { markKwitansiPrinted(JSON.parse(event.newValue)); } catch (_) { /* Ignore invalid notifications. */ }
            });
            // Read the server status when returning from another tab or a cached page.
            window.addEventListener('focus', () => tableData.ajax.reload(null, false));
            window.addEventListener('pageshow', event => {
                if (event.persisted) tableData.ajax.reload(null, false);
            });
            tableData.on('draw', function() {
                if (!currentSelectedTrxId) return;
                const selected = tableData.rows().data().toArray().find(row => String(row.transaction_id) === String(currentSelectedTrxId));
                if (selected) updateDetailKwitansi(selected);
            });

            $('#table-data tbody').on('click', 'tr', function() {
                const data = tableData.row(this).data();
                if (!data) return;
                $('#table-data tbody tr').removeClass('active');
                $(this).addClass('active');
                currentSelectedTrxId = data.transactions?.id || data.transaction_id;
                currentSelectedTrxType = data.type || '';
                updateDetailKwitansi(data);
                document.getElementById('detail-code').textContent = data.code;
                document.getElementById('detail-channel-badge').innerHTML = data.channel || '';
                document.getElementById('detail-patient').textContent = data.name || 'Umum / Tanpa Pasien';
                document.getElementById('detail-creator').textContent = data.creator_name || '-';
                loadItems(data.transaction_id, data.final_price, data.subtotal, data.totaldiscount);
            });

            $('#btn-detail-struk').on('click', function() {
                if (currentSelectedTrxId) {
                    printStruk(currentSelectedTrxId, currentSelectedTrxType);
                }
            });

            $('#btn-detail-kwitansi').on('click', function() {
                if (currentSelectedTrxId && !this.disabled) {
                    window.open(`/print/kwitansi/${currentSelectedTrxId}`, '_blank');
                }
            });

            $('#btn-detail-edit-payment').on('click', function() {
                if (currentSelectedTrxId) {
                    triggerEditPaymentModal(currentSelectedTrxId);
                }
            });
        });

        // ── Modal Edit Pembayaran & Kasir (General Manager) ──────────────────
        function toggleBankVisibility(method) {
            const wrap = document.getElementById('wrap_bank_name');
            const select = document.getElementById('edit_bank_name');
            if (!wrap) return;

            const nonCashMethods = ['QRIS', 'TRANSFER', 'DEBIT'];
            if (nonCashMethods.includes((method || '').toUpperCase())) {
                wrap.classList.remove('hidden');
                if (select) select.required = true;
            } else {
                wrap.classList.add('hidden');
                if (select) {
                    select.required = false;
                    select.value = '';
                }
            }
        }

        function openEditPaymentModal(data) {
            if (!data) return;
            const modal = document.getElementById('modalEditPayment');
            if (!modal) return;

            const trxId = data.transactions?.id || data.transaction_id;
            document.getElementById('edit_trx_id').value = trxId;
            document.getElementById('edit_display_code').textContent = data.code || '—';
            document.getElementById('edit_display_total').textContent = data.final_price || '—';

            // Set user_id / kasir
            const cashierSelect = document.getElementById('edit_user_id');
            const rawUserId = data.raw_user_id || data.user_id;
            if (cashierSelect && rawUserId) {
                $(cashierSelect).val(rawUserId);
            }

            // Set payment method
            const paymentSelect = document.getElementById('edit_payment_method');
            const rawMethod = (data.raw_payment_method || data.transactions?.payment_method || 'CASH').toUpperCase();
            if (paymentSelect) {
                paymentSelect.value = rawMethod;
            }
            toggleBankVisibility(rawMethod);

            // Set bank
            const bankSelect = document.getElementById('edit_bank_name');
            const rawBank = data.raw_bank_name || data.transactions?.transfer_bank_name || '';
            if (bankSelect && rawBank) {
                bankSelect.value = rawBank;
            }

            modal.classList.remove('hidden');

            // Initialize or refresh Select2 with dropdownParent inside modal
            if (typeof $.fn.select2 !== 'undefined' && $('#edit_user_id').length) {
                if ($('#edit_user_id').hasClass('select2-hidden-accessible')) {
                    $('#edit_user_id').val(rawUserId).trigger('change.select2');
                } else {
                    $('#edit_user_id').select2({
                        dropdownParent: $('#modalEditPayment'),
                        width: '100%',
                        placeholder: '-- Pilih Kasir / User --'
                    });
                }
            }
        }

        function closeEditPaymentModal() {
            const modal = document.getElementById('modalEditPayment');
            if (modal) {
                modal.classList.add('hidden');
                if (typeof $.fn.select2 !== 'undefined' && $('#edit_user_id').data('select2')) {
                    $('#edit_user_id').select2('close');
                }
            }
        }

        function triggerEditPaymentModal(trxId) {
            if (!tableData) return;
            const rows = tableData.rows().data().toArray();
            const found = rows.find(r => String(r.transactions?.id || r.transaction_id) === String(trxId));
            if (found) {
                openEditPaymentModal(found);
            }
        }

        $(document).on('submit', '#formEditPayment', function(e) {
            e.preventDefault();
            const trxId = $('#edit_trx_id').val();
            if (!trxId) return;

            const btn = $('#btnSubmitEditPayment');
            const originalText = btn.html();
            btn.prop('disabled', true).html('<span>Menyimpan...</span>');

            const payload = {
                payment_method: $('#edit_payment_method').val(),
                bank_name: $('#edit_bank_name').val(),
                user_id: $('#edit_user_id').val(),
            };

            axios.post(`/data/sales/${trxId}/update-payment`, payload)
                .then(res => {
                    closeEditPaymentModal();
                    iziToast.success({
                        title: 'Berhasil',
                        message: res.data?.message || 'Pembayaran dan kasir berhasil diperbarui',
                        position: 'topRight',
                        timeout: 2500
                    });
                    if (tableData) {
                        tableData.ajax.reload(null, false);
                    }
                })
                .catch(err => {
                    const msg = err.response?.data?.message || 'Gagal memperbarui pembayaran dan kasir';
                    iziToast.error({
                        title: 'Gagal',
                        message: msg,
                        position: 'topRight',
                        timeout: 3500
                    });
                })
                .finally(() => {
                    btn.prop('disabled', false).html(originalText);
                });
        });

        // ── Print Struk with optional full receipt for Resep Tunai & Resep Kredit ─
        function printStruk(trxId, trxType) {
            window.open(`/print/receipt/${trxId}`, '_blank');

            // If RESEP TUNAI (UM) or RESEP KREDIT (UK), ask to also print detail/full receipt
            if (trxType === 'UM' || trxType === 'UK' || trxType === 'RESEP TUNAI' || trxType === 'KREDIT') {
                Swal.fire({
                    title: 'Cetak Struk Detail?',
                    text: 'Apakah Anda ingin mencetak struk detail (semua obat racikan)?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Ya, Cetak Detail',
                    cancelButtonText: 'Tidak'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.open(`/print/fullreceipt/${trxId}`, '_blank');
                    }
                });
            }
        }

        // ── Load items table ──────────────────────────────────────────────────
        function loadItems(transactionId, totalPrice, subtotal, totaldiscount) {
            if (itemsTable) {
                itemsTable.destroy();
                $('#items-table tbody').empty();
            }

            document.getElementById('detail-empty').classList.add('hidden');
            document.getElementById('detail-wrap').classList.remove('hidden');
            document.getElementById('detail-total').textContent = totalPrice ?? '—';
            document.getElementById('detail-discount').textContent = totaldiscount ?? '—';
            document.getElementById('detail-subtotal').textContent = subtotal ?? '—';

            itemsTable = $('#items-table').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                paging: false,
                info: false,
                ajax: `/sales/transaction/${transactionId}/items`,
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        className: 'col-mono'
                    },
                    {
                        data: 'medicine'
                    },
                    {
                        data: 'price',
                        className: 'col-right',
                        render: val => `<span class="qty-badge">${val}</span>`
                    },
                    {
                        data: 'quantity',
                        className: 'col-right',
                        render: val => `<span class="qty-badge">${val}</span>`
                    },
                    {
                        data: 'discount',
                        className: 'col-muted',
                        render: val => val ? val : '—'
                    },
                    {
                        data: 'total',
                        className: 'col-price'
                    },
                ],
            });
        }

        // ── Export functionality ─────────────────────────────────────────────
        function toggleExportMenu(e) {
            if (e) e.stopPropagation();
            const menu = document.getElementById('exportMenu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#btnExportDropdown, #exportMenu').length) {
                $('#exportMenu').addClass('hidden');
            }
        });

        function exportSalesData(mode) {
            $('#exportMenu').addClass('hidden');

            const val = $('#date-range').val() || '';
            const parts = val.split(' to ');
            const dateFrom = parts[0] ? parts[0].trim() : '';
            const dateTo = parts[1] ? parts[1].trim() : dateFrom;
            const channel = '{{ $restrictedChannel ?? '' }}' || $('#channel-filter').val() || '';
            const search = (tableData && typeof tableData.search === 'function') ? (tableData.search() || '') : '';

            const url = new URL("{{ route('salesdata.export') }}", window.location.origin);
            if (dateFrom) url.searchParams.set('date_from', dateFrom);
            if (dateTo) url.searchParams.set('date_to', dateTo);
            if (channel) url.searchParams.set('channel', channel);
            if (search) url.searchParams.set('search', search);
            if (mode) url.searchParams.set('mode', mode);

            if (typeof iziToast !== 'undefined') {
                iziToast.info({
                    title: 'Mempersiapkan',
                    message: 'Mengunduh file Excel...',
                    position: 'topRight',
                    timeout: 2000
                });
            }

            window.location.href = url.toString();
        }
    </script>
@endsection
