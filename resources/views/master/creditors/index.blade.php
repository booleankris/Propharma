@extends('layouts.app')

@section('title', 'Kreditur')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 11c1.657 0 3-1.343 3-3S17.657 5 16 5s-3 1.343-3 3 1.343 3 3 3zM8 11c1.657 0 3-1.343 3-3S9.657 5 8 5 5 6.343 5 8s1.343 3 3 3zm0 2c-2.5 0-4.5 1.5-4.5 3.5V19h9v-2.5C12.5 14.5 10.5 13 8 13zm8 0c-2.5 0-4.5 1.5-4.5 3.5V19h9v-2.5c0-2-2-3.5-4.5-3.5z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 tracking-wide drop-shadow-sm">Data Kreditur</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Address</th>
                                    <th class="px-4 py-3">PPN</th>
                                    <th class="px-4 py-3">Phone</th>
                                    <th class="px-4 py-3">Waktu Kredit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[35%] mx-auto">
                    <form id="creditorForm" action="{{ route('creditors.store') }}" method="POST" class="space-y-2">
                        @csrf
                        <input type="hidden" id="creditor_id" name="id">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Code</label>
                                <input id="code" name="code" readonly
                                    class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200">
                            </div>

                            <div>
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Name</label>
                                <input id="name" name="name" type="text"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Masukkan Nama">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Alamat</label>
                            <input id="address" name="address" type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Masukkan alamat">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">

                            <div>
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Phone</label>
                                <input id="phone" name="phone" type="number"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Masukkan No.Telp">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">No. Seri</label>
                            <input id="numbers" name="numbers" type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Masukkan No. Seri Kreditur">
                        </div>

                        <div>
                            <div>
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Jenis PPN</label>
                                <select id="ppn_type" name="ppn_type"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px] focus:ring-2 focus:ring-blue-200"
                                    placeholder="PPN">

                                    <option value="INCLUDE">Include
                                    </option>
                                    <option value="EXCLUDE">Exclude
                                    </option>
                                    <option value="TANPA">Tanpa
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Bank</label>
                                <input id="bank_type" name="bank_type" type="text"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Nama Bank">
                            </div>

                            <div>
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Nomor Rekening</label>
                                <input id="bank_number" name="bank_number" type="number"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Masukkan No.Rekening">
                            </div>

                        </div>
                        <div>
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Nama Rekening</label>
                            <input id="bank_name" name="bank_name" type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Nama Rekening">
                        </div>
                        <div>
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">NPWP</label>
                            <input id="npwp" name="npwp" type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="NPWP">
                        </div>
                        <div class="flex gap-2 pt-3 flex-wrap">
                            <div>
                                <button type="button" id="submitForm"
                                    class="px-5 py-3 w-full bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-lg hover:shadow-blue-400/70 transition-all duration-300">
                                    Submit
                                </button>
                            </div>
                            <div>
                                <button type="button" id="cancelEdit"
                                    class="px-5 py-3 w-full bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg shadow-lg hover:shadow-yellow-300/70 transition-all duration-300">
                                    Cancel
                                </button>
                            </div>
                            <div>
                                <button type="button" id="deleteData"
                                    class="px-5 py-3 w-full bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-lg hover:shadow-red-400/70 transition-all duration-300">
                                    Delete
                                </button>
                            </div>
                            <div>
                                <button type="button" id="back"
                                    class="px-5 py-3 w-full bg-[#FF9800] hover:bg-[#FF9232] text-white rounded-lg shadow-lg hover:shadow-red-400/70 transition-all duration-300">
                                    Kembali
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <!-- JS Libraries -->
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <script type="text/javascript">
        let tableData, selectedData = null;
        const form = document.getElementById('creditorForm');

        $(function() {
            tableData = $('#table-data').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                ajax: '{{ route('creditors.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
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
                        data: 'address'
                    },
                    {
                        data: 'ppn_type'
                    },
                    {
                        data: 'phone'
                    },
                    {
                        data: 'credit_time'
                    },
                ],
            });

            // Row select to edit
            $('#table-data tbody').on('click', 'tr', function() {
                selectedData = tableData.row(this).data();
                if (!selectedData) return;
                console.log(selectedData);
                $('#table-data tbody tr').removeClass('bg-blue-100');
                $(this).addClass('bg-blue-100');

                $('#creditor_id').val(selectedData.id);
                $('#code').val(selectedData.code);
                $('#name').val(selectedData.name);
                $('#address').val(selectedData.address);
                $('#phone').val(selectedData.phone);
                $('#numbers').val(selectedData.numbers);
                $('#ppn_type').val(selectedData.ppn_type);
                $('#bank_type').val(selectedData.bank_type);
                $('#bank_name').val(selectedData.bank_name);
                $('#bank_number').val(selectedData.bank_number);
                $('#npwp').val(selectedData.npwp);
            });
            // BACK
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });
            // Cancel
            $('#cancelEdit').on('click', function() {
                form.reset();
                $('#creditor_id').val('');
                $('#table-data tbody tr').removeClass('bg-blue-100');
                selectedData = null;
            });

            // Delete
            $('#deleteData').on('click', function() {
                const id = $('#creditor_id').val();
                if (!id) return iziToast.warning({
                    title: 'No Row Selected',
                    message: 'Select a row to delete.'
                });

                swal({
                    title: 'Are you sure?',
                    text: 'This will delete the selected creditor.',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        $.ajax({
                            url: '/creditors/' + id,
                            type: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}'
                            },
                            success: function(res) {
                                iziToast.success({
                                    title: 'Deleted',
                                    message: res.message ??
                                        'Creditor deleted successfully!',
                                    position: 'topRight'
                                });
                                $('#cancelEdit').click();
                                tableData.ajax.reload(null, false);
                            }
                        });
                    }
                });
            });
        });

        // ENTER to move next
        const inputs = {
            code: document.getElementById('code'),
            name: document.getElementById('name'),
            address: document.getElementById('address'),
            phone: document.getElementById('phone'),
            numbers: document.getElementById('numbers'),
            ppn_type: document.getElementById('ppn_type'),
            bank_type: document.getElementById('bank_type'),
            bank_number: document.getElementById('bank_number'),
            bank_name: document.getElementById('bank_name'),
            npwp: document.getElementById('npwp'),
        };

        Object.values(inputs).forEach(input => {
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    switch (input.id) {
                        case 'name':
                            inputs.address.focus();
                            break;
                        case 'address':
                            inputs.phone.focus();
                            break;
                        case 'phone':
                            inputs.numbers.focus();
                            break;
                        case 'numbers':
                            inputs.ppn_type.focus();
                            break;
                        case 'ppn_type':
                            inputs.bank_type.focus();
                            break;
                        case 'bank_type':
                            inputs.bank_number.focus();
                            break;
                        case 'bank_number':
                            inputs.bank_name.focus();
                            break;
                        case 'bank_name':
                            inputs.npwp.focus();
                            break;
                        case 'npwp':
                            handleSubmit();
                            break;
                    }
                }
            });
        });

        // submit button
        document.getElementById('submitForm').addEventListener('click', handleSubmit);

        function handleSubmit() {
            const formData = new FormData(form);
            const id = document.getElementById('creditor_id').value;
            const url = id ? `/creditors/${id}` : form.action;

            if (id) formData.append('_method', 'PUT');

            axios({
                    method: 'POST',
                    url,
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(response => {
                    const res = response.data;
                    if (res.success) {
                        iziToast.success({
                            title: 'Success!',
                            message: res.message,
                            position: 'topRight',
                        });
                        form.reset();
                        $('#creditor_id').val('');
                        tableData.ajax.reload(null, false);
                    } else {
                        iziToast.error({
                            title: 'Failed!',
                            message: res.message,
                            position: 'topRight',
                        });
                    }
                })
                .catch(error => {
                    let message = 'Failed to send data.';

                    if (error.response && error.response.status === 422) {
                        const errors = error.response.data.errors;
                        message = Object.values(errors).flat().join('<br>');
                    } else if (error.response?.data?.message) {
                        message = error.response.data.message;
                    }

                    iziToast.error({
                        title: 'Error!',
                        message,
                        position: 'topRight'
                    });
                });
        }
    </script>
@endsection
