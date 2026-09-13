@extends('layouts.app')

@section('title', 'Master Transaksi Pasien')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* Select2 Custom Styles to match Tailwind aesthetics */
        .select2-container .select2-selection--single {
            height: 42px !important;
            border-radius: 12px !important;
            border: 1px solid #d1d5db !important;
            display: flex !important;
            align-items: center !important;
            padding: 0 12px !important;
            background-color: #fff !important;
            transition: all 0.2s ease;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
            padding-left: 0 !important;
            color: #1f2937 !important;
            font-size: 13px !important;
            font-weight: 500 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
        }

        .select2-container--open .select2-dropdown {
            border-radius: 12px !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            overflow: hidden !important;
            z-index: 9999 !important;
        }

        .select2-results__option {
            padding: 9px 14px !important;
            font-size: 13px !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3b82f6 !important;
            color: #fff !important;
        }

        /* Custom Badges */
        .badge-type {
            display: inline-flex;
            align-items: center;
            padding: 2px 9px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .badge-upds {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .badge-hv {
            background-color: #fef3c7;
            color: #b45309;
        }

        .badge-resep {
            background-color: #dcfce7;
            color: #15803d;
        }

        .badge-kredit {
            background-color: #fae8ff;
            color: #86198f;
        }

        .badge-retur {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .badge-other {
            background-color: #f3f4f6;
            color: #4b5563;
        }

        .badge-pay-cash {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .badge-pay-qris {
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .badge-pay-debit {
            background-color: #f5f3ff;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
        }

        .badge-pay-tf {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Preview table aesthetics */
        #previewTable {
            width: 100%;
            min-width: 1100px;
        }

        #previewTable th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 14px;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        #previewTable td {
            padding: 11px 14px;
            font-size: 13px;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
        }

        #previewTable tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Segmented Mode Button */
        .mode-btn.active {
            background-color: #2563eb !important;
            color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body space-y-5">

            {{-- ─── Header Card ─── --}}
            <div
                class="bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-600 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 opacity-10 pointer-events-none">
                    <svg class="w-64 h-64 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M19 10V4a1 1 0 0 0-1-1H9.914a1 1 0 0 0-.707.293L5.293 7.207A1 1 0 0 0 5 7.914V20a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2M10 3v4a1 1 0 0 1-1 1H5m5 6h9m0 0-2-2m2 2-2 2" />
                    </svg>
                </div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div
                            class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-xs font-semibold backdrop-blur-sm mb-2">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Master Data Pasien & Penjualan
                        </div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Master Transaksi / Penjualan Pasien
                        </h1>
                        <p class="text-blue-100 text-sm mt-1 max-w-2xl">
                            Tarik data transaksi penjualan per pasien dengan pilihan mode Detail (rincian obat) atau Rekap
                            (total per nota). Didukung antrean background export ke Excel.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('patients.index') }}"
                            class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-semibold backdrop-blur-sm transition border border-white/20 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Data Pasien
                        </a>
                    </div>
                </div>
            </div>

            {{-- ─── Filter Bar ─── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    {{-- 1. Pilih Pasien --}}
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Pilih Pasien / Semua
                        </label>
                        <select id="patientSelect" class="w-full">
                            <option value="all" selected> SEMUA PASIEN (SEMUA TRANSAKSI)</option>
                        </select>
                        <span class="text-[11px] text-gray-400 mt-1 block">Ketik nama, kode, atau nomor telepon</span>
                    </div>

                    {{-- 2. Rentang Tanggal --}}
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Rentang Tanggal
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" id="startDate" value="{{ $today }}" placeholder="Tgl Mulai"
                                class="flatpickr-input w-full rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-700 bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none cursor-pointer">
                            <input type="text" id="endDate" value="{{ $today }}" placeholder="Tgl Selesai"
                                class="flatpickr-input w-full rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-700 bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none cursor-pointer">
                        </div>
                        <span class="text-[11px] text-gray-400 mt-1 block">Filter tanggal transaksi</span>
                    </div>

                    {{-- 3. Pilihan Detail / Rekap --}}
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            Format Data
                        </label>
                        <div class="bg-gray-100 p-1 rounded-xl flex gap-1">
                            <button type="button" onclick="setMode('detail')" id="btnModeDetail"
                                class="mode-btn active flex-1 py-2 text-xs font-bold rounded-lg text-gray-600 transition flex items-center justify-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                                Detail Obat
                            </button>
                            <button type="button" onclick="setMode('rekap')" id="btnModeRekap"
                                class="mode-btn flex-1 py-2 text-xs font-bold rounded-lg text-gray-600 transition flex items-center justify-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Rekap Total
                            </button>
                        </div>
                        <span class="text-[11px] text-gray-400 mt-1 block" id="modeHelperText">Rincikan nama obat, harga,
                            dan kuantitas</span>
                    </div>

                    {{-- 4. Apotek --}}
                    <div>
                        <label
                            class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Apotek
                        </label>
                        <select id="pharmacySelect"
                            class="w-full rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-700 bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="all">Semua Apotek</option>
                            @foreach ($pharmacies as $key => $ph)
                                <option value="{{ $ph->id }}" {{ $ph->id == 1 ? 'selected' : '' }}>
                                    {{ $ph->name }}
                                </option>
                            @endforeach
                        </select>
                        <span class="text-[11px] text-gray-400 mt-1 block">Pilih cabang atau semua apotek</span>
                    </div>

                </div>

                {{-- Action Buttons --}}
                <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="loadPreview(1)" id="btnPreview"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            Tampilkan Data
                        </button>
                        <button type="button" onclick="resetFilter()"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl border border-gray-300 hover:bg-gray-50 text-gray-600 text-xs font-semibold transition cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset Filter
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" onclick="startExportExcel()" id="btnExport"
                            class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span id="btnExportText">Download Excel (Queue)</span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ─── Export Progress Box (Queue) ─── --}}
            <div id="progressContainer"
                class="hidden bg-white rounded-2xl border border-blue-200 shadow-md p-5 transition-all duration-300">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <div id="progressIconBox"
                            class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0">
                            <svg id="progressSpinner" class="w-5 h-5 text-blue-600 animate-spin"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <svg id="progressSuccessIcon" class="hidden w-5 h-5 text-emerald-600" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-sm font-bold text-gray-800" id="progressStatus">Memulai antrean ekspor
                                Excel...</span>
                            <p class="text-xs text-gray-500 mt-0.5" id="progressSubStatus">Sistem memproses seluruh data
                                transaksi per pasien di
                                latar belakang...</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs font-extrabold text-blue-700 bg-blue-100 border border-blue-200 px-3 py-1 rounded-full"
                            id="progressText">0%</span>
                        <button type="button" onclick="hideProgressBox()" id="btnCloseProgress"
                            class="hidden p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition"
                            title="Tutup panel">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden mt-3">
                    <div id="progressBar"
                        class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2.5 rounded-full transition-all duration-300"
                        style="width: 0%"></div>
                </div>
                <div class="flex items-center justify-between mt-2.5 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5" id="progressFooterNotice">
                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span id="progressFooterText">File Excel akan otomatis terunduh begitu proses di server
                            selesai.</span>
                    </span>
                    <a id="manualDownloadLink" href="#"
                        class="hidden text-blue-600 font-bold hover:underline">Unduh Manual Disini</a>
                </div>
            </div>

            {{-- ─── KPI Summary Cards ─── --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Transaksi</span>
                        <h4 class="text-xl font-extrabold text-gray-800" id="cardTotalTrx">0</h4>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Total Nominal
                            (Rp)</span>
                        <h4 class="text-xl font-extrabold text-gray-800" id="cardTotalNominal">Rp 0</h4>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-sm flex items-center gap-4">
                    <div
                        class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Jumlah Pasien
                            Terlibat</span>
                        <h4 class="text-xl font-extrabold text-gray-800" id="cardTotalPatients">0</h4>
                    </div>
                </div>
            </div>

            {{-- ─── Table Preview Card ─── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">

                {{-- Notice Anti-Crash Bar --}}
                <div class="mb-4 bg-sky-50 border border-sky-200 rounded-xl p-3.5 flex items-start gap-3">
                    <svg class="w-5 h-5 text-sky-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-xs text-sky-800">
                        <span class="font-bold">Mode Preview Aman (Anti-Crash):</span>
                        Halaman ini menampilkan preview awal data per halaman agar peramban tidak membeku/crash akibat beban
                        ribuan data.
                        Gunakan tombol <span class="font-bold text-emerald-700 underline">Download Excel</span> di atas
                        untuk mengambil seluruh rekaman transaksi secara lengkap dalam antrean server.
                    </div>
                </div>

                {{-- Table Top Header --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-bold text-gray-800">Pratinjau Data Transaksi</span>
                        <span id="currentModeBadge" class="badge-type badge-resep">Mode Detail Obat</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-gray-500" id="paginationInfo">
                        Menampilkan 0 data
                    </div>
                </div>

                {{-- Table Container --}}
                <div class="overflow-x-auto rounded-xl border border-gray-100 relative min-h-[220px]">

                    {{-- Loading Overlay --}}
                    <div id="tableLoading"
                        class="absolute inset-0 bg-white/80 backdrop-blur-sm z-20 flex flex-col items-center justify-center gap-2">
                        <svg class="w-8 h-8 text-blue-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span class="text-xs font-semibold text-gray-600">Memuat data transaksi...</span>
                    </div>

                    <table id="previewTable" class="min-w-full text-left">
                        <thead id="previewThead">
                            {{-- Rendered dynamically in JS --}}
                        </thead>
                        <tbody id="previewTbody" class="divide-y divide-gray-100 text-gray-700">
                            {{-- Rendered dynamically in JS --}}
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Footer --}}
                <div
                    class="mt-4 pt-4 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="text-xs text-gray-500" id="paginationRangeText">
                        Menampilkan 0 dari 0 data
                    </div>
                    <div class="flex items-center gap-1.5" id="paginationControls">
                        {{-- Controls rendered dynamically --}}
                    </div>
                </div>

            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>

    <script>
        let currentMode = 'detail'; // 'detail' or 'rekap'
        let currentPage = 1;
        let exportInterval = null;

        document.addEventListener("DOMContentLoaded", function() {
            // 1. Inisialisasi Flatpickr
            flatpickr(".flatpickr-input", {
                dateFormat: "Y-m-d",
                allowInput: true
            });

            // 2. Inisialisasi Select2 AJAX untuk Pasien
            $('#patientSelect').select2({
                placeholder: 'Cari Pasien (Nama / Kode / Telp)...',
                allowClear: false,
                ajax: {
                    url: "{{ route('master.patient-transactions.search-patients') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.results
                        };
                    },
                    cache: true
                },
                minimumInputLength: 0
            });

            // Trigger preview data pertama kali
            loadPreview(1);
        });

        function setMode(mode) {
            currentMode = mode;
            const btnDetail = document.getElementById('btnModeDetail');
            const btnRekap = document.getElementById('btnModeRekap');
            const helper = document.getElementById('modeHelperText');
            const badge = document.getElementById('currentModeBadge');

            if (mode === 'detail') {
                btnDetail.classList.add('active');
                btnRekap.classList.remove('active');
                helper.textContent = "Rincikan nama obat, harga, dan kuantitas";
                badge.className = "badge-type badge-resep";
                badge.textContent = "Mode Detail Obat";
            } else {
                btnRekap.classList.add('active');
                btnDetail.classList.remove('active');
                helper.textContent = "Ringkasan total per nomor transaksi";
                badge.className = "badge-type badge-upds";
                badge.textContent = "Mode Rekap Total";
            }

            loadPreview(1);
        }

        function resetFilter() {
            document.getElementById('startDate').value = "{{ $today }}";
            document.getElementById('endDate').value = "{{ $today }}";
            document.getElementById('pharmacySelect').value = "all";

            // Reset Select2 ke 'all'
            const defaultOption = new Option('SEMUA PASIEN (SEMUA TRANSAKSI)', 'all', true, true);
            $('#patientSelect').empty().append(defaultOption).trigger('change');

            setMode('detail');
        }

        function getFilterData(page = 1) {
            return {
                patient_id: $('#patientSelect').val() || 'all',
                start_date: document.getElementById('startDate').value,
                end_date: document.getElementById('endDate').value,
                pharmacy_id: document.getElementById('pharmacySelect').value,
                mode: currentMode,
                page: page,
                per_page: 50
            };
        }

        function loadPreview(page = 1) {
            currentPage = page;
            const tableLoading = document.getElementById('tableLoading');
            tableLoading.classList.remove('hidden');

            const params = getFilterData(page);

            axios.get("{{ route('master.patient-transactions.preview') }}", {
                    params: params
                })
                .then(function(response) {
                    const data = response.data;
                    renderSummary(data.summary);
                    renderTable(data);
                    renderPagination(data.pagination);
                })
                .catch(function(error) {
                    console.error("Gagal memuat preview data:", error);
                    iziToast.error({
                        title: 'Gagal',
                        message: 'Tidak dapat memuat pratinjau data. Silakan coba kembali.',
                        position: 'topRight'
                    });
                })
                .finally(function() {
                    tableLoading.classList.add('hidden');
                });
        }

        function renderSummary(summary) {
            if (!summary) return;
            document.getElementById('cardTotalTrx').textContent = summary.total_transactions_formatted || '0';
            document.getElementById('cardTotalNominal').textContent = summary.total_nominal_formatted || 'Rp 0';
            document.getElementById('cardTotalPatients').textContent = summary.unique_patients_formatted || '0';
        }

        function renderTable(data) {
            const thead = document.getElementById('previewThead');
            const tbody = document.getElementById('previewTbody');
            const rows = data.rows || [];
            const mode = data.mode || currentMode;

            // Render Thead
            if (mode === 'rekap') {
                thead.innerHTML = `
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th>Nama Pasien</th>
                        <th>No. Telp</th>
                        <th>Kode Transaksi</th>
                        <th class="text-center">Tanggal</th>
                        <th class="text-center">Jam</th>
                        <th>Apotek</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Pembayaran</th>
                        <th class="text-center">Tipe</th>
                    </tr>
                `;
            } else {
                thead.innerHTML = `
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th>Nama Pasien</th>
                        <th>No. Telp</th>
                        <th>Kode Transaksi</th>
                        <th class="text-center">Tanggal</th>
                        <th class="text-center">Jam</th>
                        <th>Apotek</th>
                        <th>Nama Obat</th>
                        <th class="text-right">Harga</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Pembayaran</th>
                        <th class="text-center">Tipe</th>
                    </tr>
                `;
            }

            // Render Tbody
            if (rows.length === 0) {
                const colSpan = mode === 'rekap' ? 10 : 13;
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${colSpan}" class="text-center py-12 text-gray-400">
                            <svg class="w-12 h-12 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="font-medium">Tidak ada transaksi yang cocok dengan filter yang dipilih.</p>
                            <span class="text-xs text-gray-400">Coba ubah tanggal atau pilih apotek lain.</span>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            rows.forEach(function(r) {
                const typeClass = getTypeBadgeClass(r.type);
                const payClass = getPayBadgeClass(r.payment);

                if (mode === 'rekap') {
                    html += `
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="text-center font-medium text-gray-500">${r.no}</td>
                            <td class="font-bold text-gray-800">${escapeHtml(r.patient_name)}</td>
                            <td class="text-gray-600 font-mono text-xs">${escapeHtml(r.patient_phone)}</td>
                            <td class="font-mono text-xs font-semibold text-blue-600">${escapeHtml(r.transaction_code)}</td>
                            <td class="text-center whitespace-nowrap text-gray-600">${escapeHtml(r.date)}</td>
                            <td class="text-center font-mono text-xs text-gray-500">${escapeHtml(r.time)}</td>
                            <td class="text-gray-700 text-xs font-medium">${escapeHtml(r.pharmacy)}</td>
                            <td class="text-right font-extrabold text-gray-900">${r.total_formatted}</td>
                            <td class="text-center">
                                <span class="badge-type ${payClass}">${escapeHtml(r.payment)}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge-type ${typeClass}">${escapeHtml(r.type)}</span>
                            </td>
                        </tr>
                    `;
                } else {
                    html += `
                        <tr class="hover:bg-gray-50/80 transition">
                            <td class="text-center font-medium text-gray-500">${r.no}</td>
                            <td class="font-bold text-gray-800">${escapeHtml(r.patient_name)}</td>
                            <td class="text-gray-600 font-mono text-xs">${escapeHtml(r.patient_phone)}</td>
                            <td class="font-mono text-xs font-semibold text-blue-600">${escapeHtml(r.transaction_code)}</td>
                            <td class="text-center whitespace-nowrap text-gray-600">${escapeHtml(r.date)}</td>
                            <td class="text-center font-mono text-xs text-gray-500">${escapeHtml(r.time)}</td>
                            <td class="text-gray-700 text-xs font-medium">${escapeHtml(r.pharmacy)}</td>
                            <td class="font-semibold text-gray-800 max-w-[220px] truncate" title="${escapeHtml(r.medicine_name)}">${escapeHtml(r.medicine_name)}</td>
                            <td class="text-right text-gray-600 font-mono text-xs">${r.price_formatted}</td>
                            <td class="text-center font-bold text-blue-700">${r.qty}</td>
                            <td class="text-right font-extrabold text-gray-900">${r.total_formatted}</td>
                            <td class="text-center">
                                <span class="badge-type ${payClass}">${escapeHtml(r.payment)}</span>
                            </td>
                            <td class="text-center">
                                <span class="badge-type ${typeClass}">${escapeHtml(r.type)}</span>
                            </td>
                        </tr>
                    `;
                }
            });

            tbody.innerHTML = html;
        }

        function renderPagination(pagination) {
            const paginationInfo = document.getElementById('paginationInfo');
            const rangeText = document.getElementById('paginationRangeText');
            const controls = document.getElementById('paginationControls');

            if (!pagination || pagination.total === 0) {
                paginationInfo.textContent = "Menampilkan 0 data";
                rangeText.textContent = "Menampilkan 0 dari 0 data";
                controls.innerHTML = '';
                return;
            }

            const total = pagination.total;
            const from = pagination.from;
            const to = pagination.to;
            const page = pagination.current_page;
            const last = pagination.last_page;

            paginationInfo.textContent = `Halaman ${page} dari ${last}`;
            rangeText.textContent = `Menampilkan ${from} - ${to} dari total ${total.toLocaleString('id-ID')} data`;

            let html = '';

            // Tombol Prev
            const prevDisabled = page <= 1 ? 'disabled opacity-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-100';
            html +=
                `<button type="button" onclick="loadPreview(${page - 1})" ${page <= 1 ? 'disabled' : ''} class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-600 ${prevDisabled}">Sebelumnya</button>`;

            // Minimalist page range
            const startPage = Math.max(1, page - 2);
            const endPage = Math.min(last, page + 2);

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === page ? 'bg-blue-600 text-white font-bold' :
                    'text-gray-600 hover:bg-gray-100 border border-gray-300';
                html +=
                    `<button type="button" onclick="loadPreview(${i})" class="w-8 h-8 rounded-lg text-xs transition ${activeClass}">${i}</button>`;
            }

            // Tombol Next
            const nextDisabled = page >= last ? 'disabled opacity-50 cursor-not-allowed' :
                'cursor-pointer hover:bg-gray-100';
            html +=
                `<button type="button" onclick="loadPreview(${page + 1})" ${page >= last ? 'disabled' : ''} class="px-3 py-1.5 rounded-lg border border-gray-300 text-xs font-semibold text-gray-600 ${nextDisabled}">Berikutnya</button>`;

            controls.innerHTML = html;
        }

        // =================================== EXCEL QUEUE EXPORT ===================================
        let autoCloseProgressTimeout = null;

        function hideProgressBox() {
            if (autoCloseProgressTimeout) clearTimeout(autoCloseProgressTimeout);
            const progressContainer = document.getElementById('progressContainer');
            if (progressContainer) {
                progressContainer.classList.add('hidden');
            }
        }

        function startExportExcel() {
            const btnExport = document.getElementById('btnExport');
            const btnExportText = document.getElementById('btnExportText');
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressStatus = document.getElementById('progressStatus');
            const progressSubStatus = document.getElementById('progressSubStatus');
            const progressSpinner = document.getElementById('progressSpinner');
            const progressSuccessIcon = document.getElementById('progressSuccessIcon');
            const progressIconBox = document.getElementById('progressIconBox');
            const btnCloseProgress = document.getElementById('btnCloseProgress');
            const manualDownloadLink = document.getElementById('manualDownloadLink');

            if (autoCloseProgressTimeout) clearTimeout(autoCloseProgressTimeout);

            const payload = getFilterData(1);

            // Disable button
            btnExport.disabled = true;
            btnExport.classList.add('opacity-70', 'cursor-not-allowed');
            btnExportText.textContent = "Mengantrekan Ekspor...";

            // Reset box state
            progressContainer.classList.remove('hidden');
            progressBar.style.width = '5%';
            progressBar.className =
                'bg-gradient-to-r from-blue-500 to-indigo-600 h-2.5 rounded-full transition-all duration-300';
            progressText.textContent = '5%';
            progressText.className =
                'text-xs font-extrabold text-blue-700 bg-blue-100 border border-blue-200 px-3 py-1 rounded-full';
            progressStatus.textContent = 'Mendaftarkan antrean ekspor di server...';
            if (progressSubStatus) {
                progressSubStatus.textContent = 'Sistem memproses seluruh data transaksi per pasien di latar belakang...';
            }
            if (progressSpinner) progressSpinner.classList.remove('hidden');
            if (progressSuccessIcon) progressSuccessIcon.classList.add('hidden');
            if (progressIconBox) progressIconBox.className =
                'w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0';
            if (btnCloseProgress) btnCloseProgress.classList.add('hidden');
            manualDownloadLink.classList.add('hidden');

            axios.post("{{ route('master.patient-transactions.export') }}", payload)
                .then(function(res) {
                    const jobId = res.data.job_id;
                    iziToast.info({
                        title: 'Antrean Dimulai',
                        message: 'Ekspor Excel sedang diproses di server. Mohon tunggu...',
                        position: 'topRight'
                    });

                    pollExportStatus(jobId);
                })
                .catch(function(err) {
                    console.error("Gagal memulai ekspor:", err);
                    iziToast.error({
                        title: 'Gagal',
                        message: 'Tidak dapat memulai proses ekspor. Periksa koneksi Anda.',
                        position: 'topRight'
                    });
                    resetExportButton();
                    progressContainer.classList.add('hidden');
                });
        }

        function pollExportStatus(jobId) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressStatus = document.getElementById('progressStatus');
            const progressSubStatus = document.getElementById('progressSubStatus');
            const progressSpinner = document.getElementById('progressSpinner');
            const progressSuccessIcon = document.getElementById('progressSuccessIcon');
            const progressIconBox = document.getElementById('progressIconBox');
            const btnCloseProgress = document.getElementById('btnCloseProgress');
            const manualDownloadLink = document.getElementById('manualDownloadLink');
            const progressContainer = document.getElementById('progressContainer');

            if (exportInterval) clearInterval(exportInterval);

            exportInterval = setInterval(function() {
                axios.get("{{ url('/master/patient-transactions/export-status') }}/" + jobId)
                    .then(function(res) {
                        const data = res.data;
                        const status = data.status;
                        const progress = data.progress || 0;
                        const file = data.file;

                        progressBar.style.width = progress + '%';
                        progressText.textContent = progress + '%';

                        if (status === 'processing' || status === 'pending') {
                            progressStatus.textContent = `Memproses data transaksi di server (${progress}%)...`;
                        } else if (status === 'completed' || status === 'finished') {
                            clearInterval(exportInterval);
                            progressBar.style.width = '100%';
                            progressBar.className =
                                'bg-emerald-500 h-2.5 rounded-full transition-all duration-300';
                            progressText.textContent = '100%';
                            progressText.className =
                                'text-xs font-extrabold text-emerald-700 bg-emerald-100 border border-emerald-200 px-3 py-1 rounded-full';

                            progressStatus.textContent = 'Ekspor Selesai!';
                            if (progressSubStatus) {
                                progressSubStatus.textContent =
                                    'File Excel berhasil dibuat dan sedang diunduh.';
                            }

                            if (progressSpinner) progressSpinner.classList.add('hidden');
                            if (progressSuccessIcon) progressSuccessIcon.classList.remove('hidden');
                            if (progressIconBox) progressIconBox.className =
                                'w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center shrink-0';
                            if (btnCloseProgress) btnCloseProgress.classList.remove('hidden');

                            iziToast.success({
                                title: 'Berhasil',
                                message: 'File Excel telah berhasil dibuat dan siap diunduh.',
                                position: 'topRight'
                            });

                            if (file) {
                                manualDownloadLink.href = file;
                                manualDownloadLink.classList.remove('hidden');
                                // Trigger auto-download
                                window.location.href = file;
                            }

                            resetExportButton();

                            // Sembunyikan otomatis box progress setelah 4 detik
                            if (autoCloseProgressTimeout) clearTimeout(autoCloseProgressTimeout);
                            autoCloseProgressTimeout = setTimeout(function() {
                                progressContainer.classList.add('hidden');
                            }, 4000);
                        } else if (status === 'failed') {
                            clearInterval(exportInterval);
                            progressStatus.textContent = 'Proses ekspor gagal di server.';
                            if (progressSubStatus) {
                                progressSubStatus.textContent = 'Terjadi kendala saat generate berkas.';
                            }
                            progressBar.className = 'bg-red-500 h-2.5 rounded-full transition-all duration-300';
                            if (btnCloseProgress) btnCloseProgress.classList.remove('hidden');

                            iziToast.error({
                                title: 'Ekspor Gagal',
                                message: 'Terjadi kesalahan saat memproses file Excel di server.',
                                position: 'topRight'
                            });

                            resetExportButton();
                        }
                    })
                    .catch(function(err) {
                        console.error("Gagal polling status export:", err);
                    });
            }, 1000);
        }

        function resetExportButton() {
            const btnExport = document.getElementById('btnExport');
            const btnExportText = document.getElementById('btnExportText');
            btnExport.disabled = false;
            btnExport.classList.remove('opacity-70', 'cursor-not-allowed');
            btnExportText.textContent = "Download Excel (Queue)";
        }

        // =================================== HELPERS ===================================
        function getTypeBadgeClass(type) {
            if (!type) return 'badge-other';
            const t = type.toUpperCase();
            if (t.includes('UPDS')) return 'badge-upds';
            if (t.includes('HV') || t.includes('OTC')) return 'badge-hv';
            if (t.includes('RESEP')) return 'badge-resep';
            if (t.includes('KREDIT')) return 'badge-kredit';
            if (t.includes('RETUR')) return 'badge-retur';
            return 'badge-other';
        }

        function getPayBadgeClass(pay) {
            if (!pay) return 'badge-other';
            const p = pay.toUpperCase();
            if (p.includes('CASH')) return 'badge-pay-cash';
            if (p.includes('QRIS')) return 'badge-pay-qris';
            if (p.includes('DEBIT')) return 'badge-pay-debit';
            if (p.includes('TRANSFER')) return 'badge-pay-tf';
            return 'badge-other';
        }

        function escapeHtml(text) {
            if (!text) return '-';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) {
                return map[m];
            });
        }
    </script>
@endsection
