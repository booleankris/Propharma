@extends('layouts.app')

@section('title', 'Parameters')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                {{-- TABLE --}}
                <div class="card w-full md:w-[65%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800">Parameter Data</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm">
                            <thead class="bg-gray-100 text-gray-700 text-xs uppercase">
                                <tr>
                                    <th>#</th>
                                    <th>Debitur</th>
                                    <th>Receipt</th>
                                    <th>PDU</th>
                                    <th>OTC</th>
                                    <th>Credit</th>
                                    <th>Embalas</th>
                                    <th>Service</th>
                                    <th>Rounding</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- FORM --}}
                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[35%]">
                    <form id="parameterForm" class="space-y-2">
                        @csrf
                        <input type="hidden" id="parameter_id">

                        <div>
                            <label class="text-sm font-semibold">Debitur</label>
                            <select id="debtor_id" class="form-control w-full">
                                <option value="">-- Select Debitur --</option>
                                @foreach ($debtors as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>

                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <label class="text-sm font-semibold">Resep Tunai</label>

                                <input id="receipt" type="number" step="0.01"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Receipt">
                            </div>
                            <div>
                                <label class="text-sm font-semibold">UPDS</label>

                                <input id="pdu" type="number" step="0.01"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="PDU">
                            </div>

                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <label class="text-sm font-semibold">HV/OTC</label>
                                <input id="otc" type="number" step="0.01"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="OTC">

                            </div>
                            <div>
                                <label class="text-sm font-semibold">Resep Kredit</label>
                                <input id="credit" type="number" step="0.01"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Credit">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <label class="text-sm font-semibold">Embalase</label>
                                <input id="embalas" type="number"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Embalas">

                            </div>
                            <div>
                                <label class="text-sm font-semibold">Jasa Racik</label>
                                <input id="service" type="number"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Service">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            <div>
                                <label class="text-sm font-semibold">Pembulatan</label>
                                <input id="rounding" type="number"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Rounding">
                            </div>
                        </div>

                        {{-- BUTTONS --}}
                        <div class="flex gap-2 pt-4">
                            <button type="button" id="submitForm"
                                class="w-full bg-blue-500 hover:bg-blue-600 text-white rounded-lg py-2">
                                Submit
                            </button>
                            <button type="button" id="cancelEdit"
                                class="w-full bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg py-2">
                                Cancel
                            </button>
                            <button type="button" id="deleteData"
                                class="w-full bg-red-500 hover:bg-red-600 text-white rounded-lg py-2">
                                Delete
                            </button>
                            <button type="button" id="backBtn"
                                class="w-full bg-orange-500 hover:bg-orange-600 text-white rounded-lg py-2">
                                Back
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
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


    <script>
        $(document).ready(function() {

            $('#debtor_id').select2({
                placeholder: '-- Select Debtor --',
                width: '100%'
            });

        });
        let tableData, selectedData = null;

        $(function() {

            tableData = $('#table-data').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('parameters.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false
                    },
                    {
                        data: 'debtor_name'
                    },
                    {
                        data: 'receipt'
                    },
                    {
                        data: 'pdu'
                    },
                    {
                        data: 'otc'
                    },
                    {
                        data: 'credit'
                    },
                    {
                        data: 'embalas'
                    },
                    {
                        data: 'service'
                    },
                    {
                        data: 'rounding'
                    },
                ]
            });

            // CLICK ROW
            $('#table-data tbody').on('click', 'tr', function() {
                selectedData = tableData.row(this).data();
                if (!selectedData) return;

                $('#table-data tbody tr').removeClass('bg-blue-100');
                $(this).addClass('bg-blue-100');

                $('#parameter_id').val(selectedData.id);
                $('#debtor_id').val(selectedData.debtor_id);
                $('#receipt').val(selectedData.receipt);
                $('#pdu').val(selectedData.pdu);
                $('#otc').val(selectedData.otc);
                $('#credit').val(selectedData.credit);
                $('#embalas').val(selectedData.embalas);
                $('#service').val(selectedData.service);
                $('#rounding').val(selectedData.rounding);
            });

        });

        // SUBMIT / UPDATE
        $('#submitForm').click(function() {
            const id = $('#parameter_id').val();
            const url = id ? `/parameters/${id}` : `/parameters`;

            const data = {
                debtor_id: $('#debtor_id').val(),
                receipt: $('#receipt').val(),
                pdu: $('#pdu').val(),
                otc: $('#otc').val(),
                credit: $('#credit').val(),
                embalas: $('#embalas').val(),
                service: $('#service').val(),
                rounding: $('#rounding').val(),
                _method: id ? 'PUT' : 'POST'
            };

            axios.post(url, data)
                .then(res => {
                    iziToast.success({
                        title: 'Success',
                        message: res.data.message
                    });
                    resetForm();
                    tableData.ajax.reload(null, false);
                })
                .catch(err => {
                    iziToast.error({
                        title: 'Error',
                        message: 'Failed to save'
                    });
                });
        });

        // DELETE
        $('#deleteData').click(function() {
            const id = $('#parameter_id').val();
            if (!id) return;

            swal({
                title: 'Delete?',
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(ok => {
                if (!ok) return;

                axios.delete(`/parameters/${id}`)
                    .then(res => {
                        iziToast.success({
                            title: 'Deleted',
                            message: res.data.message
                        });
                        resetForm();
                        tableData.ajax.reload(null, false);
                    });
            });
        });

        // RESET
        $('#cancelEdit').click(resetForm);
        $('#backBtn').click(function() {
            window.location.href = "{{ route('home') }}";
        });

        const parameterInputs = [
            document.getElementById('debtor_id'),
            document.getElementById('receipt'),
            document.getElementById('pdu'),
            document.getElementById('otc'),
            document.getElementById('credit'),
            document.getElementById('embalas'),
            document.getElementById('service'),
            document.getElementById('rounding'),
        ];

        function getNextFocusable(inputs, index) {
            for (let i = index + 1; i < inputs.length; i++) {
                const el = inputs[i];

                if (
                    el &&
                    !el.disabled &&
                    !el.readOnly &&
                    el.offsetParent !== null &&
                    el.tabIndex !== -1
                ) {
                    return el;
                }
            }
            return null;
        }
        parameterInputs.forEach((input, index) => {
            if (!input) return;

            // NORMAL INPUTS
            input.addEventListener('keydown', e => {
                if (e.key === 'Enter') {
                    e.preventDefault();

                    const next = getNextFocusable(parameterInputs, index);

                    if (next) next.focus();
                    else $('#submitForm').click();
                }
            });
        });
        $('#debtor_id').on('select2:select', function() {
            const index = parameterInputs.findIndex(el => el?.id === 'debtor_id');
            const next = getNextFocusable(parameterInputs, index);

            if (next) {
                setTimeout(() => next.focus(), 100);
            }
        });


        function resetForm() {
            $('#parameterForm')[0].reset();
            $('#parameter_id').val('');
            $('#table-data tbody tr').removeClass('bg-blue-100');
        }
    </script>
@endsection
