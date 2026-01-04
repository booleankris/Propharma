@extends('layouts.app')

@section('title', 'Medicines')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 42px !important;
            /* match your Tailwind input height */
            padding: 7px 10px !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                <div class="card w-full md:w-[40%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-blue-600 mr-3 drop-shadow-md"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Data Medicines</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Code</th>
                                    <th class="px-4 py-3">Name</th>
                                    <th class="px-4 py-3">Category</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[60%] mx-auto">
                    <form id="medicineForm" action="{{ route('medicines.store') }}" method="POST" class="space-y-3">
                        @csrf
                        <input type="hidden" id="medicine_id" name="id">

                        {{-- CODE --}}
                        <div class="flex gap-1">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Code</label>
                                <input id="code" name="code" readonly
                                    class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-[13px]">
                            </div>
                            {{-- MEDICINE CATEGORY --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Golongan Obat
                                </label>
                                <select id="medicine_category_id" name="medicine_category_id"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-10 text-[13px]">
                                </select>
                            </div>
                        </div>

                        {{-- COMPOSITION --}}
                        <div class="flex gap-2 items-center">
                            {{-- COMPOSITION --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Komposisi</label>
                                <select id="composition_id" name="composition_id"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Select Composition --</option>
                                </select>
                            </div>

                            {{-- FACTORY --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Pabrik</label>
                                <select id="factory_id" name="factory_id"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Pabrik --</option>
                                </select>
                            </div>

                        </div>
                        {{-- GENERIC TEXT --}}
                        <div>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="generic_check" name="generic_check" value="1"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="text-sm">Generik</span>
                            </label>
                        </div>
                        {{-- NAME --}}
                        <div class="w-full">
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Nama Obat</label>
                            <input id="name" name="name" type="text"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                placeholder="Masukkan Nama Obat">
                        </div>
                        {{-- PHARMACY --}}
                        <div class="hidden">
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Kreditur</label>
                            <input id="pharmacy_id" name="pharmacy_id" value="1" type="number"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                        </div>
                        <div class="flex gap-2">
                            {{-- PACKAGING --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Kemasan</label>
                                <input id="packaging" name="packaging" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            {{-- Satuan --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Satuan</label>
                                <input id="unit" name="unit" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            {{-- Isi --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Isi</label>
                                <input id="content" name="content" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            {{-- DOSAGE --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Dosage</label>
                                <input id="dosage" name="dosage" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                        </div>
                        <div class="flex gap-2">
                            {{-- Sediaan --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Sediaan</label>
                                <input id="preparations" name="preparations" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            {{-- RAW PRICE
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga HNA</label>
                                <input id="raw_price" name="raw_price" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>

                            {{-- PHARMACY NET PRICE --}}
                            {{-- <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga PPN 11%</label>
                                <input id="pharmacy_net_price" name="pharmacy_net_price" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div> --}}

                            {{-- NET PRICE --}}
                            {{-- <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">HJA/HET</label>
                                <input id="net_price" name="net_price" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>  --}}
                            {{-- <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga HNA</label>
                                <input id="raw_price" name="raw_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>

                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga PPN 11%</label>
                                <input id="pharmacy_net_price" name="pharmacy_net_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>

                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">HJA/HET</label>
                                <input id="net_price" name="net_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div> --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga Utuh</label>
                                <input id="raw_price" name="raw_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>

                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga HNA</label>
                                <input id="pharmacy_net_price" name="pharmacy_net_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga PPN 11%</label>
                                <input id="net_price" name="net_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga HET</label>
                                <input id="het_price" name="het_price" value="0" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>

                        </div>

                        <div class="flex gap-2">


                            {{-- MIN STOCK --}}

                        </div>
                        <div class="flex gap-2">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Stok Minimal</label>
                                <input id="minimal_stock" name="minimal_stock" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>

                            {{-- STOCK --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Stok</label>
                                <input id="stock" name="stock" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>

                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Creditor</label>
                                <select id="creditors_id" name="creditors_id"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Select Creditor --</option>
                                </select>
                            </div>
                        </div>
                        {{-- CHECKBOX GROUPS --}}
                        <div class="flex gap-5">
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="whole" name="whole" value="1"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="text-sm">Utuh</span>
                            </label>

                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="precursor" name="precursor" value="1"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="text-sm">Precursor</span>
                            </label>

                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="psychotropic" name="psychotropic" value="1"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="text-sm">Psychotropic</span>
                            </label>

                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="receipt" name="receipt" value="1"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="text-sm">Receipt</span>
                            </label>

                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="status" name="status" value="1" checked
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="text-sm">Active</span>
                            </label>
                        </div>

                        {{-- BUTTONS --}}
                        <div class="flex justify-end gap-2 pt-3">
                            <button type="button" id="submitForm"
                                class="px-5 py-3 w-full bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-lg">
                                Submit
                            </button>
                            <button type="button" id="cancelEdit"
                                class="px-5 py-3 w-full bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg shadow-lg">
                                Cancel
                            </button>
                            <button type="button" id="deleteData"
                                class="px-5 py-3 w-full bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-lg">
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
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


    {{-- Form --}}
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const form = document.getElementById("medicineForm");
            const creditorSelect = document.getElementById("creditors_id");

            const focusableSelectors = `
                input:not([type="hidden"]):not([disabled]),
                select:not([disabled]),
                textarea:not([disabled]),
                button:not([disabled])
            `;

            const getFocusableElements = () =>
                Array.from(form.querySelectorAll(focusableSelectors))
                .filter(el => el.offsetParent !== null);

            form.addEventListener("keydown", function(e) {
                if (e.key !== "Enter") return;

                if (e.target.tagName === "TEXTAREA") return;

                e.preventDefault();

                const focusables = getFocusableElements();
                const index = focusables.indexOf(e.target);

                if (index > -1 && index < focusables.length - 1) {
                    const next = focusables[index + 1];

                    if (next.classList.contains("select2")) {
                        $(next).select2("open");
                    } else {
                        next.focus();
                    }
                }
            });

            $(creditorSelect).on("select2:select", function(e) {
                handleSubmit();
            });
        });
    </script>

    <script>
        function formatRupiah(input) {
            let value = input.value.replace(/[^0-9]/g, '');
            if (!value) return input.value = '';

            let formatted = new Intl.NumberFormat('id-ID').format(value);
            input.value = 'Rp ' + formatted;
        }

        document.getElementById('raw_price').addEventListener('input', function() {
            formatRupiah(this);
        });
        document.getElementById('het_price').addEventListener('input', function() {
            formatRupiah(this);
        });

        document.getElementById('pharmacy_net_price').addEventListener('input', function() {
            formatRupiah(this);
            calculatePPN();

        });

        document.getElementById('net_price').addEventListener('input', function() {
            formatRupiah(this);
        });

        function cleanRupiah(value) {
            return value.replace(/[^0-9]/g, '');
        }

        function formatRupiah(input) {
            let value = cleanRupiah(input.value);

            if (value === "") {
                input.value = "";
                return;
            }

            input.value = "Rp " + new Intl.NumberFormat("id-ID").format(value);
        }

        function calculatePPN() {
            let rawValue = cleanRupiah(document.getElementById('pharmacy_net_price').value);

            if (rawValue === "" || isNaN(rawValue)) {
                document.getElementById('net_price').value = "Rp 0";
                return;
            }

            let total = Math.floor(rawValue * 1.11); // HNA + 11%

            document.getElementById('net_price').value =
                "Rp " + new Intl.NumberFormat("id-ID").format(total);
        }

        $(document).ready(function() {

            // COMPOSITION SELECT2
            $('#composition_id').select2({
                placeholder: 'Cari Komposisi...',
                allowClear: true,
                ajax: {
                    url: '{{ route('composition.select') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    },
                    cache: true
                }
            });
            // CATEGORY SELECT2
            $('#medicine_category_id').select2({
                placeholder: 'Cari Golongan...',
                allowClear: true,
                ajax: {
                    url: '{{ route('categories.select') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    },
                    cache: true
                }
            });
            // FACTORY SELECT2
            $('#factory_id').select2({
                placeholder: 'Cari Pabrik...',
                allowClear: true,
                ajax: {
                    url: '{{ route('factories.select') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    },
                    cache: true
                }
            });
            // CREDITORS SELECT2
            $('#creditors_id').select2({
                placeholder: 'Search creditor...',
                allowClear: true,
                ajax: {
                    url: '{{ route('creditors.select') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(item => ({
                                id: item.id,
                                text: item.name
                            }))
                        };
                    },
                    cache: true
                }
            });


        });

        let tableData, selectedData = null;
        const form = document.getElementById('medicineForm');

        $(function() {

            // TABLE
            tableData = $('#table-data').DataTable({
                responsive: true,
                serverSide: true,
                ajax: '{{ route('medicines.index') }}',
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
                        data: 'medicine_category_id'
                    },
                    {
                        data: 'status_label'
                    },
                ],
            });

            $('#table-data tbody').on('click', 'tr', function() {
                const selectedData = tableData.row(this).data();
                if (!selectedData) return;

                // normal inputs
                Object.keys(selectedData).forEach(key => {
                    const el = document.getElementById(key);
                    if (el && el.tagName !== 'SELECT') {
                        el.value = selectedData[key];
                    }
                });
                console.log(selectedData);

                setSelect2AjaxValue(
                    '#medicine_category_id',
                    selectedData.medicine_category_id,
                    selectedData.category_name
                );

                setSelect2AjaxValue(
                    '#composition_id',
                    selectedData.composition_id,
                    selectedData.composition_name
                );

                setSelect2AjaxValue(
                    '#factory_id',
                    selectedData.factory_id,
                    selectedData.factory_name
                );

                setSelect2AjaxValue(
                    '#creditors_id',
                    selectedData.creditors_id,
                    selectedData.creditor_name
                );

                // checkboxes
                $('#generic_check').prop('checked', selectedData.generic_check == 1);
                $('#whole').prop('checked', selectedData.whole == 1);
                $('#precursor').prop('checked', selectedData.precursor == 1);
                $('#psychotropic').prop('checked', selectedData.psychotropic == 1);
                $('#receipt').prop('checked', selectedData.receipt == 1);
                $('#status').prop('checked', selectedData.status == 1);

                $('#medicine_id').val(selectedData.id);
            });


            function setSelect2AjaxValue(selector, id, text) {
                const $select = $(selector);

                if (!id) {
                    $select.val(null).trigger('change');
                    return;
                }

                const option = new Option(text, id, true, true);
                $select.append(option).trigger('change');
            }



            // BACK
            $('#back').click(function() {
                window.location.href = "{{ route('home') }}";
            });
            // CANCEL
            $('#cancelEdit').click(function() {
                form.reset();
                $('#medicine_id').val('');
                $('#table-data tbody tr').removeClass('bg-blue-100');
            });

            // DELETE
            $('#deleteData').click(function() {
                const id = $('#medicine_id').val();
                if (!id) {
                    return iziToast.warning({
                        title: 'Warning',
                        message: 'Select a medicine to delete.'
                    });
                }

                swal({
                    title: 'Delete?',
                    text: 'This medicine will be removed permanently.',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then(function(yes) {
                    if (!yes) return;

                    axios.delete('/medicines/' + id)
                        .then(res => {
                            iziToast.success({
                                title: 'Deleted',
                                message: res.data.message
                            });
                            $('#cancelEdit').click();
                            tableData.ajax.reload(null, false);
                        });
                });
            });

        });

        // SUBMIT
        document.getElementById('submitForm').addEventListener('click', handleSubmit);

        function handleSubmit() {

            const formData = new FormData(form);
            const id = document.getElementById('medicine_id').value;

            // correct checkbox mapping
            formData.set("generic", document.getElementById('generic_check').checked ? 1 : 0);
            formData.set("whole", document.getElementById('whole').checked ? 1 : 0);
            formData.set("precursor", document.getElementById('precursor').checked ? 1 : 0);
            formData.set("psychotropic", document.getElementById('psychotropic').checked ? 1 : 0);
            formData.set("receipt", document.getElementById('receipt').checked ? 1 : 0);
            formData.set("status", document.getElementById('status').checked ? 1 : 0);
            formData.set('raw_price', cleanRupiah(document.getElementById('raw_price').value));
            formData.set('pharmacy_net_price', cleanRupiah(document.getElementById('pharmacy_net_price').value));
            formData.set('net_price', cleanRupiah(document.getElementById('net_price').value));

            const url = id ? `/medicines/${id}` : form.action;
            if (id) formData.append('_method', 'PUT');

            axios.post(url, formData)
                .then(res => {
                    iziToast.success({
                        title: 'Success',
                        message: res.data.message
                    });
                    form.reset();
                    $('#medicine_id').val('');
                    tableData.ajax.reload(null, false);

                    $('#medicine_category_id').val(null).trigger('change');
                    $('#composition_id').val(null).trigger('change');
                    $('#factory_id').val(null).trigger('change');
                    $('#creditors_id').val(null).trigger('change');
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
