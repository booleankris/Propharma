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
            padding: 9px 18px !important;
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
            padding: 15px 0px;
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

        .panel {
            height: 40vh;
            overflow: scroll;
            padding: 12px;
            background: #f6f9ff;
            border-radius: 20px;
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
                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Stock Opname</h2>

                    </div>
                    <div class="flex gap-10">

                        <div>
                            <div class="flex py-2 gap-1">

                                <div>
                                    <div class="py-1 text-[13px] font-bold">Tanggal</div>

                                    <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        autocomplete="off">
                                </div>
                                <div class="w-full">
                                    {{-- Fill this after too after selecting --}}
                                    <div class="py-1 text-[13px] font-bold">Nama Obat</div>

                                    <input type="text" readonly id="medicine_name" placeholder="Nama Obat..."
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        autocomplete="off">
                                </div>
                            </div>
                            <p class="font-poppins pb-1 font-medium">
                                Pilih Obat
                            </p>
                            <div>
                                <table id="medicines_data" class="min-w-full text-sm text-left text-gray-600">
                                    <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                        <tr>
                                            <th class="px-4 py-3">#</th>
                                            <th class="px-4 py-3">Nama</th>
                                            <th class="px-4 py-3">Satuan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100"></tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <div class="panel">
                                <input type="hidden" id="medicine_id">
                                <input type="hidden" id="medicine_stock">

                                <div class="overflow-x-auto p-3">
                                    <table id="orderItemsTable" class="min-w-full text-sm text-left text-gray-600">
                                        <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                            <tr>
                                                <th class="px-4 py-3">#</th>
                                                <th class="px-4 py-3">Tanggal</th>
                                                <th class="px-4 py-3">Kode Transaksi</th>
                                                <th class="px-4 py-3">Tipe</th>
                                                <th class="px-4 py-3">Saldo Awal</th>
                                                <th class="px-4 py-3">Qty</th>
                                                <th class="px-4 py-3">Jumlah</th>
                                                <th class="px-4 py-3">Saldo Sekarang</th>
                                                <th class="px-4 py-3">Keterangan</th>

                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="mt-3 flex items-center space-x-6">
                                <div>
                                    <p class="text-2xl font-bold text-[#2563eb]" id="qty_awal">-</p>
                                    <p class="text-sm font-poppins text-gray-400">QTY Awal</p>
                                </div>
                                <div class="h-12 w-px bg-gray-700"></div>
                                <div>
                                    <p class="text-2xl font-bold text-[#2563eb]" id="qty_beli">-</p>
                                    <p class="text-sm font-poppins text-gray-400">QTY Beli</p>
                                </div>
                                <div class="h-12 w-px bg-gray-700"></div>
                                <div>
                                    <p class="text-2xl font-bold text-[#2563eb]" id="qty_jual">-</p>
                                    <p class="text-sm font-poppins text-gray-400">QTY Jual</p>
                                </div>
                                <div class="h-12 w-px bg-gray-700"></div>
                                <div>
                                    <p class="text-2xl font-bold text-[#2563eb]" id="qty_akhir">-</p>
                                    <p class="text-sm font-poppins text-gray-400">QTY Akhir</p>
                                </div>
                            </div>
                            <div class="flex py-2 gap-1">
                                <div class="w-full">
                                    <div class="py-1 text-[13px] font-bold">Stok Fisik</div>
                                    <input type="number" required onkeyup="countDiscrepancy()" id="stock_physic"
                                        placeholder="Stok Fisik..."
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        autocomplete="off">
                                </div>
                                <div class="w-full">
                                    <div class="py-1 text-[13px] font-bold">Selisih Stok</div>
                                    <input type="text" required readonly id="stock_discrepancy" placeholder="Selisih...."
                                        class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                        autocomplete="off">
                                </div>
                            </div>

                            <div class="py-1 flex gap-2">
                                <button id="save_opname"
                                    class="inline-flex font-poppins items-center gap-2 rounded-lg btn-pharma !bg-blue-600 !shadow-[0_2px_6px_#2563eb] px-6 py-4 text-sm font-xl text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" class="w-5 h-5" stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                        <polyline points="7 3 7 8 15 8"></polyline>
                                    </svg>
                                    Simpan
                                </button>
                                <button id="back"
                                    class="inline-flex !text-[#fff] font-poppins items-center gap-2 rounded-lg !bg-[#b20b0b] !shadow-[0_2px_6px_#b20b0b] px-6 py-4 text-sm font-xl hover:bg-[#b20b0b !important] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Kembali
                                </button>
                                <a href="{{ route('supplies.printstockopname') }}" target="_blank">
                                    <button style="background:#41bd33"
                                        class="group rounded-md shadow text-white cursor-pointer flex justify-between items-center overflow-hidden transition-all hover:glow">
                                        <div
                                            class="relative px-6 py-5 bg-white bg-opacity-20 flex justify-center items-center transition-all">
                                            <svg class="w-4 h-4 transition-all group-hover:-translate-y-1" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                            </svg>
                                        </div>
                                        <p class="px-3">Export Excel</p>
                                    </button>
                                </a>
                            </div>
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
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let total_stock = '';
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
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                deferLoading: 0,
                ajax: {
                    url: "{{ route('supplies.medicineStockLog') }}",
                    data: function(d) {
                        d.searchMedicine = searchMedicine;
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: true
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'transaction_code'
                    },
                    {
                        data: 'type'
                    },
                    {
                        data: 'qty_before'
                    },
                    {
                        data: 'stock'
                    },
                    {
                        data: 'qty_after'
                    },
                    {
                        data: 'supply'
                    },
                    {
                        data: 'status'
                    }
                ],
                paging: true,
                searching: false,
                info: false,
                language: {
                    emptyTable: "Silakan pilih obat terlebih dahulu"
                }
            });


            medicineData = $("#medicines_data").DataTable({
                responsive: true,
                serverSide: true,
                ajax: "{{ route('supplies.medicines') }}",
                columns: [{
                        data: "DT_RowIndex",
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: "name"
                    },
                    {
                        data: "unit"
                    }
                ],
                initComplete: function() {
                    $('#medicines_data_filter input').focus();
                }
            });

            $('#medicines_data tbody').on('dblclick', 'tr', function() {
                let medicine = medicineData.row(this).data();
                if (!medicine) return;

                $('#medicine_name').val(medicine.name);
                searchMedicine = medicine.name;

                orderItemsTable.ajax.reload(function() {
                    let rows = orderItemsTable.rows().nodes();
                    $(rows).removeClass('table-primary');

                    let rowFound = false;
                    orderItemsTable.rows().every(function() {
                        let rowData = this.data();
                        if (rowData.name === searchMedicine) {
                            $(this.node()).addClass('table-primary');

                            // Set QTY
                            $('#qty_awal').text(rowData.stock_start || 0);
                            $('#qty_beli').text(rowData.total_orders || 0);
                            $('#qty_jual').text(rowData.total_sales || 0);
                            $('#qty_akhir').text(rowData.medicines.stock || 0);
                            console.log(rowData);
                            $('#medicine_stock').val(rowData.supply);
                            $('#medicine_id').val(medicine.id);

                            rowFound = true;
                            total_stock = rowData.medicines.stock;

                            return false;
                        }
                    });

                    if (!rowFound) {
                        $('#medicine_stock').val('');
                    }
                });
            });


            window.searchMedicines = function(medicine) {
                searchMedicine = medicine || '';
                orderItemsTable.ajax.reload(function() {
                    let rowFound = false;
                    orderItemsTable.rows().every(function() {
                        let rowData = this.data();
                        if (rowData.name === searchMedicine) {
                            $(this.node()).addClass('table-primary');
                            $('#medicine_name').val(rowData.name);
                            $('#medicine_stock').val(rowData.supply);
                            rowFound = true;
                            return false;
                        }
                    });

                    if (!rowFound) {
                        $('#medicine_name').val('');
                        $('#medicine_stock').val('');
                    }
                });
            };

        });

        function countDiscrepancy() {
            let stockPhysic = parseInt($('#stock_physic').val() || 0);
            let stockSystem = total_stock || 0;
            let discrepancy = stockPhysic - stockSystem;
            $('#stock_discrepancy').val(discrepancy);
            if (discrepancy !== 0) {
                discrepancyInput.classList.add('border-red-500', 'text-red-600');
            } else {
                discrepancyInput.classList.remove('border-red-500', 'text-red-600');
            }
            if (stockPhysic == "") {
                $('#stock_discrepancy').val("");
            }
        }

        function SaveOpname() {
            let medicineId = $('#medicine_id').val();
            let stockPhysic = parseInt($('#stock_physic').val()) || 0;
            let stockSystem = parseInt($('#medicine_stock').val()) || 0;
            let discrepancy = stockPhysic - stockSystem;

            if (!medicineId) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Pilih obat terlebih dahulu!',
                    position: 'topRight'
                });
                return;
            }

            $.ajax({
                url: "{{ route('supplies.opname') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    medicine_id: medicineId,
                    stock_physic: stockPhysic,
                    stock_system: stockSystem,
                    stock_discrepancy: discrepancy
                },
                success: function(response) {
                    iziToast.success({
                        title: 'Berhasil',
                        message: 'Stok berhasil disimpan!',
                        position: 'topRight'
                    });

                    // Reset form
                    $('#stock_physic, #stock_discrepancy, #medicine_name, #medicine_stock, #medicine_id').val(
                        '');

                    // Reload table
                    orderItemsTable.ajax.reload(function() {
                        // Optional: refresh QTY summary cards again after save
                        let rows = orderItemsTable.rows().data().toArray();
                        let stockData = rows.find(row => row.name === searchMedicine);

                        if (stockData) {
                            $('#qty_awal').text(stockData.qty_before_number || 0);
                            $('#qty_beli').text(stockData.total_orders || 0);
                            $('#qty_jual').text(stockData.total_sales || 0);
                            $('#qty_akhir').text(stockData.supply || 0);
                        }
                    });
                },
                error: function(xhr) {
                    iziToast.error({
                        title: 'Gagal',
                        message: 'Terjadi kesalahan saat menyimpan stok!',
                        position: 'topRight'
                    });
                    console.error(xhr.responseText);
                }
            });
        }
        $('#save_opname').click(SaveOpname);
        $('#stock_physic').keydown(function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                SaveOpname();

            }
        });
        // BACK BUTTON 
        $('#back').click(function() {
            window.location.href = "{{ route('home') }}";
        });
    </script>

@endsection
