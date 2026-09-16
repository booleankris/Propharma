@extends('layouts.app')

@section('title', 'Riwayat Harga Beli')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* ── DataTable layout ── */
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 14px !important;
        }

        .dataTables_filter {
            display: none !important; /* using custom search bar */
        }

        .dataTables_length select {
            padding: 5px 26px 5px 12px !important;
            border-radius: 8px !important;
            border: 1px solid #cbd5e1 !important;
            font-size: 13px !important;
        }

        #buyPriceTable {
            border-collapse: separate;
            border-spacing: 0;
            width: 100% !important;
        }

        #buyPriceTable thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 12px 14px !important;
            white-space: nowrap;
        }

        #buyPriceTable tbody td {
            padding: 12px 14px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #buyPriceTable tbody tr:hover {
            background-color: #f8fafc !important;
        }

        /* ── Filter Input Styling ── */
        .filter-control {
            width: 100%;
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 8px 12px;
            font-size: 13px;
            color: #1e293b;
            outline: none;
            transition: all 0.2s;
        }

        .filter-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .filter-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 4px;
            display: block;
        }

        /* ── Price tags ── */
        .price-invoice {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 800;
            color: #1d4ed8;
            font-size: 14px;
        }

        .price-master {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 py-3">
        <div class="section-body space-y-4">

            {{-- ─── Page Header ─── --}}
            <div class="flex flex-col gap-3 p-5 bg-white border border-slate-200/80 rounded-2xl shadow-sm md:flex-row md:items-center md:justify-between">
                <div class="flex items-center gap-3.5">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 shrink-0 text-emerald-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-bold text-slate-800 leading-tight">Riwayat Harga Beli (Obat Masuk Stok)</h2>
                            <span class="px-2.5 py-0.5 text-[11px] font-bold uppercase rounded-full bg-emerald-100 text-emerald-800 tracking-wide">
                                Stock In
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Daftar obat yang diterima masuk stok untuk pengecekan perbandingan harga faktur & master harga.</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" id="btnExportExcel" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition duration-150 shadow-sm shadow-emerald-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                            <path d="M8 11h8v7h-8z" />
                            <path d="M8 15h8" />
                            <path d="M11 11v7" />
                        </svg>
                        Export Excel
                    </button>
                    <a href="{{ route('receiving.history') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold transition">
                        <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Riwayat Perubahan Harga
                    </a>
                </div>
            </div>

            {{-- ─── Simple Info Chips ─── --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0 font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Total Item Diterima</div>
                        <div class="text-lg font-bold text-slate-800" id="chipTotal">-</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-red-50 text-red-600 flex items-center justify-center shrink-0 font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Kenaikan Harga</div>
                        <div class="text-lg font-bold text-red-600" id="chipNaik">-</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Penurunan Harga</div>
                        <div class="text-lg font-bold text-emerald-600" id="chipTurun">-</div>
                    </div>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-sm flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center shrink-0 font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
                        </svg>
                    </div>
                    <div>
                        <div class="text-[11px] font-medium text-slate-500">Harga Sama / Tetap</div>
                        <div class="text-lg font-bold text-slate-700" id="chipSama">-</div>
                    </div>
                </div>
            </div>

            {{-- ─── Filter Panel ─── --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">

                    {{-- Cari Obat --}}
                    <div>
                        <label class="filter-label" for="searchMedicine">Cari Obat</label>
                        <input type="text" id="searchMedicine" class="filter-control" placeholder="Nama atau kode obat..." autocomplete="off">
                    </div>

                    {{-- No Faktur / Terima --}}
                    <div>
                        <label class="filter-label" for="searchInvoice">No. Faktur / Terima</label>
                        <input type="text" id="searchInvoice" class="filter-control" placeholder="No. faktur / terima..." autocomplete="off">
                    </div>

                    {{-- PBF / Supplier --}}
                    <div>
                        <label class="filter-label" for="filterCreditor">PBF / Distributor</label>
                        <input type="text" id="filterCreditor" class="filter-control" placeholder="Nama PBF / supplier..." autocomplete="off">
                    </div>

                    {{-- Status Selisih Harga --}}
                    <div>
                        <label class="filter-label" for="filterPriceDiff">Status Harga</label>
                        <select id="filterPriceDiff" class="filter-control">
                            <option value="">Semua Status</option>
                            <option value="naik">⚠️ Hanya Harga Naik</option>
                            <option value="turun">📉 Hanya Harga Turun</option>
                            <option value="beda">⚖️ Selisih Harga (Naik/Turun)</option>
                            <option value="sama">➖ Harga Sama</option>
                        </select>
                    </div>

                    {{-- Rentang Tanggal --}}
                    <div>
                        <label class="filter-label" for="dateRange">Rentang Tanggal</label>
                        <input type="text" id="dateRange" class="filter-control" placeholder="Pilih tanggal..." autocomplete="off" readonly>
                    </div>

                    {{-- Action Reset --}}
                    <div class="flex items-end">
                        <button type="button" id="btnReset" class="w-full py-2 px-3 rounded-xl border border-slate-300 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition flex items-center justify-center gap-1.5 h-[38px]">
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Reset Filter
                        </button>
                    </div>

                </div>
            </div>

            {{-- ─── Export Progress Banner ─── --}}
            <div id="exportProgressContainer" class="hidden bg-white p-4 rounded-2xl border border-emerald-200 shadow-sm transition-all duration-300">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2.5">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        </span>
                        <span class="text-xs font-bold text-slate-800" id="exportProgressTitle">Sedang Memproses Export Excel...</span>
                    </div>
                    <span id="exportProgressPercent" class="text-xs font-mono font-bold text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200">0%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden border border-slate-200">
                    <div id="exportProgressBar" class="h-2.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-600 rounded-full transition-all duration-300" style="width: 0%;"></div>
                </div>
                <p id="exportProgressMessage" class="text-[11px] text-slate-500 mt-1.5 font-medium flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Menyiapkan dan memfilter data riwayat harga beli...</span>
                </p>
            </div>

            {{-- ─── Main Table Card ─── --}}
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-4 overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="buyPriceTable" class="w-full text-left">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 90px;">Tgl Terima</th>
                                <th style="width: 140px;">No. Terima & Faktur</th>
                                <th>Nama Obat & Satuan</th>
                                <th style="width: 180px;">PBF / Distributor</th>
                                <th style="width: 130px;">Harga Beli (Faktur)</th>
                                <th style="width: 150px;">Master Saat Ini</th>
                                <th style="width: 220px; text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let startDate = '';
        let endDate = '';
        let searchTimer = null;
        let buyPriceTable = null;

        const canUpdateMaster = {{ $canUpdateMaster ? 'true' : 'false' }};

        // Format Currency Helper (supports under 1000 and decimals)
        function formatRupiah(number) {
            if (number === null || number === undefined || isNaN(number)) return 'Rp 0';
            const num = parseFloat(number);
            const hasDecimal = (Math.abs(num - Math.round(num)) > 0.001);
            return new Intl.NumberFormat('id-ID', {
                style: 'currency',
                currency: 'IDR',
                minimumFractionDigits: hasDecimal ? 2 : 0,
                maximumFractionDigits: 2
            }).format(num);
        }

        // Update statistics cards from table view
        function updateSummaryChips() {
            if (!buyPriceTable) return;
            const info = buyPriceTable.page.info();
            document.getElementById('chipTotal').textContent = info.recordsTotal.toLocaleString('id-ID');

            let countNaik = 0;
            let countTurun = 0;
            let countSama = 0;

            buyPriceTable.rows({ page: 'current' }).data().each(function(row) {
                const uPrice = (row.unit_price_num !== undefined) ? row.unit_price_num : (row.raw_price_num || 0);
                const mPrice = row.master_price_num || 0;
                const diff = uPrice - mPrice;
                if (Math.abs(diff) <= 0.5) {
                    countSama++;
                } else if (diff > 0) {
                    countNaik++;
                } else {
                    countTurun++;
                }
            });

            document.getElementById('chipNaik').textContent = countNaik.toLocaleString('id-ID');
            document.getElementById('chipTurun').textContent = countTurun.toLocaleString('id-ID');
            document.getElementById('chipSama').textContent = countSama.toLocaleString('id-ID');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // ── Date Range Flatpickr ──
            flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: false,
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        endDate = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    } else {
                        startDate = '';
                        endDate = '';
                    }
                    buyPriceTable.ajax.reload(null, false);
                }
            });

            // ── Initialize Server-side DataTable ──
            buyPriceTable = $('#buyPriceTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.getBuyPriceHistory') }}",
                    data: function(d) {
                        d.search_medicine = $('#searchMedicine').val().trim();
                        d.search_invoice = $('#searchInvoice').val().trim();
                        d.creditor = $('#filterCreditor').val().trim();
                        d.price_diff = $('#filterPriceDiff').val();
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [
                    {
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '35px'
                    },
                    {
                        data: 'date',
                        orderable: false,
                        render: function(data) {
                            return `<div class="text-xs font-semibold text-slate-700">${data || '-'}</div>`;
                        }
                    },
                    {
                        data: 'no_terima',
                        orderable: false,
                        render: function(data, type, row) {
                            const noTerima = data || '-';
                            const inv = row.invoice_number ? `<span class="text-[11px] text-slate-500 font-mono block">Fak: ${row.invoice_number}</span>` : '';
                            return `<div>
                                <span class="text-xs font-bold text-slate-800 block">${noTerima}</span>
                                ${inv}
                            </div>`;
                        }
                    },
                    {
                        data: 'medicine_name',
                        orderable: false,
                        render: function(data, type, row) {
                            const name = data || '-';
                            const code = row.medicine_code ? `<span class="text-[10px] font-mono text-slate-400 mr-1.5">[${row.medicine_code}]</span>` : '';
                            return `<div>
                                <div class="text-sm font-bold text-slate-900 leading-snug">${name}</div>
                                <div class="mt-0.5 flex items-center flex-wrap gap-1.5">${code}${row.unit || ''}</div>
                            </div>`;
                        }
                    },
                    {
                        data: 'creditor_name',
                        orderable: false,
                        render: function(data) {
                            return `<div class="text-xs font-medium text-slate-700 max-w-[200px] truncate" title="${data || '-'}">${data || '-'}</div>`;
                        }
                    },
                    {
                        data: 'raw_price_fmt',
                        orderable: false
                    },
                    {
                        data: 'master_price_fmt',
                        orderable: false,
                        render: function(data, type, row) {
                            const diffBadge = row.price_diff ? `<div class="mt-1">${row.price_diff}</div>` : '';
                            return `<div>${data || ''}${diffBadge}</div>`;
                        }
                    },
                    {
                        data: 'actions',
                        orderable: false,
                        searchable: false,
                        className: 'text-right'
                    }
                ],
                order: [[1, 'desc']],
                pageLength: 25,
                language: {
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    emptyTable: 'Tidak ada obat diterima yang ditemukan',
                    paginate: {
                        previous: '‹',
                        next: '›',
                    }
                },
                drawCallback: function() {
                    updateSummaryChips();
                }
            });

            // ── Debounced inputs ──
            const reloadTableDebounced = () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    buyPriceTable.ajax.reload(null, false);
                }, 350);
            };

            $('#searchMedicine, #searchInvoice, #filterCreditor').on('input', reloadTableDebounced);
            $('#filterPriceDiff').on('change', () => buyPriceTable.ajax.reload(null, false));

            // ── Reset filter button ──
            $('#btnReset').on('click', function() {
                $('#searchMedicine').val('');
                $('#searchInvoice').val('');
                $('#filterCreditor').val('');
                $('#filterPriceDiff').val('');
                $('#dateRange').val('');
                startDate = '';
                endDate = '';
                document.getElementById('dateRange')._flatpickr?.clear();
                buyPriceTable.ajax.reload(null, false);
            });

            // ── Export Excel Button with Async Job & Live Progress Loading ──
            let exportPollTimer = null;

            function resetExportButton() {
                const btn = document.getElementById('btnExportExcel');
                if (!btn) return;
                btn.innerHTML = `
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                        <path d="M8 11h8v7h-8z" />
                        <path d="M8 15h8" />
                        <path d="M11 11v7" />
                    </svg>
                    Export Excel
                `;
                btn.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
            }

            function pollExportStatus(jobId) {
                const container = document.getElementById('exportProgressContainer');
                const bar = document.getElementById('exportProgressBar');
                const percentText = document.getElementById('exportProgressPercent');
                const msgText = document.getElementById('exportProgressMessage');

                exportPollTimer = setInterval(async () => {
                    try {
                        const res = await fetch(`/receiving/export-buy-price-history/status/${jobId}`);
                        if (!res.ok) throw new Error('Network error');
                        const data = await res.json();

                        const progress = data.progress || 0;
                        if (bar) bar.style.width = progress + '%';
                        if (percentText) percentText.textContent = progress + '%';

                        if (progress <= 30) {
                            if (msgText) msgText.innerHTML = `<svg class="w-3.5 h-3.5 text-slate-400 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Sedang mengambil dan memfilter data penerimaan...</span>`;
                        } else if (progress <= 70) {
                            if (msgText) msgText.innerHTML = `<svg class="w-3.5 h-3.5 text-slate-400 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Menghitung selisih harga & menyusun baris tabel...</span>`;
                        } else if (progress < 100) {
                            if (msgText) msgText.innerHTML = `<svg class="w-3.5 h-3.5 text-slate-400 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Menyimpan dan mengemas file spreadsheet Excel...</span>`;
                        }

                        if (data.status === 'completed' || data.status === 'finished') {
                            clearInterval(exportPollTimer);
                            if (bar) bar.style.width = '100%';
                            if (percentText) percentText.textContent = '100%';
                            if (msgText) msgText.innerHTML = `<span class="text-emerald-600 font-bold flex items-center gap-1"><svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> File Excel siap! Memulai unduhan otomatis...</span>`;

                            iziToast.success({
                                title: 'Export Berhasil',
                                message: 'File Excel riwayat harga beli berhasil dibuat dan sedang diunduh.',
                                position: 'topRight',
                                timeout: 4000
                            });

                            resetExportButton();

                            // Trigger download
                            if (data.file) {
                                window.location.href = data.file;
                            }

                            setTimeout(() => {
                                $(container).slideUp(300);
                            }, 3000);
                        } else if (data.status === 'failed') {
                            clearInterval(exportPollTimer);
                            iziToast.error({
                                title: 'Gagal Export',
                                message: 'Terjadi kesalahan saat membuat file Excel.',
                                position: 'topRight'
                            });
                            resetExportButton();
                            setTimeout(() => {
                                $(container).slideUp(300);
                            }, 3000);
                        }
                    } catch (err) {
                        console.error(err);
                    }
                }, 1500);
            }

            $('#btnExportExcel').on('click', async function(e) {
                e.preventDefault();

                const btn = this;
                const container = document.getElementById('exportProgressContainer');
                const bar = document.getElementById('exportProgressBar');
                const percentText = document.getElementById('exportProgressPercent');
                const msgText = document.getElementById('exportProgressMessage');

                // Button loading state
                btn.innerHTML = `
                    <svg class="animate-spin -ml-0.5 mr-1.5 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Memproses...</span>
                `;
                btn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');

                // Reset progress UI
                if (bar) bar.style.width = '5%';
                if (percentText) percentText.textContent = '5%';
                if (msgText) msgText.innerHTML = `<svg class="w-3.5 h-3.5 text-slate-400 animate-spin shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <span>Menghubungi server dan memulai antrean...</span>`;
                $(container).removeClass('hidden').hide().slideDown(250);

                const params = new URLSearchParams();
                const searchMedicine = $('#searchMedicine').val().trim();
                const searchInvoice = $('#searchInvoice').val().trim();
                const creditor = $('#filterCreditor').val().trim();
                const priceDiff = $('#filterPriceDiff').val();

                if (searchMedicine) params.append('search_medicine', searchMedicine);
                if (searchInvoice) params.append('search_invoice', searchInvoice);
                if (creditor) params.append('creditor', creditor);
                if (priceDiff) params.append('price_diff', priceDiff);
                if (startDate) params.append('start_date', startDate);
                if (endDate) params.append('end_date', endDate);
                params.append('async', '1');

                try {
                    const response = await fetch(`{{ route('receiving.exportBuyPriceHistory') }}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (!response.ok) throw new Error('Gagal memulai proses export');
                    const resData = await response.json();

                    if (resData.job_id) {
                        iziToast.info({
                            title: 'Export Dimulai',
                            message: 'Data sedang disiapkan di background...',
                            position: 'topRight',
                            timeout: 2500
                        });
                        pollExportStatus(resData.job_id);
                    } else {
                        throw new Error('ID antrean tidak ditemukan');
                    }
                } catch (err) {
                    iziToast.error({
                        title: 'Gagal',
                        message: 'Terjadi kesalahan saat memulai proses export Excel.',
                        position: 'topRight'
                    });
                    resetExportButton();
                    setTimeout(() => {
                        $(container).slideUp(300);
                    }, 2500);
                }
            });
        });

        // ── Action: Update Master Harga ──
        function confirmUpdateMaster(medicineId, receivingItemId, unitPrice, masterUnitPrice, medicineName, isPack, content, rawReceived, packaging, unit) {
            if (!canUpdateMaster) {
                Swal.fire({
                    icon: 'error',
                    title: 'Akses Dibatasi',
                    text: 'Hanya General Manager atau Operator yang dapat memperbarui harga master obat.',
                });
                return;
            }

            const isKemasanUtuh = (isPack == 1 && content > 1);
            const diff = unitPrice - masterUnitPrice;
            let statusText = '';
            if (Math.abs(diff) <= 0.5) {
                statusText = '<span style="color: #64748b; font-weight: 700;">(Harga Sama)</span>';
            } else if (diff > 0) {
                const pct = masterUnitPrice > 0 ? ((diff / masterUnitPrice) * 100).toFixed(1) : 0;
                statusText = `<span style="color: #dc2626; font-weight: 700;">(▲ Naik +${formatRupiah(diff)} / +${pct}%)</span>`;
            } else {
                const pct = masterUnitPrice > 0 ? ((Math.abs(diff) / masterUnitPrice) * 100).toFixed(1) : 0;
                statusText = `<span style="color: #16a34a; font-weight: 700;">(▼ Turun -${formatRupiah(Math.abs(diff))} / -${pct}%)</span>`;
            }

            const newNetPriceEstimated = Math.round(unitPrice * 1.11);
            const masterBoxPrice = Math.round(masterUnitPrice * content);

            let kemasanInfoHtml = '';
            let inputFieldsHtml = '';

            if (isKemasanUtuh) {
                kemasanInfoHtml = `
                    <div style="display: inline-block; background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 6px; margin-top: 4px;">
                        📦 Kemasan Utuh: 1 ${packaging} = ${content} ${unit}
                    </div>
                `;

                inputFieldsHtml = `
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; margin-top: 10px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 6px;">
                            Pembaruan Master Harga (Sinkronisasi Otomatis):
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                            <div>
                                <label style="display: block; font-size: 11px; font-weight: 700; color: #1e40af; margin-bottom: 2px;">
                                    HNA Satuan (/${unit}) <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="number" id="swalUnitPrice" value="${unitPrice}" class="swal2-input" style="width: 100%; margin: 0; height: 38px; font-size: 13px; font-weight: 700; border-radius: 6px; border: 1px solid #93c5fd; padding: 4px 8px;" min="0" step="any">
                            </div>
                            <div>
                                <label style="display: block; font-size: 11px; font-weight: 700; color: #475569; margin-bottom: 2px;">
                                    Ekuivalen (/${packaging})
                                </label>
                                <input type="number" id="swalBoxPrice" value="${rawReceived}" class="swal2-input" style="width: 100%; margin: 0; height: 38px; font-size: 13px; font-weight: 700; border-radius: 6px; border: 1px solid #cbd5e1; padding: 4px 8px;" min="0" step="any">
                            </div>
                        </div>
                        <div style="font-size: 10px; color: #64748b; margin-top: 4px;">
                            * Master harga obat Propharma disimpan per <strong>${unit}</strong> (satuan terkecil). Mengubah salah satu nilai di atas akan otomatis mengonversi nilai lainnya.
                        </div>
                    </div>
                `;
            } else {
                inputFieldsHtml = `
                    <div style="margin-top: 10px;">
                        <label style="display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #475569; margin-bottom: 4px;">
                            Konfirmasi HNA Master Baru (Rp / ${unit}):
                        </label>
                        <input type="number" id="swalUnitPrice" value="${unitPrice}" class="swal2-input" style="width: 100%; margin: 0; height: 40px; font-size: 14px; font-weight: 700; border-radius: 8px; border: 1px solid #cbd5e1; padding: 4px 10px;" min="0" step="any">
                    </div>
                `;
            }

            Swal.fire({
                title: 'Update Master Harga Obat?',
                html: `
                    <div style="text-align: left; font-size: 13px; line-height: 1.5; color: #334155;">
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px 14px; margin-bottom: 12px;">
                            <div style="font-size: 11px; text-transform: uppercase; color: #64748b; font-weight: 700;">Obat Terpilih</div>
                            <div style="font-size: 14px; font-weight: 800; color: #0f172a;">${medicineName}</div>
                            ${kemasanInfoHtml}
                        </div>

                        <table style="width: 100%; font-size: 12.5px; margin-bottom: 8px; border-collapse: collapse;">
                            ${isKemasanUtuh ? `
                            <tr>
                                <td style="padding: 3px 0; color: #64748b;">Harga Beli Faktur (${packaging}):</td>
                                <td style="padding: 3px 0; font-weight: 800; color: #1d4ed8; text-align: right;">${formatRupiah(rawReceived)} / ${packaging}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <td style="padding: 3px 0; color: #64748b;">HNA Faktur (per ${unit}):</td>
                                <td style="padding: 3px 0; font-weight: 800; color: #1d4ed8; text-align: right;">${formatRupiah(unitPrice)} / ${unit}</td>
                            </tr>
                            <tr>
                                <td style="padding: 3px 0; color: #64748b;">Master Saat Ini (per ${unit}):</td>
                                <td style="padding: 3px 0; font-weight: 700; text-align: right;">${formatRupiah(masterUnitPrice)} / ${unit}</td>
                            </tr>
                            ${isKemasanUtuh ? `
                            <tr>
                                <td style="padding: 3px 0; color: #94a3b8; font-size: 11px;">(Ekuivalen Master Lama per ${packaging}):</td>
                                <td style="padding: 3px 0; font-weight: 600; color: #94a3b8; font-size: 11px; text-align: right;">${formatRupiah(masterBoxPrice)} / ${packaging}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <td style="padding: 3px 0; color: #64748b;">Status Selisih:</td>
                                <td style="padding: 3px 0; text-align: right;">${statusText}</td>
                            </tr>
                            <tr style="border-top: 1px dashed #cbd5e1;">
                                <td style="padding: 5px 0 2px; color: #64748b; font-size: 11.5px;">Estimasi Netto Baru (+PPN 11%):</td>
                                <td style="padding: 5px 0 2px; font-weight: 700; color: #047857; text-align: right; font-size: 11.5px;">${formatRupiah(newNetPriceEstimated)} / ${unit}</td>
                            </tr>
                        </table>

                        ${inputFieldsHtml}
                    </div>
                `,
                didOpen: () => {
                    const uInput = document.getElementById('swalUnitPrice');
                    const bInput = document.getElementById('swalBoxPrice');
                    if (uInput && bInput && content > 1) {
                        uInput.addEventListener('input', () => {
                            const uVal = parseFloat(uInput.value) || 0;
                            bInput.value = Math.round(uVal * content);
                        });
                        bInput.addEventListener('input', () => {
                            const bVal = parseFloat(bInput.value) || 0;
                            uInput.value = content > 0 ? (bVal / content).toFixed(2) : bVal;
                        });
                    }
                },
                showCancelButton: true,
                confirmButtonText: '<svg style="width: 16px; height: 16px; display: inline-block; vertical-align: -2px; margin-right: 4px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> Ya, Update Master Harga',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#059669',
                cancelButtonColor: '#94a3b8',
                customClass: {
                    confirmButton: 'rounded-xl font-bold text-sm px-4 py-2.5 shadow-sm',
                    cancelButton: 'rounded-xl font-bold text-sm px-4 py-2.5'
                },
                preConfirm: () => {
                    const enteredPrice = document.getElementById('swalUnitPrice').value;
                    if (!enteredPrice || isNaN(enteredPrice) || parseFloat(enteredPrice) < 0) {
                        Swal.showValidationMessage('Harap masukkan nominal harga yang valid (>= 0)');
                        return false;
                    }
                    return parseFloat(enteredPrice);
                }
            }).then((result) => {
                if (result.isConfirmed && result.value !== undefined) {
                    const finalUnitPrice = result.value;

                    // Show loader
                    Swal.fire({
                        title: 'Memperbarui Harga...',
                        text: 'Mohon tunggu sebentar',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    axios.post("{{ route('receiving.updateMasterPrice') }}", {
                        medicine_id: medicineId,
                        receiving_item_id: receivingItemId,
                        new_raw_price: finalUnitPrice
                    }, {
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        }
                    })
                    .then(response => {
                        if (response.data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil Diperbarui!',
                                text: response.data.message,
                                timer: 2200,
                                showConfirmButton: false
                            });

                            if (typeof iziToast !== 'undefined') {
                                iziToast.success({
                                    title: 'Sukses',
                                    message: response.data.message,
                                    position: 'topRight'
                                });
                            }

                            // Reload table without page reset
                            buyPriceTable.ajax.reload(null, false);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: response.data.message || 'Gagal memperbarui harga master obat.',
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Update master error:', error);
                        const msg = error.response?.data?.message || 'Terjadi kesalahan sistem saat memperbarui harga.';
                        Swal.fire({
                            icon: 'error',
                            title: 'Terjadi Kesalahan',
                            text: msg,
                        });
                    });
                }
            });
        }
    </script>
@endsection
