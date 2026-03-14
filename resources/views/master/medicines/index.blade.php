@extends('layouts.app')

@section('title', 'Medicines')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 46px !important;
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

        .select2-selection__choice {
            background: #e5e7eb !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            font-size: 13px;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                <div class="card w-full md:w-[40%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-6">
                        <svg class="mr-2" width="30px" height="30px" viewBox="0 0 24 24" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                            <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC"
                                stroke-width="0.336"></g>
                            <g id="SVGRepo_iconCarrier">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M7.46778 2.25H7.53223C7.97201 2.24998 8.35136 2.24997 8.66265 2.27818C8.99183 2.30802 9.31779 2.37407 9.625 2.55144C9.96704 2.74892 10.2511 3.03296 10.4486 3.375C10.6259 3.68221 10.692 4.00817 10.7218 4.33735C10.75 4.64865 10.75 5.028 10.75 5.4678L10.75 14.5322C10.75 14.972 10.75 15.3514 10.7218 15.6627C10.692 15.9918 10.6259 16.3178 10.4486 16.625C10.2511 16.967 9.96704 17.2511 9.625 17.4486C9.31779 17.6259 8.99183 17.692 8.66265 17.7218C8.35135 17.75 7.972 17.75 7.53221 17.75H7.46779C7.028 17.75 6.64865 17.75 6.33735 17.7218C6.00817 17.692 5.68221 17.6259 5.375 17.4486C5.03296 17.2511 4.74892 16.967 4.55144 16.625C4.37407 16.3178 4.30802 15.9918 4.27818 15.6627C4.24997 15.3514 4.24998 14.972 4.25 14.5322L4.25 5.5C4.25 5.48922 4.25 5.47848 4.25 5.46778C4.24998 5.02799 4.24997 4.64864 4.27818 4.33735C4.30802 4.00817 4.37407 3.68221 4.55144 3.375C4.74892 3.03296 5.03296 2.74892 5.375 2.55144C5.68221 2.37408 6.00817 2.30802 6.33735 2.27818C6.64864 2.24997 7.02799 2.24998 7.46778 2.25ZM6.47274 3.77206C6.2476 3.79247 6.16586 3.82689 6.125 3.85048C6.01098 3.91631 5.91631 4.01099 5.85048 4.125C5.82689 4.16587 5.79246 4.2476 5.77206 4.47275C5.75072 4.7082 5.75 5.01889 5.75 5.5L5.75 14.5C5.75 14.9811 5.75072 15.2918 5.77206 15.5273C5.79246 15.7524 5.82689 15.8341 5.85048 15.875C5.91631 15.989 6.01098 16.0837 6.125 16.1495C6.16586 16.1731 6.2476 16.2075 6.47274 16.2279C6.7082 16.2493 7.01889 16.25 7.5 16.25C7.98111 16.25 8.2918 16.2493 8.52725 16.2279C8.7524 16.2075 8.83414 16.1731 8.875 16.1495C8.98901 16.0837 9.08369 15.989 9.14952 15.875C9.17311 15.8341 9.20754 15.7524 9.22794 15.5273C9.24928 15.2918 9.25 14.9811 9.25 14.5L9.25 5.5C9.25 5.01889 9.24928 4.7082 9.22794 4.47275C9.20753 4.2476 9.17311 4.16586 9.14952 4.125C9.08369 4.01099 8.98901 3.91631 8.875 3.85048C8.83414 3.82689 8.7524 3.79247 8.52725 3.77206C8.2918 3.75072 7.98111 3.75 7.5 3.75C7.01889 3.75 6.7082 3.75072 6.47274 3.77206ZM16.4678 5.25H16.5322C16.972 5.24998 17.3514 5.24997 17.6627 5.27818C17.9918 5.30802 18.3178 5.37407 18.625 5.55144C18.967 5.74892 19.2511 6.03296 19.4486 6.375C19.6259 6.68221 19.692 7.00817 19.7218 7.33735C19.75 7.64864 19.75 8.02797 19.75 8.46775V14.5322C19.75 14.972 19.75 15.3514 19.7218 15.6627C19.692 15.9918 19.6259 16.3178 19.4486 16.625C19.2511 16.967 18.967 17.2511 18.625 17.4486C18.3178 17.6259 17.9918 17.692 17.6627 17.7218C17.3514 17.75 16.972 17.75 16.5322 17.75H16.4678C16.028 17.75 15.6486 17.75 15.3373 17.7218C15.0082 17.692 14.6822 17.6259 14.375 17.4486C14.033 17.2511 13.7489 16.967 13.5514 16.625C13.3741 16.3178 13.308 15.9918 13.2782 15.6627C13.25 15.3514 13.25 14.972 13.25 14.5322V8.46776C13.25 8.02798 13.25 7.64864 13.2782 7.33735C13.308 7.00817 13.3741 6.68222 13.5514 6.375C13.7489 6.03296 14.033 5.74892 14.375 5.55144C14.6822 5.37408 15.0082 5.30802 15.3373 5.27818C15.6486 5.24997 16.028 5.24998 16.4678 5.25ZM15.4727 6.77206C15.2476 6.79247 15.1659 6.82689 15.125 6.85048C15.011 6.91631 14.9163 7.01099 14.8505 7.125C14.8269 7.16586 14.7925 7.2476 14.7721 7.47275C14.7507 7.7082 14.75 8.01889 14.75 8.5V14.5C14.75 14.9811 14.7507 15.2918 14.7721 15.5273C14.7925 15.7524 14.8269 15.8341 14.8505 15.875C14.9163 15.989 15.011 16.0837 15.125 16.1495C15.1659 16.1731 15.2476 16.2075 15.4727 16.2279C15.7082 16.2493 16.0189 16.25 16.5 16.25C16.9811 16.25 17.2918 16.2493 17.5273 16.2279C17.7524 16.2075 17.8341 16.1731 17.875 16.1495C17.989 16.0837 18.0837 15.989 18.1495 15.875C18.1731 15.8341 18.2075 15.7524 18.2279 15.5273C18.2493 15.2918 18.25 14.9811 18.25 14.5V8.5C18.25 8.01889 18.2493 7.7082 18.2279 7.47275C18.2075 7.2476 18.1731 7.16586 18.1495 7.125C18.0837 7.01099 17.989 6.91631 17.875 6.85048C17.8341 6.82689 17.7524 6.79247 17.5273 6.77206C17.2918 6.75072 16.9811 6.75 16.5 6.75C16.0189 6.75 15.7082 6.75072 15.4727 6.77206ZM1.25 21C1.25 20.5858 1.58579 20.25 2 20.25H22C22.4142 20.25 22.75 20.5858 22.75 21C22.75 21.4142 22.4142 21.75 22 21.75H2C1.58579 21.75 1.25 21.4142 1.25 21Z"
                                    fill="#688af8"></path>
                            </g>
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800 drop-shadow-sm">Data Obat</h2>
                    </div>

                    <div class="overflow-x-auto p-3">
                        <table id="table-data" class="min-w-full text-sm text-left text-gray-600">
                            <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                                <tr>
                                    <th class="px-4 py-3">#</th>
                                    <th class="px-4 py-3">Kode</th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Kategori</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[60%] mx-auto">
                    <form id="medicineForm" action="{{ route('medicines.store') }}" method="POST" class="space-y-2  ">
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
                        <div class="flex gap-1 items-center">
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
                        <div class="flex gap-1 w-full">

                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Nama Obat</label>
                                <input id="name" name="name" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Masukkan Nama Obat">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Barcode</label>
                                <input id="barcode" name="barcode" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Masukkan Barcode Obat">
                            </div>

                        </div>
                        {{-- PHARMACY --}}
                        <div class="hidden">
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Kreditur</label>
                            <input id="pharmacy_id" name="pharmacy_id" value="1" type="number"
                                class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                        </div>
                        <div class="flex gap-1">
                            {{-- PACKAGING --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Kemasan</label>
                                <select id="packaging" name="packaging" required class="select2 w-full ...">
                                    <option value="">-- Pilih Kemasan --</option>
                                    <option value="UNIT">UNIT</option>
                                    <option value="PACK">PACK</option>
                                    <option value="PCS">PCS</option>
                                    <option value="TUBE">TUBE</option>
                                    <option value="VIAL">VIAL</option>
                                    <option value="AMP">AMP</option>
                                    <option value="KTK">KTK</option>
                                    <option value="BKS">BKS</option>
                                    <option value="BTL">BTL</option>
                                    <option value="BOX">BOX</option>
                                    <option value="TAB">TAB</option>


                                </select>

                            </div>
                            {{-- Satuan --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Satuan</label>
                                <select id="unit" name="unit" required class="select2 w-full ...">
                                    <option value="">-- Pilih Unit --</option>
                                    <option value="UNIT">UNIT</option>
                                    <option value="PACK">PACK</option>
                                    <option value="PCS">PCS</option>
                                    <option value="TUBE">TUBE</option>
                                    <option value="VIAL">VIAL</option>
                                    <option value="AMP">AMP</option>
                                    <option value="KTK">KTK</option>
                                    <option value="BKS">BKS</option>
                                    <option value="BTL">BTL</option>
                                    <option value="BOX">BOX</option>
                                    <option value="TAB">TAB</option>


                                </select>


                            </div>
                            {{-- Isi --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Isi</label>
                                <input id="content" name="content" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            {{-- DOSAGE --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Dosage</label>
                                <input id="dosage" name="dosage" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                        </div>
                        <div class="flex gap-1">
                            {{-- Sediaan --}}
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Sediaan</label>
                                <input id="preparations" name="preparations" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>

                            <div class="w-full hidden">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga Utuh</label>
                                <input id="raw_price" value="0" name="raw_price" type="text"
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
                                <input id="net_price" name="net_price" readonly type="text"
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

                        <div class="flex gap-1">


                            {{-- MIN STOCK --}}

                        </div>
                        <div class="flex gap-1">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Stok Minimal</label>
                                <input id="minimal_stock" name="minimal_stock" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>

                            {{-- STOCK --}}
                            <div class="w-full hidden">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Stok</label>
                                <input id="stock" name="stock" value="0" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>

                        </div>


                        <div class="w-full space-y-1" id="creditorSelect">
                            <label class="block text-[14px] font-semibold text-gray-800">Pilih Kreditur</label>
                            <div id="pillContainer" class="flex flex-wrap gap-2"></div>

                            <div class="relative">
                                <input id="searchInput" type="text" name="creditors_id"
                                    placeholder="Cari Kreditor...."
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    autocomplete="off">

                                <ul id="dropdown"
                                    class="absolute z-50 mt-1 w-full rounded-xl border bg-white shadow-lg max-h-60 overflow-y-auto hidden">
                                </ul>
                            </div>

                            <input type="hidden" name="creditor_ids" id="creditor_ids">
                        </div>
                        <div class="flex gap-1">

                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Lokasi</label>
                                <select name="location"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px"
                                    id="location">
                                    <option value="">-- Pilih Lokasi --</option>

                                </select>
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Etalase</label>
                                <select id="items" name="etalase" required
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Etalase --</option>

                                </select>
                            </div>
                        </div>


                        <div class="w-full">
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Pilih Tipe Pesanan
                            </label>

                            <select id="type" name="type" required
                                class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                <option value="">-- Pilih Tipe --</option>
                                <option value="Narkotika">Narkotika</option>
                                <option value="Psikotropika">Psikotropika</option>
                                <option value="Reguler">Reguler</option>
                                <option value="Prekursor">Prekursor</option>
                                <option value="Obat Tertentu">Obat Tertentu</option>
                            </select>
                        </div>



                        {{-- BUTTONS --}}
                        <div class="flex flex-wrap gap-2">
                            <div>
                                <button type="button" id="submitForm"
                                    class="px-5 py-3 w-full bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-lg">
                                    Submit
                                </button>
                            </div>
                            <div>
                                <button type="button" id="cancelEdit"
                                    class="px-5 py-3 w-full bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg shadow-lg">
                                    Cancel
                                </button>
                            </div>
                            <div>
                                <button type="button" id="deleteData"
                                    class="px-5 py-3 w-full bg-red-500 hover:bg-red-600 text-white rounded-lg shadow-lg">
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
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


    <script>
        // ==========================
        // GLOBAL VARIABLES
        // ==========================
        const creditors = @json($creditors);

        const form = document.getElementById("medicineForm");

        const input = document.getElementById("searchInput");
        const dropdown = document.getElementById("dropdown");
        const pills = document.getElementById("pillContainer");
        const hidden = document.getElementById("creditor_ids");

        const rawPrice = document.getElementById("raw_price");
        const pharmacyNetPrice = document.getElementById("pharmacy_net_price");
        const netPrice = document.getElementById("net_price");
        const hetPrice = document.getElementById("het_price");

        let selected = new Map();
        let activeIndex = -1;
        let filtered = [];
        let tableData = null;

        // ==========================
        // HELPERS
        // ==========================
        document.addEventListener("mousedown", function(e) {
            const wrapper = document.getElementById("creditorSelect");

            if (!wrapper.contains(e.target)) {
                dropdown.classList.add("hidden");
                activeIndex = -1;
            }
        });
        document.getElementById("creditorSelect").addEventListener("mousedown", function(e) {
            e.stopPropagation();
        });

        document.addEventListener("mousedown", function(e) {
            dropdown.classList.add("hidden");
            activeIndex = -1;
        });

        function cleanRupiah(value) {
            return value.replace(/[^0-9]/g, "");
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
            let rawValue = cleanRupiah(pharmacyNetPrice.value);

            if (rawValue === "" || isNaN(rawValue)) {
                netPrice.value = "Rp 0";
                return;
            }

            let total = Math.floor(parseInt(rawValue) * 1.11);

            netPrice.value = "Rp " + new Intl.NumberFormat("id-ID").format(total);
        }

        function resetForm() {
            form.reset();

            $("#medicine_category_id").val(null).trigger("change");
            $("#composition_id").val(null).trigger("change");
            $("#factory_id").val(null).trigger("change");
            $("#type").val(null).trigger("change");
            $("#packaging").val(null).trigger("change");
            $("#unit").val(null).trigger("change");

            selected.clear();
            renderPills();
            syncHidden();

            $("#medicine_id").val("");
            dropdown.classList.add("hidden");
            input.value = "";
            activeIndex = -1;
            filtered = [];
        }

        function setSelect2AjaxValue(selector, id, text) {
            const $select = $(selector);

            if (!id) {
                $select.val(null).trigger("change");
                return;
            }

            const option = new Option(text, id, true, true);
            $select.append(option).trigger("change");
        }

        // ==========================
        // CREDITORS UI
        // ==========================
        function renderDropdown(filter = "") {
            dropdown.innerHTML = "";
            activeIndex = -1;

            filtered = creditors.filter(c =>
                c.name.toLowerCase().includes(filter.toLowerCase())
            );

            if (!filtered.length) {
                dropdown.innerHTML =
                    `<li class="px-4 py-2 text-sm text-gray-400">No results</li>`;
                return;
            }

            filtered.forEach((c, index) => {
                const isSelected = selected.has(c.id);

                const li = document.createElement("li");
                li.className = `
                    px-4 py-2 text-sm flex items-center justify-between
                    ${isSelected ? "text-gray-400 cursor-not-allowed" : "cursor-pointer hover:bg-blue-50"}
                `;

                li.innerHTML = `
                    <span>${c.name}</span>
                    ${isSelected ? '<span class="text-blue-600">✔</span>' : ""}
                `;

                if (!isSelected) {
                    li.onclick = () => selectItem(c);
                }

                dropdown.appendChild(li);
            });

            highlightActive();
        }

        function highlightActive() {
            [...dropdown.children].forEach((li, i) => {
                li.classList.toggle("bg-blue-100", i === activeIndex);
            });
        }

        function selectItem(item) {
            selected.set(item.id, item);

            input.value = "";
            dropdown.classList.add("hidden");
            activeIndex = -1;

            renderPills();
            syncHidden();

            setTimeout(() => {
                input.focus();
            }, 0);
        }

        function removeItem(id) {
            selected.delete(id);
            renderPills();
            syncHidden();
            renderDropdown(input.value);
        }

        function renderPills() {
            pills.innerHTML = "";

            selected.forEach(item => {
                const pill = document.createElement("div");

                pill.className =
                    "flex items-center gap-2 inline-flex items-center rounded-full border px-5 py-2 text-sm font-medium transition-all duration-200 ease-out hover:bg-[#064cba] bg-[#3b82f6] text-[#fff]";

                pill.innerHTML = `
                    ${item.name}
                    <button type="button" class="text-[#fff] hover:text-blue-700">&times;</button>
                `;

                pill.querySelector("button").onclick = () => removeItem(item.id);
                pills.appendChild(pill);
            });
        }

        function syncHidden() {
            hidden.value = [...selected.values()]
                .map(item => item.code)
                .filter(code => code)
                .join(",");
        }

        // ==========================
        // LOAD CREDITORS FROM MEDICINE
        // ==========================
        function loadMedicineCreditors(medicineId) {
            selected.clear();
            renderPills();
            syncHidden();

            axios.get(`/medicines/${medicineId}/edit-data`)
                .then(res => {
                    const data = res.data;

                    if (data.creditors && data.creditors.length > 0) {
                        data.creditors.forEach(c => {
                            selected.set(c.id, c);
                        });
                    }

                    renderPills();
                    syncHidden();
                })
                .catch(err => console.log(err));
        }

        // ==========================
        // SUBMIT
        // ==========================
        function handleSubmit() {
            const formData = new FormData(form);
            const id = document.getElementById("medicine_id").value;

            formData.set("generic", document.getElementById("generic_check").checked ? 1 : 0);

            formData.set("raw_price", cleanRupiah(rawPrice.value));
            formData.set("pharmacy_net_price", cleanRupiah(pharmacyNetPrice.value));
            formData.set("net_price", cleanRupiah(netPrice.value));
            formData.set("het_price", cleanRupiah(hetPrice.value));

            const url = id ? `/medicines/${id}` : form.action;
            if (id) formData.append("_method", "PUT");

            axios.post(url, formData)
                .then(res => {
                    iziToast.success({
                        title: "Success",
                        message: res.data.message
                    });

                    resetForm();
                    tableData.ajax.reload(null, false);
                })
                .catch(err => {
                    let msg = "Failed to save.";

                    if (err.response?.status === 422) {
                        msg = Object.values(err.response.data.errors).flat().join("<br>");
                    }

                    iziToast.error({
                        title: "Error",
                        message: msg
                    });
                });
        }

        // ==========================
        // INIT DOM
        // ==========================
        document.addEventListener("DOMContentLoaded", function() {

            // ==========================
            // PRICE FORMAT
            // ==========================
            [rawPrice, netPrice, hetPrice].forEach(el => {
                if (!el) return;

                el.addEventListener("input", function() {
                    formatRupiah(this);
                });
            });

            pharmacyNetPrice.addEventListener("input", function() {
                formatRupiah(this);
                calculatePPN();
                rawPrice.value = this.value;
            });

            // ==========================
            // SELECT2
            // ==========================
            $("#composition_id").select2({
                placeholder: "Cari Komposisi...",
                allowClear: true,
                ajax: {
                    url: "{{ route('composition.select') }}",
                    dataType: "json",
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true
                }
            });

            $("#location").select2({
                placeholder: "Cari Lokasi...",
                allowClear: true,
                ajax: {
                    url: "{{ route('locations.select') }}",
                    dataType: "json",
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true
                }
            });
            $("#items").select2({
                placeholder: "Cari Lokasi...",
                allowClear: true,
                ajax: {
                    url: "{{ route('items.select') }}",
                    dataType: "json",
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true
                }
            });

            $("#medicine_category_id").select2({
                placeholder: "Cari Golongan...",
                allowClear: true,
                ajax: {
                    url: "{{ route('categories.select') }}",
                    dataType: "json",
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true
                }
            });

            $("#factory_id").select2({
                placeholder: "Cari Pabrik...",
                allowClear: true,
                ajax: {
                    url: "{{ route('factories.select') }}",
                    dataType: "json",
                    delay: 250,
                    data: params => ({
                        q: params.term
                    }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true
                }
            });

            $("#packaging").select2({
                placeholder: "Cari Kemasan...",
                allowClear: true
            });

            $("#unit").select2({
                placeholder: "Cari Satuan...",
                allowClear: true
            });

            $("#type").select2({
                placeholder: "Cari Tipe...",
                allowClear: true
            });

            // ==========================
            // SELECT2 FLOW
            // ==========================
            $("#medicine_category_id").on("select2:select", () => $("#composition_id").select2("open"));
            $("#composition_id").on("select2:select", () => $("#factory_id").select2("open"));

            $("#factory_id").on("select2:select", () => {
                setTimeout(() => $("#name").focus(), 100);
            });
            $("#location").on("select2:select", () => $("#items").select2("open"));
            $("#items").on("select2:select", () => $("#type").select2("open"));
            $("#type").on("select2:select", () => handleSubmit());

            $("#packaging").on("select2:select", () => $("#unit").select2("open"));
            $("#unit").on("select2:select", () => $("#content").focus());

            // ==========================
            // CREDITORS INPUT
            // ==========================
            input.addEventListener("input", e => {
                dropdown.classList.remove("hidden");
                renderDropdown(e.target.value);
            });

            input.addEventListener("keydown", function(e) {

                if (!filtered.length && e.key !== "Enter") return;

                if (e.key === "ArrowDown") {
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % filtered.length;
                    highlightActive();
                }

                if (e.key === "ArrowUp") {
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + filtered.length) % filtered.length;
                    highlightActive();
                }

                if (e.key === "Enter") {
                    e.preventDefault();

                    if (dropdown.classList.contains("hidden")) {
                        dropdown.classList.remove("hidden");
                        renderDropdown(input.value);
                        return;
                    }

                    if (activeIndex === -1) activeIndex = 0;

                    if (filtered[activeIndex]) {
                        selectItem(filtered[activeIndex]);
                    }

                    dropdown.classList.add("hidden");
                    activeIndex = -1;
                }
            });
            // ==========================
            // ENTER KEY NAVIGATION
            // ==========================
            const focusableSelectors = `
                input:not([type="hidden"]):not([disabled]),
                select:not([disabled]),
                textarea:not([disabled]),
                button:not([disabled])
            `;

            function getFocusableElements() {
                return Array.from(form.querySelectorAll(focusableSelectors))
                    .filter(el => el.offsetParent !== null);
            }

            form.addEventListener("keydown", function(e) {
                if (e.key !== "Enter") return;

                if (e.target.tagName === "TEXTAREA") return;

                // stop form enter if creditor input
                if (e.target.id === "searchInput") return;

                e.preventDefault();

                const focusables = getFocusableElements();
                const index = focusables.indexOf(e.target);

                if (index === -1) return;

                const next = focusables[index + 1];
                if (!next) return;

                if (next.tagName === "SELECT") {
                    $(next).select2("open");
                } else {
                    next.focus();
                }
            });

            // ==========================
            // SUBMIT BUTTON
            // ==========================
            document.getElementById("submitForm").addEventListener("click", handleSubmit);

            // ==========================
            // CANCEL
            // ==========================
            $("#cancelEdit").click(function() {
                resetForm();
                $("#table-data tbody tr").removeClass("bg-blue-100");
            });

            // ==========================
            // BACK
            // ==========================
            $("#back").click(function() {
                window.location.href = "{{ route('home') }}";
            });

            // ==========================
            // DELETE
            // ==========================
            $("#deleteData").click(function() {
                const id = $("#medicine_id").val();

                if (!id) {
                    return iziToast.warning({
                        title: "Warning",
                        message: "Select a medicine to delete."
                    });
                }

                swal({
                    title: "Delete?",
                    text: "This medicine will be removed permanently.",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then(function(yes) {
                    if (!yes) return;

                    axios.delete("/medicines/" + id)
                        .then(res => {
                            iziToast.success({
                                title: "Deleted",
                                message: res.data.message
                            });

                            resetForm();
                            tableData.ajax.reload(null, false);
                        });
                });
            });

            // ==========================
            // DATATABLE
            // ==========================
            tableData = $("#table-data").DataTable({
                responsive: true,
                serverSide: true,
                ajax: "{{ route('medicines.index') }}",
                columns: [{
                        data: "DT_RowIndex",
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: "code"
                    },
                    {
                        data: "name"
                    },
                    {
                        data: "category_name"
                    },
                    {
                        data: "status_label"
                    },
                ],
            });

            $("#table-data tbody").on("click", "tr", function() {
                const selectedData = tableData.row(this).data();
                if (!selectedData) return;
                console.log(selectedData);

                Object.keys(selectedData).forEach(key => {
                    const el = document.getElementById(key);
                    if (el && el.tagName !== "SELECT") {
                        el.value = selectedData[key];
                    }
                });

                setSelect2AjaxValue("#medicine_category_id", selectedData.medicine_category_id, selectedData
                    .category_name);
                setSelect2AjaxValue("#composition_id", selectedData.composition_id, selectedData
                    .composition_name);
                setSelect2AjaxValue("#factory_id", selectedData.factory_id, selectedData.factory_name);
                setSelect2AjaxValue("#location", selectedData.location, selectedData.location_name);
                setSelect2AjaxValue("#items", selectedData.etalase, selectedData.etalase_name);

                $("#type").val(selectedData.type).trigger("change");
                $("#packaging").val(selectedData.packaging).trigger("change");
                $("#unit").val(selectedData.unit).trigger("change");

                $("#medicine_id").val(selectedData.id);

                // format edit price fields
                formatRupiah(rawPrice);
                formatRupiah(pharmacyNetPrice);
                formatRupiah(netPrice);
                formatRupiah(hetPrice);

                calculatePPN();

                // load creditors pills
                loadMedicineCreditors(selectedData.id);
            });

        });
    </script>



@endsection
