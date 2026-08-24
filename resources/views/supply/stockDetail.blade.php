@extends('layouts.app')

@section('title', 'Stock Pelayanan')

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

        #transferTable thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-weight: 600 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: .04em !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 12px 16px !important;
            white-space: nowrap;
        }

        #transferTable tbody td {
            padding: 12px 16px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            color: #475569 !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #transferTable tbody tr:hover td {
            background-color: #f8fafc !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 6px 12px !important;
            border-radius: 6px !important;
            margin: 0 3px !important;
            font-size: 13px !important;
            border: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb !important;
            color: #fff !important;
            border: 1px solid #2563eb !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #eff6ff !important;
            color: #2563eb !important;
            border: 1px solid #bfdbfe !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            cursor: default !important;
            color: #94a3b8 !important;
            border: 1px solid #e2e8f0 !important;
            background: #f8fafc !important;
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

        /* Select2 Custom Styles */
        #searchInput+.select2-container .select2-selection--single {
            height: 40px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 0.5rem !important;
            display: flex;
            align-items: center;
            padding: 0 0.5rem !important;
        }

        #searchInput+.select2-container .select2-selection--single .select2-selection__rendered {
            padding: 0;
            line-height: normal;
            font-size: 13px;
            color: #1e293b;
        }

        #searchInput+.select2-container .select2-selection--single .select2-selection__arrow {
            height: 100%;
            right: 0.75rem;
        }

        #searchInput+.select2-container--open .select2-selection--single,
        #searchInput+.select2-container--focus .select2-selection--single {
            border-color: #60a5fa !important;
            box-shadow: 0 0 0 2px rgba(191, 219, 254, 0.5);
        }
    </style>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50/50 pb-12 pt-2 px-4 sm:px-6 lg:px-8">
        <div class="mx-auto space-y-4">

            <div
                class="flex flex-col gap-4 p-5 bg-white border border-slate-200/80 rounded-xl shadow-sm md:flex-row md:items-center md:justify-between">

                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 leading-tight">Stock Pelayanan</h2>
                        <p class="text-xs text-slate-400">Riwayat transfer stok ke etalase pelayanan</p>
                    </div>
                </div>

            </div>

            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Cari
                            Obat</label>
                        <select id="searchInput" class="w-full"></select>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Status</label>
                        <select id="statusFilter"
                            class="px-3 h-10 w-full rounded-lg border border-slate-300 bg-white text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition">
                            <option value="">Semua status</option>
                            <option value="0">Pending</option>
                            <option value="1">Diterima</option>
                            <option value="2">Ditolak</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-2">
                        <button type="button" onclick="resetFilters()"
                            class="px-4 h-10 rounded-lg text-[13px] font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 border border-slate-200 transition">
                            Reset
                        </button>
                    </div>
                </div>

                <!-- Total Stock Info -->
                <div id="stockInfoContainer"
                    class="mt-4 hidden p-3 bg-blue-50/50 border border-blue-100 rounded-lg inline-flex items-center justify-between">
                    <div class="px-1 flex items-center gap-2 text-blue-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <span class="text-sm font-semibold">Total Stok di Pelayanan :</span>
                    </div>
                    <span id="totalStockDisplay" class="text-xl font-bold text-blue-700">0</span>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table id="transferTable" class="w-full text-sm text-left text-slate-600">
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
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>

    <script>
        let transferTable;

        function stockBadge(qty) {
            qty = parseInt(qty) || 0;
            if (qty <= 0) return `<span class="badge-stock empty">${qty}</span>`;
            if (qty <= 10) return `<span class="badge-stock low">${qty}</span>`;
            return `<span class="badge-stock ok">${qty}</span>`;
        }

        function statusBadge(status) {
            const map = {
                0: ['pending', 'Pending'],
                1: ['accepted', 'Diterima'],
                2: ['denied', 'Ditolak'],
            };

            const [cls, label] = map[parseInt(status)] ?? ['pending', 'Pending'];
            return `<span class="badge-status ${cls}">${label}</span>`;
        }

        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            transferTable.ajax.reload(null, false);
        }

        document.addEventListener('DOMContentLoaded', function() {

            $('#searchInput').select2({
                placeholder: 'Cari kode, batch, atau nama obat...',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: "{{ route('supplies.medicineSelect') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.code + ' - ' + item.name
                                };
                            })
                        };
                    },
                    cache: true
                }
            }).on('change', function() {
                transferTable.ajax.reload(null, false);
            });

            transferTable = $('#transferTable').DataTable({
                processing: true,
                serverSide: true,

                ajax: {
                    url: '{{ route('supplies.getStockDetail') }}',
                    data: function(d) {
                        d.medicine_id = $('#searchInput').val();
                        d.status = $('#statusFilter').val();
                    }
                },

                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px'
                    },
                    {
                        data: 'code',
                        orderable: false
                    },
                    {
                        data: 'medicine_name',
                        orderable: false
                    },
                    {
                        data: 'batch_name',
                        orderable: false
                    },
                    {
                        data: 'stock',
                        render: (d) => stockBadge(d),
                        className: 'text-center'
                    },
                    {
                        data: 'expired_date',
                        orderable: false
                    },
                    {
                        data: 'etalase',
                        orderable: false
                    },
                    {
                        data: 'pharmacy',
                        orderable: false
                    },
                    {
                        data: 'status',
                        render: (d) => statusBadge(d),
                        className: 'text-center'
                    },
                ],

                searching: false,
                lengthChange: true,
                autoWidth: false,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],

                language: {
                    processing: 'Memuat data...',
                    emptyTable: 'Tidak ada data tersedia',
                    zeroRecords: 'Tidak ada data ditemukan',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                    infoEmpty: 'Menampilkan 0 data',
                    infoFiltered: '(disaring dari _MAX_ total data)',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    paginate: {
                        first: 'Pertama',
                        last: 'Terakhir',
                        next: 'Selanjutnya',
                        previous: 'Sebelumnya',
                    }
                }
            });

            $('#statusFilter').on('change', function() {
                transferTable.ajax.reload(null, false);
            });

            transferTable.on('xhr.dt', function(e, settings, json, xhr) {
                let medicineId = $('#searchInput').val();
                let stockContainer = document.getElementById('stockInfoContainer');
                let stockDisplay = document.getElementById('totalStockDisplay');

                if (medicineId) {
                    stockContainer.classList.remove('hidden');
                    let total = json.total_stock_pelayanan !== undefined ? json.total_stock_pelayanan : 0;
                    stockDisplay.textContent = total;
                } else {
                    stockContainer.classList.add('hidden');
                }
            });
        });
    </script>
@endsection
