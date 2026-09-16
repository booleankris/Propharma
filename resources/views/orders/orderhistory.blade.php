@extends('layouts.app')

@section('title', 'Data Pembelian')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* ── DataTable styling ── */
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 14px !important;
        }

        .dataTables_filter {
            display: none !important;
            /* using custom Cari Faktur input */
        }

        .dataTables_length select {
            padding: 5px 26px 5px 10px !important;
            border-radius: 8px !important;
            border: 1px solid #d1d5db !important;
            font-size: 13px !important;
        }

        #orderHistoryTable thead th {
            background-color: #f8fafc !important;
            color: #334155 !important;
            font-weight: 700 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 12px 14px !important;
            white-space: nowrap;
        }

        #orderHistoryTable tbody td {
            padding: 12px 14px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #orderHistoryTable tbody tr {
            cursor: pointer;
            transition: background-color 0.15s ease;
        }

        #orderHistoryTable tbody tr:hover {
            background-color: #f8fafc !important;
        }

        #orderHistoryTable tbody tr.selected {
            background-color: #e0f2fe !important;
            border-left: 3px solid #0284c7;
        }

        /* ── Stat cards ── */
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.06);
            border: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-card .label {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .stat-card .val {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 2px;
        }

        /* ── Search Groupbox (like old desktop app) ── */
        .search-groupbox {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 18px 22px;
            position: relative;
        }

        .search-legend {
            font-size: 13px;
            font-weight: 700;
            color: #1e3a8a;
            letter-spacing: 0.02em;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .quick-pill {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .quick-pill:hover,
        .quick-pill.active {
            background: #1e40af;
            color: #ffffff;
            border-color: #1e40af;
        }

        .badge-faktur {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            color: #1d4ed8;
            background: #eff6ff;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #dbeafe;
        }

        .badge-terima {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 600;
            color: #0f766e;
            background: #f0fdfa;
            padding: 3px 8px;
            border-radius: 6px;
            border: 1px solid #ccfbf1;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 pb-12">
        <div class="section-body space-y-4">

            {{-- ─── Header ─── --}}
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3.5">
                    <div
                        class="w-12 h-12 rounded-2xl bg-blue-500 text-white flex items-center justify-center shadow-md shadow-blue-500/20 flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <rect x="1" y="3" width="15" height="13" rx="2" />
                            <path d="M16 8h4l4 5v4h-8V8z" />
                            <circle cx="5.5" cy="18.5" r="2.5" />
                            <circle cx="18.5" cy="18.5" r="2.5" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Data Pembelian</h1>
                        <p class="text-xs text-slate-500 mt-0.5">Menampilkan riwayat faktur penerimaan pembelian per faktur
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('receiving.index') }}"
                        class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 text-xs font-semibold shadow-sm transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>Input Penerimaan</span>
                    </a>
                    <button type="button" onclick="printSelectedRow()" id="btnTopPrint"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold shadow-md shadow-amber-500/20 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M6 9V3H18V9" />
                            <rect x="6" y="14" width="12" height="7" rx="1" />
                            <path d="M6 18H5A2 2 0 0 1 3 16V11A2 2 0 0 1 5 9H19A2 2 0 0 1 21 11V16A2 2 0 0 1 19 18H18" />
                        </svg>
                        <span>Cetak Faktur</span>
                    </button>
                </div>
            </div>

            {{-- ─── Summary Cards ─── --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <div class="stat-card">
                    <div class="icon bg-blue-50 text-blue-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                            <polyline points="14 2 14 8 20 8" />
                            <line x1="16" y1="13" x2="8" y2="13" />
                            <line x1="16" y1="17" x2="8" y2="17" />
                            <polyline points="10 9 9 9 8 9" />
                        </svg>
                    </div>
                    <div>
                        <div class="label">Total Faktur</div>
                        <div class="val" id="statTotalInvoices">0</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="icon bg-emerald-50 text-emerald-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <line x1="12" y1="1" x2="12" y2="23" />
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                        </svg>
                    </div>
                    <div>
                        <div class="label">Total Pembelian (Tampil)</div>
                        <div class="val text-emerald-700" id="statTotalAmount">Rp 0</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="icon bg-purple-50 text-purple-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                            <circle cx="8.5" cy="7" r="4" />
                            <line x1="20" y1="8" x2="20" y2="14" />
                            <line x1="23" y1="11" x2="17" y2="11" />
                        </svg>
                    </div>
                    <div>
                        <div class="label">PBF / Kreditur (Tampil)</div>
                        <div class="val text-purple-700" id="statTotalCreditors">0</div>
                    </div>
                </div>
            </div>

            {{-- ─── Cari Faktur (Groupbox ala Aplikasi Lama) ─── --}}
            <div class="search-groupbox shadow-sm">
                <div class="search-legend">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                        stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                    <span>Cari Faktur</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-end">
                    {{-- Dropdown Kolom Pencarian --}}
                    <div class="lg:col-span-3">
                        <label for="searchField" class="block text-xs font-semibold text-slate-600 mb-1.5">Kategori
                            Pencarian</label>
                        <select id="searchField"
                            class="w-full h-11 px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <option selected value="all">Semua Kolom</option>
                            <option value="invoice_date">Tgl Faktur</option>
                            <option value="invoice_number">No Faktur</option>
                            <option value="creditor">Kreditur</option>
                            <option value="receiving_code">No Terima</option>
                            <option value="receive_date">Tgl Terima</option>
                        </select>
                    </div>

                    {{-- Text Pencarian --}}
                    <div class="lg:col-span-5">
                        <label for="searchInput" class="block text-xs font-semibold text-slate-600 mb-1.5">Kata
                            Kunci</label>
                        <div class="relative">
                            <input type="text" id="searchInput" autocomplete="off"
                                placeholder="Ketik tanggal (contoh: 14 9), no faktur, atau kreditur..."
                                class="w-full h-11 pl-4 pr-10 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
                            <button type="button" id="btnClearSearch"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 hidden">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18" />
                                    <line x1="6" y1="6" x2="18" y2="18" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Rentang Tanggal Faktur --}}
                    <div class="lg:col-span-4">
                        <label for="dateRange" class="block text-xs font-semibold text-slate-600 mb-1.5">Rentang Tanggal
                            Faktur</label>
                        <div class="flex items-center gap-2">
                            <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..." readonly
                                class="flex-1 h-11 px-3.5 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 cursor-pointer">
                            <button type="button" onclick="resetFilter()"
                                class="h-11 px-3.5 rounded-xl border border-slate-300 bg-white hover:bg-slate-100 text-slate-600 text-xs font-semibold transition"
                                title="Reset Filter">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Quick date range pills --}}
                <div class="flex flex-wrap items-center gap-1.5 mt-3 pt-3 border-t border-slate-100">
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mr-1">Filter
                        Cepat:</span>
                    <button type="button" class="quick-pill" onclick="setQuickDate('today', this)">Hari Ini</button>
                    <button type="button" class="quick-pill" onclick="setQuickDate('7_days', this)">7 Hari
                        Terakhir</button>
                    <button type="button" class="quick-pill" onclick="setQuickDate('this_month', this)">Bulan
                        Ini</button>
                    <button type="button" class="quick-pill active" onclick="setQuickDate('all', this)">Semua
                        Data</button>
                </div>
            </div>

            {{-- ─── DataTable Data Pembelian ─── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <div class="overflow-x-auto">
                    <table id="orderHistoryTable" class="w-full">
                        <thead>
                            <tr>
                                <th style="width: 36px;">#</th>
                                <th style="width: 105px;">Tgl Terima</th>
                                <th>No Terima</th>
                                <th style="width: 105px;">Tgl Faktur</th>
                                <th>No Faktur</th>
                                <th>Kreditur</th>
                                <th style="text-align: right;">Jumlah</th>
                                <th style="width: 175px; text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-[13px]"></tbody>
                    </table>
                </div>

                {{-- Bottom Action Row ala Desktop App --}}
                <div
                    class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4 pt-4 border-t border-slate-100">
                    <div class="text-xs text-slate-500 font-medium">
                        * Klik salah satu baris untuk memilih faktur, atau klik ganda untuk langsung mencetak.
                    </div>
                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <button type="button" onclick="viewSelectedRincian()"
                            class="h-10 px-5 rounded-xl border border-blue-200 bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 text-xs font-bold shadow-xs active:scale-[0.98] transition flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            <span>Rincian</span>
                        </button>
                        <button type="button" onclick="printSelectedRow()"
                            class="h-10 px-6 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white text-xs font-bold shadow-md shadow-amber-500/20 active:scale-[0.98] transition flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <path d="M6 9V3H18V9" />
                                <rect x="6" y="14" width="12" height="7" rx="1" />
                                <path
                                    d="M6 18H5A2 2 0 0 1 3 16V11A2 2 0 0 1 5 9H19A2 2 0 0 1 21 11V16A2 2 0 0 1 19 18H18" />
                            </svg>
                            <span>Cetak</span>
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/id.js"></script>

    <script>
        let orderHistoryTable = null;
        let selectedRowData = null;
        let startDate = '';
        let endDate = '';
        let searchTimer = null;
        let datePickerInstance = null;

        document.addEventListener('DOMContentLoaded', function() {
            // ── Initialize Flatpickr ──
            datePickerInstance = flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                locale: 'id',
                allowInput: false,
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        endDate = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    } else if (selectedDates.length === 0) {
                        startDate = '';
                        endDate = '';
                    }
                    // Reset quick pills active
                    document.querySelectorAll('.quick-pill').forEach(p => p.classList.remove('active'));
                    orderHistoryTable.ajax.reload();
                }
            });

            // ── Initialize DataTable ──
            orderHistoryTable = $('#orderHistoryTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.getorderhistory') }}",
                    data: function(d) {
                        d.search_field = document.getElementById('searchField').value;
                        d.search = document.getElementById('searchInput').value.trim();
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center font-medium text-slate-400'
                    },
                    {
                        data: 'receive_date',
                        className: 'text-center font-medium text-slate-600'
                    },
                    {
                        data: 'receive_code',
                        render: function(data) {
                            return `<span class="badge-terima">${data || '-'}</span>`;
                        }
                    },
                    {
                        data: 'invoice_date',
                        className: 'text-center font-medium text-slate-600'
                    },
                    {
                        data: 'invoice_number',
                        render: function(data) {
                            return `<span class="badge-faktur">${data || '-'}</span>`;
                        }
                    },
                    {
                        data: 'creditor',
                        className: 'font-semibold text-slate-800'
                    },
                    {
                        data: 'total_formatted',
                        className: 'text-end font-bold text-slate-900',
                        render: function(data) {
                            return `<span class="text-[13px] font-bold tracking-tight">${data || '0'}</span>`;
                        }
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    }
                ],
                order: [
                    [3, 'desc']
                ], // sort by invoice_date by default
                pageLength: 25,
                language: {
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ faktur',
                    infoEmpty: 'Tidak ada data faktur',
                    emptyTable: 'Tidak ada data pembelian ditemukan',
                    zeroRecords: 'Tidak ada faktur yang cocok dengan pencarian',
                    paginate: {
                        first: '«',
                        last: '»',
                        next: '›',
                        previous: '‹'
                    }
                },
                drawCallback: function() {
                    updateSummaryStats();
                    selectedRowData = null; // reset row selection on page/draw
                }
            });

            // ── Row selection & double click ──
            $('#orderHistoryTable tbody').on('click', 'tr', function(e) {
                // If clicked directly on the button / link, let default action proceed
                if ($(e.target).closest('a, button').length) return;

                const row = orderHistoryTable.row(this);
                if (!row.data()) return;

                if ($(this).hasClass('selected')) {
                    $(this).removeClass('selected');
                    selectedRowData = null;
                } else {
                    $('#orderHistoryTable tbody tr.selected').removeClass('selected');
                    $(this).addClass('selected');
                    selectedRowData = row.data();
                }
            });

            $('#orderHistoryTable tbody').on('dblclick', 'tr', function(e) {
                if ($(e.target).closest('a, button').length) return;
                const row = orderHistoryTable.row(this);
                if (row.data() && row.data().id) {
                    window.open(`{{ url('invoice/print') }}/${row.data().id}`, '_blank');
                }
            });

            // ── Debounced search input ──
            const searchInput = document.getElementById('searchInput');
            const btnClearSearch = document.getElementById('btnClearSearch');

            searchInput.addEventListener('input', function() {
                const val = this.value.trim();
                btnClearSearch.classList.toggle('hidden', val.length === 0);

                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    orderHistoryTable.ajax.reload();
                }, 300);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    clearTimeout(searchTimer);
                    orderHistoryTable.ajax.reload();
                }
            });

            btnClearSearch.addEventListener('click', function() {
                searchInput.value = '';
                this.classList.add('hidden');
                orderHistoryTable.ajax.reload();
                searchInput.focus();
            });

            // ── Search category dropdown change ──
            document.getElementById('searchField').addEventListener('change', function() {
                orderHistoryTable.ajax.reload();
            });
        });

        // ── Quick date filters ──
        function setQuickDate(type, btn) {
            document.querySelectorAll('.quick-pill').forEach(p => p.classList.remove('active'));
            if (btn) btn.classList.add('active');

            const now = new Date();
            const fmt = d =>
                `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

            if (type === 'today') {
                startDate = fmt(now);
                endDate = fmt(now);
                if (datePickerInstance) datePickerInstance.setDate([now, now]);
            } else if (type === '7_days') {
                const past = new Date(now);
                past.setDate(now.getDate() - 6);
                startDate = fmt(past);
                endDate = fmt(now);
                if (datePickerInstance) datePickerInstance.setDate([past, now]);
            } else if (type === 'this_month') {
                const startMonth = new Date(now.getFullYear(), now.getMonth(), 1);
                const endMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                startDate = fmt(startMonth);
                endDate = fmt(endMonth);
                if (datePickerInstance) datePickerInstance.setDate([startMonth, endMonth]);
            } else {
                // all
                startDate = '';
                endDate = '';
                if (datePickerInstance) datePickerInstance.clear();
            }

            orderHistoryTable.ajax.reload();
        }

        // ── Reset filter ──
        function resetFilter() {
            document.getElementById('searchField').value = 'all';
            document.getElementById('searchInput').value = '';
            document.getElementById('btnClearSearch').classList.add('hidden');
            startDate = '';
            endDate = '';
            if (datePickerInstance) datePickerInstance.clear();
            document.querySelectorAll('.quick-pill').forEach(p => p.classList.remove('active'));
            document.querySelector('.quick-pill[onclick*="all"]')?.classList.add('active');
            orderHistoryTable.ajax.reload();
        }

        // ── View Rincian selected row ──
        function viewSelectedRincian() {
            if (selectedRowData && selectedRowData.rincian_url) {
                window.location.href = selectedRowData.rincian_url;
            } else {
                iziToast.info({
                    title: 'Pilih Faktur',
                    message: 'Silakan klik salah satu baris faktur di tabel terlebih dahulu untuk melihat rincian.',
                    position: 'topRight'
                });
            }
        }

        // ── Print selected row ──
        function printSelectedRow() {
            if (selectedRowData && selectedRowData.id) {
                window.open(`{{ url('invoice/print') }}/${selectedRowData.id}`, '_blank');
            } else {
                iziToast.info({
                    title: 'Pilih Faktur',
                    message: 'Silakan klik salah satu baris faktur di tabel terlebih dahulu untuk mencetak.',
                    position: 'topRight'
                });
            }
        }

        // ── Update Summary Stats ──
        function updateSummaryStats() {
            if (!orderHistoryTable) return;

            const info = orderHistoryTable.page.info();
            document.getElementById('statTotalInvoices').textContent = (info.recordsDisplay || 0).toLocaleString('id-ID');

            let pageSum = 0;
            const creditorsSet = new Set();

            orderHistoryTable.rows({
                page: 'current'
            }).data().each(function(row) {
                if (row.total_raw) pageSum += parseFloat(row.total_raw);
                if (row.creditor) creditorsSet.add(row.creditor);
            });

            document.getElementById('statTotalAmount').textContent = 'Rp ' + Math.round(pageSum).toLocaleString('id-ID');
            document.getElementById('statTotalCreditors').textContent = creditorsSet.size.toLocaleString('id-ID');
        }
    </script>
@endsection
