@extends('layouts.app')

@section('title', 'History Perubahan Harga')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* ── DataTable chrome ── */
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

        /* ── Price direction badges ── */
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

        .badge-same {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 10px;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        /* ── Summary cards ── */
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-card .icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-card .label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
        }

        /* ── Search bar ── */
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

        /* ── Price value styling ── */
        .price-new {
            font-weight: 700;
            color: #1d4ed8;
        }

        .price-current {
            font-size: 12px;
            color: #6b7280;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body space-y-4">

            {{-- ─── Page header ─── --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">History Perubahan Harga</h1>
                    <p class="text-sm text-gray-500 mt-1">Riwayat perubahan harga obat oleh Superadmin & Manager</p>
                </div>
            </div>

            {{-- ─── Summary cards ─── --}}
            <div class=" hidden grid-cols-2 md:grid-cols-4 gap-3" id="summaryCards">
                <div class="stat-card">
                    <div class="icon" style="background:#eff6ff">📋</div>
                    <div>
                        <div class="label">Total Perubahan</div>
                        <div class="value" id="statTotal">–</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background:#dcfce7">📈</div>
                    <div>
                        <div class="label">Harga Naik</div>
                        <div class="value" id="statUp" style="color:#16a34a">–</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background:#fee2e2">📉</div>
                    <div>
                        <div class="label">Harga Turun</div>
                        <div class="value" id="statDown" style="color:#dc2626">–</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background:#fef9c3">🕐</div>
                    <div>
                        <div class="label">Perubahan Terakhir</div>
                        <div class="value text-sm" id="statLast" style="font-size:13px">–</div>
                    </div>
                </div>
            </div>

            {{-- ─── Filter bar ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <div class="flex flex-wrap gap-3 items-end">

                    {{-- Medicine search --}}
                    <div class="flex-1 min-w-[200px]">
                        <div class="filter-label">Cari Obat</div>
                        <input type="text" id="searchMedicine" class="filter-input" placeholder="Nama atau kode obat..."
                            autocomplete="off">
                    </div>

                    {{-- Date range --}}
                    <div class="flex-1 min-w-[220px]">
                        <div class="filter-label">Rentang Tanggal</div>
                        <input type="text" id="dateRange" class="filter-input" placeholder="Pilih rentang tanggal..."
                            autocomplete="off" readonly>
                    </div>

                    {{-- Reset --}}
                    <div>
                        <button id="btnReset"
                            class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Reset Filter
                        </button>
                    </div>

                </div>
            </div>

            {{-- ─── DataTable ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <table id="historyTable" class="w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Kode Obat</th>
                            <th>Nama Obat</th>
                            <th>Satuan</th>
                            <th>Harga Beli</th>
                            <th>Diubah Oleh</th>
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
        // ════════════════════════════════════════
        // STATE
        // ════════════════════════════════════════
        let startDate = '';
        let endDate = '';
        let searchTimer = null;
        let historyTable = null;

        // ════════════════════════════════════════
        // SUMMARY CARDS  – computed from current
        // page data after every draw
        // ════════════════════════════════════════
        function updateSummaryCards() {
            // Use the full recordsTotal from the last Ajax response
            const info = historyTable.page.info();
            document.getElementById('statTotal').textContent = info.recordsTotal.toLocaleString('id-ID');

            // Count up/down from visible rows (good enough for overview)
            let up = 0,
                down = 0,
                lastDate = '–';

            historyTable.rows({
                page: 'current'
            }).data().each(function(row) {
                if (row.direction && row.direction.includes('Naik')) up++;
                if (row.direction && row.direction.includes('Turun')) down++;
            });

            document.getElementById('statUp').textContent = up;
            document.getElementById('statDown').textContent = down;

            // Last changed_at from first visible row (already sorted DESC)
            const first = historyTable.row(0).data();
            if (first) lastDate = first.changed_at ?? '–';
            document.getElementById('statLast').textContent = lastDate;
        }

        // ════════════════════════════════════════
        // DOM READY
        // ════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', function() {

            // ── Date range picker ──
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
                    historyTable.ajax.reload(null, false);
                }
            });

            // ── DataTable ──
            historyTable = $('#historyTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.gethistory') }}",
                    data: function(d) {
                        d.search_medicine = document.getElementById('searchMedicine').value.trim();
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px'
                    },
                    {
                        data: 'changed_at',
                        width: '130px'
                    },
                    {
                        data: 'medicine_code',
                        width: '110px'
                    },
                    {
                        data: 'medicine_name'
                    },
                    {
                        data: 'medicine_unit',
                        width: '80px'
                    },
                    {
                        // Harga baru — styled bold blue
                        data: 'new_price_fmt',
                        render: (data) => `<span class="price-new">${data}</span>`,
                        orderable: false
                    },
                
                    {
                        data: 'changed_by',
                        orderable: false
                    },
                ],
                order: [
                    [1, 'desc']
                ], // newest first
                pageLength: 25,
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    emptyTable: 'Tidak ada riwayat harga ditemukan',
                    paginate: {
                        previous: '‹',
                        next: '›',
                    }
                },
                drawCallback: function() {
                    updateSummaryCards();
                }
            });

            // ── Medicine search with 400 ms debounce ──
            document.getElementById('searchMedicine').addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    historyTable.ajax.reload(null, false);
                }, 400);
            });

            // ── Reset button ──
            document.getElementById('btnReset').addEventListener('click', function() {
                document.getElementById('searchMedicine').value = '';
                document.getElementById('dateRange').value = '';
                startDate = '';
                endDate = '';
                // Re-initialise flatpickr clear
                document.getElementById('dateRange')._flatpickr?.clear();
                historyTable.ajax.reload(null, false);
            });

        });
    </script>
@endsection
