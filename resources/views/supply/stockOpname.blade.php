@extends('layouts.app')

@section('title', 'Stock Opname')
@section('style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* Base Overrides for clean UI */
        body {
            background-color: #f8fafc;
        }

        /* DataTable Overrides to match new UI */
        .dataTables_wrapper {
            width: 100%;
            padding: 10px;
            font-family: inherit;
        }

        table.dataTable thead th,
        table.dataTable thead td {
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 12px 16px !important;
            white-space: nowrap;
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            color: #64748b !important;
        }

        table.dataTable tbody td {
            padding: 12px 16px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
            font-size: 10px !important;
        }

        table.dataTable tbody tr:hover {
            background-color: #f8fafc !important;
            cursor: pointer;
        }

        table.dataTable tbody tr.active {
            background-color: #eef2ff !important;
        }

        /* DataTable Controls (Search & Pagination) */
        /* Modern DataTable Controls (Search & Pagination) */
        .dataTables_wrapper .top,
        .dataTables_wrapper .bottom {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 8px 0 !important;
            gap: 8px;
            flex-wrap: wrap;
        }

        .dataTables_wrapper .bottom {
            justify-content: center !important;
        }

        .dataTables_length label,
        .dataTables_filter label {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-size: 13px !important;
            color: #64748b !important;
            margin: 0 !important;
            width: 100% !important;
        }

        .dataTables_filter input {
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 6px 12px !important;
            font-size: 12px !important;
            outline: none !important;
            width: 100% !important;
            display: inline-block !important;
            background: #fff !important;
        }

        .dataTables_filter input:focus {
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
        }

        .dataTables_length select {
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 4px 24px 4px 8px !important;
            font-size: 13px !important;
            width: auto !important;
        }

        .dataTables_info {
            font-size: 11px !important;
            color: #94a3b8 !important;
        }

        /* Centered and Clean Pagination Buttons */
        .dataTables_wrapper .dataTables_paginate {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 3px !important;
            margin: 8px 0 !important;
            width: 100% !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            padding: 4px 0 !important;
        }

        .dataTables_wrapper .dataTables_paginate span {
            display: inline-flex !important;
            align-items: center !important;
            gap: 3px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            min-width: 28px !important;
            height: 28px !important;
            padding: 0 6px !important;
            border-radius: 6px !important;
            margin: 0 !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            color: #475569 !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.04) !important;
            line-height: 1 !important;
            text-decoration: none !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.current):not(.disabled) {
            background: #f1f5f9 !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4f46e5 !important;
            color: #ffffff !important;
            border-color: #4f46e5 !important;
            box-shadow: 0 1px 3px 0 rgba(79, 70, 229, 0.3) !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.35 !important;
            cursor: not-allowed !important;
            background: #f8fafc !important;
            border-color: #f1f5f9 !important;
            color: #94a3b8 !important;
            box-shadow: none !important;
        }

        .dataTables_wrapper .dataTables_paginate .ellipsis {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 18px !important;
            height: 28px !important;
            font-size: 11px !important;
            color: #94a3b8 !important;
        }

        /* Custom Scrollbar for Logs */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50/50 pb-12 pt-2 px-4 sm:px-6 lg:px-8">
        <div class="mx-auto space-y-3">

            {{-- Header --}}
            <div
                class="flex flex-col gap-4 p-4 bg-white border border-slate-200/80 rounded-xl shadow-xs md:flex-row md:items-center md:justify-between">

                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-package">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                            <path d="M12 12l8 -4.5" />
                            <path d="M12 12l0 9" />
                            <path d="M12 12l-8 -4.5" />
                            <path d="M16 5.25l-8 4.5" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 leading-tight">Stock Opname</h2>
                        <p class="text-xs text-slate-400">Rekonsiliasi Stok Obat & Impor Massal</p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-2.5 flex-wrap">
                    {{-- Download Format Excel Menu --}}
                    <div class="relative inline-block text-left" id="dropdown_template_wrapper">
                        <button type="button" id="btn_download_template_menu"
                            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition-all shadow-xs">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Format Excel</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        {{-- Dropdown options --}}
                        <div id="dropdown_template_menu"
                            class="hidden absolute right-0 mt-2 w-56 rounded-xl shadow-lg bg-white ring-1 ring-black/5 z-50 p-1.5 border border-slate-100 divide-y divide-slate-100">
                            <div class="py-1">
                                <a href="javascript:void(0)" onclick="downloadOpnameTemplate(true)"
                                    class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 rounded-lg font-medium transition-colors">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    Dengan Daftar Obat Cabang
                                </a>
                                <a href="javascript:void(0)" onclick="downloadOpnameTemplate(false)"
                                    class="flex items-center gap-2 px-3 py-2 text-xs text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 rounded-lg font-medium transition-colors">
                                    <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                    Format Kosong (Polos)
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Import Excel Button --}}
                    <button type="button" id="btn_open_import_modal"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 rounded-xl shadow-sm transition-all transform hover:-translate-y-0.5 cursor-pointer">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Impor Excel</span>
                    </button>

                    {{-- Export Data Opname --}}
                    <a href="{{ route('supplies.printstockopname') }}"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        <span>Export Hasil</span>
                    </a>
                </div>

            </div>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

                {{-- Left Column: Medicine Selector --}}
                <div class="xl:col-span-4 space-y-3">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 h-full flex flex-col">
                        <div class="flex items-center gap-2 mb-5">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs">1</span>
                            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Pilih Obat</h2>
                        </div>

                        <div class="space-y-4 mb-6">
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Rentang
                                    Tanggal</label>
                                <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                                    class="w-full rounded-xl border-slate-200 text-sm focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-shadow py-2 px-3"
                                    autocomplete="off">
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Obat
                                    Terpilih</label>
                                <input type="text" readonly id="medicine_name" placeholder="Klik 2x pada tabel..."
                                    class="w-full rounded-xl border-slate-200 bg-slate-50 text-slate-700 text-sm font-semibold focus:ring-0 py-2 px-3 placeholder-slate-400"
                                    autocomplete="off">
                            </div>
                        </div>

                        <div class="border border-slate-200 rounded-xl overflow-hidden flex-1 flex flex-col">
                            <table id="medicines_data" class="w-full text-sm text-left">
                                <thead class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 border-b border-slate-200">#</th>
                                        <th class="px-4 py-3 border-b border-slate-200">Nama</th>
                                        <th class="px-4 py-3 border-b border-slate-200">Satuan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-600"></tbody>
                            </table>
                        </div>
                        <p
                            class="text-[11px] font-medium text-slate-400 mt-4 flex items-center gap-1.5 bg-slate-50 p-2 rounded-lg border border-slate-100">
                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122">
                                </path>
                            </svg>
                            Double-click pada baris untuk memilih obat
                        </p>
                    </div>
                </div>

                {{-- Right Column: Details & Input --}}
                <div class="xl:col-span-8">

                    {{-- Hidden Fields --}}
                    <input type="hidden" id="medicine_id">
                    <input type="hidden" id="medicine_stock">
                    <input type="hidden" id="batches_id">
                    <input type="hidden" id="expired_date">

                    {{-- Stock History & Stats --}}
                    <div class="bg-white rounded-2xl shadow-sm border mb-3 border-slate-200 p-6">
                        <div class="flex items-center gap-2 mb-5">
                            <span
                                class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs">2</span>
                            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Riwayat & Ringkasan Stok
                            </h2>
                        </div>

                        <div
                            class="grid grid-cols-2 {{ canAccessWarehouseStock() ? 'md:grid-cols-6' : 'md:grid-cols-5' }} gap-3 mb-5">
                            <div
                                class="bg-slate-50/80 p-3.5 rounded-xl border border-slate-200/80 flex flex-col justify-center transition-all hover:shadow-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Saldo
                                        Awal</span>
                                    <span
                                        class="text-[9px] px-1.5 py-0.5 rounded bg-slate-200/80 text-slate-600 font-semibold">Periode</span>
                                </div>
                                <span class="text-2xl font-black text-slate-800" id="qty_awal">—</span>
                            </div>
                            <div
                                class="bg-blue-50/40 p-3.5 rounded-xl border border-blue-100 flex flex-col justify-center transition-all hover:shadow-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Total
                                        Beli</span>
                                    <span
                                        class="text-[9px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 font-semibold">Masuk</span>
                                </div>
                                <span class="text-2xl font-black text-blue-700" id="qty_beli">—</span>
                            </div>
                            <div
                                class="bg-rose-50/40 p-3.5 rounded-xl border border-rose-100 flex flex-col justify-center transition-all hover:shadow-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600">Total
                                        Jual</span>
                                    <span
                                        class="text-[9px] px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 font-semibold">Keluar</span>
                                </div>
                                <span class="text-2xl font-black text-rose-700" id="qty_jual">—</span>
                            </div>
                            @if (canAccessWarehouseStock())
                                <div
                                    class="bg-amber-50/50 p-3.5 rounded-xl border border-amber-200/80 flex flex-col justify-center transition-all hover:shadow-xs">
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Stok
                                            Gudang</span>
                                        <span
                                            class="text-[9px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">Fisik</span>
                                    </div>
                                    <span class="text-2xl font-black text-amber-600" id="qty_gudang">—</span>
                                </div>
                            @endif
                            <div
                                class="bg-purple-50/50 p-3.5 rounded-xl border border-purple-200/80 flex flex-col justify-center transition-all hover:shadow-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-purple-700">Stok
                                        Etalase</span>
                                    <span
                                        class="text-[9px] px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 font-semibold">Fisik</span>
                                </div>
                                <span class="text-2xl font-black text-purple-600" id="qty_etalase">—</span>
                            </div>
                            <div
                                class="bg-emerald-50/50 p-3.5 rounded-xl border border-emerald-200/80 flex flex-col justify-center transition-all hover:shadow-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Total
                                        Stok</span>
                                    <span
                                        class="text-[9px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-semibold">Real-time</span>
                                </div>
                                <span class="text-2xl font-black text-emerald-600" id="qty_akhir">—</span>
                            </div>
                        </div>

                        {{-- Quick Filter Chips --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                            <div
                                class="flex items-center gap-1.5 overflow-x-auto p-1 bg-slate-100/80 rounded-xl border border-slate-200/80 text-xs">
                                <button type="button"
                                    class="stock-log-filter-btn active px-3 py-1 rounded-lg font-bold transition-all bg-white text-indigo-700 shadow-xs"
                                    data-filter="">Semua</button>
                                <button type="button"
                                    class="stock-log-filter-btn px-3 py-1 rounded-lg font-medium transition-all text-slate-600 hover:text-slate-900"
                                    data-filter="so">Stock Opname</button>
                                <button type="button"
                                    class="stock-log-filter-btn px-3 py-1 rounded-lg font-medium transition-all text-slate-600 hover:text-slate-900"
                                    data-filter="sales">Penjualan</button>
                                <button type="button"
                                    class="stock-log-filter-btn px-3 py-1 rounded-lg font-medium transition-all text-slate-600 hover:text-slate-900"
                                    data-filter="purchase">Pembelian</button>
                                <button type="button"
                                    class="stock-log-filter-btn px-3 py-1 rounded-lg font-medium transition-all text-slate-600 hover:text-slate-900"
                                    data-filter="mutation">Mutasi</button>
                            </div>
                            <span class="text-[11px] text-slate-400 italic hidden sm:inline">Kartu stok pergerakan
                                obat</span>
                        </div>

                        <div
                            class="border border-slate-200 rounded-xl overflow-hidden max-h-[380px] overflow-y-auto custom-scrollbar relative">
                            <table id="orderItemsTable" class="w-full text-sm text-left">
                                <thead
                                    class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold tracking-wider sticky top-0 z-10 shadow-xs">
                                    <tr>
                                        <th class="px-3 py-3 border-b border-slate-200 text-center w-8">#</th>
                                        <th class="px-3 py-3 border-b border-slate-200">Tanggal</th>
                                        <th class="px-3 py-3 border-b border-slate-200">No. Transaksi</th>
                                        <th class="px-3 py-3 border-b border-slate-200 text-center">Tipe</th>
                                        <th class="px-3 py-3 border-b border-slate-200">Batch & ED</th>
                                        <th class="px-3 py-3 border-b border-slate-200 text-right">Saldo Awal</th>
                                        <th class="px-3 py-3 border-b border-slate-200 text-center">Mutasi</th>
                                        <th class="px-3 py-3 border-b border-slate-200 text-right">Saldo Akhir</th>
                                        <th class="px-3 py-3 border-b border-slate-200">Petugas</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-600"></tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Opname Input --}}
                    <div
                        class="bg-white rounded-2xl shadow-md border border-slate-200 p-6 border-t-4 border-t-indigo-500 relative overflow-hidden">
                        <!-- Decorative background -->
                        <div
                            class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-indigo-50 rounded-full opacity-50 pointer-events-none">
                        </div>

                        <div class="flex items-center justify-between mb-5 relative">
                            <div class="flex items-center gap-2">
                                <span
                                    class="flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 text-indigo-700 font-bold text-xs">3</span>
                                <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Input Fisik Opname
                                </h2>
                            </div>

                            @if (canAccessWarehouseStock())
                                {{-- Target Mode Switch (Pelayanan vs Gudang PMI) --}}
                                <div class="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 gap-1">
                                    <button type="button" id="btn_desktop_mode_pelayanan"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm">
                                        🏪 Pelayanan / Toko
                                    </button>
                                    <button type="button" id="btn_desktop_mode_gudang"
                                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800">
                                        📦 Gudang PMI
                                    </button>
                                </div>
                            @endif
                        </div>
                        <input type="hidden" id="target_mode" value="pelayanan">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5 relative">
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                <label id="label_desktop_stock_physic"
                                    class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">
                                    Stok Fisik Pelayanan
                                </label>
                                <input type="number" id="current_stock_physic" onkeyup="countDiscrepancy()"
                                    placeholder="0"
                                    class="w-full rounded-lg border-slate-200 text-xl font-black text-center text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 transition-all py-3 shadow-sm"
                                    autocomplete="off">
                            </div>
                            <div class="bg-slate-50 p-3 rounded-xl border border-slate-100 flex flex-col">
                                <label
                                    class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide flex justify-between items-center">
                                    Selisih Stok
                                    <span id="discrepancy_badge"
                                        class="hidden text-[10px] px-2 py-0.5 rounded-md font-bold shadow-sm"></span>
                                </label>
                                <input type="text" readonly id="stock_discrepancy" placeholder="—"
                                    class="w-full rounded-lg border-transparent bg-slate-100/50 text-xl font-black text-center text-slate-400 focus:ring-0 py-3 mt-auto h-[54px]">
                            </div>
                        </div>

                        {{-- ED, Batch Name & Etalase Section --}}
                        <div class="mb-6 bg-slate-50/80 p-4 rounded-xl border border-slate-100 relative space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">
                                        Tanggal Kadaluarsa (ED)
                                    </label>
                                    <input type="date" id="custom_expired_date" onchange="checkExpiredDateMatch()"
                                        class="w-full rounded-lg border-slate-200 text-sm py-2.5 px-3 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 mb-1.5 uppercase tracking-wide">
                                        Nama / No. Batch
                                    </label>
                                    <input type="text" id="custom_batch_name" placeholder="Contoh: BTH01"
                                        class="w-full rounded-lg border-slate-200 text-sm py-2.5 px-3 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 bg-white shadow-sm"
                                        autocomplete="off">
                                </div>
                            </div>
                            <input type="hidden" id="selected_batch_id" value="">

                            {{-- Etalase selector (Hidden in Gudang mode) --}}
                            <div id="desktop_group_etalase">
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wide">
                                        Pilih Etalase
                                    </label>
                                    <button type="button" id="btn_desktop_open_add_etalase"
                                        class="inline-flex items-center gap-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-2.5 py-1 rounded-md transition-colors">
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2.5">
                                            <line x1="12" y1="5" x2="12" y2="19"></line>
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                        </svg>
                                        Tambah Etalase
                                    </button>
                                </div>
                                <select id="etalase_select"
                                    class="w-full rounded-lg border-slate-200 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500 py-2.5 shadow-sm bg-white">
                                    <option value="">Memuat etalase…</option>
                                </select>
                            </div>

                            <select id="batch_select" style="display:none;"></select>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-3 pt-5 border-t border-slate-100 relative">
                            <button id="back"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:text-slate-900 transition-all shadow-sm">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Kembali
                            </button>
                            <a href="{{ route('supplies.printstockopname') }}" id="btn_export_opname" target="_blank"
                                class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-xl hover:bg-emerald-100 transition-all shadow-sm">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                Export Excel
                            </a>
                            <button id="save_opname"
                                class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-md shadow-indigo-200 focus:ring-4 focus:ring-indigo-100 transition-all">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Simpan Opname
                            </button>
                        </div>
                    </div>

                    {{-- Modal Quick Add Etalase (Desktop) --}}
                    <div id="modal_desktop_add_etalase"
                        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
                        <div
                            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
                            <h3 class="text-base font-bold text-slate-800 mb-3">+ Tambah Etalase Baru</h3>
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Nama
                                    Etalase</label>
                                <input type="text" id="desktop_new_etalase_name"
                                    class="w-full rounded-lg border-slate-200 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500"
                                    placeholder="Contoh: Etalase 8, Kulkas Baru...">
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" id="btn_desktop_close_etalase_modal"
                                    class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">Batal</button>
                                <button type="button" id="btn_desktop_submit_add_etalase"
                                    class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">Simpan</button>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Smart Import Stock Opname --}}
                    <div id="modal_import_stock_opname"
                        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-3 sm:p-4 overflow-y-auto">
                        <div
                            class="bg-white rounded-2xl max-w-4xl w-full shadow-2xl border border-slate-100 my-auto overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in zoom-in-95 duration-200">

                            {{-- Modal Header --}}
                            <div
                                class="px-6 py-4 bg-white border-b border-slate-100 flex items-center justify-between shrink-0">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white shadow-sm shrink-0">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-800 leading-tight">Import Stock
                                            Opname</h3>
                                        <p class="text-xs text-slate-400">Pembaruan stok fisik massal dengan pencocokan
                                            etalase & ED pintar</p>
                                    </div>
                                </div>
                                <button type="button" id="btn_close_import_modal"
                                    class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            {{-- Step Tracker --}}
                            <div
                                class="px-6 py-2.5 bg-slate-50 border-b border-slate-100 flex items-center justify-between text-xs font-semibold shrink-0">
                                <div class="flex items-center gap-2" id="import_step_indicator_1">
                                    <span
                                        class="w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold">1</span>
                                    <span class="text-emerald-700 font-bold">Unggah & Lokasi</span>
                                </div>
                                <div class="w-8 h-px bg-slate-200"></div>
                                <div class="flex items-center gap-2" id="import_step_indicator_2">
                                    <span
                                        class="w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold">2</span>
                                    <span class="text-slate-400">Analisis & Temuan</span>
                                </div>
                                <div class="w-8 h-px bg-slate-200"></div>
                                <div class="flex items-center gap-2" id="import_step_indicator_3">
                                    <span
                                        class="w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold">3</span>
                                    <span class="text-slate-400">Eksekusi</span>
                                </div>
                            </div>

                            {{-- Modal Body --}}
                            <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">

                                {{-- STEP 1: Upload File & Pilih Mode --}}
                                <div id="import_container_step_1" class="space-y-5">
                                    @if (canAccessWarehouseStock())
                                        <div>
                                            <label
                                                class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-2">Target
                                                Lokasi Opname</label>
                                            <div class="grid grid-cols-2 gap-3">
                                                <label
                                                    class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-indigo-400 transition-all bg-white has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40">
                                                    <input type="radio" name="import_target_mode" value="pelayanan"
                                                        checked class="text-indigo-600 focus:ring-indigo-500">
                                                    <div>
                                                        <span class="block text-xs font-bold text-slate-800">🏪 Pelayanan /
                                                            Toko</span>
                                                        <span class="block text-[11px] text-slate-400">Disimpan ke etalase
                                                            aktif cabang</span>
                                                    </div>
                                                </label>
                                                <label
                                                    class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:border-amber-400 transition-all bg-white has-[:checked]:border-amber-600 has-[:checked]:bg-amber-50/40">
                                                    <input type="radio" name="import_target_mode" value="gudang"
                                                        class="text-amber-600 focus:ring-amber-500">
                                                    <div>
                                                        <span class="block text-xs font-bold text-slate-800">📦 Gudang
                                                            PMI</span>
                                                        <span class="block text-[11px] text-slate-400">Disimpan ke stok
                                                            gudang pusat</span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    @else
                                        <input type="hidden" name="import_target_mode" value="pelayanan">
                                    @endif

                                    {{-- File Dropzone --}}
                                    <div>
                                        <label
                                            class="block text-xs font-bold text-slate-600 uppercase tracking-wide mb-2">File
                                            Excel Stock Opname</label>
                                        <div id="import_dropzone"
                                            class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-8 text-center bg-slate-50/50 hover:bg-emerald-50/20 transition-all cursor-pointer group">
                                            <input type="file" id="import_excel_file" accept=".xlsx, .xls, .csv"
                                                class="hidden">
                                            <div
                                                class="w-14 h-14 rounded-2xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform">
                                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                            </div>
                                            <p class="text-sm font-bold text-slate-700 mb-1">
                                                <span class="text-emerald-600 underline">Klik untuk memilih file</span>
                                                atau seret file ke sini
                                            </p>
                                            <p class="text-xs text-slate-400">Mendukung format Excel (.xlsx, .xls, .csv)
                                                hingga 20MB</p>
                                            <div id="import_file_preview"
                                                class="hidden mt-3 inline-flex items-center gap-2 px-3 py-1.5 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-lg">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span id="import_file_name">nama_file.xlsx</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Format Rules Highlight --}}
                                    <div
                                        class="p-4 bg-slate-50 rounded-xl border border-slate-200/80 text-xs text-slate-600 space-y-2">
                                        <div class="flex items-center gap-2 text-slate-700 font-bold">
                                            <span class="text-indigo-600">💡</span>
                                            <span>Aturan Kolom & Penyesuaian Cerdas:</span>
                                        </div>
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px]">
                                            <div class="bg-white p-2 rounded-lg border border-slate-200">
                                                <span class="font-bold text-slate-700 block">Kolom B:</span>
                                                <span class="text-slate-500">Kode Barang</span>
                                            </div>
                                            <div class="bg-white p-2 rounded-lg border border-slate-200">
                                                <span class="font-bold text-slate-700 block">Kolom C:</span>
                                                <span class="text-slate-500">Stok Fisik</span>
                                            </div>
                                            <div class="bg-white p-2 rounded-lg border border-slate-200">
                                                <span class="font-bold text-slate-700 block">Kolom D:</span>
                                                <span class="text-slate-500">Expired Date</span>
                                            </div>
                                            <div class="bg-white p-2 rounded-lg border border-slate-200">
                                                <span class="font-bold text-slate-700 block">Kolom E:</span>
                                                <span class="text-slate-500">Etalase</span>
                                            </div>
                                        </div>
                                        <p class="text-[11px] text-slate-500 pt-1 leading-relaxed">
                                            ✨ <strong>Stok Habis / Nihil (0) & ED Kosong:</strong> Jika barang habis di cabang, kolom stok boleh dikosongkan, diisi <code class="text-indigo-600 font-mono">0</code>, atau <code class="text-indigo-600 font-mono">-</code>, dan kolom ED boleh dikosongkan. Sistem otomatis menganggapnya valid dengan saldo akhir <strong>0</strong> (meniadakan sisa stok lama cabang).<br>
                                            ✨ <strong>Tanpa Batch:</strong> Sistem akan otomatis mencocokkan batch lama yang
                                            memiliki tanggal ED sama, atau membuatkan nomor batch baru jika belum ada.<br>
                                            ✨ <strong>Format ED Fleksibel:</strong> Mendukung penulisan Bulan-Tahun seperti
                                            <code class="text-indigo-600 font-mono">Jun-26</code>, <code
                                                class="text-indigo-600 font-mono">06/26</code>, <code
                                                class="text-indigo-600 font-mono">Agu-26</code> (otomatis disesuaikan ke
                                            tanggal akhir bulan, misal <strong class="text-slate-700">30/06/2026</strong>
                                            sesuai standar farmasi) maupun format tanggal lengkap.<br>
                                            ✨ <strong>Smart Normalizer:</strong> Typo penulisan etalase (misal <code
                                                class="text-pink-600 font-mono">Rak1</code>, <code
                                                class="text-pink-600 font-mono">RaK 1</code>, <code
                                                class="text-pink-600 font-mono">RAK 1</code>) akan otomatis dihubungkan ke
                                            etalase resmi cabang (<strong class="text-slate-700">Rak 1</strong>).
                                        </p>
                                    </div>
                                </div>

                                {{-- STEP 2: Laporan Temuan & Pratinjau --}}
                                <div id="import_container_step_2" class="space-y-5 hidden">
                                    {{-- KPI Stats Grid --}}
                                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                        <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 text-center">
                                            <span
                                                class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total
                                                Baris</span>
                                            <span class="text-xl font-black text-slate-700" id="stat_total_rows">0</span>
                                        </div>
                                        <div class="bg-emerald-50 p-3 rounded-xl border border-emerald-100 text-center">
                                            <span
                                                class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block">Obat
                                                Cocok</span>
                                            <span class="text-xl font-black text-emerald-700"
                                                id="stat_med_matched">0</span>
                                        </div>
                                        <div class="bg-blue-50 p-3 rounded-xl border border-blue-100 text-center">
                                            <span
                                                class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block">ED
                                                Terbaca</span>
                                            <span class="text-xl font-black text-blue-700" id="stat_ed_valid">0</span>
                                        </div>
                                        <div class="bg-purple-50 p-3 rounded-xl border border-purple-100 text-center">
                                            <span
                                                class="text-[10px] font-bold text-purple-600 uppercase tracking-wider block">Etalase
                                                Cocok</span>
                                            <span class="text-xl font-black text-purple-700"
                                                id="stat_etalase_matched">0</span>
                                        </div>
                                        <div class="bg-amber-50 p-3 rounded-xl border border-amber-100 text-center">
                                            <span
                                                class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block">Typo
                                                Dibenahi</span>
                                            <span class="text-xl font-black text-amber-700"
                                                id="stat_etalase_corrected">0</span>
                                        </div>
                                    </div>

                                    {{-- Typo Corrections Alert / List --}}
                                    <div id="import_typo_section"
                                        class="hidden p-3.5 bg-amber-50/70 border border-amber-200 rounded-xl space-y-1.5">
                                        <div class="flex items-center gap-1.5 text-xs font-bold text-amber-800">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <span>Penyesuaian Etalase Cerdas (Auto-Corrected Typo):</span>
                                        </div>
                                        <div id="import_typo_badges" class="flex flex-wrap gap-2 text-[11px]"></div>
                                    </div>

                                    {{-- Anomalies / Warnings (If any) --}}
                                    <div id="import_anomalies_section"
                                        class="hidden p-3.5 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 space-y-1 max-h-32 overflow-y-auto custom-scrollbar">
                                        <div class="font-bold flex items-center gap-1.5 text-rose-800">
                                            <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            <span>Peringatan / Data Tidak Cocok (Akan Dilewati):</span>
                                        </div>
                                        <ul id="import_anomalies_list"
                                            class="list-disc list-inside space-y-0.5 text-[11px] text-rose-600"></ul>
                                    </div>

                                    {{-- Preview Table --}}
                                    <div>
                                        <div class="flex items-center justify-between mb-2">
                                            <label
                                                class="text-xs font-bold text-slate-700 uppercase tracking-wide">Pratinjau
                                                Data Temuan</label>
                                            <span class="text-[11px] text-slate-400">Menampilkan sampel awal</span>
                                        </div>
                                        <div
                                            class="border border-slate-200 rounded-xl overflow-hidden max-h-56 overflow-y-auto custom-scrollbar">
                                            <table class="w-full text-left text-xs">
                                                <thead
                                                    class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold tracking-wider sticky top-0 border-b border-slate-200 z-10">
                                                    <tr>
                                                        <th class="px-3 py-2">Baris</th>
                                                        <th class="px-3 py-2">Kode</th>
                                                        <th class="px-3 py-2">Nama Obat</th>
                                                        <th class="px-3 py-2 text-right">Stok Fisik</th>
                                                        <th class="px-3 py-2 text-center">Expired Date</th>
                                                        <th class="px-3 py-2">Etalase</th>
                                                        <th class="px-3 py-2 text-center">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="import_preview_tbody"
                                                    class="divide-y divide-slate-100 text-slate-600"></tbody>
                                            </table>
                                        </div>
                                    </div>

                                    {{-- Execution Options --}}
                                    <div class="pt-2">
                                        <label class="flex items-center gap-2.5 cursor-pointer text-xs text-slate-700">
                                            <input type="checkbox" id="import_is_async"
                                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            <div>
                                                <span class="font-bold">Proses di latar belakang (Antrean / Background
                                                    Queue)</span>
                                                <span class="text-[11px] text-slate-400 block">Aktifkan opsi ini jika data
                                                    sangat besar (>300 baris) agar browser tidak mengalami timeout.</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                {{-- STEP 3: Loading & Progress Bar --}}
                                <div id="import_container_step_3" class="py-12 px-4 text-center space-y-4 hidden">
                                    <div
                                        class="w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mx-auto text-2xl animate-spin">
                                        ⏳
                                    </div>
                                    <div class="space-y-1">
                                        <h4 class="text-base font-bold text-slate-800" id="import_progress_title">
                                            Memproses Impor Stok Opname...</h4>
                                        <p class="text-xs text-slate-500" id="import_progress_subtitle">Harap tunggu,
                                            sistem sedang menyelaraskan batch & memperbarui stok.</p>
                                    </div>
                                    <div
                                        class="max-w-md mx-auto w-full bg-slate-100 rounded-full h-3 overflow-hidden border border-slate-200">
                                        <div id="import_progress_bar"
                                            class="bg-gradient-to-r from-indigo-600 to-emerald-500 h-full w-0 transition-all duration-300">
                                        </div>
                                    </div>
                                    <span class="text-xs font-black text-indigo-600"
                                        id="import_progress_percent">0%</span>
                                </div>

                                {{-- STEP 4: Success Message --}}
                                <div id="import_container_step_4" class="py-10 px-4 text-center space-y-4 hidden">
                                    <div
                                        class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-3xl shadow-sm">
                                        ✓
                                    </div>
                                    <div class="space-y-1">
                                        <h4 class="text-lg font-black text-slate-800">Impor Stok Opname Berhasil!</h4>
                                        <p class="text-xs text-slate-500" id="import_success_msg">Seluruh data stok fisik
                                            telah berhasil diperbarui dan direkonsiliasi ke sistem.</p>
                                    </div>
                                </div>

                            </div>

                            {{-- Modal Footer --}}
                            <div
                                class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between shrink-0">
                                <button type="button" id="btn_import_cancel"
                                    class="px-4 py-2 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 transition-colors">
                                    Batal
                                </button>
                                <div class="flex items-center gap-2">
                                    <button type="button" id="btn_back_to_step1"
                                        class="hidden px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-100 transition-colors">
                                        Ganti File
                                    </button>
                                    <button type="button" id="btn_analyze_excel"
                                        class="px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition-all flex items-center gap-2 cursor-pointer">
                                        <span>Periksa Data Excel</span>
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                    </button>
                                    <button type="button" id="btn_execute_import"
                                        class="hidden px-5 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-sm transition-all flex items-center gap-2 cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span id="btn_execute_import_text">Proses Simpan Opname</span>
                                    </button>
                                    <button type="button" id="btn_import_finish_close"
                                        class="hidden px-5 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm transition-all">
                                        Tutup & Muat Ulang
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>

                    {{-- Modal Batch Confirmation (Desktop) --}}
                    <div id="modal_desktop_batch_confirm"
                        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
                        <div
                            class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 text-center animate-in fade-in zoom-in-95 duration-200">
                            <div
                                class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-3 text-xl">
                                ℹ️</div>
                            <h3 class="text-base font-bold text-slate-800 mb-2">Batch dengan ED Sama Ditemukan</h3>
                            <p class="text-sm text-slate-600 mb-5 leading-relaxed">
                                Tanggal ED <strong id="desktop_dup_ed_text" class="text-indigo-600"></strong> sudah ada di
                                sistem dengan batch <strong id="desktop_dup_batch_text"
                                    class="text-amber-600"></strong>.<br>
                                Apakah ingin menggabungkan ke batch yang sudah ada?
                            </p>
                            <div class="flex flex-col gap-2">
                                <button type="button" id="btn_desktop_confirm_merge_batch"
                                    class="w-full py-2.5 px-4 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Ya, Gabung
                                </button>
                                <button type="button" id="btn_desktop_confirm_new_batch"
                                    class="w-full py-2.5 px-4 rounded-xl text-sm font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors">
                                    Tidak, Buat Baru Saja
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Scanner FAB (Mobile) --}}
    <a href="{{ route('supplies.scanner') }}"
        class="fixed bottom-6 right-6 z-50 flex items-center justify-center w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg hover:bg-indigo-700 transition-transform hover:scale-105 md:hidden"
        title="Scan Barcode">
        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M5 9V7a2 2 0 012-2h2M15 5h2a2 2 0 012 2v2M19 15v2a2 2 0 01-2 2h-2M9 19H7a2 2 0 01-2-2v-2" />
            <rect x="9" y="9" width="6" height="6" rx="1" />
        </svg>
    </a>
