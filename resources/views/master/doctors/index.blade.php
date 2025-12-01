@extends('layouts.app')

@section('title', 'Dokter')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">

            <div class="flex flex-col lg:flex-row gap-4">

                {{-- LEFT: TABLE --}}
                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M16 11c1.657 0 3-1.343 3-3S17.657 5 16 5s-3 1.343-3 3 1.343 3 3 3zM8 11c1.657 0 3-1.343 3-3S9.657 5 8 5 5 6.343 5 8s1.343 3 3 3zm0 2c-2.5 0-4.5 1.5-4.5 3.5V19h9v-2.5C12.5 14.5 10.5 13 8 13zm8 0c-2.5 0-4.5 1.5-4.5 3.5V19h9v-2.5c0-2-2-3.5-4.5-3.5z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 tracking-wide drop-shadow-sm">Data Dokter</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Pharmacy</th>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Specialist</th>
                                    <th class="px-4 py-3">Address</th>
                                    <th class="px-4 py-3">City</th>
                                    <th class="px-4 py-3">Phone</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                {{-- RIGHT: FORM --}}
                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[35%] mx-auto">
                    <form id="doctorForm" action="{{ route('doctors.store') }}" method="POST" class="space-y-2">
                        @csrf

                        <input type="hidden" id="doctor_id" name="id">

                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Pharmacy</label>
                            <select id="pharmacy_id" name="pharmacy_id"
                                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm">
                                <option value="">-- Select Pharmacy --</option>
                                @foreach ($pharmacies as $pharmacy)
                                    <option value="{{ $pharmacy->id }}">{{ $pharmacy->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">Code</label>
                                <input id="code" name="code" readonly
                                    class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2 text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">Name</label>
                                <input id="name" name="name"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                    placeholder="Enter name">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Specialist</label>
                            <input id="specialist" name="specialist"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                placeholder="Enter specialist">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Address</label>
                            <input id="address" name="address"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                placeholder="Enter address">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">City</label>
                                <input id="city" name="city"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                    placeholder="Enter city">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1">Phone</label>
                                <input id="phone" name="phone"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm"
                                    placeholder="Enter phone">
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 pt-3">
                            <button type="button" id="submitForm"
                                class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow">
                                Submit
                            </button>

                            <button type="button" id="cancelEdit"
                                class="px-4 py-2 bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg shadow">
                                Cancel
                            </button>

                            <button type="button" id="deleteData"
                                class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg shadow">
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
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        let tableData, selectedData = null;
        const form = document.getElementById('doctorForm');

        $(function() {

            $('#pharmacy_id').select2({
                placeholder: '-- Select Pharmacy --',
                width: '100%'
            });

            tableData = $('#table-data').DataTable({
                responsive: true,
                processing: true,
                serverSide: true,
                ajax: '{{ route('doctors.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'pharmacy_name',
                        name: 'pharmacy.name'
                    },
                    {
                        data: 'code',
                        name: 'code'
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'specialist',
                        name: 'specialist'
                    },
                    {
                        data: 'address',
                        name: 'address'
                    },
                    {
                        data: 'city',
                        name: 'city'
                    },
                    {
                        data: 'phone',
                        name: 'phone'
                    },
                ],
            });

            // Row select
            $('#table-data tbody').on('click', 'tr', function() {
                selectedData = tableData.row(this).data();
                if (!selectedData) return;

                $('#table-data tbody tr').removeClass('bg-blue-100');
                $(this).addClass('bg-blue-100');

                $('#doctor_id').val(selectedData.id);
                $('#pharmacy_id').val(selectedData.pharmacy_id).trigger('change');

                $('#code').val(selectedData.code);
                $('#name').val(selectedData.name);
                $('#specialist').val(selectedData.specialist);
                $('#address').val(selectedData.address);
                $('#city').val(selectedData.city);
                $('#phone').val(selectedData.phone);
            });

            $('#cancelEdit').on('click', function() {
                form.reset();
                $('#pharmacy_id').val('').trigger('change');
                $('#doctor_id').val('');
                $('#table-data tbody tr').removeClass('bg-blue-100');
                selectedData = null;
            });
            // BACK
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });
            // DELETE
            $('#deleteData').on('click', function() {
                const id = $('#doctor_id').val();
                if (!id)
                    return iziToast.warning({
                        title: 'Warning',
                        message: 'No data selected!',
                        position: 'topRight'
                    });

                axios.delete('/doctors/' + id)
                    .then(res => {
                        iziToast.success({
                            title: 'Deleted',
                            message: res.data.message,
                            position: 'topRight'
                        });
                        $('#cancelEdit').click();
                        tableData.ajax.reload();
                    });
            });
        });
        // === ENTER Key Navigation for Doctors ===

        // Order of fields in the Doctors form
        const fieldOrder = [
            "pharmacy_id", // select2
            "code",
            "name",
            "specialist",
            "address",
            "city",
            "phone"
        ];

        function getField(id) {
            return document.getElementById(id);
        }

        const fields = fieldOrder.map(id => getField(id));

        fields.forEach((field, index) => {
            // Select2 requires special event handling
            if (field && field.tagName === "SELECT") {

                $(field).on("select2:close", function() {
                    const nextField = fields[index + 1];
                    if (nextField) nextField.focus();
                });

            } else if (field) {

                field.addEventListener("keydown", e => {
                    if (e.key === "Enter") {
                        e.preventDefault();

                        const nextField = fields[index + 1];

                        if (nextField) {
                            nextField.focus();
                        } else {
                            handleSubmit(); // last field
                        }
                    }
                });
            }
        });



        document.getElementById('submitForm').addEventListener('click', handleSubmit);

        function handleSubmit() {
            const id = $('#doctor_id').val();
            const formData = new FormData(form);
            const url = id ? `/doctors/${id}` : form.action;

            if (id) formData.append('_method', 'PUT');

            axios.post(url, formData)
                .then(res => {
                    iziToast.success({
                        title: 'Success',
                        message: res.data.message,
                        position: 'topRight'
                    });
                    $('#cancelEdit').click();
                    tableData.ajax.reload();
                })
                .catch(err => {
                    let msg = 'Failed to save.';
                    if (err.response?.status === 422) {
                        msg = Object.values(err.response.data.errors).flat().join('<br>');
                    }
                    iziToast.error({
                        title: 'Error',
                        message: msg,
                        position: 'topRight'
                    });
                });
        }
    </script>
@endsection
