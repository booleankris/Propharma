@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        table.dataTable thead th,
        table.dataTable thead td {
            padding: 21px 18px !important;
            background: #ffffff !important;
            border-bottom: 1px solid #111 !important;
        }

        .dataTables_wrapper .top {
            font-family: "Poppins";
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter {
            display: block !important;
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

        .dataTables_length {
            display: block !important;
        }

        .dataTables_length select {
            padding: 4px 23px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }


        #medicineTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
        }


        #medicineTable tbody td {
            padding: 12px 10px !important;
            font-size: 14px !important;
            vertical-align: middle !important;
        }

        #medicineTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        #orderItemsTable tr.selected {
            background-color: #e0f2fe !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 6px 12px !important;
            border-radius: 6px !important;
            background: #f3f3f3;
            margin: 0 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb;
            color: #fff !important;
            border: 1px solid #2563eb;
            margin: 0 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            cursor: default !important;
            color: #666 !important;
            border: 1px solid transparent !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .paginate_button.previous {
            background: #ffd7d7 !important;
        }

        .paginate_button.next {
            background: #c4ffcf !important;
            font-family: 'Poppins';
            font-size: 14px;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="">

                <div class="card  shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Data Stok</h2>

                    </div>
                    <div class="flex py-2 gap-1">

                        <div>
                            <div class="py-1 text-[13px] font-bold">Tanggal</div>

                            <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                autocomplete="off">
                        </div>
                        <div>
                            <div class="py-1 text-[13px] font-bold">Cari Obat...</div>

                            <input type="text" onkeyup="searchMedicines(this.value)" id="medicine"
                                placeholder="Ketik Nama atau Kode Obat..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                autocomplete="off">
                        </div>
                        <div>
                            <div class="py-1 text-[13px] font-bold">Nama Obat</div>

                            <input type="text" readonly id="medicine_name" placeholder="Ketik Nama Obat..."
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                autocomplete="off">
                        </div>
                    </div>
                    <a href="{{ route('supplies.printstockdata') }}" target="_blank">
                        <button style="background:#41bd33"
                            class="group rounded-md shadow text-white cursor-pointer flex justify-between items-center overflow-hidden transition-all hover:glow">
                            <div
                                class="relative w-10 h-12 bg-white bg-opacity-20 flex justify-center items-center transition-all">
                                <svg class="w-4 h-4 transition-all group-hover:-translate-y-1" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                </svg>
                            </div>
                            <p class="px-3">Export Excel</p>
                        </button>
                    </a>
                    <div class="overflow-x-auto p-3">
                        <table id="orderItemsTable" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Kode Obat</th>
                                    <th class="px-4 py-3">Nama Obat</th>
                                    <th class="px-4 py-3">Satuan</th>
                                    <th class="px-4 py-3">QTY Awal</th>
                                    <th class="px-4 py-3">QTY Jual</th>
                                    <th class="px-4 py-3">QTY Beli</th>
                                    <th class="px-4 py-3">Saldo Sekarang</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
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
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let orderItemsTable;
        let startDate = '';
        let endDate = '';
        var searchMedicine = '';
        let tableData, selectedData = null;
        const form = document.getElementById('patientForm');

        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#dateRange", {
                mode: "range",
                dateFormat: "Y-m-d",
                onClose: function(selectedDates, dateStr) {

                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                        endDate = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                    } else {
                        startDate = '';
                        endDate = '';
                    }

                    orderItemsTable.ajax.reload();
                }
            });


            // DATATABLE INIT
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('supplies.getStockData') }}",
                    data: function(d) {
                        d.searchMedicine = searchMedicine;
                        d.start_date = startDate;
                        d.end_date = endDate;

                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },

                    {
                        data: 'code'
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'unit'
                    },
                    {
                        data: 'qty_start'
                    },
                    {
                        data: 'qty_sales'
                    },
                    {
                        data: 'qty_orders'
                    },

                    {
                        data: 'qty_now'
                    }

                ],
                paging: true,
                searching: false,
                info: false,

            });

        });

        function searchMedicines(medicine) {
            if (!medicine || medicine.trim() === '') {
                $('#medicine_name').val('');
                searchMedicine = '';
                orderItemsTable.ajax.reload();
                return;
            }

            searchMedicine = medicine;

            orderItemsTable.ajax.reload(function(json) {

                if (json.data && json.data.length > 0) {
                    let firstRow = json.data[0];

                    let firstMedicineName = firstRow.name ?? '-';

                    $('#medicine_name').val(firstMedicineName);

                    console.log('First medicine name:', firstMedicineName);
                } else {
                    console.log('No rows found for this medicine.');
                    $('#medicine_name').val('');
                }

            }, false);
        }
        // BACK BUTTON 
        $('#back').click(function() {
            window.location.href = "{{ route('home') }}";
        });
        $('#back').click(function() {
            form.reset();
            $('#patient_id').val('');
            $('#table-data tbody tr').removeClass('bg-blue-100');
        });
    </script>

@endsection
