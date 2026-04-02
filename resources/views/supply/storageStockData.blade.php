{{-- resources/views/supply/history.blade.php --}}
@extends('layouts.app')

@section('title', 'Riwayat Stok')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* ── DataTables overrides ── */
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
            font-family: inherit;
        }

        .dataTables_filter input,
        .dataTables_length select {
            padding: 5px 10px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            outline: none !important;
        }

        .dataTables_filter input:focus {
            border-color: #378ADD !important;
            box-shadow: 0 0 0 3px rgba(55, 138, 221, 0.12) !important;
        }

        /* ── Table ── */
        #historyTable thead th {
            padding: 10px 14px !important;
            font-size: 11px !important;
            font-weight: 500 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            background: #f8fafc !important;
            border-bottom: 1px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #historyTable tbody td {
            padding: 10px 14px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #historyTable tbody tr:hover td {
            background: #f8fafc !important;
        }

        /* ── Pagination ── */
        .dataTables_wrapper .paginate_button {
            padding: 5px 11px !important;
            border-radius: 6px !important;
            margin: 0 2px !important;
            font-size: 13px !important;
        }

        .dataTables_wrapper .paginate_button.current {
            background: #378ADD !important;
            color: #fff !important;
            border: 1px solid #378ADD !important;
        }

        .dataTables_wrapper .paginate_button.previous {
            background: #FCEBEB !important;
            color: #A32D2D !important;
        }

        .dataTables_wrapper .paginate_button.next {
            background: #EAF3DE !important;
            color: #27500A !important;
        }

        .dataTables_wrapper .paginate_button.disabled {
            opacity: 0.4 !important;
            cursor: default !important;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="card shadow-sm rounded-2xl p-6 bg-white">

                {{-- Header --}}
                <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0
                                     002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0
                                     002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <h2 class="text-lg font-semibold text-gray-800">Riwayat Stok Gudang</h2>
                    </div>

                    <a href="{{ route('supplies.printstockdata') }}" target="_blank">
                        <button
                            class="flex items-center gap-2 px-4 h-9 rounded-lg text-sm font-medium
                                   text-green-900 bg-green-100 hover:bg-green-200 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Export Excel
                        </button>
                    </a>
                </div>

                {{-- Filters --}}
                <div class="flex flex-wrap gap-3 mb-5 items-end">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Tanggal</p>
                        <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..." autocomplete="off"
                            class="h-9 px-3 rounded-lg border border-gray-300 text-sm w-56
                                  focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Cari obat</p>
                        <input type="text" id="medicine" onkeyup="searchMedicines(this.value)"
                            placeholder="Nama atau kode obat..." autocomplete="off"
                            class="h-9 px-3 rounded-lg border border-gray-300 text-sm w-52
                                  focus:outline-none focus:ring-2 focus:ring-blue-200">
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Nama obat</p>
                        <input type="text" id="medicine_name" readonly placeholder="—"
                            class="h-9 px-3 rounded-lg border border-gray-300 text-sm w-44
                                  bg-gray-50 text-gray-500 cursor-default">
                    </div>
                </div>

                {{-- Table --}}
                <div class="overflow-x-auto">
                    <table id="historyTable" class="w-full text-sm text-gray-700">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal</th>
                                <th>Kode transaksi</th>
                                <th>Kode obat</th>
                                <th>Nama obat</th>
                                <th>Status</th>
                                <th>QTY</th>
                                <th>QTY Awal</th>
                                <th>QTY Akhir</th>
                                <th>Stok saat ini</th>
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
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let table, startDate = '',
            endDate = '',
            searchMedicine = '';

        document.addEventListener('DOMContentLoaded', function() {

            flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                onClose(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        endDate = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    } else {
                        startDate = endDate = '';
                    }
                    table.ajax.reload();
                }
            });

            table = $('#historyTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('supplies.getStorageSupplies') }}",
                    type: 'GET',
                    data(d) {
                        d.searchMedicine = searchMedicine;
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'date',
                        orderable: true
                    },
                    {
                        data: 'transaction_code',
                        orderable: false
                    },
                    {
                        data: 'code',
                        orderable: false
                    },
                    {
                        data: 'name',
                        orderable: false
                    },
                    { 
                        data: 'status',
                        orderable: false,
                        render(data) {
                            return data;
                        }
                    },
                    {
                        data: 'stock',
                        orderable: false,
                        render(data) {
                            return data;
                        }
                    },
                    {
                        data: 'qty_before',
                        orderable: false,
                        render(data) {
                            return data;
                        }
                    },
                    {
                        data: 'qty_after',
                        orderable: false,
                        render(data) {
                            return data;
                        }
                    },
                    {
                        data: 'supply',
                        orderable: false
                    },
                ],
                searching: false,
                info: true, 
                paging: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    processing: 'Memuat data...',
                    emptyTable: 'Tidak ada data tersedia',
                    zeroRecords: 'Tidak ada data yang cocok',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                    infoEmpty: 'Menampilkan 0 data',
                    infoFiltered: '(disaring dari _MAX_ total data)',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    paginate: {
                        first: 'Pertama',
                        last: 'Terakhir',
                        next: 'Selanjutnya →',
                        previous: '← Sebelumnya',
                    }
                },
            });
        });

        function searchMedicines(value) {
            searchMedicine = value.trim();
            if (!searchMedicine) {
                $('#medicine_name').val('');
            }
            table.ajax.reload(function(json) {
                $('#medicine_name').val(
                    json.data?.length ? (json.data[0].name ?? '—') : '—'
                );
            }, false); 
        }
    </script>
@endsection