@endsection
@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        /* ── State ─────────────────────────────────────────────────────── */
        let current_storage_stock = 0;
        let current_counter_stock = 0;
        let total_stock = 0; // current system stock (storage + counter)
        let cachedBatches = [];
        let matchedBatchOnEd = null;
        let orderItemsTable, medicineData;
        let startDate = '',
            endDate = '',
            searchMedicine = '';

        /* ── Enter-key navigation ──────────────────────────────────────── */
        // Map: elementId → nextElementId  (or 'submit')
        const NAV_MAP = {
            'current_stock_physic': 'custom_expired_date',
            'custom_expired_date': 'custom_batch_name',
            'custom_batch_name': 'etalase_select',
            'etalase_select': 'submit',
        };

        function initEnterNavigation() {
            Object.entries(NAV_MAP).forEach(([id, nextId]) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    if (nextId === 'submit') {
                        SaveOpname();
                    } else {
                        document.getElementById(nextId)?.focus();
                    }
                });
            });
        }

        /* ── Etalase List & Quick Add (Desktop) ─────────────────────────── */
        function loadEtalases(selectedId = null) {
            const select = document.getElementById('etalase_select');
            select.innerHTML = '<option value="">Memuat etalase…</option>';

            fetch(`{{ route('items.select') }}`)
                .then(res => res.json())
                .then(data => {
                    select.innerHTML = '';
                    if (data && data.length > 0) {
                        data.forEach(item => {
                            const opt = document.createElement('option');
                            opt.value = item.id;
                            opt.textContent = item.name;
                            if (selectedId && selectedId == item.id) {
                                opt.selected = true;
                            }
                            select.appendChild(opt);
                        });
                    } else {
                        select.innerHTML = '<option value="">— Tidak ada etalase —</option>';
                    }
                })
                .catch(() => {
                    select.innerHTML = '<option value="">— Gagal memuat etalase —</option>';
                });
        }

        /* ── Target Mode Switch (Pelayanan vs Gudang PMI) ───────────────── */
        function setDesktopTargetMode(mode) {
            $('#target_mode').val(mode);
            const btnPel = document.getElementById('btn_desktop_mode_pelayanan');
            const btnGud = document.getElementById('btn_desktop_mode_gudang');
            const grpEtalase = document.getElementById('desktop_group_etalase');
            const lblPhysic = document.getElementById('label_desktop_stock_physic');

            if (mode === 'gudang') {
                if (btnGud) {
                    btnGud.className =
                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm';
                }
                if (btnPel) {
                    btnPel.className =
                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800';
                }
                if (grpEtalase) grpEtalase.style.display = 'none';
                if (lblPhysic) lblPhysic.textContent = 'Stok Fisik Gudang PMI';
            } else {
                if (btnPel) {
                    btnPel.className =
                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm';
                }
                if (btnGud) {
                    btnGud.className =
                        'px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800';
                }
                if (grpEtalase) grpEtalase.style.display = 'block';
                if (lblPhysic) lblPhysic.textContent = 'Stok Fisik Pelayanan';
            }

            countDiscrepancy();
        }

        /* ── Load batches into <select> ────────────────────────────────── */
        function loadBatches(medicine_id) {
            const canSeeWarehouse = {{ canAccessWarehouseStock() ? 'true' : 'false' }};

            fetch(`{{ route('supplies.batches') }}?medicine_id=${medicine_id}`)
                .then(res => res.json())
                .then(batches => {
                    cachedBatches = batches || [];

                    let totalStorageStock = 0;
                    let totalCounterStock = 0;

                    batches.forEach(b => {
                        const gStock = parseInt(b.stock || 0);
                        const cStock = parseInt(b.counter_stock || 0);
                        totalStorageStock += gStock;
                        totalCounterStock += cStock;
                    });

                    current_storage_stock = totalStorageStock;
                    current_counter_stock = totalCounterStock;
                    total_stock = totalStorageStock + totalCounterStock;

                    $('#qty_gudang').text(current_storage_stock);
                    $('#qty_etalase').text(current_counter_stock);
                    $('#qty_akhir').text(canSeeWarehouse ? total_stock : totalCounterStock);

                    countDiscrepancy();
                })
                .catch(() => {
                    cachedBatches = [];
                });
        }

        /* ── Check Duplicate Expired Date (ED) ─────────────────────────── */
        function checkExpiredDateMatch() {
            const enteredEd = $('#custom_expired_date').val();
            if (!enteredEd || !cachedBatches || cachedBatches.length === 0) return;

            const formattedEntered = enteredEd.replace(/\//g, '-');

            const matched = cachedBatches.find(b => {
                if (!b.expired_date) return false;
                const bEd = b.expired_date.substring(0, 10);
                return bEd === formattedEntered;
            });

            if (matched) {
                matchedBatchOnEd = matched;
                $('#desktop_dup_ed_text').text(matched.expired_date);
                $('#desktop_dup_batch_text').text(matched.name);
                const modal = document.getElementById('modal_desktop_batch_confirm');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }
        }

        /* ── Discrepancy indicator ─────────────────────────────────────── */
        function countDiscrepancy() {
            const valPhysic = $('#current_stock_physic').val();
            const input = document.getElementById('stock_discrepancy');
            const badge = document.getElementById('discrepancy_badge');
            const mode = $('#target_mode').val();

            if (valPhysic === '') {
                input.value = '';
                input.classList.remove('border-red-500', 'text-red-600');
                badge.style.display = 'none';
                return;
            }

            const physicNum = parseInt(valPhysic) || 0;
            const systemTargetStock = (mode === 'gudang') ? current_storage_stock : current_counter_stock;
            const discrepancy = physicNum - systemTargetStock;
            input.value = (discrepancy > 0 ? '+' : '') + discrepancy;

            if (discrepancy !== 0) {
                input.classList.add('border-red-500', 'text-red-600');
                if (discrepancy > 0) {
                    badge.textContent = `+${discrepancy} Lebih`;
                    badge.style.cssText =
                        'display:inline-block;background:#dcfce7;color:#16a34a;font-size:12px;font-weight:700;padding:3px 10px;border-radius:99px;margin-top:6px;';
                } else {
                    badge.textContent = `${discrepancy} Kurang`;
                    badge.style.cssText =
                        'display:inline-block;background:#fee2e2;color:#dc2626;font-size:12px;font-weight:700;padding:3px 10px;border-radius:99px;margin-top:6px;';
                }
            } else {
                input.classList.remove('border-red-500', 'text-red-600');
                badge.style.display = 'none';
            }
        }

        /* ── Save opname ───────────────────────────────────────────────── */
        function SaveOpname() {
            const medicineId = $('#medicine_id').val();
            const valPhysic = $('#current_stock_physic').val();
            const targetMode = $('#target_mode').val() || 'pelayanan';
            const etalasesId = $('#etalase_select').val();
            const customBatchName = $('#custom_batch_name').val();
            const customExpiredDate = $('#custom_expired_date').val();
            const batchesId = $('#selected_batch_id').val();

            if (!medicineId) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Pilih obat terlebih dahulu!',
                    position: 'topRight'
                });
                return;
            }
            if (valPhysic === '') {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Isi stok fisik terlebih dahulu!',
                    position: 'topRight'
                });
                document.getElementById('current_stock_physic')?.focus();
                return;
            }

            const btn = document.getElementById('save_opname');
            btn.disabled = true;
            btn.innerHTML =
                `<svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="40" stroke-dashoffset="15"/></svg> Menyimpan…`;

            const payload = {
                _token: "{{ csrf_token() }}",
                medicine_id: medicineId,
                target_mode: targetMode,
                batches_id: batchesId || null,
                custom_batch_name: customBatchName || null,
                custom_expired_date: customExpiredDate || null,
                etalases_id: (targetMode === 'pelayanan') ? (etalasesId || null) : null,
            };

            if (targetMode === 'gudang') {
                payload.stock_physic = valPhysic;
            } else {
                payload.counter_stock_physic = valPhysic;
            }

            $.ajax({
                url: "{{ route('supplies.opname') }}",
                type: 'POST',
                data: payload,
                success: function(response) {
                    iziToast.success({
                        title: 'Berhasil',
                        message: response.message || 'Stok berhasil disimpan!',
                        position: 'topRight'
                    });

                    // Reset input fields (keep medicine & batch list intact)
                    $('#current_stock_physic, #stock_discrepancy, #custom_batch_name, #custom_expired_date, #selected_batch_id')
                        .val('');
                    document.getElementById('discrepancy_badge').style.display = 'none';
                    document.getElementById('stock_discrepancy').classList.remove('border-red-500',
                        'text-red-600');
                    matchedBatchOnEd = null;

                    // Reload stock log
                    orderItemsTable.ajax.reload(null, false);

                    // Reload batches to reflect updated stock numbers
                    loadBatches(medicineId);

                    document.getElementById('current_stock_physic')?.focus();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Terjadi kesalahan!';
                    iziToast.error({
                        title: 'Gagal',
                        message: msg,
                        position: 'topRight'
                    });
                },
                complete: function() {
                    btn.disabled = false;
                    btn.innerHTML =
                        `<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Simpan Opname`;
                }
            });
        }

        /* ── DOM ready ─────────────────────────────────────────────────── */
        document.addEventListener('DOMContentLoaded', function() {
            initEnterNavigation();

            // Toggle custom batch container
            const toggleCustomBtn = document.getElementById('btn_toggle_custom_batch');
            const customBatchContainer = document.getElementById('custom_batch_container');
            if (toggleCustomBtn && customBatchContainer) {
                toggleCustomBtn.addEventListener('click', function() {
                    customBatchContainer.classList.toggle('hidden');
                    if (!customBatchContainer.classList.contains('hidden')) {
                        document.getElementById('custom_batch_name')?.focus();
                    }
                });
            }

            // Date range picker
            flatpickr("#dateRange", {
                mode: "range",
                dateFormat: "Y-m-d",
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                        endDate = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                    } else {
                        startDate = endDate = '';
                    }

                    let exportUrl = new URL("{{ route('supplies.printstockopname') }}", window.location
                        .origin);
                    if (startDate) exportUrl.searchParams.set('start_date', startDate);
                    if (endDate) exportUrl.searchParams.set('end_date', endDate);
                    $('#btn_export_opname').attr('href', exportUrl.toString());

                    orderItemsTable.ajax.reload();
                }
            });

            let stockLogFilterType = '';

            // Stock log DataTable
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                deferLoading: 0,
                ajax: {
                    url: "{{ route('supplies.medicineStockLog') }}",
                    data: d => {
                        d.searchMedicine = searchMedicine;
                        d.start_date = startDate;
                        d.end_date = endDate;
                        d.filter_type = stockLogFilterType;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        className: 'text-center text-xs font-semibold text-slate-400 py-3 px-3'
                    },
                    {
                        data: 'date',
                        className: 'py-3 px-3 text-xs'
                    },
                    {
                        data: 'transaction_code',
                        className: 'py-3 px-3'
                    },
                    {
                        data: 'type_badge',
                        className: 'text-center py-3 px-3'
                    },
                    {
                        data: 'batch_info',
                        className: 'py-3 px-3'
                    },
                    {
                        data: 'qty_before',
                        className: 'text-right font-medium text-slate-600 py-3 px-3 text-xs'
                    },
                    {
                        data: 'stock',
                        className: 'text-center py-3 px-3'
                    },
                    {
                        data: 'qty_after',
                        className: 'text-right font-bold text-slate-800 py-3 px-3 text-xs'
                    },
                    {
                        data: 'user_name',
                        className: 'py-3 px-3 text-xs'
                    },
                ],
                paging: true,
                pageLength: 10,
                lengthChange: false,
                searching: false,
                info: true,
                language: {
                    emptyTable: "Silakan pilih obat terlebih dahulu",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ transaksi",
                    infoEmpty: "Tidak ada data riwayat stok",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    paginate: {
                        previous: '‹',
                        next: '›'
                    }
                },
            });

            // Update stats whenever DataTable gets response from server
            orderItemsTable.on('xhr', function() {
                const json = orderItemsTable.ajax.json();
                if (json) {
                    $('#qty_awal').text(json.qty_awal !== undefined ? json.qty_awal : 0);
                    $('#qty_beli').text(json.qty_beli !== undefined ? json.qty_beli : 0);
                    $('#qty_jual').text(json.qty_jual !== undefined ? json.qty_jual : 0);
                }
            });

            // Filter button click handler
            $(document).on('click', '.stock-log-filter-btn', function() {
                $('.stock-log-filter-btn').removeClass(
                    'active bg-white text-indigo-700 shadow-xs font-bold').addClass(
                    'text-slate-600 hover:text-slate-900 font-medium');
                $(this).addClass('active bg-white text-indigo-700 shadow-xs font-bold').removeClass(
                    'text-slate-600 hover:text-slate-900 font-medium');
                stockLogFilterType = $(this).data('filter') || '';
                if (searchMedicine) {
                    orderItemsTable.ajax.reload();
                }
            });

            // Medicine DataTable
            medicineData = $('#medicines_data').DataTable({
                responsive: true,
                serverSide: true,
                ajax: "{{ route('supplies.medicines') }}",
                dom: '<"top"f>rt<"bottom"p>', // Show only search at top and pagination at bottom
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'unit'
                    },
                ],
                pageLength: 10,
                language: {
                    paginate: {
                        previous: '‹',
                        next: '›'
                    },
                    search: '',
                    searchPlaceholder: 'Cari obat...',
                    emptyTable: 'Tidak ada data obat'
                },
                initComplete: function() {
                    $('#medicines_data_filter input').focus();
                }
            });

            // Load etalases on load
            loadEtalases();

            // Desktop target mode switch
            document.getElementById('btn_desktop_mode_pelayanan')?.addEventListener('click', () =>
                setDesktopTargetMode('pelayanan'));
            document.getElementById('btn_desktop_mode_gudang')?.addEventListener('click', () =>
                setDesktopTargetMode('gudang'));

            // Desktop Etalase Modal
            document.getElementById('btn_desktop_open_add_etalase')?.addEventListener('click', () => {
                $('#desktop_new_etalase_name').val('');
                const modal = document.getElementById('modal_desktop_add_etalase');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.getElementById('desktop_new_etalase_name')?.focus();
                }
            });

            document.getElementById('btn_desktop_close_etalase_modal')?.addEventListener('click', () => {
                const modal = document.getElementById('modal_desktop_add_etalase');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            });

            document.getElementById('btn_desktop_submit_add_etalase')?.addEventListener('click', () => {
                const name = $('#desktop_new_etalase_name').val().trim();
                if (!name) {
                    iziToast.warning({
                        title: 'Peringatan',
                        message: 'Nama etalase tidak boleh kosong.',
                        position: 'topRight'
                    });
                    return;
                }

                const btn = document.getElementById('btn_desktop_submit_add_etalase');
                btn.disabled = true;
                btn.textContent = 'Menyimpan...';

                $.ajax({
                    url: "{{ route('items.store') }}",
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        name: name
                    },
                    success: function(res) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: 'Etalase baru ditambahkan!',
                            position: 'topRight'
                        });
                        const modal = document.getElementById('modal_desktop_add_etalase');
                        if (modal) {
                            modal.classList.add('hidden');
                            modal.classList.remove('flex');
                        }
                        loadEtalases(res.data?.id);
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON?.message || 'Gagal menambahkan etalase!';
                        iziToast.error({
                            title: 'Gagal',
                            message: msg,
                            position: 'topRight'
                        });
                    },
                    complete: function() {
                        btn.disabled = false;
                        btn.textContent = 'Simpan';
                    }
                });
            });

            // Desktop Batch Confirm Modal Actions
            document.getElementById('btn_desktop_confirm_merge_batch')?.addEventListener('click', () => {
                if (matchedBatchOnEd) {
                    $('#custom_batch_name').val(matchedBatchOnEd.name);
                    $('#selected_batch_id').val(matchedBatchOnEd.id);
                }
                const modal = document.getElementById('modal_desktop_batch_confirm');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                document.getElementById('custom_batch_name')?.focus();
            });

            document.getElementById('btn_desktop_confirm_new_batch')?.addEventListener('click', () => {
                $('#selected_batch_id').val('');
                $('#custom_batch_name').val('');
                const modal = document.getElementById('modal_desktop_batch_confirm');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                document.getElementById('custom_batch_name')?.focus();
            });

            // Double-click a medicine row to select it
            $('#medicines_data tbody').on('dblclick', 'tr', function() {
                const medicine = medicineData.row(this).data();
                if (!medicine) return;

                $('#medicines_data tbody tr').removeClass('active');
                $(this).addClass('active');

                $('#medicine_name').val(medicine.name);
                $('#medicine_id').val(medicine.id);
                searchMedicine = medicine.name;

                // Load batches (FEFO first)
                loadBatches(medicine.id);

                // Load stock log for this medicine
                orderItemsTable.ajax.reload(function(json) {
                    if (json) {
                        $('#qty_awal').text(json.qty_awal ?? 0);
                        $('#qty_beli').text(json.qty_beli ?? 0);
                        $('#qty_jual').text(json.qty_jual ?? 0);
                    }
                    document.getElementById('current_stock_physic')?.focus();
                });
            });

            /* ── Smart Stock Opname Import & Template Logic ───────────── */
            let currentImportToken = null;
            let currentImportStats = null;
            let importPollInterval = null;

            // Template Download Handler
            window.downloadOpnameTemplate = function(includeMedicines) {
                const mode = $('#target_mode').val() || 'pelayanan';
                const url =
                    `{{ route('supplies.stockOpname.template') }}?mode=${encodeURIComponent(mode)}&include_medicines=${includeMedicines ? 1 : 0}`;
                window.location.href = url;
                $('#dropdown_template_menu').addClass('hidden');
            };

            // Toggle template menu dropdown
            $('#btn_download_template_menu').on('click', function(e) {
                e.stopPropagation();
                $('#dropdown_template_menu').toggleClass('hidden');
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('#dropdown_template_wrapper').length) {
                    $('#dropdown_template_menu').addClass('hidden');
                }
            });

            // Open & Close Smart Import Modal
            function openImportModal() {
                // Sync target mode with page selection
                const pageMode = $('#target_mode').val() || 'pelayanan';
                $(`input[name="import_target_mode"][value="${pageMode}"]`).prop('checked', true);

                // Reset inputs & steps
                $('#import_excel_file').val('');
                $('#import_file_preview').addClass('hidden');
                $('#import_dropzone').removeClass('border-emerald-500 bg-emerald-50/20');
                currentImportToken = null;
                currentImportStats = null;
                if (importPollInterval) clearInterval(importPollInterval);

                switchImportStep(1);

                const modal = document.getElementById('modal_import_stock_opname');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeImportModal() {
                const modal = document.getElementById('modal_import_stock_opname');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                if (importPollInterval) clearInterval(importPollInterval);
            }

            $('#btn_open_import_modal').on('click', openImportModal);
            $('#btn_close_import_modal, #btn_import_cancel').on('click', closeImportModal);
            $('#btn_import_finish_close').on('click', function() {
                closeImportModal();
                window.location.reload();
            });

            // Step Switcher
            function switchImportStep(step) {
                // Reset containers
                $('#import_container_step_1, #import_container_step_2, #import_container_step_3, #import_container_step_4')
                    .addClass('hidden');
                $('#btn_analyze_excel, #btn_execute_import, #btn_back_to_step1, #btn_import_finish_close, #btn_import_cancel')
                    .addClass('hidden');

                // Update step indicators
                const step1Ind = $('#import_step_indicator_1');
                const step2Ind = $('#import_step_indicator_2');
                const step3Ind = $('#import_step_indicator_3');

                if (step === 1) {
                    $('#import_container_step_1').removeClass('hidden');
                    $('#btn_analyze_excel, #btn_import_cancel').removeClass('hidden');

                    step1Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold'
                    );
                    step1Ind.find('span:last').attr('class', 'text-emerald-700 font-bold');
                    step2Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold'
                    );
                    step2Ind.find('span:last').attr('class', 'text-slate-400');
                    step3Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold'
                    );
                    step3Ind.find('span:last').attr('class', 'text-slate-400');
                } else if (step === 2) {
                    $('#import_container_step_2').removeClass('hidden');
                    $('#btn_execute_import, #btn_back_to_step1, #btn_import_cancel').removeClass('hidden');

                    step1Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold'
                    );
                    step1Ind.find('span:last').attr('class', 'text-emerald-700 font-semibold');
                    step2Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold'
                    );
                    step2Ind.find('span:last').attr('class', 'text-emerald-700 font-bold');
                    step3Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center text-[10px] font-bold'
                    );
                    step3Ind.find('span:last').attr('class', 'text-slate-400');
                } else if (step === 3) {
                    $('#import_container_step_3').removeClass('hidden');

                    step1Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold'
                    );
                    step2Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold'
                    );
                    step3Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold'
                    );
                    step3Ind.find('span:last').attr('class', 'text-emerald-700 font-bold');
                } else if (step === 4) {
                    $('#import_container_step_4').removeClass('hidden');
                    $('#btn_import_finish_close').removeClass('hidden');

                    step1Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold'
                    );
                    step2Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] font-bold'
                    );
                    step3Ind.find('span:first').attr('class',
                        'w-5 h-5 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px] font-bold'
                    );
                    step3Ind.find('span:last').attr('class', 'text-emerald-700 font-bold');
                }
            }

            $('#btn_back_to_step1').on('click', () => switchImportStep(1));

            // Dropzone & File Select
            const dropzone = document.getElementById('import_dropzone');
            const fileInput = document.getElementById('import_excel_file');

            dropzone?.addEventListener('click', () => fileInput.click());

            fileInput?.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    $('#import_file_name').text(`${file.name} (${(file.size / 1024).toFixed(1)} KB)`);
                    $('#import_file_preview').removeClass('hidden');
                    $('#import_dropzone').addClass('border-emerald-500 bg-emerald-50/20');
                }
            });

            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone?.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('border-emerald-500', 'bg-emerald-50/30');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone?.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('border-emerald-500', 'bg-emerald-50/30');
                });
            });

            dropzone?.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files && files.length > 0) {
                    fileInput.files = files;
                    const file = files[0];
                    $('#import_file_name').text(`${file.name} (${(file.size / 1024).toFixed(1)} KB)`);
                    $('#import_file_preview').removeClass('hidden');
                    $('#import_dropzone').addClass('border-emerald-500 bg-emerald-50/20');
                }
            });

            // Analyze Excel
            $('#btn_analyze_excel').on('click', function() {
                const files = fileInput.files;
                if (!files || files.length === 0) {
                    iziToast.warning({
                        title: 'Peringatan',
                        message: 'Silakan pilih file Excel terlebih dahulu.',
                        position: 'topRight'
                    });
                    return;
                }

                const file = files[0];
                const targetMode = $('input[name="import_target_mode"]:checked').val() || 'pelayanan';

                const btn = $(this);
                btn.prop('disabled', true).addClass('opacity-70');
                btn.html(`
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Menganalisis Data...</span>
                `);

                const formData = new FormData();
                formData.append('file', file);
                formData.append('target_mode', targetMode);
                formData.append('_token', '{{ csrf_token() }}');

                axios.post('{{ route('supplies.stockOpname.analyze') }}', formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })
                    .then(res => {
                        const data = res.data;
                        if (!data.success) {
                            throw new Error(data.message || 'Gagal menganalisis file.');
                        }

                        currentImportToken = data.token;
                        currentImportStats = data.stats;

                        // Render Stats
                        $('#stat_total_rows').text(data.stats.total_rows);
                        $('#stat_med_matched').text(data.stats.medicines_matched_count);
                        $('#stat_ed_valid').text(data.stats.ed_valid_count + (data.stats
                            .ed_defaulted_count > 0 ?
                            ` (+${data.stats.ed_defaulted_count} Def)` : ''));
                        $('#stat_etalase_matched').text(data.stats.etalases_matched_count);
                        $('#stat_etalase_corrected').text(data.stats.etalases_corrected_count);

                        // Render Typo Corrections Badges
                        const typoBadgesContainer = $('#import_typo_badges');
                        typoBadgesContainer.empty();
                        const correctedList = [];

                        (data.preview_rows || []).forEach(row => {
                            if (row.etalase_info && row.etalase_info.status === 'corrected') {
                                const pair =
                                    `"${row.etalase_info.original}" ➔ "${row.etalase_info.corrected}"`;
                                if (!correctedList.includes(pair)) correctedList.push(pair);
                            }
                        });

                        if (correctedList.length > 0 || data.stats.etalases_corrected_count > 0) {
                            $('#import_typo_section').removeClass('hidden');
                            correctedList.forEach(pair => {
                                typoBadgesContainer.append(`
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-100/80 border border-amber-300 text-amber-900 rounded-lg font-mono text-[11px] font-semibold">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    ${pair}
                                </span>
                            `);
                            });
                            if (data.stats.etalases_corrected_count > correctedList.length) {
                                typoBadgesContainer.append(`
                                <span class="px-2 py-1 text-slate-500 italic text-[10px] self-center">
                                    +${data.stats.etalases_corrected_count - correctedList.length} penyesuaian lainnya
                                </span>
                            `);
                            }
                        } else {
                            $('#import_typo_section').addClass('hidden');
                        }

                        // Render Anomalies / Warnings
                        const anomaliesSection = $('#import_anomalies_section');
                        const anomaliesList = $('#import_anomalies_list');
                        anomaliesList.empty();

                        if (data.anomalies && data.anomalies.length > 0) {
                            anomaliesSection.removeClass('hidden');
                            data.anomalies.slice(0, 15).forEach(anom => {
                                const allMsgs = [...(anom.errors || []), ...(anom.warnings ||
                                [])];
                                anomaliesList.append(`
                                <li>
                                    <strong>Baris ${anom.row} (${anom.code || 'Tanpa Kode'}):</strong> ${allMsgs.join('; ')}
                                </li>
                            `);
                            });
                            if (data.anomalies.length > 15) {
                                anomaliesList.append(`
                                <li class="font-bold text-rose-800 italic">
                                    ... dan ${data.anomalies.length - 15} catatan lainnya
                                </li>
                            `);
                            }
                        } else {
                            anomaliesSection.addClass('hidden');
                        }

                        // Render Preview Table
                        const tbody = $('#import_preview_tbody');
                        tbody.empty();

                        (data.preview_rows || []).forEach(row => {
                            let etalaseBadge = '';
                            if (row.etalase_info) {
                                if (row.etalase_info.status === 'corrected') {
                                    etalaseBadge =
                                        `<span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold text-[10px]" title="Disesuaikan dari ${row.etalase_info.original}">✨ ${row.etalase_name}</span>`;
                                } else if (row.etalase_info.status === 'exact') {
                                    etalaseBadge =
                                        `<span class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-medium">${row.etalase_name}</span>`;
                                } else {
                                    etalaseBadge =
                                        `<span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px] font-medium">${row.etalase_name}</span>`;
                                }
                            } else {
                                etalaseBadge =
                                    `<span class="text-slate-400 text-[10px]">${row.etalase_name || '-'}</span>`;
                            }

                            let stockDisplay = '';
                            if (row.stock === 0) {
                                stockDisplay = `<span class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-bold text-xs" title="Stok Habis / Nihil (0)">0 (Nihil)</span>`;
                            } else {
                                stockDisplay = `<span class="font-black text-slate-800">${row.stock}</span> <span class="text-[10px] font-normal text-slate-400">${row.medicine_unit || ''}</span>`;
                            }

                            let edDisplay = '';
                            if (row.is_empty_ed && row.stock === 0) {
                                edDisplay = `<span class="text-slate-400 italic text-xs" title="ED kosong otomatis diselaraskan karena barang habis">— (Stok 0)</span>`;
                            } else {
                                edDisplay = `<span class="font-mono text-slate-600">${row.expired_date || '-'}</span>`;
                            }

                            let statusBadge = '';
                            if (row.is_valid) {
                                if (row.stock === 0) {
                                    statusBadge = `<span class="inline-flex items-center gap-1 text-emerald-600 font-bold text-xs"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Siap (0)</span>`;
                                } else {
                                    statusBadge = `<span class="inline-flex items-center gap-1 text-emerald-600 font-bold text-xs"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Siap</span>`;
                                }
                            } else {
                                statusBadge = `<span class="inline-flex items-center gap-1 text-rose-500 font-bold text-xs"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg> Error</span>`;
                            }

                            tbody.append(`
                            <tr class="hover:bg-slate-50 transition-colors ${!row.is_valid ? 'bg-rose-50/40' : ''}">
                                <td class="px-3 py-2 font-mono text-slate-400">${row.row_index}</td>
                                <td class="px-3 py-2 font-mono font-bold text-slate-700">${row.medicine_code || '-'}</td>
                                <td class="px-3 py-2 font-semibold text-slate-800">${row.medicine_name || '-'}</td>
                                <td class="px-3 py-2 text-right">${stockDisplay}</td>
                                <td class="px-3 py-2 text-center">${edDisplay}</td>
                                <td class="px-3 py-2">${etalaseBadge}</td>
                                <td class="px-3 py-2 text-center">${statusBadge}</td>
                            </tr>
                        `);
                        });

                        // Update execute button text
                        const validCount = data.stats.valid_rows || 0;
                        $('#btn_execute_import_text').text(
                            `Proses Simpan Opname (${validCount} Obat Valid)`);

                        switchImportStep(2);
                    })
                    .catch(err => {
                        const msg = err.response?.data?.message || err.message ||
                            'Gagal membaca atau memproses file Excel.';
                        iziToast.error({
                            title: 'Kesalahan File',
                            message: msg,
                            position: 'topRight'
                        });
                    })
                    .finally(() => {
                        btn.prop('disabled', false).removeClass('opacity-70');
                        btn.html(`
                        <span>Periksa Data Excel</span>
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    `);
                    });
            });

            // Execute Import
            $('#btn_execute_import').on('click', function() {
                if (!currentImportToken) {
                    iziToast.warning({
                        title: 'Peringatan',
                        message: 'Sesi impor tidak valid. Silakan ulangi unggah file.'
                    });
                    return;
                }

                const targetMode = $('input[name="import_target_mode"]:checked').val() || 'pelayanan';
                const isAsync = $('#import_is_async').is(':checked') ? 1 : 0;

                switchImportStep(3);
                $('#import_progress_bar').css('width', '15%');
                $('#import_progress_percent').text('15%');
                $('#import_progress_title').text('Menyiapkan batch & stok fisik...');

                const payload = {
                    token: currentImportToken,
                    target_mode: targetMode,
                    is_async: isAsync,
                    _token: '{{ csrf_token() }}'
                };

                if (isAsync) {
                    axios.post('{{ route('supplies.stockOpname.execute') }}', payload)
                        .then(res => {
                            if (res.data.is_async && res.data.job_id) {
                                const jobId = res.data.job_id;
                                let elapsed = 0;
                                importPollInterval = setInterval(() => {
                                    elapsed += 1;
                                    axios.get(
                                            `{{ url('/stockopname/import-status') }}/${jobId}`)
                                        .then(statusRes => {
                                            const job = statusRes.data;
                                            const pct = Math.max(15, job.progress || 0);
                                            $('#import_progress_bar').css('width',
                                                `${pct}%`);
                                            $('#import_progress_percent').text(`${pct}%`);

                                            if (pct >= 80) {
                                                $('#import_progress_title').text(
                                                    'Menyelaraskan saldo dan log riwayat...'
                                                );
                                            }

                                            if (job.finished || job.status === 'finished' ||
                                                job.status === 'completed') {
                                                clearInterval(importPollInterval);
                                                $('#import_progress_bar').css('width',
                                                    '100%');
                                                $('#import_progress_percent').text('100%');
                                                setTimeout(() => {
                                                    switchImportStep(4);
                                                    iziToast.success({
                                                        title: 'Berhasil',
                                                        message: 'Impor stok opname selesai diproses!',
                                                        position: 'topRight'
                                                    });
                                                }, 400);
                                            } else if (job.failed || job.status ===
                                                'failed') {
                                                clearInterval(importPollInterval);
                                                switchImportStep(2);
                                                iziToast.error({
                                                    title: 'Gagal',
                                                    message: 'Proses impor di latar belakang mengalami kegagalan.',
                                                    position: 'topRight'
                                                });
                                            }
                                        })
                                        .catch(() => {
                                            if (elapsed > 120) {
                                                clearInterval(importPollInterval);
                                                switchImportStep(2);
                                            }
                                        });
                                }, 1200);
                            }
                        })
                        .catch(err => {
                            switchImportStep(2);
                            iziToast.error({
                                title: 'Gagal',
                                message: err.response?.data?.message ||
                                    'Gagal memulai proses latar belakang.',
                                position: 'topRight'
                            });
                        });
                } else {
                    // Synchronous processing
                    let pseudoProgress = 15;
                    const pseudoTimer = setInterval(() => {
                        if (pseudoProgress < 85) {
                            pseudoProgress += Math.floor(Math.random() * 15) + 5;
                            if (pseudoProgress > 85) pseudoProgress = 85;
                            $('#import_progress_bar').css('width', `${pseudoProgress}%`);
                            $('#import_progress_percent').text(`${pseudoProgress}%`);
                        }
                    }, 400);

                    axios.post('{{ route('supplies.stockOpname.execute') }}', payload)
                        .then(res => {
                            clearInterval(pseudoTimer);
                            $('#import_progress_bar').css('width', '100%');
                            $('#import_progress_percent').text('100%');

                            setTimeout(() => {
                                switchImportStep(4);
                                if (res.data.message) {
                                    $('#import_success_msg').text(res.data.message);
                                }
                                iziToast.success({
                                    title: 'Berhasil',
                                    message: res.data.message ||
                                        'Stok opname berhasil disimpan!',
                                    position: 'topRight'
                                });
                            }, 300);
                        })
                        .catch(err => {
                            clearInterval(pseudoTimer);
                            switchImportStep(2);
                            iziToast.error({
                                title: 'Gagal Menyimpan',
                                message: err.response?.data?.message ||
                                    'Terjadi kesalahan saat memproses data ke database.',
                                position: 'topRight'
                            });
                        });
                }
            });

            $('#save_opname').on('click', SaveOpname);
            $('#back').on('click', () => window.location.href = "{{ route('home') }}");
        });
    </script>
@endsection
