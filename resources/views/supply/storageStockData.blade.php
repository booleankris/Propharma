@extends('layouts.app')

@section('title', 'Riwayat Stok Gudang')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .select2-container--default .select2-selection--single {
            height: 42px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 0.5rem !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 40px !important;
            padding-left: 0.9rem !important;
            color: #334155 !important;
            font-size: 13px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
        }

        #historyTable thead th {
            background-color: #f8fafc !important;
            color: #475569 !important;
            font-weight: 600 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 12px 16px !important;
            white-space: nowrap;
        }

        #historyTable tbody td {
            padding: 12px 16px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            color: #475569 !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #historyTable tbody tr:hover td {
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
    </style>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50/50 pb-12 pt-2 px-4 sm:px-6 lg:px-8">
        <div class="mx-auto space-y-4">

            <div
                class="flex flex-col gap-4 p-5 bg-white border border-slate-200/80 rounded-xl shadow-sm md:flex-row md:items-center md:justify-between">

                <div class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0
                                 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0
                                 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 leading-tight">Riwayat Stok Gudang</h2>
                        <p class="text-xs text-slate-400">Pergerakan stok masuk dan keluar gudang</p>
                    </div>
                </div>

                <a href="{{ route('supplies.printstockdata') }}" target="_blank"
                    class="inline-flex items-center gap-2 px-4 h-10 rounded-lg text-sm font-semibold text-white bg-emerald-500 hover:bg-emerald-600 transition shadow-sm shrink-0 w-fit">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export Excel
                </a>
            </div>

            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Rentang
                            Tanggal</label>
                        <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                            class="w-full h-[42px] rounded-lg border border-slate-300 bg-white px-4 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                            autocomplete="off">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Pilih
                            Obat</label>
                        <select id="medicine" class="w-full"></select>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table id="historyTable" class="w-full text-sm text-left">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Tanggal</th>
                                <th>Kode Obat</th>
                                <th>Nama Obat</th>
                                <th>Status</th>
                                <th>QTY</th>
                                <th>QTY Awal</th>
                                <th>QTY Akhir</th>
                                <th>Stok Saat Ini</th>
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
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let table, startDate = '',
            endDate = '';
        let selectedMedicineId = '';

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

            $('#medicine').select2({
                placeholder: 'Cari nama atau kode obat...',
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
                selectedMedicineId = $(this).val() || '';
                table.ajax.reload();
            });

            table = $('#historyTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('supplies.getStorageSupplies') }}",
                    type: 'GET',
                    data(d) {
                        d.medicine_id = selectedMedicineId;
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
                        data: 'medicines.code',
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
                        next: 'Selanjutnya',
                        previous: 'Sebelumnya',
                    }
                },
            });
        });
    </script>
@endsection
