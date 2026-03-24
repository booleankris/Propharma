@extends('layouts.app')

@section('title', 'Data Stok')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter input {
            width: 220px !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            outline: none !important;
        }

        .dataTables_length select {
            padding: 4px 8px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }

        #stockTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: .04em !important;
            border-bottom: 2px solid #e5e7eb !important;
            padding: 10px 12px !important;
            color: #6b7280 !important;
        }

        #stockTable tbody td {
            padding: 11px 12px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #stockTable tbody tr:hover {
            background-color: #f8fafc !important;
        }

        #stockTable tbody tr:last-child td {
            border-bottom: none !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 5px 10px !important;
            border-radius: 6px !important;
            margin: 0 2px !important;
            background: #f3f4f6 !important;
            font-size: 13px !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb !important;
            color: #fff !important;
            border: 1px solid #2563eb !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            background: transparent !important;
            color: #9ca3af !important;
            border: 1px solid transparent !important;
        }

        .badge-stock {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-stock.ok {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-stock.low {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-stock.empty {
            background: #fee2e2;
            color: #b91c1c;
        }

        .flatpickr-input {
            cursor: pointer;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="bg-white border border-gray-100 rounded-xl p-6 shadow-sm">

                {{-- Header --}}
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="20" height="14" rx="2" />
                                <path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2" />
                                <line x1="12" y1="12" x2="12" y2="17" />
                                <line x1="9.5" y1="14.5" x2="14.5" y2="14.5" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-[15px] font-semibold text-gray-800 leading-tight">Data Stok</h2>
                            <p class="text-[12px] text-gray-400">Semua batch obat beserta lokasi dan stok</p>
                        </div>
                    </div>

                    <a href="{{ route('supplies.exportStockData') }}" target="_blank">
                        <button
                            class="flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-[13px] font-medium transition-colors duration-150">
                            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M8 2v9M4 7l4 4 4-4" />
                                <path d="M2 13h12" />
                            </svg>
                            Export Excel
                        </button>
                    </a>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap gap-3 mb-5">
                    <div>
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Rentang tanggal expired</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                                <rect x="1" y="2" width="14" height="13" rx="2" />
                                <path d="M1 6h14M5 1v2M11 1v2" />
                            </svg>
                            <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                                class="pl-9 pr-3 py-2 rounded-lg border border-gray-200 bg-white text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 w-64"
                                autocomplete="off">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Cari obat</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="6.5" cy="6.5" r="4.5" />
                                <line x1="10.5" y1="10.5" x2="14" y2="14" />
                            </svg>
                            <input type="text" id="searchInput" placeholder="Kode atau nama obat..."
                                oninput="filterStock(this.value)"
                                class="pl-9 pr-3 py-2 rounded-lg border border-gray-200 bg-white text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 w-64"
                                autocomplete="off">
                        </div>
                    </div>

                    <div class="flex items-end">
                        <button onclick="resetFilters()"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-[13px] transition-colors duration-150">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                                stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 4h14M4 4V2h8v2M3 4l1 10h8l1-10" />
                            </svg>
                            Reset
                        </button>
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table id="stockTable" class="w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Kode Obat</th>
                                <th>Nama Obat</th>
                                <th>Batch</th>
                                <th>Stok</th>
                                <th>Expired</th>
                                <th>Lokasi</th>
                                <th>Etalase</th>
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
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        // ─── State ────────────────────────────────────────────────────────────────────
        let stockTable;
        let startDate = '';
        let endDate = '';
        let searchValue = '';

        // ─── Helpers ──────────────────────────────────────────────────────────────────
        function stockBadge(qty) {
            qty = parseInt(qty) || 0;
            if (qty <= 0) return `<span class="badge-stock empty">${qty}</span>`;
            if (qty <= 10) return `<span class="badge-stock low">${qty}</span>`;
            return `<span class="badge-stock ok">${qty}</span>`;
        }

        function filterStock(value) {
            searchValue = value.trim();
            stockTable.ajax.reload();
        }

        function resetFilters() {
            searchValue = '';
            startDate = '';
            endDate = '';
            document.getElementById('searchInput').value = '';
            document.getElementById('dateRange').value = '';
            stockTable.ajax.reload();
        }

        // ─── Init ─────────────────────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {

            // Date range picker
            flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        endDate = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    } else {
                        startDate = '';
                        endDate = '';
                    }
                    stockTable.ajax.reload();
                }
            });

            // DataTable
            stockTable = $('#stockTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '{{ route('supplies.getStockDetail') }}',
                    data: function(d) {
                        d.search = searchValue;
                        d.start_date = startDate;
                        d.end_date = endDate;
                    },
                    dataSrc: 'data'
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px'
                    },
                    {
                        data: 'medicine_code'
                    },
                    {
                        data: 'medicine_name'
                    },
                    {
                        data: 'batch_name'
                    },
                    {
                        data: 'stock',
                        render: (data) => stockBadge(data),
                        className: 'text-center'
                    },
                    {
                        data: 'expired_date'
                    },
                    {
                        data: 'location'
                    },
                    {
                        data: 'etalase'
                    },
                ],
                order: [
                    [2, 'asc']
                ],
                paging: true,
                searching: false,
                info: true,
                lengthChange: true,
                autoWidth: false,
                language: {
                    processing: 'Memuat data...',
                    zeroRecords: 'Tidak ada data ditemukan',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    paginate: {
                        previous: '&#8592;',
                        next: '&#8594;',
                    }
                }
            });
        });
    </script>
@endsection
