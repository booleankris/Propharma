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
                        <p class="text-xs text-slate-400">Rekonsiliasi Stok Obat</p>
                    </div>
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
                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

                        <div class="grid grid-cols-2 {{ canAccessWarehouseStock() ? 'md:grid-cols-6' : 'md:grid-cols-5' }} gap-3 mb-6">
                            <div
                                class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col justify-center transition-all hover:shadow-sm">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">QTY
                                    Awal</span>
                                <span class="text-2xl font-black text-slate-700" id="qty_awal">—</span>
                            </div>
                            <div
                                class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col justify-center transition-all hover:shadow-sm">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">QTY
                                    Beli</span>
                                <span class="text-2xl font-black text-slate-700" id="qty_beli">—</span>
                            </div>
                            <div
                                class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex flex-col justify-center transition-all hover:shadow-sm">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">QTY
                                    Jual</span>
                                <span class="text-2xl font-black text-slate-700" id="qty_jual">—</span>
                            </div>
                            @if(canAccessWarehouseStock())
                            <div
                                class="bg-amber-50/50 p-4 rounded-xl border border-amber-100 flex flex-col justify-center transition-all hover:shadow-sm hover:bg-amber-50">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 mb-1">Stok
                                    Gudang</span>
                                <span class="text-2xl font-black text-amber-600" id="qty_gudang">—</span>
                            </div>
                            @endif
                            <div
                                class="bg-purple-50/50 p-4 rounded-xl border border-purple-100 flex flex-col justify-center transition-all hover:shadow-sm hover:bg-purple-50">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600 mb-1">Stok
                                    Etalase</span>
                                <span class="text-2xl font-black text-purple-600" id="qty_etalase">—</span>
                            </div>
                            <div
                                class="bg-emerald-50/50 p-4 rounded-xl border border-emerald-100 flex flex-col justify-center transition-all hover:shadow-sm hover:bg-emerald-50">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 mb-1">Total
                                    Stok</span>
                                <span class="text-2xl font-black text-emerald-600" id="qty_akhir">—</span>
                            </div>
                        </div>

                        <div
                            class="border border-slate-200 rounded-xl overflow-hidden max-h-[350px] overflow-y-auto custom-scrollbar relative">
                            <table id="orderItemsTable" class="w-full text-sm text-left">
                                <thead
                                    class="bg-slate-50 text-slate-500 uppercase text-[10px] font-bold tracking-wider sticky top-0 z-10 shadow-sm">
                                    <tr>
                                        <th class="px-4 py-3 border-b border-slate-200">#</th>
                                        <th class="px-4 py-3 border-b border-slate-200">Tanggal</th>
                                        <th class="px-4 py-3 border-b border-slate-200">Kode</th>
                                        <th class="px-4 py-3 border-b border-slate-200">Tipe</th>
                                        <th class="px-4 py-3 border-b border-slate-200 text-right">Saldo Awal</th>
                                        <th class="px-4 py-3 border-b border-slate-200 text-right">Qty</th>
                                        <th class="px-4 py-3 border-b border-slate-200 text-right">Jumlah</th>
                                        <th class="px-4 py-3 border-b border-slate-200 text-right">Saldo Kini</th>
                                        <th class="px-4 py-3 border-b border-slate-200">Ket.</th>
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
                                <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Input Fisik Opname</h2>
                            </div>

                            @if(canAccessWarehouseStock())
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
                                <label id="label_desktop_stock_physic" class="block text-xs font-bold text-slate-500 mb-2 uppercase tracking-wide">
                                    Stok Fisik Pelayanan
                                </label>
                                <input type="number" id="current_stock_physic"
                                    onkeyup="countDiscrepancy()" placeholder="0"
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
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
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
                    <div id="modal_desktop_add_etalase" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
                        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
                            <h3 class="text-base font-bold text-slate-800 mb-3">+ Tambah Etalase Baru</h3>
                            <div class="mb-4">
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Nama Etalase</label>
                                <input type="text" id="desktop_new_etalase_name" class="w-full rounded-lg border-slate-200 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-500" placeholder="Contoh: Etalase 8, Kulkas Baru...">
                            </div>
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" id="btn_desktop_close_etalase_modal" class="px-4 py-2 text-xs font-bold text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">Batal</button>
                                <button type="button" id="btn_desktop_submit_add_etalase" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">Simpan</button>
                            </div>
                        </div>
                    </div>

                    {{-- Modal Batch Confirmation (Desktop) --}}
                    <div id="modal_desktop_batch_confirm" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
                        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-100 text-center animate-in fade-in zoom-in-95 duration-200">
                            <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-3 text-xl">ℹ️</div>
                            <h3 class="text-base font-bold text-slate-800 mb-2">Batch dengan ED Sama Ditemukan</h3>
                            <p class="text-sm text-slate-600 mb-5 leading-relaxed">
                                Tanggal ED <strong id="desktop_dup_ed_text" class="text-indigo-600"></strong> sudah ada di sistem dengan batch <strong id="desktop_dup_batch_text" class="text-amber-600"></strong>.<br>
                                Apakah ingin menggabungkan ke batch yang sudah ada?
                            </p>
                            <div class="flex flex-col gap-2">
                                <button type="button" id="btn_desktop_confirm_merge_batch" class="w-full py-2.5 px-4 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                    Ya, Gabung
                                </button>
                                <button type="button" id="btn_desktop_confirm_new_batch" class="w-full py-2.5 px-4 rounded-xl text-sm font-bold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors">
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
                    btnGud.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm';
                }
                if (btnPel) {
                    btnPel.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800';
                }
                if (grpEtalase) grpEtalase.style.display = 'none';
                if (lblPhysic) lblPhysic.textContent = 'Stok Fisik Gudang PMI';
            } else {
                if (btnPel) {
                    btnPel.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-indigo-600 text-white shadow-sm';
                }
                if (btnGud) {
                    btnGud.className = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-800';
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
                    $('#current_stock_physic, #stock_discrepancy, #custom_batch_name, #custom_expired_date, #selected_batch_id').val('');
                    document.getElementById('discrepancy_badge').style.display = 'none';
                    document.getElementById('stock_discrepancy').classList.remove('border-red-500', 'text-red-600');
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

                    let exportUrl = new URL("{{ route('supplies.printstockopname') }}", window.location.origin);
                    if (startDate) exportUrl.searchParams.set('start_date', startDate);
                    if (endDate) exportUrl.searchParams.set('end_date', endDate);
                    $('#btn_export_opname').attr('href', exportUrl.toString());

                    orderItemsTable.ajax.reload();
                }
            });

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
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'transaction_code'
                    },
                    {
                        data: 'type'
                    },
                    {
                        data: 'qty_before'
                    },
                    {
                        data: 'stock'
                    },
                    {
                        data: 'qty_after'
                    },
                    {
                        data: 'supply'
                    },
                    {
                        data: 'status'
                    },
                ],
                paging: true,
                searching: false,
                info: false,
                language: {
                    emptyTable: "Silakan pilih obat terlebih dahulu",
                    paginate: {
                        previous: '‹',
                        next: '›'
                    }
                },
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
            document.getElementById('btn_desktop_mode_pelayanan')?.addEventListener('click', () => setDesktopTargetMode('pelayanan'));
            document.getElementById('btn_desktop_mode_gudang')?.addEventListener('click', () => setDesktopTargetMode('gudang'));

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
                    iziToast.warning({ title: 'Peringatan', message: 'Nama etalase tidak boleh kosong.', position: 'topRight' });
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
                        iziToast.success({ title: 'Berhasil', message: 'Etalase baru ditambahkan!', position: 'topRight' });
                        const modal = document.getElementById('modal_desktop_add_etalase');
                        if (modal) {
                            modal.classList.add('hidden');
                            modal.classList.remove('flex');
                        }
                        loadEtalases(res.data?.id);
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON?.message || 'Gagal menambahkan etalase!';
                        iziToast.error({ title: 'Gagal', message: msg, position: 'topRight' });
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

            $('#save_opname').on('click', SaveOpname);
            $('#back').on('click', () => window.location.href = "{{ route('home') }}");
        });
    </script>
@endsection
