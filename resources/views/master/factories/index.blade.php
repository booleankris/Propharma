@extends('layouts.app')

@section('title', 'Factories')

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
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Data Factories</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[35%] mx-auto">
                    <form id="factoryForm" action="{{ route('factories.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" id="factory_id" name="id">

                        <div>
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Code</label>
                            <input id="code" name="code" readonly
                                class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200">
                        </div>

                        <div>
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Name</label>
                            <input id="name" name="name" type="text"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Enter name">
                        </div>

                        {{-- Buttons --}}
                        <div class="flex justify-end gap-2 pt-3">
                            <button type="button" id="submitForm"
                                class="px-5 py-3 w-full bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-lg hover:shadow-blue-400/70 transition-all duration-300">
                                Submit
                            </button>
                            <button type="button" id="cancelEdit"
                                class="px-5 py-3 w-full bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg shadow-lg hover:shadow-yellow-300/70 transition-all duration-300">
                                Cancel
                            </button>
                            <button type="button" id="deleteData"
                                class="px-5 py-3 w-full bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-lg hover:shadow-red-400/70 transition-all duration-300">
                                Delete
                            </button>
                            <button type="button" id="back"
                                class="px-5 py-3 w-full bg-[#FF9800] hover:bg-[#FF9232] text-white rounded-lg shadow-lg hover:shadow-red-400/70 transition-all duration-300">
                                Kembali
                            </button>
                        </div>

                    </form>
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
        const form = document.getElementById('factoryForm');

        $(function() {

            // DATATABLE
            tableData = $('#table-data').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                ajax: '{{ route('factories.index') }}',
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
                        data: 'status_label'
                    },
                ],
            });

            // CLICK ROW → EDIT
            $('#table-data tbody').on('click', 'tr', function() {
                selectedData = tableData.row(this).data();
                if (!selectedData) return;

                $('#table-data tbody tr').removeClass('bg-blue-100');
                $(this).addClass('bg-blue-100');

                $('#factory_id').val(selectedData.id);
                $('#code').val(selectedData.code);
                $('#name').val(selectedData.name);
            });

            // BACK
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });
            // CANCEL
            $('#cancelEdit').click(function() {
                form.reset();
                $('#factory_id').val('');
                $('#table-data tbody tr').removeClass('bg-blue-100');
            });

            // DELETE
            $('#deleteData').click(function() {
                const id = $('#factory_id').val();

                if (!id) {
                    return iziToast.warning({
                        title: 'Warning',
                        message: 'Select a factory to delete.'
                    });
                }

                swal({
                    title: 'Delete?',
                    text: 'This factory will be removed permanently.',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then(function(yes) {
                    if (!yes) return;

                    $.ajax({
                        url: '/factories/' + id,
                        type: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function(res) {
                            iziToast.success({
                                title: 'Deleted',
                                message: res.message
                            });
                            $('#cancelEdit').click();
                            tableData.ajax.reload(null, false);
                        }
                    });
                });
            });

        });

        // ENTER NAVIGATION
        document.getElementById('name').addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleSubmit();
            }
        });

        // SUBMIT FORM
        document.getElementById('submitForm').addEventListener('click', handleSubmit);

        function handleSubmit() {
            const formData = new FormData(form);
            const id = document.getElementById('factory_id').value;

            const url = id ? `/factories/${id}` : form.action;
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
                        message: res.data.message
                    });

                    form.reset();
                    $('#factory_id').val('');
                    tableData.ajax.reload(null, false);
                })
                .catch(err => {
                    let msg = 'Failed to save.';
                    if (err.response?.status === 422) {
                        msg = Object.values(err.response.data.errors).flat().join('<br>');
                    }
                    iziToast.error({
                        title: 'Error',
                        message: msg
                    });
                });
        }
    </script>
@endsection
