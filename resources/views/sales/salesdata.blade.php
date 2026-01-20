@extends('layouts.app')

@section('title', 'Sales Data')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <style>
        /* Container */
        .dataTables_filter {
            display: block;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        /* Label */
        .dataTables_filter label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        /* Input */
        .dataTables_filter input {
            margin-left: 8px;
            padding: 8px 12px;
            width: 40%;
            font-size: 13px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            outline: none;
        }

        .dataTables_wrapper .dataTables_filter {
            float: none !important;
        }

        .dataTables_filter input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Data Penjualan</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Nomor</th>
                                    <th class="px-4 py-3">Nama Pelanggan/Pasien</th>
                                    <th class="px-4 py-3">Harga</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[50%] mx-auto">
                    <table id="items-table" class="table table-bordered w-full mt-4">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Medicine</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Disc</th>
                                <th>Total</th>
                            </tr>
                        </thead>
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
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <script>
        let tableData, selectedData = null;
        const form = document.getElementById('patientForm');

        $(function() {

            tableData = $('#table-data').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                ajax: '{{ route('salesdata.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'date'
                    },
                    {
                        data: 'code'
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'final_price'
                    }
                ]
            });



            let itemsTable;

            function loadItems(transactionId) {

                if (itemsTable) {
                    itemsTable.destroy();
                    $('#items-table tbody').empty();
                }

                itemsTable = $('#items-table').DataTable({
                    processing: true,
                    serverSide: true,
                    searchable: false,
                    searching: false,
                    ajax: `/sales/transaction/${transactionId}/items`,
                    columns: [{
                            data: 'DT_RowIndex',
                            orderable: false
                        },
                        {
                            data: 'medicine'
                        },
                        {
                            data: 'quantity'
                        },
                        {
                            data: 'price'
                        },
                        {
                            data: 'discount'
                        },
                        {
                            data: 'total'
                        }
                    ]
                });
            }

            $('#table-data tbody').on('click', 'tr', function() {

                const data = tableData.row(this).data();
                if (!data) return;

                const transactionId = data.transaction_id;

                // highlight selected row
                $('#table-data tbody tr').removeClass('bg-blue-100');
                $(this).addClass('bg-blue-100');

                loadItems(transactionId);
            });




            // BACK BUTTON 
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });
            $('#back').click(function() {
                form.reset();
                $('#patient_id').val('');
                $('#table-data tbody tr').removeClass('bg-blue-100');
            });

        });


        // ENTER NAVIGATION
        const inputs = ['name', 'address', 'phone', 'city', 'birth']
            .map(id => document.getElementById(id));

        inputs.forEach((input, index) => {
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (index < inputs.length - 1) inputs[index + 1].focus();
                    else handleSubmit();
                }
            });
        });


        // SUBMIT
        document.getElementById('submitForm').addEventListener('click', handleSubmit);

        function handleSubmit() {
            const formData = new FormData(form);
            const id = document.getElementById('patient_id').value;

            const url = id ?
                `/patients/${id}` :
                form.action;

            if (id) formData.append('_method', 'PUT');

            axios({
                    method: 'POST',
                    url: url,
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(res => {
                    iziToast.success({
                        title: 'Success',
                        message: res.data.message || 'Patient saved.'
                    });

                    form.reset();
                    $('#patient_id').val('');
                    tableData.ajax.reload(null, false);
                })
                .catch(err => {
                    let msg = 'Failed to save.';

                    if (err.response?.status === 422) {
                        msg = Object.values(err.response.data.errors)
                            .flat()
                            .join('<br>');
                    }

                    iziToast.error({
                        title: 'Error',
                        message: msg
                    });
                });
        }
    </script>

@endsection
