@extends('layouts.app')

@section('title', 'Data Penjualan')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* ── DataTables overrides ── */
        .dataTables_wrapper .dataTables_filter {
            float: none !important;
            margin-bottom: 12px;
        }

        .dataTables_wrapper .dataTables_filter label {
            font-size: 13px;
            color: #6b7280;
        }

        .dataTables_wrapper .dataTables_filter input {
            margin-left: 8px;
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 8px;
            border: 0.5px solid #d1d5db;
            outline: none;
            width: 200px;
        }

        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
        }

        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_length label {
            font-size: 12px;
            color: #9ca3af;
        }

        .dataTables_wrapper .dataTables_length select {
            font-size: 12px;
            padding: 4px 6px;
            border-radius: 6px;
            border: 0.5px solid #d1d5db;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            font-size: 12px !important;
            padding: 4px 9px !important;
            border-radius: 6px !important;
            border: none !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #E6F1FB !important;
            color: #185FA5 !important;
            border: none !important;
        }

        /* ── Tables ── */
        #table-data thead th,
        #items-table thead th {
            font-size: 11px !important;
            font-weight: 500 !important;
            text-transform: uppercase !important;
            color: #9ca3af !important;
            padding: 8px 10px !important;
            background: #fff !important;
            border-bottom: 0.5px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #table-data tbody td,
        #items-table tbody td {
            font-size: 12px !important;
            padding: 9px 2px !important;
            vertical-align: middle !important;
            color: #111827;
            border-bottom: 0.5px solid #f3f4f6 !important;
        }

        #table-data tbody td {
            text-align: center !important;
        }

        #table-data tbody tr,
        #items-table tbody tr {
            cursor: pointer;
            transition: background .1s;
        }

        #table-data tbody tr:hover {
            background: #f8fafc !important;
        }

        #table-data tbody tr.active {
            background: #E6F1FB !important;
        }

        .col-mono {
            font-family: ui-monospace, monospace;
            font-size: 11px !important;
            color: #6b7280 !important;
        }

        .col-price {
            text-align: right !important;
            font-weight: 500 !important;
        }

        .col-right {
            text-align: right !important;
        }

        .col-muted {
            text-align: right !important;
            color: #9ca3af !important;
        }

        /* ── Print button ── */
        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            border: 0.5px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            cursor: pointer;
            white-space: nowrap;
            transition: background .12s;
            line-height: 1.4;
        }

        .status-completed {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            border: 0.5px solid #bfdbfe;
            background: #00940b;
            color: #ffffff;
            cursor: pointer;
            white-space: nowrap;
            transition: background .12s;
            line-height: 1.4;
        }

        .status-pending {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 6px;
            background: #e29e00;
            color: #ffffff;
            cursor: pointer;
            white-space: nowrap;
            transition: background .12s;
            line-height: 1.4;
        }

        .btn-print:hover {
            background: #dbeafe;
        }

        /* ── Qty badge ── */
        .qty-badge {
            display: inline-block;
            font-size: 11px;
            padding: 1px 7px;
            border-radius: 20px;
            background: #f3f4f6;
            color: #374151;
            font-weight: 500;
        }

        /* ── Total row ── */
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 10px 0;
            margin-top: 8px;
            border-top: 0.5px solid #e5e7eb;
        }

        .total-row span:first-child {
            font-size: 12px;
            color: #6b7280;
        }

        .total-row span:last-child {
            font-size: 14px;
            font-weight: 500;
            color: #111827;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #d1d5db;
            font-size: 12px;
        }

        .empty-state svg {
            width: 28px;
            height: 28px;
            margin: 0 auto 8px;
            display: block;
        }

        .date-filter-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .date-filter-wrap label {
            font-size: 12px;
            color: #6b7280;
            white-space: nowrap;
        }

        .date-filter-wrap input[type="text"] {
            padding: 6px 10px;
            font-size: 12px;
            border-radius: 8px;
            border: 0.5px solid #d1d5db;
            outline: none;
            width: 120px;
            cursor: pointer;
        }

        .date-filter-wrap input[type="text"]:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .12);
        }

        .btn-clear-date {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 7px;
            border: 0.5px solid #e5e7eb;
            background: #f9fafb;
            color: #6b7280;
            cursor: pointer;
            transition: background .12s;
        }

        .btn-clear-date:hover {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fca5a5;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">

                {{-- ─── LEFT: Sales Table (3/5) ─────────────────────────────────── --}}
                <div class="lg:col-span-3 bg-white border border-gray-100 rounded-xl p-5">

                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">

                                    <svg class="w-5 h-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="icon icon-tabler icons-tabler-outline icon-tabler-cash-register">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                        <path
                                            d="M21 15h-2.5c-.398 0 -.779 .158 -1.061 .439c-.281 .281 -.439 .663 -.439 1.061c0 .398 .158 .779 .439 1.061c.281 .281 .663 .439 1.061 .439h1c.398 0 .779 .158 1.061 .439c.281 .281 .439 .663 .439 1.061c0 .398 -.158 .779 -.439 1.061c-.281 .281 -.663 .439 -1.061 .439h-2.5" />
                                        <path d="M19 21v1m0 -8v1" />
                                        <path
                                            d="M13 21h-7c-.53 0 -1.039 -.211 -1.414 -.586c-.375 -.375 -.586 -.884 -.586 -1.414v-10c0 -.53 .211 -1.039 .586 -1.414c.375 -.375 .884 -.586 1.414 -.586h2m12 3.12v-1.12c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-2" />
                                        <path
                                            d="M16 10v-6c0 -.53 -.211 -1.039 -.586 -1.414c-.375 -.375 -.884 -.586 -1.414 -.586h-4c-.53 0 -1.039 .211 -1.414 .586c-.375 .375 -.586 .884 -.586 1.414v6m8 0h-8m8 0h1m-9 0h-1" />
                                        <path d="M8 14v.01" />
                                        <path d="M8 17v.01" />
                                        <path d="M12 13.99v.01" />
                                        <path d="M12 17v.01" />
                                    </svg>
                                </div>
                                <div>
                                    <h2 class="text-[15px] font-semibold text-gray-800 leading-tight">Data Penjualan</h2>
                                    <p class="text-[12px] text-gray-400">Data dan riwayat penjualan kasir</p>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('home') }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-200
                                   text-[12px] text-gray-500 hover:bg-gray-50 transition-colors">
                            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round">
                                <path d="M10 12L6 8l4-4" />
                            </svg>
                            Kembali
                        </a>
                    </div>
                    <div class="date-filter-wrap">
                        <label>Periode</label>
                        <input type="text" id="date-range" placeholder="dd/mm/yyyy — dd/mm/yyyy" readonly
                            style="width: 210px;">
                        <button class="btn-clear-date" id="btn-clear-date">Reset</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table id="table-data" class="w-full">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Tanggal</th>
                                    <th>Jam</th>
                                    <th>Nomor</th>
                                    <th>Tipe</th>
                                    <th>Pasien</th>
                                    <th class="col-right">Harga</th>
                                    <th class="col-right">Pembayaran</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- ─── RIGHT: Transaction Detail (2/5) ────────────────────────── --}}
                <div class="lg:col-span-2 bg-white border border-gray-100 rounded-xl p-5">

                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-[14px] font-medium text-gray-800">Detail transaksi</h2>
                        <span id="detail-code" class="text-[11px] font-mono text-gray-400">—</span>
                    </div>

                    <div id="detail-empty" class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4">
                            <path d="M9 12h6M9 16h6M7 2H5a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8l-6-6z" />
                            <polyline points="14 2 14 8 20 8" />
                        </svg>
                        Pilih transaksi untuk melihat detail
                    </div>

                    <div id="detail-wrap" class="hidden">
                        <div class="overflow-x-auto">
                            <table id="items-table" class="w-full">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Obat</th>
                                        <th class="col-right">Harga</th>
                                        <th class="col-right">Qty</th>
                                        <th class="col-right">Disc</th>
                                        <th class="col-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="total-row">
                            <span>Subtotal</span>
                            <span id="detail-subtotal">—</span>
                        </div>
                        <div class="total-row">
                            <span>Total Diskon</span>
                            <span id="detail-discount">—</span>
                        </div>
                        <div class="total-row">
                            <span>Total</span>
                            <span id="detail-total">—</span>
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let tableData = null;
        let itemsTable = null;

        $(function() {

            // ── Flatpickr instances ───────────────────────────────────────────
            const today = flatpickr.formatDate(new Date(), 'd/m/Y');

            const fpRange = flatpickr('#date-range', {
                mode: 'range',
                dateFormat: 'd/m/Y',
                locale: {
                    firstDayOfWeek: 1
                },
                defaultDate: [today, today], 
                onClose: function(selectedDates) {
                    if (selectedDates.length === 1 || selectedDates.length === 2) {
                        tableData.ajax.reload();
                    }
                },
            });

            $('#btn-clear-date').on('click', function() {
                fpRange.clear();
                tableData.ajax.reload();
            });

            // ── Main sales table ──────────────────────────────────────────────
            tableData = $('#table-data').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('salesdata.index') }}',
                    data: function(d) {
                        const val = $('#date-range').val();
                        const parts = val.split(' to ');
                        d.date_from = parts[0] ?? '';
                        d.date_to = parts[1] ?? '';
                    }
                },
                language: {
                    search: '',
                    searchPlaceholder: 'Cari nomor, nama pasien...'
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'col-mono'
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'time',
                        className: 'col-mono'
                    },
                    {
                        data: 'code',
                        className: 'col-mono'
                    },
                    {
                        data: 'type',
                        className: 'col-mono'
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'final_price',
                        className: 'col-price'
                    },
                    {
                        data: 'payment_method',
                        defaultContent: '-'
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: (d) =>
                            `<button class="btn-print" onclick="event.stopPropagation(); window.open('/print/receipt/${d.transactions.id}','_blank')">
                    <svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                        <polyline points="4 6 4 1 12 1 12 6"/>
                        <path d="M4 12H3a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1"/>
                        <rect x="4" y="10" width="8" height="5"/>
                    </svg>Cetak</button>`
                    },
                ],
            });

            // ── Row click → load items ────────────────────────────────────────
            $('#table-data tbody').on('click', 'tr', function() {
                const data = tableData.row(this).data();
                if (!data) return;
                $('#table-data tbody tr').removeClass('active');
                $(this).addClass('active');
                document.getElementById('detail-code').textContent = data.code;
                loadItems(data.transaction_id, data.final_price, data.subtotal, data.totaldiscount);
            });
        });

        // ── Load items table ──────────────────────────────────────────────────
        function loadItems(transactionId, totalPrice, subtotal, totaldiscount) {
            if (itemsTable) {
                itemsTable.destroy();
                $('#items-table tbody').empty();
            }

            document.getElementById('detail-empty').classList.add('hidden');
            document.getElementById('detail-wrap').classList.remove('hidden');
            document.getElementById('detail-total').textContent = totalPrice ?? '—';
            document.getElementById('detail-discount').textContent = totaldiscount ?? '—';
            document.getElementById('detail-subtotal').textContent = subtotal ?? '—';

            itemsTable = $('#items-table').DataTable({
                processing: true,
                serverSide: true,
                searching: false,
                paging: false,
                info: false,
                ajax: `/sales/transaction/${transactionId}/items`,
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        className: 'col-mono'
                    },
                    {
                        data: 'medicine'
                    },
                    {
                        data: 'price',
                        className: 'col-right',
                        render: val => `<span class="qty-badge">${val}</span>`
                    },
                    {
                        data: 'quantity',
                        className: 'col-right',
                        render: val => `<span class="qty-badge">${val}</span>`
                    },
                    {
                        data: 'discount',
                        className: 'col-muted',
                        render: val => val ? val : '—'
                    },
                    {
                        data: 'total',
                        className: 'col-price'
                    },
                ],
            });
        }
    </script>
@endsection
