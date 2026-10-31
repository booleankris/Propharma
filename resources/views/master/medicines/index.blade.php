@extends('layouts.app')

@section('title', 'Medicines')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ── Select2 height fix ── */
        .select2-container .select2-selection--single {
            height: 42px !important;
            padding: 6px 10px !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
        }

        .select2-selection__choice {
            background: #e5e7eb !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            font-size: 13px;
        }

        /* ── Table row states ── */
        #table-data tbody tr {
            cursor: pointer;
            transition: background .15s;
        }

        #table-data tbody tr:hover {
            background: #eff6ff;
        }

        #table-data tbody tr.active-row {
            background: #dbeafe !important;
        }

        /* ── Edit mode indicator ── */
        #formModeBar {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        #formModeBar.edit {
            display: flex;
            background: #fef9c3;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        #formModeBar.add {
            display: flex;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        /* ── Status badges ── */
        .badge-active {
            padding: 2px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            font-size: 12px;
        }

        .badge-inactive {
            padding: 2px 10px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 999px;
            font-size: 12px;
        }

        /* ── Pill tags ── */
        .creditor-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #3b82f6;
            color: #fff;
            border-radius: 999px;
            padding: 4px 14px;
            font-size: 13px;
            font-weight: 500;
            transition: background .15s;
        }

        .creditor-pill:hover {
            background: #2563eb;
        }

        .creditor-pill button {
            background: none;
            border: none;
            color: #fff;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
        }

        /* ── Double-click hint ── */
        .dbl-hint {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- PAGE HEADER & DEDICATED MASTER ACTION TOOLBAR              --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <div
                class="mb-5 bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                {{-- Left: Page Title & Counter Badge --}}
                <div class="flex items-center gap-3.5">
                    <div class="rounded-2xl text-[#2563eb] flex items-center justify-center  flex-shrink-0">
                        <svg class="w-12 h-10 text-[#2563eb]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-xl sm:text-2xl font-black text-slate-800 tracking-tight leading-tight">Master &
                                Katalog Obat</h1>
                            <span id="badgeTotalMedicines"
                                class="px-2.5 py-0.5 text-xs font-bold rounded-full bg-blue-50 text-blue-700 border border-blue-200">
                                Total: {{ number_format(\App\Models\Medicines::count(), 0, ',', '.') }} Obat
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">
                            Pusat manajemen data obat, formulasi harga, stok, serta export & import katalog farmasi
                        </p>
                    </div>
                </div>

                {{-- Right: Dedicated Actions Toolbar (Prominent, clean, never cramped) --}}
                <div class="flex items-center gap-2 flex-wrap">
                    {{-- Status Filter --}}
                    <div
                        class="flex items-center gap-1.5 bg-slate-50 border border-slate-200/90 rounded-xl px-3 py-1.5 shadow-2xs">
                        <span class="text-xs font-bold text-slate-500">Filter:</span>
                        <select id="statusFilter"
                            class="text-xs font-semibold bg-transparent border-0 p-0 text-slate-800 focus:ring-0 cursor-pointer">
                            <option value="1" selected>🟢 Aktif Saja</option>
                            <option value="0">🔴 Nonaktif Saja</option>
                            <option value="all">⚪ Semua Status</option>
                        </select>
                    </div>

                    {{-- Export Master Excel (.xlsx) Button --}}
                    <button type="button" id="btnExportMasterExcel" onclick="exportMasterExcel()"
                        class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-95 text-white text-xs font-bold rounded-xl shadow-xs shadow-emerald-500/20 transition-all gap-2 cursor-pointer"
                        title="Download seluruh katalog obat ke file Excel (.xlsx) rapi dan berformat">
                        <svg class="w-4 h-4 text-emerald-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span id="btnExportMasterExcelText">Export Excel (.xlsx)</span>
                    </button>

                    {{-- Export Dropdown / More Options (CSV & Rekap Stok) --}}
                    <div class="relative inline-block text-left" id="dropdownExportMoreWrapper">
                        <button type="button" id="btnExportMoreToggle" onclick="toggleExportMenu(event)"
                            class="inline-flex items-center px-3 py-2 bg-white hover:bg-slate-50 active:bg-slate-100 text-slate-700 border border-slate-200 rounded-xl text-xs font-semibold shadow-2xs transition gap-1.5 cursor-pointer">
                            <span>Opsi Lain</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="exportMoreMenu"
                            class="hidden absolute right-0 mt-1.5 w-56 bg-white rounded-xl shadow-xl border border-slate-100 py-1.5 z-50">
                            <a href="{{ route('master-data.export', ['type' => 'medicines', 'format' => 'csv']) }}"
                                class="flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-8-4 4 4m0 0 4-4m-4 4V4" />
                                </svg>
                                <span>Export Format Ringkas CSV</span>
                            </a>
                            <button type="button" onclick="exportExcelGudang()"
                                class="w-full text-left flex items-center gap-2 px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-blue-600 transition cursor-pointer">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                <span>Export Rekap Saldo Stok</span>
                            </button>
                        </div>
                    </div>

                    {{-- Import Excel Button --}}
                    <button type="button" id="btnOpenImport"
                        class="inline-flex items-center px-3.5 py-2 bg-white hover:bg-slate-50 active:bg-slate-100 text-slate-700 border border-slate-300 rounded-xl text-xs font-bold shadow-2xs transition gap-1.5 cursor-pointer"
                        title="Import atau perbarui katalog obat dari file Excel">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        <span>Import Excel</span>
                    </button>
                </div>
            </div>

            {{-- ══════════════════════════════════════════════════════════ --}}
            {{-- MAIN TWO-COLUMN WORKSPACE: TABLE PANEL + FORM PANEL         --}}
            {{-- ══════════════════════════════════════════════════════════ --}}
            <div class="flex flex-col lg:flex-row gap-4 items-start">

                {{-- ─────────────────── TABLE PANEL ─────────────────── --}}
                <div
                    class="card w-full lg:w-[42%] shadow-sm border border-slate-200/80 rounded-2xl p-5 bg-white flex flex-col gap-3.5">
                    {{-- Header: Title & Hint --}}
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                        <div>
                            <h2 class="text-base font-bold text-slate-800 leading-tight">Daftar Katalog Obat</h2>
                            <p class="text-[11px] text-slate-500 font-medium">Klik 1x untuk preview detail, double-klik
                                untuk edit formulir</p>
                        </div>
                        <span
                            class="text-[11px] font-semibold text-slate-400 bg-slate-50 border border-slate-100 px-2 py-0.5 rounded-lg">
                            Tabel Cepat
                        </span>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-100 shadow-2xs">
                        <table id="table-data" class="min-w-full text-xs text-left text-slate-600">
                            <thead
                                class="bg-slate-50 text-slate-700 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="px-3 py-2.5">#</th>
                                    <th class="px-3 py-2.5">Kode</th>
                                    <th class="px-3 py-2.5">Nama</th>
                                    <th class="px-3 py-2.5">Kategori</th>
                                    <th class="px-3 py-2.5">Keterangan</th>
                                    <th class="px-3 py-2.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100"></tbody>
                        </table>
                    </div>
                </div>

                {{-- ─────────────────── FORM PANEL ─────────────────── --}}
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 w-full lg:w-[58%]">

                    {{-- Mode indicator bar --}}
                    <div id="formModeBar">
                        <span id="formModeIcon"></span>
                        <span id="formModeText"></span>
                    </div>

                    <form id="medicineForm" action="{{ route('medicines.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" id="medicine_id" name="id">
                        <input type="hidden" id="pharmacy_id" name="pharmacy_id" value="1">
                        <input type="hidden" id="stock" name="stock" value="0">
                        <input type="hidden" id="raw_price" name="raw_price" value="0">

                        {{-- ── SECTION 1: IDENTITAS & KLASIFIKASI OBAT ── --}}
                        <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200/80 space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                                <h3
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> 1. Identitas & Klasifikasi Obat
                                </h3>
                                <label
                                    class="inline-flex items-center gap-1.5 cursor-pointer text-xs text-amber-700 font-semibold hover:text-amber-800 select-none bg-amber-50 px-2.5 py-1 rounded-md border border-amber-200 shadow-sm transition">
                                    <input type="checkbox" id="enable_manual_code"
                                        class="w-3.5 h-3.5 text-amber-600 rounded border-gray-300 focus:ring-amber-500">
                                    <span>Edit Kode Manual</span>
                                </label>
                            </div>

                            {{-- Row 1: Code & Category --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Kode Obat</label>
                                    <input id="code" name="code" readonly
                                        class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3.5 py-2 text-xs transition"
                                        placeholder="Otomatis digenerate sistem">
                                    <p id="code_warning"
                                        class="hidden mt-1 text-[11px] text-rose-600 font-semibold leading-tight">
                                        ⚠️ Bahaya: Mengubah kode manual berisiko duplikasi jika kode sudah digunakan obat
                                        lain!
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Golongan Obat <span
                                            class="text-rose-500">*</span></label>
                                    <select id="medicine_category_id" name="medicine_category_id"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs"></select>
                                </div>
                            </div>

                            {{-- Row 2: Composition, Component & Factory --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Komposisi (Zat Aktif) <span
                                            class="text-rose-500">*</span></label>
                                    <select id="composition_id" name="composition_id"
                                        class="select2 w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs">
                                        <option value="">-- Pilih Komposisi --</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Komponen (SP)</label>
                                    <input id="component" name="component" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs"
                                        placeholder="Contoh: Paracetamol 500mg">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Pabrik / Manufaktur <span
                                            class="text-rose-500">*</span></label>
                                    <select id="factory_id" name="factory_id"
                                        class="select2 w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs">
                                        <option value="">-- Pilih Pabrik --</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Row 3: Name, Barcode & Generic --}}
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                                <div class="md:col-span-7">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Nama Obat <span
                                            class="text-rose-500">*</span></label>
                                    <input id="name" name="name" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs font-semibold text-gray-800 uppercase focus:ring-2 focus:ring-blue-400 focus:border-blue-500"
                                        placeholder="Contoh: BETASERC TAB 8MG">
                                </div>
                                <div class="md:col-span-3">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Barcode</label>
                                    <input id="barcode" name="barcode" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs focus:ring-2 focus:ring-blue-400"
                                        placeholder="Scan / Ketik Barcode">
                                </div>
                                <div class="md:col-span-2 pt-4">
                                    <label class="flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" id="generic_check" name="generic_check" value="1"
                                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="text-xs font-semibold text-gray-700">Generik</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- ── SECTION 2: BENTUK, KEMASAN & SEDIAAN ── --}}
                        <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200/80 space-y-3">
                            <div class="border-b border-slate-200 pb-2">
                                <h3
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> 2. Kemasan, Satuan & Sediaan
                                </h3>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Kemasan</label>
                                    <select id="packaging" name="packaging"
                                        class="select2 w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs">
                                        <option value="">-- Pilih --</option>
                                        @foreach (['UNIT', 'PACK', 'PCS', 'TUBE', 'VIAL', 'AMP', 'KTK', 'BKS', 'BTL', 'BOX', 'TAB', 'DUS', 'STRIP', 'KAPSUL', 'KAPLET'] as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Satuan</label>
                                    <select id="unit" name="unit"
                                        class="select2 w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs">
                                        <option value="">-- Pilih --</option>
                                        @foreach (['UNIT', 'PACK', 'PCS', 'TUBE', 'VIAL', 'AMP', 'KTK', 'BKS', 'BTL', 'BOX', 'TAB', 'DUS', 'STRIP', 'KAPSUL', 'KAPLET'] as $opt)
                                            <option value="{{ $opt }}">{{ $opt }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-xs font-bold text-gray-700">Isi</label>
                                        <label
                                            class="inline-flex items-center gap-1 cursor-pointer text-[10px] text-gray-600 select-none">
                                            <input type="checkbox" id="pack" name="is_active"
                                                class="w-3 h-3 text-blue-600 rounded">
                                            <span>Utuh</span>
                                        </label>
                                    </div>
                                    <input id="content" name="content" type="number"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Dosis</label>
                                    <input id="dosage" name="dosage" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs"
                                        placeholder="Misal: 500">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Strip</label>
                                    <input id="strip" name="strip" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs">
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Sediaan</label>
                                    <input id="preparations" name="preparations" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-xs"
                                        placeholder="Tablet/Sirup">
                                </div>
                            </div>
                        </div>

                        {{-- ── SECTION 3: HARGA & STOK ── --}}
                        <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200/80 space-y-3">
                            <div class="border-b border-slate-200 pb-2">
                                <h3
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span> 3. Skema Harga & Batas Stok
                                </h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1"><span>Harga HNA (Beli)</span>
                                        <span id="hnaUnitLabel" class="font-medium text-blue-600">(per
                                            satuan)</span></label>
                                    <input id="pharmacy_net_price" name="pharmacy_net_price" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs font-semibold text-gray-800"
                                        placeholder="Rp 0">
                                </div>
                                <div>
                                    <label
                                        class="block text-xs font-bold text-gray-700 mb-1 flex items-center justify-between">
                                        <span>Harga PPN 11%</span>
                                        <span class="text-[10px] text-blue-600 font-medium">(otomatis)</span>
                                    </label>
                                    <input id="net_price" name="net_price" readonly type="text"
                                        class="w-full rounded-lg border border-blue-200 bg-blue-50/70 px-3.5 py-2 text-xs text-blue-800 font-bold"
                                        placeholder="Rp 0">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Harga HET (Eceran)</label>
                                    <input id="het_price" name="het_price" type="text"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs font-semibold text-gray-800"
                                        placeholder="Rp 0">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Stok Minimal</label>
                                    <input id="minimal_stock" name="minimal_stock" type="number"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs"
                                        placeholder="0">
                                </div>
                            </div>
                        </div>

                        {{-- ── SECTION 4: PENEMPATAN & KREDITUR ── --}}
                        <div class="p-4 bg-slate-50/80 rounded-xl border border-slate-200/80 space-y-3">
                            <div class="border-b border-slate-200 pb-2">
                                <h3
                                    class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-purple-500"></span> 4. Supplier, Penempatan & Tipe
                                </h3>
                            </div>

                            {{-- Creditors --}}
                            <div class="w-full space-y-1.5" id="creditorSelect">
                                <label class="block text-xs font-bold text-gray-700">Pilih Kreditur (Supplier) & Diskon
                                    %</label>
                                <div id="pillContainer" class="flex flex-wrap gap-1.5 mb-1"></div>
                                <div class="relative">
                                    <input id="searchInput" type="text"
                                        placeholder="Cari Kreditur / Tekan Enter untuk lewati..."
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs"
                                        autocomplete="off">
                                    <ul id="creditorDropdown"
                                        class="absolute z-[99999] mt-1 w-full rounded-xl border bg-white shadow-lg max-h-60 overflow-y-auto hidden">
                                    </ul>
                                </div>
                                <input type="hidden" name="creditor_ids" id="creditor_ids">
                            </div>

                            {{-- Location, Etalase, Type --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Lokasi Rak</label>
                                    <select id="location" name="location"
                                        class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs">
                                        <option value="">-- Pilih Lokasi --</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Etalase</label>
                                    <select id="items" name="etalase"
                                        class="select2 w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs">
                                        <option value="">-- Pilih Etalase --</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Tipe Pesanan <span
                                            class="text-rose-500">*</span></label>
                                    <select id="type" name="type"
                                        class="select2 w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs">
                                        <option value="">-- Pilih Tipe --</option>
                                        <option value="NARKOTIKA">Narkotika</option>
                                        <option value="PSIKOTROPIKA">Psikotropika</option>
                                        <option value="REGULER">Reguler</option>
                                        <option value="PREKURSOR">Prekursor</option>
                                        <option value="OBAT-OBAT TERTENTU (OOT)">Obat Tertentu</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Keterangan Pemesanan --}}
                            <div class="pt-1">
                                <label class="block text-xs font-bold text-gray-700 mb-1 flex items-center justify-between">
                                    <span>Keterangan Pemesanan</span>
                                    <span class="text-[10px] text-gray-400 font-normal">Aturan diskon / min. order untuk SP</span>
                                </label>
                                <textarea id="notes" name="notes" rows="3"
                                    class="w-full rounded-lg border border-gray-300 px-3.5 py-2 text-xs font-mono text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-500 transition-all leading-relaxed"
                                    placeholder="Contoh:&#10;1 -netto&#10;2 - 10box 5%&#10;min. 20box 11%"></textarea>
                            </div>
                        </div>

                        {{-- BUTTONS ACTION BAR --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-gray-200">
                            <div class="flex items-center gap-2">
                                <button type="button" id="submitForm"
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl shadow-md font-semibold text-xs transition flex items-center gap-1.5">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.5">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    <span>Simpan (Enter)</span>
                                </button>
                                <button type="button" id="cancelEdit"
                                    class="px-5 py-2.5 bg-amber-400 hover:bg-amber-500 text-slate-800 rounded-xl shadow font-semibold text-xs transition">
                                    Batal (Esc)
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" id="deleteData"
                                    class="px-4 py-2.5 bg-rose-500 hover:bg-rose-600 text-white rounded-xl shadow font-semibold text-xs transition">
                                    Nonaktifkan
                                </button>
                                <button type="button" id="restoreData"
                                    class="hidden px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl shadow font-semibold text-xs transition">
                                    Aktifkan Kembali
                                </button>
                                <button type="button" id="back"
                                    class="px-4 py-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl font-semibold text-xs transition">
                                    Kembali
                                </button>
                            </div>
                        </div>

                    </form>
                </div><!-- /form panel -->

            </div>
        </div>
    </section>

    {{-- ─────────────────── MODAL IMPORT MASTER OBAT ─────────────────── --}}
    <div id="modalImportMedicine" class="fixed inset-0 hidden overflow-y-auto" style="z-index: 99999 !important;"
        aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-60 backdrop-blur-sm"
                style="z-index: 99998 !important;" id="backdropImport"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div class="relative inline-block px-6 pt-6 pb-6 overflow-hidden text-left align-bottom transition-all transform bg-white rounded-2xl shadow-2xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100"
                style="z-index: 99999 !important;">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-800" id="modal-title">Import Master Obat dari Excel
                            </h3>
                            <p class="text-xs text-gray-500">Mendukung format .xlsx, .xls, .csv (hingga 100MB)</p>
                        </div>
                    </div>
                    <button type="button" id="btnCloseImport"
                        class="text-gray-400 hover:text-gray-600 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="formImportMedicine" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <input type="file" id="importFileInput" name="file" accept=".xlsx,.xls,.csv" class="hidden">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Pilih File Excel Master
                            Obat</label>
                        <div id="dropzoneImport"
                            class="border-2 border-dashed border-gray-300 hover:border-emerald-500 rounded-xl p-6 text-center cursor-pointer transition bg-gray-50/50 hover:bg-emerald-50/30">
                            <svg class="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p id="fileNamePreview" class="text-xs font-medium text-gray-700">Klik untuk memilih file atau
                                drag & drop ke sini</p>
                            <p class="text-[11px] text-emerald-600 font-semibold mt-1">✨ Mendukung file hasil Export Excel
                                Master Obat, file dengan judul kolom, maupun format kolom lama.</p>
                        </div>
                    </div>

                    {{-- Panduan Kolom --}}
                    <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 text-[11px] text-slate-600">
                        <div class="font-semibold text-slate-700 mb-1">Panduan Format File:</div>
                        <p class="mb-2 text-slate-500">Sistem otomatis mendeteksi header kolom. Anda dapat mengunggah file
                            hasil <strong>Export Master (.xlsx)</strong> langsung, atau menggunakan layout kolom berikut:
                        </p>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-0.5">
                            <div>• <strong>Kode Obat</strong> / Kolom B</div>
                            <div>• <strong>Nama Obat</strong> / Kolom C</div>
                            <div>• <strong>Harga Beli (HNA)</strong> / Kolom O</div>
                            <div>• <strong>HET</strong> / Kolom P</div>
                            <div>• <strong>Barcode</strong> / Kolom Q</div>
                            <div>• <strong>Supplier/PBF</strong> / Kolom R</div>
                            <div>• <strong>Pabrik</strong> / Kolom AH</div>
                            <div>• <strong>Golongan/Kategori</strong> / Kolom AL</div>
                        </div>
                    </div>

                    {{-- Loading Indicator --}}
                    <div id="importProgress" class="hidden space-y-2">
                        <div class="flex items-center justify-between text-xs font-medium text-gray-600">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span id="importProgressLabel">Sedang menganalisis file...</span>
                            </span>
                            <span class="text-emerald-600 font-bold">Harap tunggu</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div class="bg-emerald-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                        </div>
                    </div>

                    <div id="importSummary" class="hidden rounded-xl border border-blue-200 bg-blue-50 p-4 space-y-2">
                        <div class="font-semibold text-sm text-slate-800">Ringkasan sebelum import</div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                            <div class="rounded-lg bg-white p-2">
                                <div class="text-slate-500">Baris data</div><strong id="summaryTotal"
                                    class="text-slate-800">0</strong>
                            </div>
                            <div class="rounded-lg bg-white p-2">
                                <div class="text-slate-500">Obat baru</div><strong id="summaryCreate"
                                    class="text-emerald-700">0</strong>
                            </div>
                            <div class="rounded-lg bg-white p-2">
                                <div class="text-slate-500">Akan diperbarui</div><strong id="summaryUpdate"
                                    class="text-blue-700">0</strong>
                            </div>
                            <div class="rounded-lg bg-white p-2">
                                <div class="text-slate-500">Dilewati</div><strong id="summarySkipped"
                                    class="text-amber-700">0</strong>
                            </div>
                        </div>
                        <div class="text-[11px] text-slate-600">Pastikan ringkasan sudah sesuai. Perubahan database baru
                            dijalankan setelah tombol konfirmasi ditekan.</div>
                        <div class="text-[11px] text-slate-600"><strong>Kolom yang disinkronkan:</strong> nama, satuan, HNA
                            dan net price + PPN 11%, barcode, HET, kemasan, sediaan, dan golongan.</div>
                        <ul id="summarySkippedSamples" class="hidden list-disc pl-5 text-[11px] text-amber-800"></ul>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button type="button" id="btnCancelImport"
                            class="px-4 py-2 text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition cursor-pointer">Batal</button>
                        <button type="submit" id="btnSubmitImport"
                            class="px-4 py-2 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow transition flex items-center gap-1.5 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            <span>Tampilkan Ringkasan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════ --}}
    {{-- EXPORT LOADING POPUP MODAL                                 --}}
    {{-- ══════════════════════════════════════════════════════════ --}}
    <div id="exportLoadingModal"
        class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 transition-all"
        style="z-index: 99999 !important;">
        <div
            class="bg-white rounded-3xl p-6 sm:p-7 shadow-2xl max-w-md w-full border border-slate-100 text-center animate-scaleUp">
            <div
                class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-4 relative shadow-sm">
                <svg class="w-8 h-8 animate-pulse text-emerald-600" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <div class="absolute -top-1 -right-1 w-4 h-4 bg-emerald-500 rounded-full animate-ping"></div>
            </div>
            <h3 class="text-lg font-bold text-slate-800 mb-1.5">Menyiapkan File Excel Master Obat</h3>
            <p class="text-xs text-slate-500 mb-5 leading-relaxed">
                Sedang menyusun 8.000+ data katalog obat lengkap (harga, barcode, supplier, stok) ke format Excel rapi.
                Unduhan akan berjalan otomatis dalam beberapa detik.
            </p>
            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden p-0.5 mb-3">
                <div class="bg-gradient-to-r from-emerald-500 via-teal-500 to-blue-600 h-full rounded-full animate-pulse"
                    style="width: 100%"></div>
            </div>
            <div class="flex items-center justify-center gap-2 text-[11px] font-semibold text-slate-400">
                <svg class="w-3.5 h-3.5 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>Mengompresi dan memformat spreadsheet...</span>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        function toggleExportMenu(e) {
            e.stopPropagation();
            const menu = document.getElementById('exportMoreMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        document.addEventListener('click', function(e) {
            const menu = document.getElementById('exportMoreMenu');
            const wrapper = document.getElementById('dropdownExportMoreWrapper');
            if (menu && !menu.classList.contains('hidden') && wrapper && !wrapper.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        function exportMasterExcel() {
            const btn = document.getElementById('btnExportMasterExcel');
            const btnText = document.getElementById('btnExportMasterExcelText');
            const statusVal = document.getElementById('statusFilter')?.value || '1';

            // Generate unique token for download tracking
            const token = 'token_' + Date.now() + '_' + Math.random().toString(36).substring(2, 7);

            // Disable button
            if (btn) {
                btn.disabled = true;
                btn.classList.add('opacity-75', 'cursor-not-allowed');
            }
            if (btnText) {
                btnText.innerText = 'Menyiapkan File...';
            }

            // Show Sleek Loading Modal / Popup
            const modalEl = document.getElementById('exportLoadingModal');
            if (modalEl) {
                modalEl.classList.remove('hidden');
                modalEl.classList.add('flex');
            }

            // Build URL
            const url = "{{ route('master-data.export', ['type' => 'medicines']) }}?status=" + encodeURIComponent(
                statusVal) + "&download_token=" + encodeURIComponent(token);

            // Trigger download via hidden iframe
            let iframe = document.getElementById('exportDownloadFrame');
            if (!iframe) {
                iframe = document.createElement('iframe');
                iframe.id = 'exportDownloadFrame';
                iframe.style.display = 'none';
                document.body.appendChild(iframe);
            }
            iframe.src = url;

            // Watch for download completion via cookie
            const checkInterval = setInterval(() => {
                if (document.cookie.split(';').some(c => c.trim().startsWith('download_token=' + token))) {
                    clearInterval(checkInterval);
                    clearTimeout(fallbackTimeout);
                    finishExportMaster();
                }
            }, 300);

            // Fallback timeout after 12 seconds in case cookies disabled
            const fallbackTimeout = setTimeout(() => {
                clearInterval(checkInterval);
                finishExportMaster();
            }, 12000);

            function finishExportMaster() {
                // Delete tracking cookie
                document.cookie = 'download_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';

                if (modalEl) {
                    modalEl.classList.add('hidden');
                    modalEl.classList.remove('flex');
                }

                if (btn) {
                    btn.disabled = false;
                    btn.classList.remove('opacity-75', 'cursor-not-allowed');
                }
                if (btnText) {
                    btnText.innerText = 'Export Excel (.xlsx)';
                }

                iziToast.success({
                    title: 'Export Berhasil',
                    message: 'File Master Obat (.xlsx) berhasil diunduh secara lengkap.',
                    position: 'topRight',
                    timeout: 4000
                });
            }
        }

        function exportExcelGudang() {
            const menu = document.getElementById('exportMoreMenu');
            if (menu) menu.classList.add('hidden');

            const statusVal = document.getElementById('statusFilter')?.value || '1';

            iziToast.info({
                title: 'Export Rekap Stok',
                message: 'Sedang menyusun data stok gudang & obat ke Excel. Unduhan akan segera dimulai otomatis...',
                position: 'topRight',
                timeout: 4000
            });

            let url = "{{ route('supplies.printstockdata') }}?status=" + encodeURIComponent(statusVal);
            window.location.href = url;
        }

        // ════════════════════════════════════════════════
        // GLOBALS
        // ════════════════════════════════════════════════
        const CREDITORS = @json($creditors); // injected once from PHP

        const form = document.getElementById('medicineForm');
        const $modeBar = document.getElementById('formModeBar');
        const $modeIcon = document.getElementById('formModeIcon');
        const $modeText = document.getElementById('formModeText');

        const elPNP = document.getElementById('pharmacy_net_price');
        const elNet = document.getElementById('net_price');
        const elHet = document.getElementById('het_price');
        const elRaw = document.getElementById('raw_price');

        const input = document.getElementById('searchInput');
        const dropdown = document.getElementById('creditorDropdown');
        const pills = document.getElementById('pillContainer');
        const hiddenCreds = document.getElementById('creditor_ids');

        const elCode = document.getElementById('code');
        const elManualCodeCheck = document.getElementById('enable_manual_code');
        const elCodeWarning = document.getElementById('code_warning');
        let originalCode = '';

        let selected = new Map();
        let filtered = [];
        let activeIdx = -1;
        let tableData = null;

        function setManualCodeMode(enabled) {
            if (!elCode || !elManualCodeCheck) return;
            if (enabled) {
                elCode.removeAttribute('readonly');
                elCode.classList.remove('bg-gray-100', 'border-gray-300');
                elCode.classList.add('bg-white', 'border-amber-400', 'focus:border-amber-500', 'ring-2', 'ring-amber-200');
                if (elCodeWarning) elCodeWarning.classList.remove('hidden');
                elManualCodeCheck.checked = true;
            } else {
                elCode.setAttribute('readonly', true);
                elCode.classList.remove('bg-white', 'border-amber-400', 'focus:border-amber-500', 'ring-2',
                    'ring-amber-200');
                elCode.classList.add('bg-gray-100', 'border-gray-300');
                if (elCodeWarning) elCodeWarning.classList.add('hidden');
                elManualCodeCheck.checked = false;
            }
        }

        if (elManualCodeCheck) {
            elManualCodeCheck.addEventListener('change', function(e) {
                if (this.checked) {
                    const ok = confirm(
                        "⚠️ PERINGATAN BAHAYA:\n\n" +
                        "Mengubah kode obat secara manual berisiko tinggi menyebabkan duplikasi data kode obat jika kode yang diinput sama dengan obat lain.\n\n" +
                        "Apakah Anda yakin ingin mengaktifkan edit kode manual?"
                    );
                    if (ok) {
                        setManualCodeMode(true);
                        elCode.focus();
                    } else {
                        this.checked = false;
                        setManualCodeMode(false);
                    }
                } else {
                    setManualCodeMode(false);
                }
            });
        }

        // ════════════════════════════════════════════════
        // PRICE HELPERS
        // ════════════════════════════════════════════════
        function stripRupiah(val) {
            return val.replace(/[^0-9]/g, '') || '0';
        }

        function toRupiah(num) {
            if (!num || isNaN(num)) return 'Rp 0';
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
        }

        function formatField(el) {
            const raw = stripRupiah(el.value);
            el.value = raw === '0' ? '' : toRupiah(parseInt(raw, 10));
        }

        function hnaMultiplier() {
            const packChecked = document.getElementById('pack')?.checked;
            const content = parseFloat(document.getElementById('content')?.value) || 1;
            return packChecked && content > 0 ? content : 1;
        }

        function updateHnaUnitLabel() {
            const label = document.getElementById('hnaUnitLabel');
            if (label) label.textContent = document.getElementById('pack')?.checked ? '(per box)' : '(per satuan)';
        }

        /** HNA is entered at the selected pack size, while stored prices stay per unit. */
        function recalcPPN() {
            const enteredHna = parseInt(stripRupiah(elPNP.value), 10) || 0;
            const unitHna = enteredHna / hnaMultiplier();
            elNet.value = toRupiah(Math.floor(unitHna * 1.11));
            elRaw.value = unitHna;
            updateHnaUnitLabel();
        }

        // ════════════════════════════════════════════════
        // FORM MODE INDICATOR
        // ════════════════════════════════════════════════
        function setMode(mode) {
            $modeBar.className = mode; // 'edit' | 'add' | ''
            if (mode === 'edit') {
                $modeIcon.textContent = '✏️';
                $modeText.textContent = 'Mode Edit — data di-load dari tabel';
            } else if (mode === 'add') {
                $modeIcon.textContent = '➕';
                $modeText.textContent = 'Mode Tambah — isi form untuk menambah data baru';
            }
        }

        // ════════════════════════════════════════════════
        // SELECT2 HELPER – set value without re-fetching
        // ════════════════════════════════════════════════
        function setSelect2Value(selector, id, text) {
            const $s = $(selector);
            if (!id) {
                $s.val(null).trigger('change');
                return;
            }
            if ($s.find(`option[value="${id}"]`).length === 0) {
                $s.append(new Option(text, id, true, true));
            } else {
                $s.val(id);
            }
            $s.trigger('change');
        }

        // ════════════════════════════════════════════════
        // RESET FORM
        // ════════════════════════════════════════════════
        function resetForm() {
            form.reset();
            ['#medicine_category_id', '#composition_id', '#factory_id',
                '#type', '#packaging', '#unit', '#location', '#items'
            ]
            .forEach(s => $(s).val(null).trigger('change'));

            selected.clear();
            renderPills();
            syncHidden();

            $('#medicine_id').val('');
            dropdown.classList.add('hidden');
            input.value = '';
            activeIdx = -1;
            filtered = [];

            // Reset prices to blank (HET defaults to 0 on submit, not display)
            elPNP.value = '';
            elNet.value = 'Rp 0';
            elHet.value = '';
            elRaw.value = '0';
            updateHnaUnitLabel();

            originalCode = '';
            setManualCodeMode(false);
            if (elCode) elCode.value = '';

            $('#deleteData').removeClass('hidden');
            $('#restoreData').addClass('hidden');

            setMode('add');
            $('#table-data tbody tr').removeClass('active-row');
        }

        // ════════════════════════════════════════════════
        // CREDITOR PILL UI
        // ════════════════════════════════════════════════
        function renderDropdown(filter = '') {
            dropdown.innerHTML = '';
            activeIdx = -1;

            filtered = CREDITORS.filter(c =>
                c.name.toLowerCase().includes(filter.toLowerCase())
            );

            if (!filtered.length) {
                dropdown.innerHTML = `<li class="px-4 py-2 text-sm text-gray-400">No results</li>`;
                return;
            }

            filtered.forEach((c, i) => {
                const isSel = selected.has(c.id);
                const li = document.createElement('li');
                li.className = `px-4 py-2 text-sm flex items-center justify-between
            ${isSel ? 'text-gray-400 cursor-not-allowed' : 'cursor-pointer hover:bg-blue-50'}`;
                li.innerHTML = `<span>${c.name}</span>${isSel ? '<span class="text-blue-600">✔</span>' : ''}`;
                if (!isSel) li.onclick = () => selectCreditor(c);
                dropdown.appendChild(li);
            });

            highlightActive();
        }

        function highlightActive() {
            [...dropdown.children].forEach((li, i) =>
                li.classList.toggle('bg-blue-100', i === activeIdx)
            );
        }

        function selectCreditor(item) {
            selected.set(item.id, {
                ...item,
                discount: item.discount ?? 0
            });
            input.value = '';
            dropdown.classList.add('hidden');
            activeIdx = -1;
            renderPills();
            syncHidden();
            setTimeout(() => input.focus(), 0);
        }

        function removeCreditor(id) {
            selected.delete(id);
            renderPills();
            syncHidden();
            renderDropdown(input.value);
        }

        function updateDiscount(id, value) {
            const item = selected.get(id);
            if (!item) return;
            item.discount = parseFloat(value) || 0;
            syncHidden();
        }

        function renderPills() {
            pills.innerHTML = '';
            selected.forEach(item => {
                const pill = document.createElement('div');
                pill.className = 'creditor-pill';
                pill.innerHTML = `
            <span>${item.name}</span>
            <input type="number" step="0.01" min="0" max="100"
                   value="${item.discount}" style="width: auto; color: #111; border: none; background: #e4f1ff; text-align: center; border-radius: 30px; padding: 2px 4px;"
                   class="discount-input">
            <span style="font-size:11px;">%</span>
            <button type="button">&times;</button>
        `;
                pill.querySelector('.discount-input').addEventListener('input', e => updateDiscount(item.id, e
                    .target.value));
                pill.querySelector('button').onclick = () => removeCreditor(item.id);
                pills.appendChild(pill);
            });
        }

        function syncHidden() {
            const payload = [...selected.values()].map(c => ({
                code: c.code,
                discount: c.discount ?? 0
            })).filter(c => c.code);
            hiddenCreds.value = JSON.stringify(payload);
        }
        // ════════════════════════════════════════════════
        // LOAD CREDITORS FOR EXISTING MEDICINE
        // ════════════════════════════════════════════════
        function loadMedicineCreditors(medicineId) {
            selected.clear();
            renderPills();
            syncHidden();

            axios.get(`/medicines/${medicineId}/edit-data`)
                .then(({
                    data
                }) => {
                    (data.creditors || []).forEach(c => selected.set(c.id, {
                        id: c.id,
                        code: c.code,
                        name: c.name,
                        discount: c.discount ?? 0
                    }));
                    renderPills();
                    syncHidden();
                })
                .catch(console.error);
        }

        // ════════════════════════════════════════════════
        // POPULATE FORM FROM ROW DATA
        // ════════════════════════════════════════════════
        function populateForm(row) {
            // Plain inputs
            ['code', 'name', 'barcode', 'component', 'content', 'dosage',
                'minimal_stock', 'strip', 'stock', 'preparations', 'notes'
            ].forEach(key => {
                const el = document.getElementById(key);
                if (el) el.value = row[key] ?? '';
            });

            // Price fields are stored per unit. Show the equivalent whole-box HNA when selected.
            const packCheckbox = document.getElementById('pack');
            if (packCheckbox) packCheckbox.checked = Number(row.whole) === 1;
            const unitHna = Number(row.raw_price) || Number(row.pharmacy_net_price) || 0;
            elPNP.value = unitHna ? toRupiah(Math.round(unitHna * hnaMultiplier())) : '';
            elHet.value = row.het_price ? toRupiah(row.het_price) : '';
            recalcPPN();

            // Select2 AJAX fields
            setSelect2Value('#medicine_category_id', row.medicine_category_id, row.category_name);
            setSelect2Value('#composition_id', row.composition_id, row.composition_name);
            setSelect2Value('#factory_id', row.factory_id, row.factory_name);
            setSelect2Value('#location', row.location, row.location_name);
            setSelect2Value('#items', row.etalase, row.etalase_name);

            // Static select2
            $('#type').val(row.type).trigger('change');
            $('#packaging').val(row.packaging).trigger('change');
            $('#unit').val(row.unit).trigger('change');

            // Code & manual edit reset
            originalCode = row.code ?? '';
            setManualCodeMode(false);

            // Hidden id
            $('#medicine_id').val(row.id);

            // Toggle Delete vs Restore button based on status
            if (row.status == 0) {
                $('#deleteData').addClass('hidden');
                $('#restoreData').removeClass('hidden');
            } else {
                $('#deleteData').removeClass('hidden');
                $('#restoreData').addClass('hidden');
            }
        }

        // ════════════════════════════════════════════════
        // SUBMIT
        // ════════════════════════════════════════════════
        function handleSubmit() {
            const fd = new FormData(form);
            const id = document.getElementById('medicine_id').value;
            const currentCode = elCode ? elCode.value.trim() : '';

            // Konfirmasi bahaya jika edit manual aktif atau kode berubah
            if (elManualCodeCheck && elManualCodeCheck.checked && currentCode !== '') {
                const proceed = confirm(
                    "⚠️ KONFIRMASI BAHAYA KODE OBAT:\n\n" +
                    "Anda akan menyimpan obat dengan kode: '" + currentCode + "'.\n" +
                    "Pastikan kode ini belum pernah digunakan oleh obat lain agar tidak terjadi duplikasi kode.\n\n" +
                    "Apakah Anda yakin ingin melanjutkan penyimpanan?"
                );
                if (!proceed) return;
            }

            // send cleaned numeric values
            fd.set('generic', document.getElementById('generic_check').checked ? 1 : 0);
            fd.set('raw_price', elRaw.value || '0');
            fd.set('pharmacy_net_price', stripRupiah(elPNP.value));
            fd.set('net_price', stripRupiah(elNet.value));

            // HET: default to 0 when blank
            const hetRaw = stripRupiah(elHet.value);
            fd.set('het_price', hetRaw === '0' || hetRaw === '' ? 0 : hetRaw);

            const url = id ? `/medicines/${id}` : form.action;
            if (id) fd.append('_method', 'PUT');

            axios.post(url, fd)
                .then(({
                    data
                }) => {
                    iziToast.success({
                        title: 'Sukses',
                        message: data.message
                    });
                    resetForm();
                    tableData.ajax.reload(null, false);
                })
                .catch(err => {
                    const msg = err.response?.status === 422 ?
                        Object.values(err.response.data.errors).flat().join('<br>') :
                        'Gagal menyimpan data.';
                    iziToast.error({
                        title: 'Error',
                        message: msg
                    });
                });
        }

        // ════════════════════════════════════════════════
        // ENTER-KEY NAVIGATION
        // ════════════════════════════════════════════════

        // The canonical tab-order for the form.
        const NAV_ORDER = [{
                id: 'medicine_category_id',
                type: 'select2ajax'
            },
            {
                id: 'composition_id',
                type: 'select2ajax'
            },
            {
                id: 'component',
                type: 'input'
            },
            {
                id: 'factory_id',
                type: 'select2ajax'
            },
            {
                id: 'name',
                type: 'input'
            },
            {
                id: 'barcode',
                type: 'input'
            },
            {
                id: 'packaging',
                type: 'select2'
            },
            {
                id: 'unit',
                type: 'select2'
            },
            {
                id: 'content',
                type: 'input'
            },
            {
                id: 'dosage',
                type: 'input'
            },
            {
                id: 'strip',
                type: 'input'
            },
            {
                id: 'preparations',
                type: 'input'
            },
            {
                id: 'pharmacy_net_price',
                type: 'input'
            },
            {
                id: 'het_price',
                type: 'input'
            },
            {
                id: 'minimal_stock',
                type: 'input'
            },
            {
                id: 'searchInput',
                type: 'creditor'
            },
            {
                id: 'location',
                type: 'select2ajax'
            },
            {
                id: 'items',
                type: 'select2ajax'
            },
            {
                id: 'type',
                type: 'select2'
            },
        ];

        /** Focus / open the field described by a NAV_ORDER entry. */
        function focusNavItem(item) {
            if (!item) return;
            if (item.type === 'input' || item.type === 'creditor') {
                const el = document.getElementById(item.id);
                if (el) {
                    el.focus();
                    if (el.select && typeof el.select === 'function') el.select();
                }
            } else {
                // select2 or select2ajax → open the dropdown
                setTimeout(() => {
                    $('#' + item.id).select2('open');
                }, 50);
            }
        }

        /** Navigate forward from the element with the given id. */
        function navForward(fromId) {
            if (fromId === 'code') {
                focusNavItem(NAV_ORDER[0]);
                return;
            }
            const idx = NAV_ORDER.findIndex(n => n.id === fromId);
            if (idx === -1) return;
            const next = NAV_ORDER[idx + 1];
            if (next) {
                focusNavItem(next);
            } else {
                // End of form → submit
                handleSubmit();
            }
        }

        // ── Attach Enter key listeners to plain inputs ──
        function initEnterNavigation() {
            document.querySelectorAll(
                '#code,#component,#name,#barcode,#content,#dosage,#strip,#preparations,' +
                '#pharmacy_net_price,#het_price,#minimal_stock'
            ).forEach(el => {
                el.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        navForward(this.id);
                    }
                });
            });

            // Global Shortcut: Ctrl+S / Cmd+S for Quick Save, Esc for Reset
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    handleSubmit();
                } else if (e.key === 'Escape') {
                    resetForm();
                    setMode('add');
                }
            });
        }

        // ── Select2 selects: after user picks a value, auto-advance ──
        // (select2:select fires after the user chooses an option)
        // We attach these inside DOMContentLoaded after select2 is initialised,
        // so they live in the DOMContentLoaded block below.

        // ── Creditor search input: Enter handled inside its own keydown ──
        // (already present in the creditor block below)

        // ════════════════════════════════════════════════
        // DOM READY
        // ════════════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', function() {

            // ── Set initial mode ──
            setMode('add');
            initEnterNavigation();

            // ── Price input events ──
            elPNP.addEventListener('input', function() {
                formatField(this);
                recalcPPN();
            });
            document.getElementById('pack').addEventListener('change', recalcPPN);
            document.getElementById('content').addEventListener('input', recalcPPN);
            elHet.addEventListener('input', function() {
                formatField(this);
            });

            // ── SELECT2 INIT ──
            const ajaxSelect2 = (selector, url, placeholder) => {
                $(selector).select2({
                    placeholder,
                    allowClear: true,
                    ajax: {
                        url,
                        dataType: 'json',
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
                        cache: true,
                    }
                });
            };

            ajaxSelect2('#composition_id', "{{ route('composition.select') }}", 'Cari Komposisi...');
            ajaxSelect2('#factory_id', "{{ route('factories.select') }}", 'Cari Pabrik...');
            ajaxSelect2('#medicine_category_id', "{{ route('categories.select') }}", 'Cari Golongan...');
            ajaxSelect2('#location', "{{ route('locations.select') }}", 'Cari Lokasi...');
            ajaxSelect2('#items', "{{ route('items.select') }}", 'Cari Etalase...');

            $('#packaging').select2({
                placeholder: 'Pilih Kemasan...',
                allowClear: true
            });
            $('#unit').select2({
                placeholder: 'Pilih Satuan...',
                allowClear: true
            });
            $('#type').select2({
                placeholder: 'Pilih Tipe...',
                allowClear: true
            });

            // ── Select2 auto-advance: after picking a value, move to next field ──
            // Uses the same NAV_ORDER table so the order is always consistent.
            [
                'medicine_category_id', 'composition_id', 'factory_id',
                'packaging', 'unit', 'location', 'items', 'type'
            ].forEach(id => {
                $('#' + id).on('select2:select', function() {
                    // Small delay so select2 can finish closing before we open next
                    setTimeout(() => navForward(id), 80);
                });
                // Also allow clearing without advancing (select2:clear → do nothing)
            });

            // ── CREDITOR SEARCH ──
            input.addEventListener('input', e => {
                dropdown.classList.remove('hidden');
                renderDropdown(e.target.value);
            });

            // ── CREDITOR SEARCH keydown ──
            input.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (dropdown.classList.contains('hidden')) {
                        dropdown.classList.remove('hidden');
                        renderDropdown(input.value);
                    }
                    if (filtered.length) {
                        activeIdx = (activeIdx + 1) % filtered.length;
                        highlightActive();
                    }
                    return;
                }

                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (filtered.length) {
                        activeIdx = (activeIdx - 1 + filtered.length) % filtered.length;
                        highlightActive();
                    }
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();

                    // If dropdown is open and has a highlighted item → select it
                    if (!dropdown.classList.contains('hidden') && filtered.length) {
                        const pickIdx = activeIdx >= 0 ? activeIdx : 0;
                        if (filtered[pickIdx]) {
                            selectCreditor(filtered[pickIdx]);
                            return;
                        }
                    }

                    // If search box is empty (no text, no pending pick) → advance to next field
                    if (input.value.trim() === '') {
                        dropdown.classList.add('hidden');
                        navForward('searchInput');
                        return;
                    }

                    // Has text but dropdown hidden → open it
                    dropdown.classList.remove('hidden');
                    renderDropdown(input.value);
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('mousedown', e => {
                if (!document.getElementById('creditorSelect').contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            // ── BUTTONS ──
            // Prevent Enter key from re-triggering button click events
            ['submitForm', 'cancelEdit', 'deleteData', 'back'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) btn.addEventListener('keydown', e => {
                    if (e.key === 'Enter') e.preventDefault();
                });
            });

            document.getElementById('submitForm').addEventListener('click', handleSubmit);

            document.getElementById('cancelEdit').addEventListener('click', () => {
                resetForm();
                setMode('add');
            });

            document.getElementById('back').addEventListener('click', () => {
                window.location.href = "{{ route('home') }}";
            });

            document.getElementById('deleteData').addEventListener('click', () => {
                const id = document.getElementById('medicine_id').value;
                if (!id) {
                    iziToast.warning({
                        title: 'Perhatian',
                        message: 'Pilih data obat terlebih dahulu.'
                    });
                    return;
                }
                swal({
                    title: 'Hapus Obat?',
                    text: 'Data akan dinonaktifkan.',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then(yes => {
                    if (!yes) return;
                    axios.delete('/medicines/' + id)
                        .then(({
                            data
                        }) => {
                            iziToast.success({
                                title: 'Dihapus',
                                message: data.message
                            });
                            resetForm();
                            tableData.ajax.reload(null, false);
                        });
                });
            });

            document.getElementById('restoreData').addEventListener('click', () => {
                const id = document.getElementById('medicine_id').value;
                if (!id) {
                    iziToast.warning({
                        title: 'Perhatian',
                        message: 'Pilih data obat terlebih dahulu.'
                    });
                    return;
                }
                swal({
                    title: 'Aktifkan Kembali Obat?',
                    text: 'Obat ini akan kembali aktif dan dapat dicari di kasir serta pemesanan.',
                    icon: 'info',
                    buttons: ['Batal', 'Ya, Aktifkan'],
                }).then(yes => {
                    if (!yes) return;
                    axios.post('/medicines/' + id + '/restore')
                        .then(({
                            data
                        }) => {
                            iziToast.success({
                                title: 'Sukses',
                                message: data.message
                            });
                            resetForm();
                            tableData.ajax.reload(null, false);
                        })
                        .catch(err => {
                            iziToast.error({
                                title: 'Error',
                                message: 'Gagal mengaktifkan obat.'
                            });
                        });
                });
            });

            // ── DATATABLE ──
            tableData = $('#table-data').DataTable({
                responsive: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('medicines.index') }}",
                    data: function(d) {
                        d.status_filter = $('#statusFilter').val();
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'code'
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'category_name'
                    },
                    {
                        data: 'notes',
                        name: 'notes',
                        defaultContent: '-',
                        render: function(data) {
                            if (!data || data === '-') return '<span class="text-slate-300">-</span>';
                            const escaped = $('<div>').text(data).html();
                            return `<div class="whitespace-pre-line font-mono text-[11px] leading-snug text-slate-700 max-w-[200px]">${escaped}</div>`;
                        }
                    },
                    {
                        data: 'status_label',
                        orderable: false
                    },
                ],
            });

            tableData.on('xhr.dt', function(e, settings, json, xhr) {
                if (json && json.recordsTotal !== undefined) {
                    const badge = document.getElementById('badgeTotalMedicines');
                    if (badge) {
                        badge.innerText = 'Total: ' + Number(json.recordsTotal).toLocaleString('id-ID') +
                            ' Obat';
                    }
                }
            });

            $('#statusFilter').on('change', function() {
                resetForm();
                tableData.ajax.reload();
            });

            // ── SINGLE CLICK  → preview (highlight row only) ──
            // ── DOUBLE CLICK  → load into form for editing ──
            let clickTimer = null;

            $('#table-data tbody').on('click', 'tr', function() {
                const rowData = tableData.row(this).data();
                if (!rowData) return;

                clearTimeout(clickTimer);
                clickTimer = setTimeout(() => {
                    // Single-click: just highlight
                    $('#table-data tbody tr').removeClass('active-row');
                    $(this).addClass('active-row');
                }, 220);
            });

            $('#table-data tbody').on('dblclick', 'tr', function() {
                clearTimeout(clickTimer); // cancel single-click highlight timer

                const rowData = tableData.row(this).data();
                if (!rowData) return;

                $('#table-data tbody tr').removeClass('active-row');
                $(this).addClass('active-row');

                populateForm(rowData);
                loadMedicineCreditors(rowData.id);
                setMode('edit');

                // Scroll form into view on mobile
                document.getElementById('medicineForm').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });

            // ── IMPORT MASTER OBAT DARI EXCEL ──
            const $modalImport = $('#modalImportMedicine');
            const $fileInput = $('#importFileInput');
            const $dropzone = $('#dropzoneImport');
            const $namePreview = $('#fileNamePreview');
            const $progress = $('#importProgress');
            const $summary = $('#importSummary');
            const $btnSubmit = $('#btnSubmitImport');
            const importPreviewUrl = "{{ route('medicines.import') }}";
            const executeImportUrl = "{{ route('medicines.import.execute') }}";
            let previewToken = null;

            function resetImportPreview() {
                previewToken = null;
                $summary.addClass('hidden');
                $('#summarySkippedSamples').empty().addClass('hidden');
                $btnSubmit.find('span').text('Tampilkan Ringkasan');
                $btnSubmit.prop('disabled', false).removeClass('opacity-50');
                $('#importProgressLabel').text('Sedang menganalisis file...');
            }

            $('#btnOpenImport').on('click', function() {
                try {
                    $('.select2').select2('close');
                } catch (e) {}
                $fileInput.val('');
                $namePreview.text('Klik untuk memilih file atau drag & drop ke sini').removeClass(
                    'text-emerald-600 font-semibold');
                $progress.addClass('hidden');
                resetImportPreview();
                $btnSubmit.prop('disabled', false).removeClass('opacity-50');
                $modalImport.removeClass('hidden');
            });

            $('#btnCloseImport, #btnCancelImport, #backdropImport').on('click', function() {
                $modalImport.addClass('hidden');
            });

            $dropzone.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $fileInput[0].click();
            });

            $fileInput.on('click', function(e) {
                e.stopPropagation();
            });

            $fileInput.on('change', function() {
                resetImportPreview();
                if (this.files && this.files[0]) {
                    $namePreview.text(
                            `${this.files[0].name} (${(this.files[0].size / 1024 / 1024).toFixed(2)} MB)`)
                        .addClass('text-emerald-600 font-semibold');
                }
            });

            // Drag & Drop
            $dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('border-emerald-500 bg-emerald-50/50');
            });

            $dropzone.on('dragleave dragend drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('border-emerald-500 bg-emerald-50/50');
            });

            $dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length) {
                    $fileInput[0].files = e.originalEvent.dataTransfer.files;
                    resetImportPreview();
                    $namePreview.text(
                        `${$fileInput[0].files[0].name} (${($fileInput[0].files[0].size / 1024 / 1024).toFixed(2)} MB)`
                    ).addClass('text-emerald-600 font-semibold');
                }
            });

            $('#formImportMedicine').on('submit', function(e) {
                e.preventDefault();
                if (!previewToken && (!$fileInput[0].files || !$fileInput[0].files[0])) {
                    swal('Peringatan', 'Silakan pilih file Excel terlebih dahulu.', 'warning');
                    return;
                }

                const isExecuting = Boolean(previewToken);
                const formData = isExecuting ? new FormData() : new FormData(this);
                if (isExecuting) {
                    formData.append('preview_token', previewToken);
                    formData.append('_token', $('input[name="_token"]').val());
                    $('#importProgressLabel').text('Sedang memproses import & relasi PBF...');
                } else {
                    $('#importProgressLabel').text('Sedang menganalisis file...');
                }
                $progress.removeClass('hidden');
                $btnSubmit.prop('disabled', true).addClass('opacity-50');

                axios.post(isExecuting ? executeImportUrl : importPreviewUrl, formData, {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    })
                    .then(function(res) {
                        $progress.addClass('hidden');
                        $btnSubmit.prop('disabled', false).removeClass('opacity-50');

                        if (!isExecuting) {
                            const summary = res.data.summary || {};
                            previewToken = res.data.preview_token;
                            $('#summaryTotal').text(Number(summary.total_rows || 0).toLocaleString(
                                'id-ID'));
                            $('#summaryCreate').text(Number(summary.created_count || 0).toLocaleString(
                                'id-ID'));
                            $('#summaryUpdate').text(Number(summary.updated_count || 0).toLocaleString(
                                'id-ID'));
                            $('#summarySkipped').text(Number(summary.skipped_count || 0).toLocaleString(
                                'id-ID'));

                            const skippedSamples = summary.skipped_samples || [];
                            if (skippedSamples.length) {
                                const $samples = $('#summarySkippedSamples').empty();
                                skippedSamples.forEach(item => {
                                    $('<li>').text(
                                        `Baris ${Number(item.row)}: ${String(item.reason || '')}`
                                    ).appendTo($samples);
                                });
                                $samples.removeClass('hidden');
                            }

                            $summary.removeClass('hidden');
                            $btnSubmit.find('span').text('Konfirmasi & Import');
                            if (Number(summary.created_count || 0) + Number(summary.updated_count ||
                                    0) === 0) {
                                $btnSubmit.prop('disabled', true).addClass('opacity-50');
                            }
                            return;
                        }

                        $modalImport.addClass('hidden');
                        swal({
                            title: 'Import Berhasil!',
                            text: res.data.message || 'Data master obat berhasil diimpor.',
                            icon: 'success',
                            button: 'OK',
                        }).then(() => tableData.ajax.reload());
                    })
                    .catch(function(err) {
                        $progress.addClass('hidden');
                        $btnSubmit.prop('disabled', false).removeClass('opacity-50');
                        if (isExecuting) resetImportPreview();
                        const msg = err.response && err.response.data && err.response.data.message ?
                            err.response.data.message :
                            (err.message || 'Gagal memproses file import.');
                        swal(isExecuting ? 'Gagal Import' : 'Gagal Menganalisis', msg, 'error');
                    });
            });

        });
    </script>
@endsection
