@extends('layouts.app')

@section('title', 'Data Transfer Obat')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">

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

        #transferTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: .04em !important;
            border-bottom: 2px solid #e5e7eb !important;
            padding: 10px 12px !important;
            color: #6b7280 !important;
        }

        #transferTable tbody td {
            padding: 11px 12px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #transferTable tbody tr:hover {
            background-color: #f8fafc !important;
        }

        #transferTable tbody tr:last-child td {
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

        .badge-status {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 99px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-status.pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .badge-status.accepted {
            background: #dcfce7;
            color: #15803d;
        }

        .badge-status.denied {
            background: #fee2e2;
            color: #b91c1c;
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
                                <path d="M5 12h14M12 5l7 7-7 7" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-[15px] font-semibold text-gray-800 leading-tight">Data Transfer Obat</h2>
                            <p class="text-[12px] text-gray-400">Riwayat transfer stok ke etalase</p>
                        </div>
                    </div>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap gap-3 mb-5">
                    <div>
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Cari obat</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6">
                                <circle cx="6.5" cy="6.5" r="4.5" />
                                <line x1="10.5" y1="10.5" x2="14" y2="14" />
                            </svg>
                            <input type="text" id="searchInput" placeholder="Kode atau nama obat..."
                                oninput="filterTable(this.value)"
                                class="pl-9 pr-3 py-2 rounded-lg border border-gray-200 bg-white text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400 w-64"
                                autocomplete="off">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[12px] font-medium text-gray-500 mb-1">Status</label>
                        <select id="statusFilter" onchange="filterTable()"
                            class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            <option value="">Semua status</option>
                            <option value="0">Pending</option>
                            <option value="1">Diterima</option>
                            <option value="2">Ditolak</option>
                        </select>
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
                    <table id="transferTable" class="w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Kode Transfer</th>
                                <th>Nama Obat</th>
                                <th>Batch</th>
                                <th>Stok</th>
                                <th>Expired</th>
                                <th>Etalase</th>
                                <th>Apotek</th>
                                <th>Status</th>
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
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        let table;

        $(document).ready(function() {
            table = $('#transferTable').DataTable({
                processing: true,
                serverSide: false,
                ajax: {
                    url: '/getstockdetail',
                    type: 'GET',
                    data: function(d) {
                        d.search = $('#searchInput').val();
                        d.status = $('#statusFilter').val();
                    },
                    dataSrc: 'data',
                    error: function() {
                        console.error('Gagal mengambil data');
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        title: '#',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'code',
                        title: 'Kode Transfer'
                    },
                    {
                        data: 'medicine_name',
                        title: 'Nama Obat'
                    },
                    {
                        data: 'batch_name',
                        title: 'Batch'
                    },
                    {
                        data: 'stock',
                        title: 'Stok'
                    },
                    {
                        data: 'expired_date',
                        title: 'Expired'
                    },
                    {
                        data: 'etalase',
                        title: 'Etalase'
                    },
                    {
                        data: 'pharmacy',
                        title: 'Apotek'
                    },
                    {
                        data: 'status',
                        title: 'Status',
                        render: function(val) {
                            const map = {
                                0: {
                                    label: 'Pending',
                                    cls: 'bg-yellow-50 text-yellow-700 border border-yellow-200'
                                },
                                1: {
                                    label: 'Diterima',
                                    cls: 'bg-green-50 text-green-700 border border-green-200'
                                },
                                2: {
                                    label: 'Ditolak',
                                    cls: 'bg-red-50 text-red-700 border border-red-200'
                                },
                            };
                            const s = map[val] ?? {
                                label: '-',
                                cls: 'bg-gray-100 text-gray-500'
                            };
                            return `<span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-medium ${s.cls}">${s.label}</span>`;
                        }
                    },
                ],
                language: {
                    processing: 'Memuat data...',
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    infoFiltered: '(difilter dari _MAX_ total data)',
                    zeroRecords: 'Tidak ada data ditemukan',
                    emptyTable: 'Tidak ada data tersedia',
                    paginate: {
                        first: 'Pertama',
                        last: 'Terakhir',
                        next: 'Selanjutnya',
                        previous: 'Sebelumnya',
                    },
                },
                dom: '<"flex items-center justify-between mb-3"lp>t<"flex items-center justify-between mt-3"ip>',
                pageLength: 10,
                order: [],
            });

            // Hook custom filters to reload DataTable
            let debounceTimer;
            $('#searchInput').on('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => table.ajax.reload(), 300);
            });

            $('#statusFilter').on('change', function() {
                table.ajax.reload();
            });
        });

        function resetFilters() {
            $('#searchInput').val('');
            $('#statusFilter').val('');
            table.ajax.reload();
        }
    </script>
@endsection
