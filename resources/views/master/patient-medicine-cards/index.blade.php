@extends('layouts.app')

@section('title', 'Kartu Obat Pasien')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .dataTables_wrapper .top {
            font-family: "Poppins", sans-serif;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
            flex-wrap: wrap;
            gap: 8px;
        }

        .dataTables_filter label {
            font-weight: 600 !important;
            font-size: 12px !important;
        }

        .dataTables_filter input {
            width: 240px !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            outline: none !important;
            font-size: 12px !important;
        }

        .dataTables_length select {
            padding: 4px 23px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            font-size: 12px !important;
        }

        table.card-table thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: .03em;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 12px 10px !important;
            white-space: nowrap;
        }

        table.card-table tbody td {
            padding: 10px !important;
            font-size: 12.5px !important;
            vertical-align: middle !important;
        }

        table.card-table tbody tr:hover {
            background-color: #f8fafc !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 5px 11px !important;
            border-radius: 6px !important;
            background: #f3f3f3;
            margin: 0 3px;
            font-size: 12px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb !important;
            color: #fff !important;
            border: 1px solid #2563eb !important;
        }

        .dropdown-table {
            position: absolute;
            z-index: 9999;
            top: 44px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
            border: 1px solid #e2e8f0;
            display: none;
            overflow: hidden;
            width: 100%;
        }

        .dropdown-table tbody tr.active {
            background-color: #dbeafe;
        }

        .dropdown-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 12.5px;
        }

        .dropdown-table thead th {
            position: sticky;
            top: 0;
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 8px 10px;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
            font-size: 10.5px;
            letter-spacing: .04em;
            z-index: 20;
        }

        .dropdown-table tbody tr {
            transition: background-color .15s ease;
            cursor: pointer;
        }

        .dropdown-table tbody tr:hover {
            background-color: #f1f5f9;
        }

        .dropdown-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }

        .dropdown-table tbody tr:last-child td {
            border-bottom: none;
        }

        .view-tab-btn.active {
            background-color: #1678df;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(22, 120, 223, 0.28);
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="card shadow-md rounded-2xl p-6 bg-white">
                {{-- Header (mirip Kartu Stock) --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                            <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                            <path d="M9 14l2 2l4 -4" />
                        </svg>
                        <h2 class="text-lg font-semibold text-gray-800">Kartu Obat Pasien</h2>
                        @if ($activePharmacy)
                            <span
                                class="px-2.5 py-0.5 text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 rounded-full">
                                {{ $activePharmacy->name }}
                            </span>
                        @endif
                    </div>

                    <div class="text-xs text-slate-500">
                        Menampilkan riwayat pembelian obat pasien, frekuensi beli, qty, dan retur penjualan
                    </div>
                </div>

                {{-- Filter Bar (mirip Kartu Stock) --}}
                <div class="flex flex-wrap items-end gap-3 py-2">
                    {{-- Rentang Tanggal (Default Hari Ini) --}}
                    <div class="w-full sm:w-56">
                        <div class="py-1 text-[13px] font-bold text-gray-700">Tanggal</div>
                        <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                            autocomplete="off">
                    </div>

                    {{-- Cari Pasien (Autocomplete + Enter) --}}
                    <div class="flex-1 min-w-[230px]">
                        <div class="py-1 text-[13px] font-bold text-gray-700">Cari Pasien (Opsional)</div>
                        <div class="relative">
                            <input type="text" id="patientSearchInput" autofocus
                                class="w-full rounded-lg border border-gray-300 bg-white pl-3.5 pr-8 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Ketik nama, kode, atau no. telp pasien..." autocomplete="off">
                            <button type="button" id="clearPatientBtn" title="Hapus pilihan pasien"
                                class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-sm font-bold">
                                &times;
                            </button>

                            <div id="patientDropdown"
                                class="dropdown-table left-0 mt-1 w-full min-w-full sm:min-w-[420px]">
                                <div id="patientTableScroll" style="max-height: 260px; overflow-y: auto;">
                                    <table class="mb-0 w-full">
                                        <thead>
                                            <tr>
                                                <th style="width: 38px; text-align: center;">#</th>
                                                <th style="width: 90px;">Kode</th>
                                                <th>Nama Pasien</th>
                                                <th style="width: 120px;">No. Telp</th>
                                            </tr>
                                        </thead>
                                        <tbody id="patientResults"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Cari Obat (Autocomplete + Enter) --}}
                    <div class="flex-1 min-w-[230px]">
                        <div class="py-1 text-[13px] font-bold text-gray-700">Cari Obat (Opsional)</div>
                        <div class="relative">
                            <input type="text" id="medicineSearchInput"
                                class="w-full rounded-lg border border-gray-300 bg-white pl-3.5 pr-8 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Ketik nama atau kode obat..." autocomplete="off">
                            <button type="button" id="clearMedicineBtn" title="Hapus pilihan obat"
                                class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 text-sm font-bold">
                                &times;
                            </button>

                            <div id="medicineDropdown"
                                class="dropdown-table left-0 mt-1 w-full min-w-full sm:min-w-[420px]">
                                <div id="medicineTableScroll" style="max-height: 260px; overflow-y: auto;">
                                    <table class="mb-0 w-full">
                                        <thead>
                                            <tr>
                                                <th style="width: 38px; text-align: center;">#</th>
                                                <th style="width: 100px;">Kode</th>
                                                <th>Nama Obat</th>
                                                <th style="width: 80px; text-align: center;">Satuan</th>
                                            </tr>
                                        </thead>
                                        <tbody id="medicineResults"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Pilihan Cabang Khusus HO / GM / Admin / Koordinator --}}
                    @if ($canSwitchBranch)
                        <div class="w-full sm:w-52">
                            <div class="py-1 text-[13px] font-bold text-gray-700">Cabang</div>
                            <select id="pharmacyFilter"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200">
                                <option value="all">Semua Cabang</option>
                                @foreach ($pharmacies as $ph)
                                    <option value="{{ $ph->id }}"
                                        {{ (int) $activePharmacyId === (int) $ph->id ? 'selected' : '' }}>
                                        {{ $ph->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Action Buttons --}}
                    <div class="flex items-center gap-2">
                        <button type="button" id="searchButton"
                            class="flex items-center justify-center gap-1.5 h-[38px] px-4 rounded-lg bg-[#1678df] text-white text-[12px] font-bold hover:bg-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
                                <path d="M21 21l-6 -6"></path>
                            </svg>
                            <span>Cari</span>
                        </button>

                        <button type="button" id="resetButton"
                            class="flex items-center justify-center gap-1 h-[38px] px-3.5 rounded-lg bg-slate-100 text-slate-700 text-[12px] font-semibold hover:bg-slate-200 transition-colors border border-slate-200">
                            <span>Reset</span>
                        </button>

                        <button type="button" id="exportExcelButton"
                            class="flex items-center justify-center gap-1.5 h-[38px] px-4 rounded-lg bg-emerald-600 text-white text-[12px] font-bold hover:bg-emerald-700 transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-300">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                                <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                                <path d="M8 11h8v7h-8z" />
                                <path d="M8 15h8" />
                                <path d="M11 11v7" />
                            </svg>
                            <span>Export Excel</span>
                        </button>
                    </div>
                </div>

                {{-- Statcards (mirip Kartu Stock) --}}
                <div class="mx-1 mt-5 mb-4">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider truncate">Pasien Unik</span>
                                <span id="stat-patients" class="block text-lg font-bold text-gray-800 leading-tight">0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2" />
                                    <rect x="9" y="3" width="6" height="4" rx="1" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider truncate">Total Transaksi</span>
                                <span id="stat-transactions" class="block text-lg font-bold text-gray-800 leading-tight">0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M4 12v-3a3 3 0 0 1 3-3h13m-3-3l3 3l-3 3" />
                                    <path d="M20 12v3a3 3 0 0 1-3 3h-13m3 3l-3-3l3-3" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider truncate">Frekuensi Beli</span>
                                <span id="stat-freq" class="block text-lg font-bold text-gray-800 leading-tight">0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/70 px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                    <path d="M17 17h-11v-14h-2" />
                                    <path d="M6 5l14 1l-1 7h-13" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-emerald-700 uppercase tracking-wider truncate">Qty Dibeli</span>
                                <span id="stat-qty-beli" class="block text-lg font-bold text-gray-800 leading-tight">0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl border border-rose-100 bg-rose-50/70 px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M9 14l-4 -4l4 -4" />
                                    <path d="M5 10h11a4 4 0 1 1 0 8h-1" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-rose-600 uppercase tracking-wider truncate">Qty Diretur</span>
                                <span id="stat-qty-retur" class="block text-lg font-bold text-gray-800 leading-tight">0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl border border-purple-100 bg-purple-50/60 px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                                    <path d="M12 12l8 -4.5" />
                                    <path d="M12 12l0 9" />
                                    <path d="M12 12l-8 -4.5" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-purple-700 uppercase tracking-wider truncate">Qty Bersih</span>
                                <span id="stat-qty-net" class="block text-lg font-bold text-gray-800 leading-tight">0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M6 5h12a2 2 0 0 1 2 2v10a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2v-10a2 2 0 0 1 2 -2z" />
                                    <path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider truncate">Nilai Beli</span>
                                <span id="stat-nominal-beli" class="block text-base font-bold text-gray-800 leading-tight">Rp 0</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3.5 shadow-sm">
                            <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-pink-50 text-pink-600">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none">
                                    <path d="M5 21v-16a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v16l-3 -2l-2 2l-2 -2l-2 2l-2 -2l-3 2" />
                                    <path d="M15 14v-2a2 2 0 0 0 -2 -2h-4l2 -2m0 4l-2 -2" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-[11px] font-semibold text-gray-400 uppercase tracking-wider truncate">Nilai Retur</span>
                                <span id="stat-nominal-retur" class="block text-base font-bold text-rose-600 leading-tight">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- View Tabs (2 Tampilan: Rekap Frekuensi & Riwayat Kronologis mirip Kartu Stock) --}}
                <div class="flex flex-wrap items-center justify-between gap-3 px-2 py-2 border-b border-gray-100 mb-3">
                    <div class="inline-flex items-center bg-slate-100 p-1 rounded-xl gap-1">
                        <button type="button" id="tabSummaryBtn"
                            class="view-tab-btn active px-4 py-2 rounded-lg text-xs font-bold transition">
                            Rekap Frekuensi &amp; Qty per Obat (<span id="count-summary">0</span>)
                        </button>
                        <button type="button" id="tabTimelineBtn"
                            class="view-tab-btn px-4 py-2 rounded-lg text-xs font-bold text-slate-600 hover:text-slate-900 transition">
                            Riwayat Kronologis (Beli &amp; Retur) (<span id="count-timeline">0</span>)
                        </button>
                    </div>

                    <div id="timelineFocusBanner" class="hidden items-center gap-2 text-xs bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg border border-blue-200">
                        <span id="timelineFocusText" class="font-semibold"></span>
                        <button type="button" id="clearFocusBtn" class="underline font-bold hover:text-blue-900">Tampilkan Semua</button>
                    </div>
                </div>

                {{-- Loading Indicator --}}
                <div id="loadingOverlay" class="hidden py-8 text-center text-sm text-slate-500 font-medium">
                    Memuat data kartu obat pasien...
                </div>

                {{-- Tampilan 1: Rekap Frekuensi & Qty per Obat --}}
                <div id="summaryPanel" class="overflow-x-auto p-2">
                    <table id="summaryTable" class="card-table min-w-full text-left text-gray-600">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Pasien</th>
                                <th>Obat</th>
                                <th class="text-center">Frekuensi Beli</th>
                                <th class="text-right">Qty Beli</th>
                                <th class="text-right">Qty Retur</th>
                                <th class="text-right">Qty Bersih</th>
                                <th class="text-right">Nilai Beli</th>
                                <th class="text-right">Nilai Retur</th>
                                <th>Kapan Dibeli (Pertama / Terakhir)</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>

                {{-- Tampilan 2: Riwayat Kronologis mirip Kartu Stock --}}
                <div id="timelinePanel" class="hidden overflow-x-auto p-2">
                    <table id="timelineTable" class="card-table min-w-full text-left text-gray-600">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal &amp; Jam</th>
                                <th>Kode Transaksi / Retur</th>
                                <th>Pasien</th>
                                <th>Obat</th>
                                <th class="text-center">Jenis</th>
                                <th class="text-center">Frekuensi</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Akumulasi Qty</th>
                                <th class="text-right">Subtotal</th>
                                <th>Kasir / Cabang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        const DEFAULT_TODAY = "{{ $today }}";
        const CAN_SWITCH_BRANCH = @json($canSwitchBranch);
        const DEFAULT_PHARMACY_ID = @json($activePharmacyId);

        let startDate = DEFAULT_TODAY;
        let endDate = DEFAULT_TODAY;
        let selectedPatientId = '';
        let selectedMedicineId = '';
        let activeTab = 'summary';
        let focusPair = null; // { patient_id, medicine_id, label }

        let allSummaryRows = [];
        let allTimelineRows = [];
        let summaryDataTable = null;
        let timelineDataTable = null;
        let datePickerInstance = null;

        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatNumber(val) {
            const num = Number(val || 0);
            return new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 2
            }).format(num);
        }

        function formatRupiah(val) {
            const num = Number(val || 0);
            const sign = num < 0 ? '-' : '';
            return sign + 'Rp ' + new Intl.NumberFormat('id-ID', {
                maximumFractionDigits: 0
            }).format(Math.abs(num));
        }

        /**
         * Generic autocomplete controller matching Kartu Stock UX (debounce + infinite scroll + arrow keys + Enter).
         */
        function createAutocomplete(config) {
            const input = document.getElementById(config.inputId);
            const dropdown = document.getElementById(config.dropdownId);
            const tbody = document.getElementById(config.tbodyId);
            const scrollBox = document.getElementById(config.scrollId);
            const clearBtn = document.getElementById(config.clearBtnId);

            let debounceTimer = null;
            let abortController = null;
            let requestSeq = 0;
            let page = 1;
            let hasMore = true;
            let keyword = '';
            let activeIndex = -1;

            function toggleClearButton() {
                if (!clearBtn) return;
                if (input.value.trim() !== '') {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            }

            function hideDropdown() {
                dropdown.style.display = 'none';
            }

            function fetchPage() {
                if (!hasMore) return;
                if (abortController) abortController.abort();
                abortController = new AbortController();

                const seq = ++requestSeq;
                const currentKeyword = keyword;
                const currentPage = page;

                fetch(`${config.url}?search=${encodeURIComponent(currentKeyword)}&page=${currentPage}`, {
                        signal: abortController.signal
                    })
                    .then(res => res.json())
                    .then(res => {
                        if (seq !== requestSeq) return;
                        const items = res.data || [];

                        if (currentPage === 1 && items.length === 0) {
                            tbody.innerHTML =
                                `<tr><td colspan="4" class="text-center py-4 text-slate-400 italic">${escapeHtml(config.emptyText)}</td></tr>`;
                            hasMore = false;
                            dropdown.style.display = 'block';
                            return;
                        }

                        items.forEach((item, idx) => {
                            const tr = document.createElement('tr');
                            tr._itemData = item;
                            const num = ((currentPage - 1) * (res.per_page || 10)) + idx + 1;
                            tr.innerHTML = config.renderRow(item, num);
                            tbody.appendChild(tr);
                        });

                        hasMore = (res.current_page || 1) < (res.last_page || 1);
                        page = currentPage + 1;
                        dropdown.style.display = 'block';
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') {
                            console.error('Autocomplete error:', err);
                        }
                    });
            }

            function selectRow(row) {
                const item = row && row._itemData;
                if (!item) return;

                input.value = config.getDisplayValue(item);
                config.onSelect(item);
                toggleClearButton();
                hideDropdown();
                loadCardData();
            }

            function updateActiveRow(rows) {
                rows.forEach(r => r.classList.remove('active'));
                if (activeIndex >= 0 && rows[activeIndex]) {
                    rows[activeIndex].classList.add('active');
                    rows[activeIndex].scrollIntoView({
                        block: 'nearest'
                    });
                }
            }

            input.addEventListener('input', function() {
                config.onClearSelection();
                toggleClearButton();
                clearTimeout(debounceTimer);

                const trimmed = this.value.trim();
                if (trimmed.length < 1) {
                    hideDropdown();
                    tbody.innerHTML = '';
                    if (abortController) abortController.abort();
                    return;
                }

                debounceTimer = setTimeout(() => {
                    keyword = trimmed;
                    page = 1;
                    hasMore = true;
                    activeIndex = -1;
                    tbody.innerHTML = '';
                    fetchPage();
                }, 250);
            });

            input.addEventListener('keydown', function(e) {
                const isVisible = dropdown.style.display === 'block';
                const rows = tbody.querySelectorAll('tr');

                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (isVisible && activeIndex >= 0 && rows[activeIndex] && rows[activeIndex]._itemData) {
                        selectRow(rows[activeIndex]);
                    } else {
                        hideDropdown();
                        loadCardData();
                    }
                    return;
                }

                if (!isVisible || !rows.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, rows.length - 1);
                    updateActiveRow(rows);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    updateActiveRow(rows);
                } else if (e.key === 'Escape') {
                    hideDropdown();
                }
            });

            scrollBox.addEventListener('scroll', function() {
                if (scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 5) {
                    fetchPage();
                }
            });

            tbody.addEventListener('mouseover', function(e) {
                const row = e.target.closest('tr');
                if (!row || !row._itemData) return;
                const rows = [...tbody.children];
                rows.forEach(r => r.classList.remove('active'));
                row.classList.add('active');
                activeIndex = rows.indexOf(row);
            });

            tbody.addEventListener('click', function(e) {
                const row = e.target.closest('tr');
                if (row && row._itemData) {
                    selectRow(row);
                    e.stopPropagation();
                }
            });

            if (clearBtn) {
                clearBtn.addEventListener('click', function() {
                    input.value = '';
                    config.onClearSelection();
                    toggleClearButton();
                    hideDropdown();
                    loadCardData();
                });
            }

            document.addEventListener('click', function(e) {
                if (dropdown.style.display === 'block' && !input.contains(e.target) && !dropdown.contains(e.target)) {
                    hideDropdown();
                }
            });

            return {
                syncClearBtn: toggleClearButton,
                hide: hideDropdown,
            };
        }

        function currentFilterParams() {
            const patientVal = selectedPatientId || document.getElementById('patientSearchInput').value.trim();
            const medicineVal = selectedMedicineId || document.getElementById('medicineSearchInput').value.trim();
            const pharmacyEl = document.getElementById('pharmacyFilter');

            return {
                start_date: startDate || DEFAULT_TODAY,
                end_date: endDate || DEFAULT_TODAY,
                patient_filter: patientVal,
                medicine_filter: medicineVal,
                pharmacy_id: pharmacyEl ? pharmacyEl.value : DEFAULT_PHARMACY_ID,
            };
        }

        function updateStats(stats) {
            document.getElementById('stat-patients').textContent = formatNumber(stats.total_patients || 0);
            document.getElementById('stat-transactions').textContent = formatNumber(stats.total_transactions || 0);
            document.getElementById('stat-freq').textContent = formatNumber(stats.total_freq || 0) + 'x';
            document.getElementById('stat-qty-beli').textContent = formatNumber(stats.total_qty_beli || 0);
            document.getElementById('stat-qty-retur').textContent = formatNumber(stats.total_qty_retur || 0);
            document.getElementById('stat-qty-net').textContent = formatNumber(stats.total_qty_net || 0);
            document.getElementById('stat-nominal-beli').textContent = formatRupiah(stats.total_nominal_beli || 0);
            document.getElementById('stat-nominal-retur').textContent = formatRupiah(stats.total_nominal_retur || 0);
        }

        function renderSummaryTable(rows) {
            document.getElementById('count-summary').textContent = rows.length;

            if (summaryDataTable) {
                summaryDataTable.destroy();
            }

            const tbody = document.querySelector('#summaryTable tbody');
            tbody.innerHTML = rows.map((r, idx) => {
                const returQtyBadge = Number(r.qty_retur) > 0
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700">-${formatNumber(r.qty_retur)}</span>`
                    : `<span class="text-slate-400">0</span>`;

                const returNote = r.last_retur_at
                    ? `<div class="text-[11px] text-rose-600 mt-0.5">Retur terakhir: ${escapeHtml(r.last_retur_at)}</div>`
                    : '';

                const buyDates = r.first_buy_at && r.first_buy_at !== r.last_buy_at
                    ? `<div>Pertama: <span class="font-medium text-slate-700">${escapeHtml(r.first_buy_at)}</span></div>
                       <div>Terakhir: <span class="font-semibold text-blue-700">${escapeHtml(r.last_buy_at)}</span></div>`
                    : `<div class="font-semibold text-blue-700">${escapeHtml(r.last_buy_at || '-')}</div>`;

                return `
                    <tr>
                        <td class="text-center text-slate-400">${idx + 1}</td>
                        <td>
                            <div class="font-semibold text-slate-800">${escapeHtml(r.patient_name)}</div>
                            <div class="text-[11px] text-slate-400">${escapeHtml(r.patient_code)} &bull; ${escapeHtml(r.patient_phone)}</div>
                        </td>
                        <td>
                            <div class="font-semibold text-slate-800">${escapeHtml(r.medicine_name)}</div>
                            <div class="text-[11px] text-slate-400">${escapeHtml(r.medicine_code)} &bull; ${escapeHtml(r.medicine_unit)}</div>
                        </td>
                        <td class="text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                ${formatNumber(r.frekuensi_beli)}x
                            </span>
                            ${Number(r.frekuensi_retur) > 0 ? `<div class="text-[10px] text-rose-600 font-semibold mt-0.5">${formatNumber(r.frekuensi_retur)}x retur</div>` : ''}
                        </td>
                        <td class="text-right font-bold text-emerald-700">${formatNumber(r.qty_beli)}</td>
                        <td class="text-right">${returQtyBadge}</td>
                        <td class="text-right font-bold text-slate-900">${formatNumber(r.qty_net)}</td>
                        <td class="text-right font-medium text-slate-700">${formatRupiah(r.nominal_beli)}</td>
                        <td class="text-right font-medium ${Number(r.nominal_retur) > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'}">${formatRupiah(r.nominal_retur)}</td>
                        <td class="text-xs">${buyDates}${returNote}</td>
                        <td class="text-center">
                            <button type="button"
                                onclick="focusTimeline(${r.patient_id}, ${r.medicine_id})"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-blue-600 hover:text-white text-slate-700 text-xs font-semibold transition">
                                Riwayat
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            summaryDataTable = $('#summaryTable').DataTable({
                pageLength: 25,
                order: [],
                language: {
                    emptyTable: 'Tidak ada data pembelian obat pasien pada periode ini',
                    search: 'Cari di tabel:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: '0 data',
                    paginate: {
                        previous: 'Sebelumnya',
                        next: 'Berikutnya'
                    }
                }
            });
        }

        function renderTimelineTable(rows) {
            const visibleRows = focusPair
                ? rows.filter(r => r.patient_id === focusPair.patient_id && r.medicine_id === focusPair.medicine_id)
                : rows;

            document.getElementById('count-timeline').textContent = visibleRows.length;

            const banner = document.getElementById('timelineFocusBanner');
            if (focusPair) {
                banner.classList.remove('hidden');
                banner.classList.add('flex');
                document.getElementById('timelineFocusText').textContent = `Filter: ${focusPair.label}`;
            } else {
                banner.classList.add('hidden');
                banner.classList.remove('flex');
            }

            if (timelineDataTable) {
                timelineDataTable.destroy();
            }

            const tbody = document.querySelector('#timelineTable tbody');
            tbody.innerHTML = visibleRows.map((r, idx) => {
                const isRetur = r.event_type === 'RETUR';
                const jenisBadge = isRetur
                    ? `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-700">RETUR</span>`
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-700">BELI</span>
                       <div class="text-[10px] text-slate-400 mt-0.5">${escapeHtml(r.transaction_type)}</div>`;

                const freqBadge = isRetur
                    ? `<span class="text-[11px] font-semibold text-rose-600">Retur</span>`
                    : `<span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold bg-blue-50 text-blue-700">Ke-${r.frekuensi_ke}</span>`;

                const qtyCell = isRetur
                    ? `<span class="font-bold text-rose-600">-${formatNumber(r.qty)}</span>`
                    : `<span class="font-bold text-emerald-700">+${formatNumber(r.qty)}</span>`;

                const kodeRef = r.reference_code
                    ? `<div class="font-mono font-bold text-rose-700">${escapeHtml(r.transaction_code)}</div>
                       <div class="text-[10px] text-slate-400">Ref: ${escapeHtml(r.reference_code)}</div>`
                    : `<div class="font-mono font-semibold text-slate-800">${escapeHtml(r.transaction_code)}</div>`;

                return `
                    <tr>
                        <td class="text-center text-slate-400">${idx + 1}</td>
                        <td>
                            <div class="font-semibold text-slate-800">${escapeHtml(r.date)}</div>
                            <div class="text-[11px] text-slate-400">${escapeHtml(r.time)}</div>
                        </td>
                        <td>${kodeRef}</td>
                        <td>
                            <div class="font-semibold text-slate-800">${escapeHtml(r.patient_name)}</div>
                            <div class="text-[11px] text-slate-400">${escapeHtml(r.patient_code)} &bull; ${escapeHtml(r.patient_phone)}</div>
                        </td>
                        <td>
                            <div class="font-semibold text-slate-800">${escapeHtml(r.medicine_name)}</div>
                            <div class="text-[11px] text-slate-400">${escapeHtml(r.medicine_code)} &bull; ${escapeHtml(r.medicine_unit)}</div>
                        </td>
                        <td class="text-center">${jenisBadge}</td>
                        <td class="text-center">${freqBadge}</td>
                        <td class="text-right">${qtyCell}</td>
                        <td class="text-right font-bold text-slate-800">${formatNumber(r.akumulasi_qty)}</td>
                        <td class="text-right font-semibold ${isRetur ? 'text-rose-600' : 'text-slate-800'}">${formatRupiah(r.signed_subtotal)}</td>
                        <td>
                            <div class="font-medium text-slate-700">${escapeHtml(r.operator)}</div>
                            <div class="text-[11px] text-slate-400">${escapeHtml(r.pharmacy_name)}</div>
                        </td>
                    </tr>
                `;
            }).join('');

            timelineDataTable = $('#timelineTable').DataTable({
                pageLength: 25,
                order: [],
                language: {
                    emptyTable: 'Tidak ada riwayat pembelian atau retur pada periode ini',
                    search: 'Cari di tabel:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: '0 data',
                    paginate: {
                        previous: 'Sebelumnya',
                        next: 'Berikutnya'
                    }
                }
            });
        }

        function switchTab(tab) {
            activeTab = tab;
            const summaryBtn = document.getElementById('tabSummaryBtn');
            const timelineBtn = document.getElementById('tabTimelineBtn');
            const summaryPanel = document.getElementById('summaryPanel');
            const timelinePanel = document.getElementById('timelinePanel');

            if (tab === 'summary') {
                summaryBtn.classList.add('active');
                summaryBtn.classList.remove('text-slate-600');
                timelineBtn.classList.remove('active');
                timelineBtn.classList.add('text-slate-600');
                summaryPanel.classList.remove('hidden');
                timelinePanel.classList.add('hidden');
                if (summaryDataTable) summaryDataTable.columns.adjust();
            } else {
                timelineBtn.classList.add('active');
                timelineBtn.classList.remove('text-slate-600');
                summaryBtn.classList.remove('active');
                summaryBtn.classList.add('text-slate-600');
                timelinePanel.classList.remove('hidden');
                summaryPanel.classList.add('hidden');
                if (timelineDataTable) timelineDataTable.columns.adjust();
            }
        }

        function focusTimeline(patientId, medicineId) {
            const match = allSummaryRows.find(r => r.patient_id === patientId && r.medicine_id === medicineId);
            focusPair = {
                patient_id: patientId,
                medicine_id: medicineId,
                label: match ? `${match.patient_name} — ${match.medicine_name}` : 'Pasien & Obat Terpilih',
            };
            renderTimelineTable(allTimelineRows);
            switchTab('timeline');
        }

        function loadCardData() {
            const params = currentFilterParams();
            const qs = new URLSearchParams(params).toString();

            document.getElementById('loadingOverlay').classList.remove('hidden');

            fetch(`{{ route('master.patient-medicine-cards.data') }}?${qs}`)
                .then(res => res.json())
                .then(res => {
                    if (res.status !== 'success') {
                        throw new Error(res.message || 'Gagal memuat data');
                    }
                    focusPair = null;
                    allSummaryRows = res.summary || [];
                    allTimelineRows = res.timeline || [];

                    updateStats(res.stats || {});
                    renderSummaryTable(allSummaryRows);
                    renderTimelineTable(allTimelineRows);
                })
                .catch(err => {
                    console.error(err);
                    iziToast.error({
                        title: 'Gagal',
                        message: 'Tidak dapat memuat data kartu obat pasien.',
                        position: 'topRight'
                    });
                })
                .finally(() => {
                    document.getElementById('loadingOverlay').classList.add('hidden');
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            datePickerInstance = flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                defaultDate: [DEFAULT_TODAY, DEFAULT_TODAY],
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        endDate = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    } else if (selectedDates.length === 1) {
                        startDate = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        endDate = startDate;
                    } else {
                        startDate = DEFAULT_TODAY;
                        endDate = DEFAULT_TODAY;
                    }
                    loadCardData();
                }
            });

            const patientAC = createAutocomplete({
                inputId: 'patientSearchInput',
                dropdownId: 'patientDropdown',
                tbodyId: 'patientResults',
                scrollId: 'patientTableScroll',
                clearBtnId: 'clearPatientBtn',
                url: "{{ route('master.patient-medicine-cards.search-patients') }}",
                emptyText: 'Pasien tidak ditemukan',
                getDisplayValue: (item) => item.name || '',
                onSelect: (item) => {
                    selectedPatientId = String(item.id);
                },
                onClearSelection: () => {
                    selectedPatientId = '';
                },
                renderRow: (item, num) => `
                    <td style="width: 38px; text-align: center;" class="text-slate-400 text-xs">${num}</td>
                    <td style="width: 90px;" class="font-mono text-slate-600 text-xs">${escapeHtml(item.code || '-')}</td>
                    <td class="font-semibold text-slate-800">${escapeHtml(item.name || '-')}</td>
                    <td style="width: 120px;" class="text-slate-500 text-xs">${escapeHtml(item.phone || '-')}</td>
                `,
            });

            const medicineAC = createAutocomplete({
                inputId: 'medicineSearchInput',
                dropdownId: 'medicineDropdown',
                tbodyId: 'medicineResults',
                scrollId: 'medicineTableScroll',
                clearBtnId: 'clearMedicineBtn',
                url: "{{ route('master.patient-medicine-cards.search-medicines') }}",
                emptyText: 'Obat tidak ditemukan',
                getDisplayValue: (item) => item.name || '',
                onSelect: (item) => {
                    selectedMedicineId = String(item.id);
                },
                onClearSelection: () => {
                    selectedMedicineId = '';
                },
                renderRow: (item, num) => `
                    <td style="width: 38px; text-align: center;" class="text-slate-400 text-xs">${num}</td>
                    <td style="width: 100px;" class="font-mono text-slate-600 text-xs">${escapeHtml(item.code || '-')}</td>
                    <td class="font-semibold text-slate-800">${escapeHtml(item.name || '-')}</td>
                    <td style="width: 80px; text-align: center;" class="text-slate-500 text-xs">${escapeHtml(item.unit || '-')}</td>
                `,
            });

            document.getElementById('searchButton').addEventListener('click', function() {
                patientAC.hide();
                medicineAC.hide();
                loadCardData();
            });

            document.getElementById('resetButton').addEventListener('click', function() {
                startDate = DEFAULT_TODAY;
                endDate = DEFAULT_TODAY;
                if (datePickerInstance) {
                    datePickerInstance.setDate([DEFAULT_TODAY, DEFAULT_TODAY], false);
                }
                selectedPatientId = '';
                selectedMedicineId = '';
                document.getElementById('patientSearchInput').value = '';
                document.getElementById('medicineSearchInput').value = '';
                patientAC.syncClearBtn();
                medicineAC.syncClearBtn();

                const pharmacyEl = document.getElementById('pharmacyFilter');
                if (pharmacyEl) {
                    pharmacyEl.value = String(DEFAULT_PHARMACY_ID || 'all');
                }
                focusPair = null;
                switchTab('summary');
                loadCardData();
            });

            document.getElementById('exportExcelButton').addEventListener('click', function() {
                const params = currentFilterParams();
                const qs = new URLSearchParams(params).toString();
                window.location.href = `{{ route('master.patient-medicine-cards.export') }}?${qs}`;
            });

            const pharmacyFilter = document.getElementById('pharmacyFilter');
            if (pharmacyFilter) {
                pharmacyFilter.addEventListener('change', loadCardData);
            }

            document.getElementById('tabSummaryBtn').addEventListener('click', () => switchTab('summary'));
            document.getElementById('tabTimelineBtn').addEventListener('click', () => switchTab('timeline'));
            document.getElementById('clearFocusBtn').addEventListener('click', () => {
                focusPair = null;
                renderTimelineTable(allTimelineRows);
            });

            // Auto-load transaksi hari ini saat halaman dibuka
            loadCardData();
        });
    </script>
@endsection
