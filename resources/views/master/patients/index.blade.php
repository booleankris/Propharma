@extends('layouts.app')

@section('title', 'Data Pasien')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">

    <style>
        /* Modern DataTables Styling */
        .dataTables_wrapper {
            font-family: inherit;
        }

        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 1.25rem;
            color: #475569;
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 0.4rem 2rem 0.4rem 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            color: #1e293b;
            font-size: 0.8125rem;
            outline: none;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .dataTables_length select:focus {
            border-color: #3b82f6;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .dataTables_wrapper .dataTables_filter input {
            padding: 0.45rem 0.9rem;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            color: #1e293b;
            font-size: 0.8125rem;
            outline: none;
            width: 240px;
            margin-left: 0.5rem;
            transition: all 0.2s ease;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #3b82f6;
            background-color: #fff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            width: 280px;
        }

        /* Table styling */
        #table-data {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
            border: none;
        }

        #table-data thead th {
            background: #f8fafc !important;
            color: #475569 !important;
            font-size: 0.75rem !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 0.875rem 1rem !important;
            border-top: 1px solid #f1f5f9 !important;
            border-bottom: 2px solid #e2e8f0 !important;
            white-space: nowrap;
        }

        #table-data tbody tr {
            transition: all 0.15s ease-in-out;
            cursor: pointer;
        }

        #table-data tbody tr:hover {
            background-color: #f0f7ff !important;
        }

        #table-data tbody tr.selected-row {
            background-color: #e0f2fe !important;
            position: relative;
        }

        #table-data tbody tr.selected-row td {
            color: #0369a1;
            font-weight: 600;
        }

        #table-data tbody td {
            padding: 0.85rem 1rem !important;
            font-size: 0.8125rem !important;
            color: #334155;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle;
        }

        /* Pagination & Info */
        .dataTables_wrapper .dataTables_info {
            padding-top: 1.25rem;
            color: #64748b;
            font-size: 0.8125rem;
        }

        .dataTables_wrapper .dataTables_paginate {
            padding-top: 1rem;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 0.625rem !important;
            padding: 0.35rem 0.75rem !important;
            font-size: 0.8125rem !important;
            font-weight: 500 !important;
            color: #475569 !important;
            border: 1px solid transparent !important;
            margin: 0 2px;
            transition: all 0.15s ease;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f1f5f9 !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(135deg, #2563eb, #1d4ed8) !important;
            color: #ffffff !important;
            border: 1px solid #1d4ed8 !important;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.25);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            color: #cbd5e1 !important;
            background: transparent !important;
        }

        .form-input-modern {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .form-input-modern:focus {
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
        }

        /* Code Pill Badge */
        .patient-code-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.2rem 0.6rem;
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            border-radius: 0.5rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            font-weight: 600;
        }

        #table-data thead th.col-status,
        #table-data tbody td.col-status {
            white-space: nowrap !important;
            text-align: center !important;
            width: 110px !important;
            min-width: 110px !important;
        }

        /* Status interactive pill button on table row */
        .status-toggle-btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.375rem !important;
            padding: 0.3rem 0.75rem !important;
            border-radius: 9999px !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            line-height: 1 !important;
            white-space: nowrap !important;
            cursor: pointer !important;
            user-select: none !important;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
        }

        .status-toggle-btn:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1) !important;
        }

        .status-toggle-btn .status-dot {
            width: 6px !important;
            height: 6px !important;
            border-radius: 9999px !important;
            flex-shrink: 0 !important;
        }

        .status-toggle-btn.status-active {
            background-color: #ecfdf5 !important;
            color: #047857 !important;
            border: 1px solid #a7f3d0 !important;
        }

        .status-toggle-btn.status-active:hover {
            background-color: #d1fae5 !important;
            border-color: #6ee7b7 !important;
        }

        .status-toggle-btn.status-inactive {
            background-color: #fff1f2 !important;
            color: #e11d48 !important;
            border: 1px solid #fecdd3 !important;
        }

        .status-toggle-btn.status-inactive:hover {
            background-color: #ffe4e6 !important;
            border-color: #fda4af !important;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 py-2">
        <div class="section-body space-y-5">

            {{-- ─── Header Hero Card ─── --}}
            <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-600 rounded-3xl p-6 text-white shadow-xl relative overflow-hidden">
                <div class="absolute -right-8 -bottom-8 opacity-10 pointer-events-none">
                    <svg class="w-60 h-60 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/20 text-xs font-semibold backdrop-blur-md mb-2">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span>Database Master Pasien</span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Master Data Pasien</h1>
                        <p class="text-blue-100 text-sm mt-1 max-w-2xl leading-relaxed">
                            Kelola data demografis pasien, riwayat kontak, dan status keaktifan untuk integrasi transaksi penjualan & resep klinik apotek.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        <a href="{{ route('master.patient-transactions.index') }}"
                            class="px-4 py-2.5 bg-white/10 hover:bg-white/25 text-white rounded-xl text-xs font-semibold backdrop-blur-md transition-all duration-200 border border-white/20 flex items-center gap-2 shadow-sm">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Kunjungan Pasien</span>
                        </a>

                        @include('master.partials.export-button', ['type' => 'patients'])
                    </div>
                </div>
            </div>

            {{-- ─── Main Content Grid: Table (Left) + Form Card (Right) ─── --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

                {{-- Left: DataTable Card (8 Columns) --}}
                <div class="lg:col-span-7 xl:col-span-8 bg-white rounded-3xl shadow-sm border border-slate-200/80 p-6 transition-all">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-800">Daftar Pasien Terdaftar</h3>
                                <p class="text-xs text-slate-400">Klik baris tabel untuk edit data, atau klik badge status untuk ubah Aktif/Non-Aktif</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            {{-- Filter Status Dropdown --}}
                            <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200/90 rounded-xl px-3 py-1.5 shadow-2xs">
                                <span class="text-xs font-bold text-slate-500">Filter:</span>
                                <select id="statusFilter"
                                    class="text-xs font-semibold bg-transparent border-0 p-0 text-slate-800 focus:ring-0 cursor-pointer">
                                    <option value="all" selected>⚪ Semua Status</option>
                                    <option value="1">🟢 Aktif Saja</option>
                                    <option value="0">🔴 Non-Aktif Saja</option>
                                </select>
                            </div>

                            <span id="selectedRowIndicator" class="hidden text-xs font-semibold px-3 py-1.5 bg-blue-50 text-blue-600 rounded-xl border border-blue-200">
                                1 data terpilih
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="table-data" class="min-w-full text-sm text-left">
                            <thead>
                                <tr>
                                    <th class="w-12 text-center">#</th>
                                    <th>Kode</th>
                                    <th>Nama Pasien</th>
                                    <th>Alamat</th>
                                    <th>No. Telepon</th>
                                    <th>Kota</th>
                                    <th>Tgl Lahir</th>
                                    <th class="col-status">Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- Right: Form Card (5 Columns) --}}
                <div class="lg:col-span-5 xl:col-span-4 bg-white rounded-3xl shadow-sm border border-slate-200/80 p-6 sticky top-6">
                    <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                            <div>
                                <h3 id="formTitle" class="text-sm font-bold text-slate-800">Tambah Pasien Baru</h3>
                                <p id="formSubtitle" class="text-[11px] text-slate-400">Isi formulir di bawah ini</p>
                            </div>
                        </div>

                        <span id="formModeBadge" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Mode Baru
                        </span>
                    </div>

                    <form id="patientForm" action="{{ route('patients.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" id="patient_id" name="id">
                        <input type="hidden" id="status" name="status" value="1">

                        {{-- Kode & Nama Pasien --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-1">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Kode
                                </label>
                                <input id="code" name="code" readonly placeholder="Otomatis"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-mono font-bold text-slate-600 focus:outline-none cursor-not-allowed">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5 flex items-center gap-1">
                                    <span>Nama Pasien</span>
                                    <span class="text-rose-500">*</span>
                                </label>
                                <input id="name" name="name" type="text" required
                                    class="form-input-modern w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:bg-white"
                                    placeholder="Nama lengkap pasien">
                            </div>
                        </div>

                        {{-- Alamat --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Alamat Domisili
                            </label>
                            <input id="address" name="address" type="text"
                                class="form-input-modern w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500"
                                placeholder="Jalan, RT/RW, Kelurahan">
                        </div>

                        {{-- No Telepon & Kota --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    No. WhatsApp / HP
                                </label>
                                <input id="phone" name="phone" type="text"
                                    class="form-input-modern w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500"
                                    placeholder="Contoh: 08123456789">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                    Kota / Kabupaten
                                </label>
                                <input id="city" name="city" type="text"
                                    class="form-input-modern w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-blue-500"
                                    placeholder="Nama kota">
                            </div>
                        </div>

                        {{-- Tanggal Lahir --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Tanggal Lahir
                            </label>
                            <input id="birth" name="birth" type="date"
                                class="form-input-modern w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-800 focus:outline-none focus:border-blue-500">
                        </div>

                        {{-- Status Keaktifan (Segmented Control Pill UI) --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Status Keaktifan Pasien
                            </label>
                            <div class="grid grid-cols-2 gap-2 bg-slate-100/90 p-1 rounded-2xl border border-slate-200/80">
                                <button type="button" id="btnStatusActive" onclick="setStatusValue(1)"
                                    class="flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-bold transition-all duration-200 bg-emerald-600 text-white shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Aktif</span>
                                </button>
                                <button type="button" id="btnStatusInactive" onclick="setStatusValue(0)"
                                    class="flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-800 transition-all duration-200">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Non-Aktif</span>
                                </button>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1.5">Pasien aktif dapat dicari dan dipilih pada modul kasir & transaksi</p>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="pt-3 border-t border-slate-100 flex flex-col gap-2.5">
                            {{-- Primary Save Button --}}
                            <button type="button" id="submitForm"
                                class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-semibold text-xs shadow-md shadow-blue-500/25 hover:shadow-lg hover:shadow-blue-500/35 transition-all duration-200 active:scale-[0.98]">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span id="submitBtnText">Simpan Data Pasien</span>
                            </button>

                            {{-- Secondary Actions: Cancel / Reset, Delete, Return --}}
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" id="cancelEdit"
                                    class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-all active:scale-[0.98]">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span id="cancelBtnText">Reset / Batal</span>
                                </button>

                                <button type="button" id="deleteData"
                                    class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200/60 font-semibold text-xs transition-all active:scale-[0.98]">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>Hapus</span>
                                </button>
                            </div>

                            <button type="button" id="back"
                                class="w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-50 text-xs font-medium transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                <span>Kembali ke Halaman Utama</span>
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <script>
        let tableData, selectedData = null;
        const form = document.getElementById('patientForm');

        function setStatusValue(val) {
            const statusInput = document.getElementById('status');
            statusInput.value = val;

            const btnActive = document.getElementById('btnStatusActive');
            const btnInactive = document.getElementById('btnStatusInactive');

            if (parseInt(val) === 1) {
                btnActive.className = "flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-bold transition-all duration-200 bg-emerald-600 text-white shadow-sm";
                btnInactive.className = "flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-800 transition-all duration-200";
            } else {
                btnActive.className = "flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-800 transition-all duration-200";
                btnInactive.className = "flex items-center justify-center gap-1.5 py-2.5 px-3 rounded-xl text-xs font-bold transition-all duration-200 bg-rose-600 text-white shadow-sm";
            }
        }

        function updateFormMode(isEdit = false, data = null) {
            const formTitle = document.getElementById('formTitle');
            const formSubtitle = document.getElementById('formSubtitle');
            const formModeBadge = document.getElementById('formModeBadge');
            const submitBtnText = document.getElementById('submitBtnText');
            const indicator = document.getElementById('selectedRowIndicator');

            if (isEdit && data) {
                formTitle.textContent = 'Edit Data Pasien';
                formSubtitle.textContent = `Memperbarui pasien: ${data.name || ''}`;
                formModeBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200';
                formModeBadge.textContent = 'Mode Edit';
                submitBtnText.textContent = 'Perbarui Data Pasien';
                indicator.classList.remove('hidden');
                indicator.textContent = `${data.code || data.name} terpilih`;
            } else {
                formTitle.textContent = 'Tambah Pasien Baru';
                formSubtitle.textContent = 'Isi formulir di bawah ini';
                formModeBadge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200';
                formModeBadge.textContent = 'Mode Baru';
                submitBtnText.textContent = 'Simpan Data Pasien';
                indicator.classList.add('hidden');
            }
        }

        function resetFormState() {
            form.reset();
            $('#patient_id').val('');
            $('#code').val('');
            setStatusValue(1);
            $('#table-data tbody tr').removeClass('selected-row');
            selectedData = null;
            updateFormMode(false);
        }

        function quickToggleStatus(event, patientId, currentStatus) {
            event.stopPropagation();

            const nextStatus = currentStatus == 1 ? 0 : 1;
            const actionText = nextStatus == 1 ? 'Aktifkan' : 'Non-aktifkan';

            axios.patch(`/patients/${patientId}/toggle-status`, {}, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                }
            })
            .then(res => {
                iziToast.success({
                    title: 'Status Diperbarui',
                    message: res.data.message || `Pasien berhasil di-${actionText.toLowerCase()}.`,
                    position: 'topRight'
                });

                if (selectedData && selectedData.id == patientId) {
                    setStatusValue(nextStatus);
                    selectedData.status = nextStatus;
                }

                tableData.ajax.reload(null, false);
            })
            .catch(err => {
                iziToast.error({
                    title: 'Gagal',
                    message: 'Tidak dapat mengubah status pasien.',
                    position: 'topRight'
                });
            });
        }

        $(function() {
            // DATATABLE INITIALIZATION
            tableData = $('#table-data').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('patients.index') }}',
                    data: function(d) {
                        d.status_filter = $('#statusFilter').val();
                    }
                },
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Cari nama, kode, telp...",
                    lengthMenu: "Tampil _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari total _TOTAL_ pasien",
                    infoEmpty: "Tidak ada data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    zeroRecords: "Data pasien tidak ditemukan",
                    paginate: {
                        first: "«",
                        previous: "‹",
                        next: "›",
                        last: "»"
                    }
                },
                columns: [
                    {
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center font-medium text-slate-400'
                    },
                    {
                        data: 'code',
                        render: function(data) {
                            return data ? `<span class="patient-code-badge">${data}</span>` : '<span class="text-slate-400 italic">-</span>';
                        }
                    },
                    {
                        data: 'name',
                        className: 'font-semibold text-slate-800'
                    },
                    {
                        data: 'address',
                        render: function(data) {
                            return data ? `<span class="truncate block max-w-xs" title="${data}">${data}</span>` : '<span class="text-slate-400 italic">-</span>';
                        }
                    },
                    {
                        data: 'phone',
                        render: function(data) {
                            return data ? `<span class="font-mono text-xs text-slate-600">${data}</span>` : '<span class="text-slate-400 italic">-</span>';
                        }
                    },
                    {
                        data: 'city',
                        render: function(data) {
                            return data || '<span class="text-slate-400 italic">-</span>';
                        }
                    },
                    {
                        data: 'birth',
                        render: function(data) {
                            return data || '<span class="text-slate-400 italic">-</span>';
                        }
                    },
                    {
                        data: 'status',
                        orderable: false,
                        searchable: false,
                        className: 'col-status',
                        render: function(data, type, row) {
                            const isAktif = (parseInt(data) === 1);
                            if (isAktif) {
                                return `
                                    <button type="button" onclick="quickToggleStatus(event, ${row.id}, 1)"
                                        class="status-toggle-btn status-active" title="Klik untuk menonaktifkan">
                                        <span class="status-dot bg-emerald-500 animate-pulse"></span>
                                        <span>Aktif</span>
                                    </button>
                                `;
                            } else {
                                return `
                                    <button type="button" onclick="quickToggleStatus(event, ${row.id}, 0)"
                                        class="status-toggle-btn status-inactive" title="Klik untuk mengaktifkan">
                                        <span class="status-dot bg-rose-500"></span>
                                        <span>Non-Aktif</span>
                                    </button>
                                `;
                            }
                        }
                    },
                ],
            });

            // Filter status change
            $('#statusFilter').on('change', function() {
                resetFormState();
                tableData.ajax.reload();
            });

            // CLICK ROW → EDIT
            $('#table-data tbody').on('click', 'tr', function(e) {
                if ($(e.target).closest('.status-toggle-btn').length) {
                    return;
                }

                selectedData = tableData.row(this).data();
                if (!selectedData) return;

                $('#table-data tbody tr').removeClass('selected-row');
                $(this).addClass('selected-row');

                $('#patient_id').val(selectedData.id);
                $('#code').val(selectedData.code || '');
                $('#name').val(selectedData.name || '');
                $('#address').val(selectedData.address || '');
                $('#phone').val(selectedData.phone || '');
                $('#city').val(selectedData.city || '');
                $('#birth').val(selectedData.birth || '');
                
                const statusVal = selectedData.status !== undefined ? parseInt(selectedData.status) : 1;
                setStatusValue(statusVal);

                updateFormMode(true, selectedData);
            });

            // BACK BUTTON 
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });

            // CANCEL EDIT
            $('#cancelEdit').click(function() {
                resetFormState();
            });

            // DELETE
            $('#deleteData').click(function() {
                const id = $('#patient_id').val();

                if (!id) {
                    return iziToast.warning({
                        title: 'Peringatan',
                        message: 'Pilih pasien di tabel terlebih dahulu untuk dihapus.',
                        position: 'topRight'
                    });
                }

                swal({
                    title: 'Hapus Pasien?',
                    text: 'Data pasien ini akan dihapus secara permanen.',
                    icon: 'warning',
                    buttons: {
                        cancel: {
                            text: 'Batal',
                            visible: true,
                            className: 'bg-slate-200 text-slate-700'
                        },
                        confirm: {
                            text: 'Ya, Hapus',
                            className: 'bg-rose-600 text-white'
                        }
                    },
                    dangerMode: true,
                }).then(function(yes) {
                    if (!yes) return;

                    $.ajax({
                        url: '/patients/' + id,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(res) {
                            iziToast.success({
                                title: 'Berhasil',
                                message: res.message || 'Pasien berhasil dihapus.',
                                position: 'topRight'
                            });

                            resetFormState();
                            tableData.ajax.reload(null, false);
                        },
                        error: function(err) {
                            iziToast.error({
                                title: 'Gagal',
                                message: 'Tidak dapat menghapus pasien.',
                                position: 'topRight'
                            });
                        }
                    });
                });
            });
        });

        // ENTER NAVIGATION
        const inputs = ['name', 'address', 'phone', 'city', 'birth']
            .map(id => document.getElementById(id));

        inputs.forEach((input, index) => {
            if (!input) return;
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (index < inputs.length - 1) inputs[index + 1].focus();
                    else handleSubmit();
                }
            });
        });

        // SUBMIT
        document.getElementById('submitForm').addEventListener('click', handleSubmit);

        function handleSubmit() {
            const nameInput = document.getElementById('name');
            if (!nameInput.value.trim()) {
                nameInput.focus();
                return iziToast.warning({
                    title: 'Perhatian',
                    message: 'Nama pasien wajib diisi.',
                    position: 'topRight'
                });
            }

            const formData = new FormData(form);
            const id = document.getElementById('patient_id').value;

            const url = id ?
                `/patients/${id}` :
                form.action;

            if (id) formData.append('_method', 'PUT');

            const submitBtn = document.getElementById('submitForm');
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-70', 'cursor-wait');

            axios({
                    method: 'POST',
                    url: url,
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(res => {
                    iziToast.success({
                        title: 'Sukses',
                        message: res.data.message || 'Data pasien berhasil disimpan.',
                        position: 'topRight'
                    });

                    resetFormState();
                    tableData.ajax.reload(null, false);
                })
                .catch(err => {
                    let msg = 'Gagal menyimpan data.';

                    if (err.response?.status === 422) {
                        msg = Object.values(err.response.data.errors)
                            .flat()
                            .join('<br>');
                    }

                    iziToast.error({
                        title: 'Error',
                        message: msg,
                        position: 'topRight'
                    });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-70', 'cursor-wait');
                });
        }
    </script>
@endsection
