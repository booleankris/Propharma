@extends('layouts.app')

@section('title', 'Medicines')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ── Select2 height fix ── */
        .select2-container .select2-selection--single {
            height: 42px !important;
            padding: 6px 10px !important;
            display: flex !important;
            align-items: center !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
        }

        .select2-selection__choice {
            background: #e5e7eb !important;
            border-radius: 6px !important;
            padding: 4px 8px !important;
            font-size: 13px;
        }

        /* ── Table row states ── */
        #table-data tbody tr {
            cursor: pointer;
            transition: background .15s;
        }

        #table-data tbody tr:hover {
            background: #eff6ff;
        }

        #table-data tbody tr.active-row {
            background: #dbeafe !important;
        }

        /* ── Edit mode indicator ── */
        #formModeBar {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        #formModeBar.edit {
            display: flex;
            background: #fef9c3;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        #formModeBar.add {
            display: flex;
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        /* ── Status badges ── */
        .badge-active {
            padding: 2px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            font-size: 12px;
        }

        .badge-inactive {
            padding: 2px 10px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 999px;
            font-size: 12px;
        }

        /* ── Pill tags ── */
        .creditor-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #3b82f6;
            color: #fff;
            border-radius: 999px;
            padding: 4px 14px;
            font-size: 13px;
            font-weight: 500;
            transition: background .15s;
        }

        .creditor-pill:hover {
            background: #2563eb;
        }

        .creditor-pill button {
            background: none;
            border: none;
            color: #fff;
            font-size: 16px;
            line-height: 1;
            cursor: pointer;
            padding: 0;
        }

        /* ── Double-click hint ── */
        .dbl-hint {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="flex flex-col lg:flex-row gap-4">

                {{-- ─────────────────── TABLE PANEL ─────────────────── --}}
                <div class="card w-full md:w-[40%] shadow-md rounded-2xl p-6 bg-white">
                    <div class="flex items-center mb-4">
                        <svg class="mr-2" width="28" height="28" viewBox="0 0 24 24" fill="none">
                            <path fill-rule="evenodd" clip-rule="evenodd"
                                d="M7.46778 2.25H7.53223C7.97201 2.24998 8.35136 2.24997 8.66265 2.27818C8.99183 2.30802 9.31779 2.37407 9.625 2.55144C9.96704 2.74892 10.2511 3.03296 10.4486 3.375C10.6259 3.68221 10.692 4.00817 10.7218 4.33735C10.75 4.64865 10.75 5.028 10.75 5.4678L10.75 14.5322C10.75 14.972 10.75 15.3514 10.7218 15.6627C10.692 15.9918 10.6259 16.3178 10.4486 16.625C10.2511 16.967 9.96704 17.2511 9.625 17.4486C9.31779 17.6259 8.99183 17.692 8.66265 17.7218C8.35135 17.75 7.972 17.75 7.53221 17.75H7.46779C7.028 17.75 6.64865 17.75 6.33735 17.7218C6.00817 17.692 5.68221 17.6259 5.375 17.4486C5.03296 17.2511 4.74892 16.967 4.55144 16.625C4.37407 16.3178 4.30802 15.9918 4.27818 15.6627C4.24997 15.3514 4.24998 14.972 4.25 14.5322L4.25 5.5C4.25 5.48922 4.25 5.47848 4.25 5.46778C4.24998 5.02799 4.24997 4.64864 4.27818 4.33735C4.30802 4.00817 4.37407 3.68221 4.55144 3.375C4.74892 3.03296 5.03296 2.74892 5.375 2.55144C5.68221 2.37408 6.00817 2.30802 6.33735 2.27818C6.64864 2.24997 7.02799 2.24998 7.46778 2.25ZM6.47274 3.77206C6.2476 3.79247 6.16586 3.82689 6.125 3.85048C6.01098 3.91631 5.91631 4.01099 5.85048 4.125C5.82689 4.16587 5.79246 4.2476 5.77206 4.47275C5.75072 4.7082 5.75 5.01889 5.75 5.5L5.75 14.5C5.75 14.9811 5.75072 15.2918 5.77206 15.5273C5.79246 15.7524 5.82689 15.8341 5.85048 15.875C5.91631 15.989 6.01098 16.0837 6.125 16.1495C6.16586 16.1731 6.2476 16.2075 6.47274 16.2279C6.7082 16.2493 7.01889 16.25 7.5 16.25C7.98111 16.25 8.2918 16.2493 8.52725 16.2279C8.7524 16.2075 8.83414 16.1731 8.875 16.1495C8.98901 16.0837 9.08369 15.989 9.14952 15.875C9.17311 15.8341 9.20754 15.7524 9.22794 15.5273C9.24928 15.2918 9.25 14.9811 9.25 14.5L9.25 5.5C9.25 5.01889 9.24928 4.7082 9.22794 4.47275C9.20753 4.2476 9.17311 4.16586 9.14952 4.125C9.08369 4.01099 8.98901 3.91631 8.875 3.85048C8.83414 3.82689 8.7524 3.79247 8.52725 3.77206C8.2918 3.75072 7.98111 3.75 7.5 3.75C7.01889 3.75 6.7082 3.75072 6.47274 3.77206ZM16.4678 5.25H16.5322C16.972 5.24998 17.3514 5.24997 17.6627 5.27818C17.9918 5.30802 18.3178 5.37407 18.625 5.55144C18.967 5.74892 19.2511 6.03296 19.4486 6.375C19.6259 6.68221 19.692 7.00817 19.7218 7.33735C19.75 7.64864 19.75 8.02797 19.75 8.46775V14.5322C19.75 14.972 19.75 15.3514 19.7218 15.6627C19.692 15.9918 19.6259 16.3178 19.4486 16.625C19.2511 16.967 18.967 17.2511 18.625 17.4486C18.3178 17.6259 17.9918 17.692 17.6627 17.7218C17.3514 17.75 16.972 17.75 16.5322 17.75H16.4678C16.028 17.75 15.6486 17.75 15.3373 17.7218C15.0082 17.692 14.6822 17.6259 14.375 17.4486C14.033 17.2511 13.7489 16.967 13.5514 16.625C13.3741 16.3178 13.308 15.9918 13.2782 15.6627C13.25 15.3514 13.25 14.972 13.25 14.5322V8.46776C13.25 8.02798 13.25 7.64864 13.2782 7.33735C13.308 7.00817 13.3741 6.68222 13.5514 6.375C13.7489 6.03296 14.033 5.74892 14.375 5.55144C14.6822 5.37408 15.0082 5.30802 15.3373 5.27818C15.6486 5.24997 16.028 5.24998 16.4678 5.25ZM15.4727 6.77206C15.2476 6.79247 15.1659 6.82689 15.125 6.85048C15.011 6.91631 14.9163 7.01099 14.8505 7.125C14.8269 7.16586 14.7925 7.2476 14.7721 7.47275C14.7507 7.7082 14.75 8.01889 14.75 8.5V14.5C14.75 14.9811 14.7507 15.2918 14.7721 15.5273C14.7925 15.7524 14.8269 15.8341 14.8505 15.875C14.9163 15.989 15.011 16.0837 15.125 16.1495C15.1659 16.1731 15.2476 16.2075 15.4727 16.2279C15.7082 16.2493 16.0189 16.25 16.5 16.25C16.9811 16.25 17.2918 16.2493 17.5273 16.2279C17.7524 16.2075 17.8341 16.1731 17.875 16.1495C17.989 16.0837 18.0837 15.989 18.1495 15.875C18.1731 15.8341 18.2075 15.7524 18.2279 15.5273C18.2493 15.2918 18.25 14.9811 18.25 14.5V8.5C18.25 8.01889 18.2493 7.7082 18.2279 7.47275C18.2075 7.2476 18.1731 7.16586 18.1495 7.125C18.0837 7.01099 17.989 6.91631 17.875 6.85048C17.8341 6.82689 17.7524 6.79247 17.5273 6.77206C17.2918 6.75072 16.9811 6.75 16.5 6.75C16.0189 6.75 15.7082 6.75072 15.4727 6.77206ZM1.25 21C1.25 20.5858 1.58579 20.25 2 20.25H22C22.4142 20.25 22.75 20.5858 22.75 21C22.75 21.4142 22.4142 21.75 22 21.75H2C1.58579 21.75 1.25 21.4142 1.25 21Z"
                                fill="#688af8" />
                        </svg>
                        <h2 class="text-2xl font-bold text-gray-800">Data Obat</h2>
                    </div>

                    <div class="dbl-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="12" cy="12" r="10" />
                            <line x1="12" y1="8" x2="12" y2="12" />
                            <line x1="12" y1="16" x2="12.01" y2="16" />
                        </svg>
                        Double-klik baris untuk edit, klik sekali untuk preview
                    </div>

                    <div class="overflow-x-auto mt-3">
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

                {{-- ─────────────────── FORM PANEL ─────────────────── --}}
                <div class="bg-white p-6 rounded-2xl shadow-md w-full md:w-[60%] mx-auto">

                    {{-- Mode indicator bar --}}
                    <div id="formModeBar">
                        <span id="formModeIcon"></span>
                        <span id="formModeText"></span>
                    </div>

                    <form id="medicineForm" action="{{ route('medicines.store') }}" method="POST" class="space-y-2">
                        @csrf
                        <input type="hidden" id="medicine_id" name="id">

                        {{-- CODE + CATEGORY --}}
                        <div class="flex gap-1">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Code</label>
                                <input id="code" name="code"
                                    class="w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-2.5 text-[13px]">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Golongan Obat</label>
                                <select id="medicine_category_id" name="medicine_category_id"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"></select>
                            </div>
                        </div>

                        {{-- COMPOSITION + FACTORY --}}
                        <div class="flex gap-1">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Komposisi</label>
                                <select id="composition_id" name="composition_id"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Select Composition --</option>
                                </select>
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Pabrik</label>
                                <select id="factory_id" name="factory_id"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Pabrik --</option>
                                </select>
                            </div>
                        </div>

                        {{-- GENERIC checkbox --}}
                        <div>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" id="generic_check" name="generic_check" value="1"
                                    class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="text-sm">Generik</span>
                            </label>
                        </div>

                        {{-- NAME + BARCODE --}}
                        <div class="flex gap-1">
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
                                    placeholder="Masukkan Barcode">
                            </div>
                        </div>

                        {{-- hidden pharmacy_id --}}
                        <input type="hidden" id="pharmacy_id" name="pharmacy_id" value="1">

                        {{-- PACKAGING + UNIT + ISI + DOSAGE --}}
                        <div class="flex gap-1">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Kemasan</label>
                                <select id="packaging" name="packaging"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Kemasan --</option>
                                    @foreach (['UNIT', 'PACK', 'PCS', 'TUBE', 'VIAL', 'AMP', 'KTK', 'BKS', 'BTL', 'BOX', 'TAB'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Satuan</label>
                                <select id="unit" name="unit"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Unit --</option>
                                    @foreach (['UNIT', 'PACK', 'PCS', 'TUBE', 'VIAL', 'AMP', 'KTK', 'BKS', 'BTL', 'BOX', 'TAB'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="px-[10px]">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Kemasan</label>

                                <div class="flex items-center gap-2 mt-2">

                                    <input type="checkbox" id="pack" name="is_active"
                                        class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="text-sm">Utuh</span>
                                </div>
                            </div>

                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Isi</label>
                                <input id="content" name="content" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Dosis</label>
                                <input id="dosage" name="dosage" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Strip</label>
                                <input id="strip" name="strip" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                        </div>

                        {{-- SEDIAAN + HNA + PPN (auto) + HET --}}
                        <div class="flex gap-1">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Sediaan</label>
                                <input id="preparations" name="preparations" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga HNA</label>
                                <input id="pharmacy_net_price" name="pharmacy_net_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">
                                    Harga PPN 11%
                                    <span class="text-xs text-blue-500 font-normal">(otomatis)</span>
                                </label>
                                <input id="net_price" name="net_price" readonly type="text"
                                    class="w-full rounded-lg border border-gray-300 bg-blue-50 px-4 py-2.5 text-[13px] text-blue-700 font-semibold"
                                    placeholder="Rp 0">
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Harga HET</label>
                                <input id="het_price" name="het_price" type="text"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    placeholder="Rp 0">
                            </div>
                        </div>

                        {{-- STOK MINIMAL --}}
                        <div class="flex gap-1">
                            <div class="w-1/4">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Stok Minimal</label>
                                <input id="minimal_stock" name="minimal_stock" type="number"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                            </div>
                            <input type="hidden" id="stock" name="stock" value="0">
                            <input type="hidden" id="raw_price" name="raw_price" value="0">
                        </div>

                        {{-- CREDITORS --}}
                        <div class="w-full space-y-1" id="creditorSelect">
                            <label class="block text-[14px] font-semibold text-gray-800">Pilih Kreditur</label>
                            <div id="pillContainer" class="flex flex-wrap gap-2"></div>
                            <div class="relative">
                                <input id="searchInput" type="text" placeholder="Cari Kreditor...."
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]"
                                    autocomplete="off">
                                <ul id="creditorDropdown"
                                    class="absolute z-[99999] mt-1 w-full rounded-xl border bg-white shadow-lg max-h-60 overflow-y-auto hidden">
                                </ul>
                            </div>
                            <input type="hidden" name="creditor_ids" id="creditor_ids">
                        </div>

                        {{-- LOKASI + ETALASE --}}
                        <div class="flex gap-1">
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Lokasi</label>
                                <select id="location" name="location"
                                    class="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Lokasi --</option>
                                </select>
                            </div>
                            <div class="w-full">
                                <label class="block text-[14px] font-semibold text-gray-800 mb-1">Etalase</label>
                                <select id="items" name="etalase"
                                    class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                    <option value="">-- Pilih Etalase --</option>
                                </select>
                            </div>
                        </div>

                        {{-- TIPE --}}
                        <div class="w-full">
                            <label class="block text-[14px] font-semibold text-gray-800 mb-1">Tipe Pesanan</label>
                            <select id="type" name="type"
                                class="select2 w-full rounded-lg border border-gray-300 px-4 py-2.5 text-[13px]">
                                <option value="">-- Pilih Tipe --</option>
                                <option value="NARKOTIKA">Narkotika</option>
                                <option value="PSIKOTROPIKA">Psikotropika</option>
                                <option value="REGULER">Reguler</option>
                                <option value="PREKURSOR">Prekursor</option>
                                <option value="OBAT-OBAT TERTENTU (OOT)">Obat Tertentu</option>
                            </select>
                        </div>

                        {{-- BUTTONS --}}
                        <div class="flex flex-wrap gap-2 pt-2">
                            <button type="button" id="submitForm"
                                class="px-5 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow">
                                Submit
                            </button>
                            <button type="button" id="cancelEdit"
                                class="px-5 py-3 bg-yellow-400 hover:bg-yellow-500 text-white rounded-lg shadow">
                                Cancel
                            </button>
                            <button type="button" id="deleteData"
                                class="px-5 py-3 bg-red-500 hover:bg-red-600 text-white rounded-lg shadow">
                                Delete
                            </button>
                            <button type="button" id="back"
                                class="px-5 py-3 bg-orange-400 hover:bg-orange-500 text-white rounded-lg shadow">
                                Kembali
                            </button>
                        </div>

                    </form>
                </div><!-- /form panel -->

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
        // ════════════════════════════════════════════════
        // GLOBALS
        // ════════════════════════════════════════════════
        const CREDITORS = @json($creditors); // injected once from PHP

        const form = document.getElementById('medicineForm');
        const $modeBar = document.getElementById('formModeBar');
        const $modeIcon = document.getElementById('formModeIcon');
        const $modeText = document.getElementById('formModeText');

        const elPNP = document.getElementById('pharmacy_net_price');
        const elNet = document.getElementById('net_price');
        const elHet = document.getElementById('het_price');
        const elRaw = document.getElementById('raw_price');

        const input = document.getElementById('searchInput');
        const dropdown = document.getElementById('creditorDropdown');
        const pills = document.getElementById('pillContainer');
        const hiddenCreds = document.getElementById('creditor_ids');

        let selected = new Map();
        let filtered = [];
        let activeIdx = -1;
        let tableData = null;

        // ════════════════════════════════════════════════
        // PRICE HELPERS
        // ════════════════════════════════════════════════
        function stripRupiah(val) {
            return val.replace(/[^0-9]/g, '') || '0';
        }

        function toRupiah(num) {
            if (!num || isNaN(num)) return 'Rp 0';
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
        }

        function formatField(el) {
            const raw = stripRupiah(el.value);
            el.value = raw === '0' ? '' : toRupiah(parseInt(raw, 10));
        }

        /** Recalculate PPN (11%) from HNA; also mirror raw_price = HNA */
        function recalcPPN() {
            const hna = parseInt(stripRupiah(elPNP.value), 10) || 0;
            elNet.value = toRupiah(Math.floor(hna * 1.11));
            elRaw.value = hna; // hidden raw_price mirrors HNA
        }

        // ════════════════════════════════════════════════
        // FORM MODE INDICATOR
        // ════════════════════════════════════════════════
        function setMode(mode) {
            $modeBar.className = mode; // 'edit' | 'add' | ''
            if (mode === 'edit') {
                $modeIcon.textContent = '✏️';
                $modeText.textContent = 'Mode Edit — data di-load dari tabel';
            } else if (mode === 'add') {
                $modeIcon.textContent = '➕';
                $modeText.textContent = 'Mode Tambah — isi form untuk menambah data baru';
            }
        }

        // ════════════════════════════════════════════════
        // SELECT2 HELPER – set value without re-fetching
        // ════════════════════════════════════════════════
        function setSelect2Value(selector, id, text) {
            const $s = $(selector);
            if (!id) {
                $s.val(null).trigger('change');
                return;
            }
            if ($s.find(`option[value="${id}"]`).length === 0) {
                $s.append(new Option(text, id, true, true));
            } else {
                $s.val(id);
            }
            $s.trigger('change');
        }

        // ════════════════════════════════════════════════
        // RESET FORM
        // ════════════════════════════════════════════════
        function resetForm() {
            form.reset();
            ['#medicine_category_id', '#composition_id', '#factory_id',
                '#type', '#packaging', '#unit', '#location', '#items'
            ]
            .forEach(s => $(s).val(null).trigger('change'));

            selected.clear();
            renderPills();
            syncHidden();

            $('#medicine_id').val('');
            dropdown.classList.add('hidden');
            input.value = '';
            activeIdx = -1;
            filtered = [];

            // Reset prices to blank (HET defaults to 0 on submit, not display)
            elPNP.value = '';
            elNet.value = 'Rp 0';
            elHet.value = '';
            elRaw.value = '0';

            setMode('add');
            $('#table-data tbody tr').removeClass('active-row');
        }

        // ════════════════════════════════════════════════
        // CREDITOR PILL UI
        // ════════════════════════════════════════════════
        function renderDropdown(filter = '') {
            dropdown.innerHTML = '';
            activeIdx = -1;

            filtered = CREDITORS.filter(c =>
                c.name.toLowerCase().includes(filter.toLowerCase())
            );

            if (!filtered.length) {
                dropdown.innerHTML = `<li class="px-4 py-2 text-sm text-gray-400">No results</li>`;
                return;
            }

            filtered.forEach((c, i) => {
                const isSel = selected.has(c.id);
                const li = document.createElement('li');
                li.className = `px-4 py-2 text-sm flex items-center justify-between
            ${isSel ? 'text-gray-400 cursor-not-allowed' : 'cursor-pointer hover:bg-blue-50'}`;
                li.innerHTML = `<span>${c.name}</span>${isSel ? '<span class="text-blue-600">✔</span>' : ''}`;
                if (!isSel) li.onclick = () => selectCreditor(c);
                dropdown.appendChild(li);
            });

            highlightActive();
        }

        function highlightActive() {
            [...dropdown.children].forEach((li, i) =>
                li.classList.toggle('bg-blue-100', i === activeIdx)
            );
        }

        function selectCreditor(item) {
            selected.set(item.id, {
                ...item,
                discount: item.discount ?? 0
            });
            input.value = '';
            dropdown.classList.add('hidden');
            activeIdx = -1;
            renderPills();
            syncHidden();
            setTimeout(() => input.focus(), 0);
        }

        function removeCreditor(id) {
            selected.delete(id);
            renderPills();
            syncHidden();
            renderDropdown(input.value);
        }

        function updateDiscount(id, value) {
            const item = selected.get(id);
            if (!item) return;
            item.discount = parseFloat(value) || 0;
            syncHidden();
        }

        function renderPills() {
            pills.innerHTML = '';
            selected.forEach(item => {
                const pill = document.createElement('div');
                pill.className = 'creditor-pill';
                pill.innerHTML = `
            <span>${item.name}</span>
            <input type="number" step="0.01" min="0" max="100"
                   value="${item.discount}" style="width: auto; color: #111; border: none; background: #e4f1ff; text-align: center; border-radius: 30px; padding: 2px 4px;"
                   class="discount-input">
            <span style="font-size:11px;">%</span>
            <button type="button">&times;</button>
        `;
                pill.querySelector('.discount-input').addEventListener('input', e => updateDiscount(item.id, e
                    .target.value));
                pill.querySelector('button').onclick = () => removeCreditor(item.id);
                pills.appendChild(pill);
            });
        }

        function syncHidden() {
            const payload = [...selected.values()].map(c => ({
                code: c.code,
                discount: c.discount ?? 0
            })).filter(c => c.code);
            hiddenCreds.value = JSON.stringify(payload);
        }
        // ════════════════════════════════════════════════
        // LOAD CREDITORS FOR EXISTING MEDICINE
        // ════════════════════════════════════════════════
        function loadMedicineCreditors(medicineId) {
            selected.clear();
            renderPills();
            syncHidden();

            axios.get(`/medicines/${medicineId}/edit-data`)
                .then(({
                    data
                }) => {
                    (data.creditors || []).forEach(c => selected.set(c.id, {
                        id: c.id,
                        code: c.code,
                        name: c.name,
                        discount: c.discount ?? 0
                    }));
                    renderPills();
                    syncHidden();
                })
                .catch(console.error);
        }

        // ════════════════════════════════════════════════
        // POPULATE FORM FROM ROW DATA
        // ════════════════════════════════════════════════
        function populateForm(row) {
            // Plain inputs
            ['code', 'name', 'barcode', 'content', 'dosage',
                'minimal_stock', 'strip', 'stock', 'preparations'
            ].forEach(key => {
                const el = document.getElementById(key);
                if (el) el.value = row[key] ?? '';
            });

            // Price fields → format as rupiah
            elPNP.value = row.pharmacy_net_price ? toRupiah(row.pharmacy_net_price) : '';
            elNet.value = row.net_price ? toRupiah(row.net_price) : 'Rp 0';
            elHet.value = row.het_price ? toRupiah(row.het_price) : '';
            elRaw.value = row.raw_price ?? 0;

            // Select2 AJAX fields
            setSelect2Value('#medicine_category_id', row.medicine_category_id, row.category_name);
            setSelect2Value('#composition_id', row.composition_id, row.composition_name);
            setSelect2Value('#factory_id', row.factory_id, row.factory_name);
            setSelect2Value('#location', row.location, row.location_name);
            setSelect2Value('#items', row.etalase, row.etalase_name);

            // Static select2
            $('#type').val(row.type).trigger('change');
            $('#packaging').val(row.packaging).trigger('change');
            $('#unit').val(row.unit).trigger('change');

            // Set the checkbox based on "content" value
            const packCheckbox = document.getElementById('pack');
            if (packCheckbox) {
                // If content is not '1', check the checkbox; otherwise, uncheck it
                packCheckbox.checked = row.content !== '1';
            }

            // Hidden id
            $('#medicine_id').val(row.id);
        }

        // ════════════════════════════════════════════════
        // SUBMIT
        // ════════════════════════════════════════════════
        function handleSubmit() {
            const fd = new FormData(form);
            const id = document.getElementById('medicine_id').value;

            // send cleaned numeric values
            fd.set('generic', document.getElementById('generic_check').checked ? 1 : 0);
            fd.set('raw_price', stripRupiah(elRaw.value));
            fd.set('pharmacy_net_price', stripRupiah(elPNP.value));
            fd.set('net_price', stripRupiah(elNet.value));

            // HET: default to 0 when blank
            const hetRaw = stripRupiah(elHet.value);
            fd.set('het_price', hetRaw === '0' || hetRaw === '' ? 0 : hetRaw);

            const url = id ? `/medicines/${id}` : form.action;
            if (id) fd.append('_method', 'PUT');

            axios.post(url, fd)
                .then(({
                    data
                }) => {
                    iziToast.success({
                        title: 'Sukses',
                        message: data.message
                    });
                    resetForm();
                    tableData.ajax.reload(null, false);
                })
                .catch(err => {
                    const msg = err.response?.status === 422 ?
                        Object.values(err.response.data.errors).flat().join('<br>') :
                        'Gagal menyimpan data.';
                    iziToast.error({
                        title: 'Error',
                        message: msg
                    });
                });
        }

        // ════════════════════════════════════════════════
        // ENTER-KEY NAVIGATION
        // Explicit ordered list of field IDs to navigate.
        // Select2-powered selects are identified by their
        // underlying <select> id; plain inputs by id.
        // ════════════════════════════════════════════════

        // The canonical tab-order for the form.
        // Each entry: { id, type: 'input'|'select2'|'select2ajax' }
        const NAV_ORDER = [{
                id: 'medicine_category_id',
                type: 'select2ajax'
            },
            {
                id: 'composition_id',
                type: 'select2ajax'
            },
            {
                id: 'factory_id',
                type: 'select2ajax'
            },
            {
                id: 'name',
                type: 'input'
            },
            {
                id: 'barcode',
                type: 'input'
            },
            {
                id: 'packaging',
                type: 'select2'
            },
            {
                id: 'unit',
                type: 'select2'
            },
            {
                id: 'content',
                type: 'input'
            },
            {
                id: 'dosage',
                type: 'input'
            },
            {
                id: 'strip',
                type: 'input'
            },
            {
                id: 'preparations',
                type: 'input'
            },
            {
                id: 'pharmacy_net_price',
                type: 'input'
            },
            {
                id: 'het_price',
                type: 'input'
            },
            {
                id: 'minimal_stock',
                type: 'input'
            },
            {
                id: 'searchInput',
                type: 'creditor'
            },
            {
                id: 'location',
                type: 'select2ajax'
            },
            {
                id: 'items',
                type: 'select2ajax'
            },
            {
                id: 'type',
                type: 'select2'
            },
        ];

        /** Focus / open the field described by a NAV_ORDER entry. */
        function focusNavItem(item) {
            if (!item) return;
            if (item.type === 'input' || item.type === 'creditor') {
                const el = document.getElementById(item.id);
                if (el) el.focus();
            } else {
                // select2 or select2ajax → open the dropdown
                $('#' + item.id).select2('open');
            }
        }

        /** Navigate forward from the element with the given id. */
        function navForward(fromId) {
            const idx = NAV_ORDER.findIndex(n => n.id === fromId);
            if (idx === -1) return;
            const next = NAV_ORDER[idx + 1];
            if (next) {
                focusNavItem(next);
            } else {
                // End of form → submit
                handleSubmit();
            }
        }

        // ── Plain inputs: Enter → move forward ──
        document.querySelectorAll(
            '#name,#barcode,#content,#dosage,#strip,#preparations,' +
            '#pharmacy_net_price,#het_price,#minimal_stock'
        ).forEach(el => {
            el.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter') return;
                e.preventDefault();
                navForward(this.id);
            });
        });

        // ── Select2 selects: after user picks a value, auto-advance ──
        // (select2:select fires after the user chooses an option)
        // We attach these inside DOMContentLoaded after select2 is initialised,
        // so they live in the DOMContentLoaded block below.

        // ── Creditor search input: Enter handled inside its own keydown ──
        // (already present in the creditor block below)

        // ════════════════════════════════════════════════
        // DOM READY
        // ════════════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', function() {

            // ── Set initial mode ──
            setMode('add');

            // ── Price input events ──
            elPNP.addEventListener('input', function() {
                formatField(this);
                recalcPPN();
            });
            elHet.addEventListener('input', function() {
                formatField(this);
            });

            // ── SELECT2 INIT ──
            const ajaxSelect2 = (selector, url, placeholder) => {
                $(selector).select2({
                    placeholder,
                    allowClear: true,
                    ajax: {
                        url,
                        dataType: 'json',
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
                        cache: true,
                    }
                });
            };

            ajaxSelect2('#composition_id', "{{ route('composition.select') }}", 'Cari Komposisi...');
            ajaxSelect2('#factory_id', "{{ route('factories.select') }}", 'Cari Pabrik...');
            ajaxSelect2('#medicine_category_id', "{{ route('categories.select') }}", 'Cari Golongan...');
            ajaxSelect2('#location', "{{ route('locations.select') }}", 'Cari Lokasi...');
            ajaxSelect2('#items', "{{ route('items.select') }}", 'Cari Etalase...');

            $('#packaging').select2({
                placeholder: 'Pilih Kemasan...',
                allowClear: true
            });
            $('#unit').select2({
                placeholder: 'Pilih Satuan...',
                allowClear: true
            });
            $('#type').select2({
                placeholder: 'Pilih Tipe...',
                allowClear: true
            });

            // ── Select2 auto-advance: after picking a value, move to next field ──
            // Uses the same NAV_ORDER table so the order is always consistent.
            [
                'medicine_category_id', 'composition_id', 'factory_id',
                'packaging', 'unit', 'location', 'items', 'type'
            ].forEach(id => {
                $('#' + id).on('select2:select', function() {
                    // Small delay so select2 can finish closing before we open next
                    setTimeout(() => navForward(id), 80);
                });
                // Also allow clearing without advancing (select2:clear → do nothing)
            });

            // ── CREDITOR SEARCH ──
            input.addEventListener('input', e => {
                dropdown.classList.remove('hidden');
                renderDropdown(e.target.value);
            });

            // ── CREDITOR SEARCH keydown ──
            input.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (dropdown.classList.contains('hidden')) {
                        dropdown.classList.remove('hidden');
                        renderDropdown(input.value);
                    }
                    if (filtered.length) {
                        activeIdx = (activeIdx + 1) % filtered.length;
                        highlightActive();
                    }
                    return;
                }

                if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (filtered.length) {
                        activeIdx = (activeIdx - 1 + filtered.length) % filtered.length;
                        highlightActive();
                    }
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();

                    // If dropdown is open and has a highlighted item → select it
                    if (!dropdown.classList.contains('hidden') && filtered.length) {
                        const pickIdx = activeIdx >= 0 ? activeIdx : 0;
                        if (filtered[pickIdx]) {
                            selectCreditor(filtered[pickIdx]);
                            return;
                        }
                    }

                    // If search box is empty (no text, no pending pick) → advance to next field
                    if (input.value.trim() === '') {
                        dropdown.classList.add('hidden');
                        navForward('searchInput');
                        return;
                    }

                    // Has text but dropdown hidden → open it
                    dropdown.classList.remove('hidden');
                    renderDropdown(input.value);
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('mousedown', e => {
                if (!document.getElementById('creditorSelect').contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            // ── BUTTONS ──
            // Prevent Enter key from re-triggering button click events
            ['submitForm', 'cancelEdit', 'deleteData', 'back'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) btn.addEventListener('keydown', e => {
                    if (e.key === 'Enter') e.preventDefault();
                });
            });

            document.getElementById('submitForm').addEventListener('click', handleSubmit);

            document.getElementById('cancelEdit').addEventListener('click', () => {
                resetForm();
                setMode('add');
            });

            document.getElementById('back').addEventListener('click', () => {
                window.location.href = "{{ route('home') }}";
            });

            document.getElementById('deleteData').addEventListener('click', () => {
                const id = document.getElementById('medicine_id').value;
                if (!id) {
                    iziToast.warning({
                        title: 'Perhatian',
                        message: 'Pilih data obat terlebih dahulu.'
                    });
                    return;
                }
                swal({
                    title: 'Hapus Obat?',
                    text: 'Data akan dinonaktifkan.',
                    icon: 'warning',
                    buttons: true,
                    dangerMode: true,
                }).then(yes => {
                    if (!yes) return;
                    axios.delete('/medicines/' + id)
                        .then(({
                            data
                        }) => {
                            iziToast.success({
                                title: 'Dihapus',
                                message: data.message
                            });
                            resetForm();
                            tableData.ajax.reload(null, false);
                        });
                });
            });

            // ── DATATABLE ──
            tableData = $('#table-data').DataTable({
                responsive: true,
                serverSide: true,
                ajax: "{{ route('medicines.index') }}",
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
                        data: 'category_name'
                    },
                    {
                        data: 'status_label',
                        orderable: false
                    },
                ],
            });

            // ── SINGLE CLICK  → preview (highlight row only) ──
            // ── DOUBLE CLICK  → load into form for editing ──
            let clickTimer = null;

            $('#table-data tbody').on('click', 'tr', function() {
                const rowData = tableData.row(this).data();
                if (!rowData) return;

                clearTimeout(clickTimer);
                clickTimer = setTimeout(() => {
                    // Single-click: just highlight
                    $('#table-data tbody tr').removeClass('active-row');
                    $(this).addClass('active-row');
                }, 220);
            });

            $('#table-data tbody').on('dblclick', 'tr', function() {
                clearTimeout(clickTimer); // cancel single-click highlight timer

                const rowData = tableData.row(this).data();
                if (!rowData) return;

                $('#table-data tbody tr').removeClass('active-row');
                $(this).addClass('active-row');

                populateForm(rowData);
                loadMedicineCreditors(rowData.id);
                setMode('edit');

                // Scroll form into view on mobile
                document.getElementById('medicineForm').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });

        });
    </script>
@endsection
