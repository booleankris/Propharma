@extends('layouts.app')

@section('title', 'Data Retur')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
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
            background: #FEF3C7 !important;
            color: #92400E !important;
            border: none !important;
        }

        /* ── Table ── */
        #table-retur thead th {
            font-size: 11px !important;
            font-weight: 500 !important;
            text-transform: uppercase !important;
            color: #9ca3af !important;
            padding: 8px 10px !important;
            background: #fff !important;
            border-bottom: 0.5px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #table-retur tbody td {
            font-size: 12px !important;
            padding: 9px 10px !important;
            vertical-align: middle !important;
            color: #111827;
            border-bottom: 0.5px solid #f3f4f6 !important;
        }

        #table-retur tbody tr {
            transition: background .1s;
        }

        #table-retur tbody tr:hover {
            background: #fffbeb !important;
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

        /* ── Qty badge (amber for retur) ── */
        .qty-badge {
            display: inline-block;
            font-size: 11px;
            padding: 1px 7px;
            border-radius: 20px;
            background: #fef3c7;
            color: #92400e;
            font-weight: 500;
        }

        /* ── Retur badge on status ── */
        .retur-badge {
            display: inline-block;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 20px;
            background: #fee2e2;
            color: #991b1b;
            font-weight: 500;
            letter-spacing: .3px;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="bg-white border border-gray-100 rounded-xl p-5">

                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <h1 class="text-[14px] font-medium text-gray-800">Data Retur</h1>
                        <span class="retur-badge">Retur</span>
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

                <div class="overflow-x-auto">
                    <table id="table-retur" class="w-full">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Kode Transaksi</th>
                                <th>Pasien</th>
                                <th>Obat</th>
                                <th class="col-right">Total Retur</th>
                                <th class="col-right">Total</th>
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
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>

    <script>
        $(function() {
            $('#table-retur').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                ajax: '{{ route('returdata.index') }}',
                language: {
                    search: '',
                    searchPlaceholder: 'Cari kode, pasien, obat...'
                },
                order: [
                    [1, 'desc']
                ],
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'col-mono'
                    },
                    {
                        data: 'date',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'time',
                        orderable: false,
                        searchable: false,
                        className: 'col-mono'
                    },
                    {
                        data: 'transaction_code',
                        className: 'col-mono'
                    },
                    {
                        data: 'patient_name'
                    },
                    {
                        data: 'medicine_name'
                    },
                    {
                        data: 'qty_retur',
                        className: 'col-right',
                        orderable: false,
                        render: val => `<span class="qty-badge">${val}</span>`
                    },
                    {
                        data: 'total_formatted',
                        className: 'col-price',
                        orderable: false
                    },
                ],
            });
        });
    </script>
@endsection
