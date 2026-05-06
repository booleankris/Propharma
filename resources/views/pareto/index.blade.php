@extends('layouts.app')

@section('title', 'Pareto')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
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

        .dataTables_length select {
            padding: 4px 23px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }

        #historyTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #historyTable tbody td {
            padding: 11px 10px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
        }

        #historyTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        .badge-up {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-down {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 10px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-mid {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            background: #fef9c3;
            color: #854d0e;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .filter-input {
            width: 100%;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 10px 14px;
            font-size: 13px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .filter-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        /* ── Toggle switch ── */
        .pareto-toggle {
            display: inline-flex;
            background: #dbedff;
            border-radius: 12px;
            padding: 4px;
            gap: 2px;
        }

        .pareto-toggle button {
            padding: 8px 20px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all .2s;
            color: #6b7280;
            background: transparent;
        }

        .pareto-toggle button.active-jual {
            background: #2563eb;
            color: #fff;
            box-shadow: 0 2px 8px rgba(37, 99, 235, .25);
        }

        .pareto-toggle button.active-beli {
            background: #16a34a;
            color: #fff;
            box-shadow: 0 2px 8px rgba(22, 163, 74, .25);
        }

        /* ── Header accent bar ── */
        .header-accent {
            width: 4px;
            height: 32px;
            border-radius: 4px;
            flex-shrink: 0;
            transition: background .3s;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body space-y-4">

            {{-- ─── Page header ─── --}}
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3">
                    <div class="header-accent" id="headerAccent" style="background:#2563eb"></div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800 tracking-tight" id="pageTitle">Pareto Penjualan</h1>
                        <p class="text-sm text-gray-500 mt-0.5" id="pageSubtitle">Analisis pareto berdasarkan data penjualan
                            obat</p>
                    </div>
                </div>

                {{-- Toggle --}}
                <div class="pareto-toggle">
                    <button id="btnJual" class="active-jual" onclick="switchMode('jual')">
                        <div class="flex gap-1">
                            <svg height="15px" width="15px" version="1.1" id="Layer_1"
                                xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                viewBox="0 0 486.944 486.944" xml:space="preserve" fill="#000000">
                                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                <g id="SVGRepo_iconCarrier">
                                    <g>
                                        <path style="fill:#FF9478;"
                                            d="M90.47,289.875h123.954V60H4.057l29.685,181.689C38.172,268.709,63.09,289.875,90.47,289.875z">
                                        </path>
                                        <polygon style="fill:#FF7956;"
                                            points="369.501,289.875 407.181,60 214.425,60 214.425,289.875 "></polygon>
                                        <path style="fill:#f3c55e;"
                                            d="M38.583,433.41c0,29.519,24.016,53.534,53.535,53.534V379.875 C62.599,379.875,38.583,403.891,38.583,433.41z">
                                        </path>
                                        <path style="fill:#f3c55e;"
                                            d="M297.115,433.41c0,29.519,24.015,53.534,53.534,53.534V379.875 C321.13,379.875,297.115,403.891,297.115,433.41z">
                                        </path>
                                        <path style="fill:#f3c55e;"
                                            d="M92.118,379.875v107.069c29.519,0,53.534-24.015,53.534-53.534S121.637,379.875,92.118,379.875z">
                                        </path>
                                        <path style="fill:#f3c55e;"
                                            d="M350.649,379.875v107.069c29.519,0,53.535-24.015,53.535-53.534S380.168,379.875,350.649,379.875z">
                                        </path>
                                        <polygon style="fill:#f3c55e;"
                                            points="417.016,0 364.583,319.875 58.583,319.875 58.583,359.875 398.561,359.875 450.993,40 482.887,40 482.887,0 ">
                                        </polygon>
                                    </g>
                                </g>
                            </svg>
                            Penjualan
                        </div>

                    </button>
                    <button id="btnBeli" onclick="switchMode('beli')">
                        <div class="flex gap-1">
                            <svg height="15px" width="15px" version="1.1" id="Layer_1"
                                xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
                                viewBox="0 0 491.293 491.293" xml:space="preserve" fill="#000000">
                                <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
                                <g id="SVGRepo_iconCarrier">
                                    <g>
                                        <polygon style="fill:#FFDA44;"
                                            points="34.821,122.823 1.7,200.657 212.526,323.48 245.646,245.646 "></polygon>
                                        <polygon style="fill:#FFCD00;"
                                            points="212.526,323.48 34.821,219.956 34.821,368.47 245.646,491.293 245.646,245.646 ">
                                        </polygon>
                                        <polygon style="fill:#FFCD00;"
                                            points="278.767,323.48 489.593,200.657 456.472,122.823 245.646,245.646 ">
                                        </polygon>
                                        <polygon style="fill:#EEBF00;"
                                            points="456.472,122.823 245.646,0 34.821,122.823 245.646,245.646 "></polygon>
                                        <polygon style="fill:#EEBF00;"
                                            points="245.646,245.646 245.646,491.293 456.472,368.47 456.472,219.956 278.767,323.48 ">
                                        </polygon>
                                    </g>
                                </g>
                            </svg>
                            Pembelian
                        </div>
                    </button>
                </div>
            </div>

            {{-- ─── Filter bar ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <div class="filter-label">Cari Obat</div>
                        <input type="text" id="searchMedicine" class="filter-input" placeholder="Nama atau kode obat..."
                            autocomplete="off">
                    </div>
                    <div class="flex-1 min-w-[220px]">
                        <div class="filter-label">Rentang Tanggal</div>
                        <input type="text" id="dateRange" class="filter-input" placeholder="Pilih rentang tanggal..."
                            autocomplete="off" readonly>
                    </div>
                    <div>
                        <button id="btnReset"
                            class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Reset Filter
                        </button>
                    </div>
                    <div>
                        <a id="btnExport" href="{{ route('pareto.export') }}"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                            </svg>
                            Export Excel
                        </a>
                    </div>
                </div>
            </div>

            {{-- ─── DataTable ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">
                {{-- Mode badge --}}
                <div class="flex items-center gap-2 mb-4">
                    <span id="modeBadge"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                        style="background:#eff6ff;color:#1d4ed8;">
                        🛒 Mode: Penjualan
                    </span>
                    <span class="text-xs text-gray-400" id="modeDesc">Diurutkan berdasarkan total nilai jual
                        tertinggi</span>
                </div>

                <table id="historyTable" class="w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nama Obat</th>
                            <th>Kode Obat</th>
                            <th>Qty</th>
                            <th>Jumlah</th>
                            <th>Persen</th>
                            <th>Kumulatif</th>
                            <th>Freq R/</th>
                            <th>Satuan</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]"></tbody>
                </table>
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

    <script>
        const ROUTES = {
            jual: '{{ route('pareto.get') }}',
            beli: '{{ route('pareto.orders.get') }}',
        };

        let currentMode = 'jual';
        let startDate = '';
        let endDate = '';
        let searchTimer = null;
        let Table = null;

        // ── Flatpickr ────────────────────────────────
        flatpickr('#dateRange', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: {
                rangeSeparator: ' s/d '
            },
            onClose(selectedDates) {
                if (selectedDates.length === 2) {
                    startDate = selectedDates[0].toISOString().slice(0, 10);
                    endDate = selectedDates[1].toISOString().slice(0, 10);
                    loadTable();
                    updateExportUrl();

                }
            },
        });

        // ── Switch mode ───────────────────────────────
        function switchMode(mode) {
            if (mode === currentMode) return;
            currentMode = mode;

            const isJual = mode === 'jual';

            // Toggle button styles
            $('#btnJual').toggleClass('active-jual', isJual).toggleClass('active-beli', false);
            $('#btnBeli').toggleClass('active-beli', !isJual).toggleClass('active-jual', false);

            // Header accent + title
            $('#headerAccent').css('background', isJual ? '#2563eb' : '#16a34a');
            $('#pageTitle').text(isJual ? 'Pareto Penjualan' : 'Pareto Pembelian');
            $('#pageSubtitle').text(isJual ?
                'Analisis pareto berdasarkan data penjualan obat' :
                'Analisis pareto berdasarkan data pembelian / penerimaan obat'
            );

            // Mode badge
            $('#modeBadge')
                .html(isJual ? '🛒 Mode: Penjualan' : '📦 Mode: Pembelian')
                .css({
                    background: isJual ? '#eff6ff' : '#f0fdf4',
                    color: isJual ? '#1d4ed8' : '#15803d'
                });
            $('#modeDesc').text(isJual ?
                'Diurutkan berdasarkan total nilai jual tertinggi' :
                'Diurutkan berdasarkan total nilai pembelian tertinggi'
            );

            loadTable();
        }

        // ── Build / reload DataTable ──────────────────
        function loadTable() {
            if (Table) {
                Table.destroy();
                $('#historyTable tbody').empty();
            }

            Table = $('#historyTable').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                searching: false,
                ajax: {
                    url: ROUTES[currentMode],
                    data: {
                        start_date: startDate,
                        end_date: endDate,
                        search_medicine: $('#searchMedicine').val(),
                    },
                },
                language: {
                    processing: 'Memuat data...',
                    emptyTable: 'Tidak ada data',
                    zeroRecords: 'Data tidak ditemukan',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    paginate: {
                        next: '›',
                        previous: '‹'
                    },
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px',
                        className: 'text-gray-400 text-xs'
                    },
                    {
                        data: 'medicine_name'
                    },
                    {
                        data: 'medicine_code',
                        className: 'font-mono text-xs text-gray-500'
                    },
                    {
                        data: 'total_qty',
                        orderable: false,
                        className: 'text-right',
                        render: val => parseInt(val).toLocaleString('id-ID'),
                    },
                    {
                        data: 'total_jumlah_fmt',
                        orderable: false,
                        className: 'text-right font-semibold text-blue-700'
                    },
                    {
                        data: 'persen',
                        orderable: false,
                        className: 'text-right',
                        render: val => parseFloat(val).toFixed(2) + '%',
                    },
                    {
                        data: 'kumulatif',
                        orderable: false,
                        className: 'text-right',
                        render(val) {
                            const pct = parseFloat(val).toFixed(2) + '%';
                            const v = parseFloat(val);
                            if (v <= 80) return `<span class="badge-up">${pct}</span>`;
                            if (v <= 95) return `<span class="badge-mid">${pct}</span>`;
                            return `<span class="badge-down">${pct}</span>`;
                        },
                    },
                    {
                        data: 'freq',
                        orderable: false,
                        className: 'text-right text-gray-500'
                    },
                    {
                        data: 'medicine_unit',
                        orderable: false,
                        className: 'text-gray-500'
                    },
                ],
                order: [
                    [4, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                drawCallback() {
                    const info = this.api().page.info();
                    this.api().column(0, {
                        page: 'current'
                    }).nodes().each((cell, i) => {
                        cell.innerHTML = info.start + i + 1;
                    });
                },
            });
        }

        // ── Medicine search (debounced) ───────────────
        $('#searchMedicine').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                loadTable();
                updateExportUrl();
            }, 400);
        });

        // ── Reset filter ──────────────────────────────
        $('#btnReset').on('click', function() {
            startDate = '';
            endDate = '';
            $('#dateRange').val('');
            $('#searchMedicine').val('');
            loadTable();
            updateExportUrl();
        });

        function updateExportUrl() {
            const params = new URLSearchParams();
            if (startDate) params.set('start_date', startDate);
            if (endDate) params.set('end_date', endDate);
            if ($('#searchMedicine').val()) params.set('search_medicine', $('#searchMedicine').val());

            const base = '{{ route('pareto.export') }}';
            $('#btnExport').attr('href', base + (params.toString() ? '?' + params.toString() : ''));
        }
        // ── Initial load ──────────────────────────────
        loadTable();
        updateExportUrl();
    </script>
@endsection
