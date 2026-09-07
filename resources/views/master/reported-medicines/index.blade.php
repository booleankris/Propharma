@extends('layouts.app')

@section('title', 'Master Pelaporan Obat')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 42px !important;
            padding: 6px 10px !important;
            display: flex !important;
            align-items: center !important;
            border-radius: 0.75rem !important;
            border-color: #d1d5db !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 38px !important;
            font-size: 13px !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 38px !important;
        }

        #table-reported tbody tr:hover {
            background-color: #f8fafc;
        }

        .btn-action-edit {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 5px 10px !important;
            border-radius: 8px !important;
            background-color: #2563eb !important;
            color: #ffffff !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08) !important;
            line-height: 1 !important;
        }

        .btn-action-edit:hover {
            background-color: #1d4ed8 !important;
            transform: translateY(-1px) !important;
        }

        .btn-action-delete {
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            padding: 5px 10px !important;
            border-radius: 8px !important;
            background-color: #e11d48 !important;
            color: #ffffff !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            border: none !important;
            cursor: pointer !important;
            transition: all 0.15s ease !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08) !important;
            line-height: 1 !important;
        }

        .btn-action-delete:hover {
            background-color: #be123c !important;
            transform: translateY(-1px) !important;
        }

        .btn-submit-add {
            background-color: #2563eb !important;
            color: #ffffff !important;
            box-shadow: 0 1px 3px rgba(37, 99, 235, 0.2) !important;
        }

        .btn-submit-add:hover {
            background-color: #1d4ed8 !important;
        }

        .btn-download-export {
            background: linear-gradient(135deg, #059669, #0d9488) !important;
            color: #ffffff !important;
            box-shadow: 0 1px 3px rgba(5, 150, 105, 0.2) !important;
        }

        .btn-download-export:hover {
            background: linear-gradient(135deg, #047857, #0f766e) !important;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            {{-- Header Title --}}
            <div
                class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white p-5 rounded-2xl shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div
                        class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shadow-sm">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-gray-800">Master Pelaporan Obat</h2>

                        </div>
                        <p class="text-xs text-gray-500 mt-0.5">Kelola daftar obat yang wajib dilaporkan dan unduh
                            rekapitulasi transaksi selesai dalam format Excel multi-sheet.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Total Terdaftar:</span>
                    <span id="totalBadge" class="px-3 py-1 bg-rose-500 text-white rounded-xl text-xs font-bold shadow-sm">
                        {{ number_format($totalReported) }} Obat
                    </span>
                </div>
            </div>

            {{-- Action Cards Grid: Add Medicine & Export Excel --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 mb-6">

                {{-- Card 1: Tambah Obat Wajib Lapor (Col 5) --}}
                <div
                    class="lg:col-span-5 bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-3 pb-2 border-b border-gray-100">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <h3 class="font-bold text-sm text-gray-800">Tambah Obat Wajib Lapor</h3>
                        </div>

                        <form id="formAddReported">
                            <div class="mb-3">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                    Cari & Pilih Obat <span class="text-red-500">*</span>
                                </label>
                                <select id="selectMedicine" name="medicine_id" class="w-full text-xs" style="width: 100%;">
                                    <option value="">-- Ketik nama / barcode / kode obat --</option>
                                </select>
                                <p class="text-[11px] text-gray-400 mt-1">Hanya menampilkan obat aktif yang belum masuk
                                    daftar pelaporan.</p>
                            </div>

                            <div class="mb-3">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">
                                    Catatan Tambahan (Opsional)
                                </label>
                                <input type="text" id="inputNotes" name="notes"
                                    placeholder="Misal: Wajib lapor bulanan SIPNAP Dinkes"
                                    class="w-full px-3.5 py-2 text-xs rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-200">
                            </div>

                            <button type="submit" id="btnSubmitAdd"
                                class="btn-submit-add w-full mt-2 py-2.5 px-4 rounded-xl text-white text-xs font-semibold transition-all flex items-center justify-center gap-2 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Tambahkan ke Daftar Pelaporan</span>
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Card 2: Export Laporan Excel (Col 7) --}}
                <div
                    class="lg:col-span-7 bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="font-bold text-sm text-gray-800">Export Laporan Excel (Multi-Tab per Obat)</h3>
                            </div>
                            <span
                                class="text-[11px] text-emerald-700 font-medium bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-100">
                                Format Resmi
                            </span>
                        </div>

                        <form id="formExport" action="{{ route('reported-medicines.export') }}" method="GET"
                            target="_blank">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Bulan Periode</label>
                                    <select name="month" id="exportMonth"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                                        @foreach ([
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ] as $mNum => $mName)
                                            <option value="{{ $mNum }}"
                                                {{ (int) date('n') === $mNum ? 'selected' : '' }}>
                                                {{ $mName }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Tahun</label>
                                    <select name="year" id="exportYear"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                                        @php $currentYear = (int)date('Y'); @endphp
                                        @for ($y = $currentYear + 1; $y >= $currentYear - 3; $y--)
                                            <option value="{{ $y }}" {{ $y === $currentYear ? 'selected' : '' }}>
                                                {{ $y }}
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">Apotek / Cabang</label>
                                    <select name="pharmacy_id" id="exportPharmacy"
                                        class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-emerald-200">
                                        <option value="">Semua Cabang</option>
                                        @foreach ($pharmacies as $p)
                                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pt-1">
                                <p class="text-[11px] text-gray-500">
                                    <i class="fas fa-info-circle text-blue-500 me-1"></i> Data diambil dari transaksi kasir
                                    <strong>status selesai</strong> beserta data pasien & dokter.
                                </p>
                                <button type="submit" id="btnExport"
                                    class="btn-download-export py-2.5 px-6 rounded-xl text-white text-xs font-semibold transition-all flex items-center gap-2 shadow-sm whitespace-nowrap">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    <span>Download Laporan (.xlsx)</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            {{-- Main Data Table --}}
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                <div
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-gray-100">
                    <div>
                        <h3 class="font-bold text-base text-gray-800">Daftar Obat yang Wajib Dilaporkan</h3>
                        <p class="text-xs text-gray-500">Seluruh obat di tabel ini akan otomatis dibuatkan lembar kerja
                            (tab sheet) pada laporan Excel.</p>
                    </div>
                    <div>
                        <button type="button" onclick="reloadTable()"
                            class="px-3 py-1.5 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs font-medium transition-all flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span>Segarkan Tabel</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table id="table-reported" class="min-w-full text-xs text-left text-gray-600">
                        <thead class="bg-gray-50 text-gray-700 uppercase font-semibold text-[11px]">
                            <tr>
                                <th class="px-4 py-3 text-center" style="width: 40px;">No</th>
                                <th class="px-4 py-3">Kode Obat</th>
                                <th class="px-4 py-3">Barcode</th>
                                <th class="px-4 py-3">Nama Obat</th>
                                <th class="px-4 py-3">Kategori</th>
                                <th class="px-4 py-3">Satuan</th>
                                <th class="px-4 py-3 text-center">Stok Master</th>
                                <th class="px-4 py-3">Catatan</th>
                                <th class="px-4 py-3">Ditambahkan Oleh</th>
                                <th class="px-4 py-3 text-center" style="width: 130px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            {{-- Modal Edit Catatan Pelaporan --}}
            <div id="modalEditReported"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 backdrop-blur-sm hidden">
                <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-gray-100 p-6 mx-4">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-sm text-gray-800">Edit Catatan Pelaporan</h3>
                        </div>
                        <button type="button" onclick="closeEditModal()"
                            class="text-gray-400 hover:text-gray-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <form id="formEditReported">
                        <input type="hidden" id="editReportedId">
                        <div class="mb-3">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Obat</label>
                            <input type="text" id="editMedicineName" readonly
                                class="w-full px-3.5 py-2 text-xs rounded-xl border border-gray-200 bg-gray-50 text-gray-700 font-medium">
                        </div>
                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Catatan / Keterangan
                                Pelaporan</label>
                            <textarea id="editNotes" rows="3"
                                class="w-full px-3.5 py-2 text-xs rounded-xl border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="Misal: Wajib lapor bulanan SIPNAP Dinkes"></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                            <button type="button" onclick="closeEditModal()"
                                class="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl transition-all">Batal</button>
                            <button type="submit" id="btnSubmitEdit"
                                class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-all shadow-sm flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Simpan Perubahan</span>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        let table;

        $(document).ready(function() {
            // Setup AJAX CSRF
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            // Initialize Select2 AJAX for Medicine Search
            $('#selectMedicine').select2({
                placeholder: '-- Ketik nama / barcode / kode obat --',
                allowClear: true,
                ajax: {
                    url: "{{ route('reported-medicines.search') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term || ''
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.results
                        };
                    },
                    cache: true
                },
                minimumInputLength: 1
            });

            // Initialize DataTables
            table = $('#table-reported').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('reported-medicines.index') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    },
                    {
                        data: 'code',
                        name: 'medicine.code'
                    },
                    {
                        data: 'barcode',
                        name: 'medicine.barcode'
                    },
                    {
                        data: 'name',
                        name: 'medicine.name',
                        className: 'font-semibold text-gray-800'
                    },
                    {
                        data: 'category',
                        name: 'medicine.category.name'
                    },
                    {
                        data: 'unit',
                        name: 'medicine.unit'
                    },
                    {
                        data: 'stock',
                        name: 'medicine.stock',
                        className: 'text-center'
                    },
                    {
                        data: 'notes',
                        name: 'notes'
                    },
                    {
                        data: 'added_by',
                        name: 'user.name'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'text-center'
                    }
                ],
                order: [
                    [3, 'asc']
                ],
                language: {
                    search: "Cari di tabel:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ obat",
                    infoEmpty: "Tidak ada obat terdaftar",
                    zeroRecords: "Tidak ada data obat yang cocok",
                    paginate: {
                        first: "Awal",
                        last: "Akhir",
                        next: "Lanjut",
                        previous: "Kembali"
                    }
                }
            });

            // Submit Add Medicine Form
            $('#formAddReported').on('submit', function(e) {
                e.preventDefault();
                const medId = $('#selectMedicine').val();
                if (!medId) {
                    iziToast.warning({
                        title: 'Peringatan',
                        message: 'Silakan pilih obat terlebih dahulu!',
                        position: 'topRight'
                    });
                    return;
                }

                const btn = $('#btnSubmitAdd');
                btn.prop('disabled', true).addClass('opacity-75');

                $.ajax({
                    url: "{{ route('reported-medicines.store') }}",
                    type: "POST",
                    data: {
                        medicine_id: medId,
                        notes: $('#inputNotes').val()
                    },
                    success: function(res) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: res.message || 'Obat berhasil ditambahkan.',
                            position: 'topRight'
                        });
                        $('#selectMedicine').val(null).trigger('change');
                        $('#inputNotes').val('');
                        table.ajax.reload(null, false);
                    },
                    error: function(err) {
                        let msg = 'Gagal menambahkan obat.';
                        if (err.responseJSON && err.responseJSON.message) {
                            msg = err.responseJSON.message;
                        }
                        iziToast.error({
                            title: 'Gagal',
                            message: msg,
                            position: 'topRight'
                        });
                    },
                    complete: function() {
                        btn.prop('disabled', false).removeClass('opacity-75');
                    }
                });
            });

            // Submit Edit Medicine Form
            $('#formEditReported').on('submit', function(e) {
                e.preventDefault();
                const id = $('#editReportedId').val();
                const btn = $('#btnSubmitEdit');
                btn.prop('disabled', true).addClass('opacity-75');

                $.ajax({
                    url: `/reported-medicines/${id}`,
                    type: "POST",
                    data: {
                        _method: 'PUT',
                        notes: $('#editNotes').val()
                    },
                    success: function(res) {
                        iziToast.success({
                            title: 'Berhasil',
                            message: res.message ||
                                'Catatan pelaporan obat berhasil diperbarui.',
                            position: 'topRight'
                        });
                        closeEditModal();
                        table.ajax.reload(null, false);
                    },
                    error: function(err) {
                        let msg = 'Gagal memperbarui catatan.';
                        if (err.responseJSON && err.responseJSON.message) {
                            msg = err.responseJSON.message;
                        }
                        iziToast.error({
                            title: 'Gagal',
                            message: msg,
                            position: 'topRight'
                        });
                    },
                    complete: function() {
                        btn.prop('disabled', false).removeClass('opacity-75');
                    }
                });
            });
        });

        function reloadTable() {
            if (table) {
                table.ajax.reload();
            }
        }

        function openEditModal(id, name, notes) {
            $('#editReportedId').val(id);
            $('#editMedicineName').val(name);
            $('#editNotes').val(notes || '');
            $('#modalEditReported').removeClass('hidden');
        }

        function closeEditModal() {
            $('#modalEditReported').addClass('hidden');
            $('#editReportedId').val('');
            $('#editMedicineName').val('');
            $('#editNotes').val('');
        }

        function deleteReportedMedicine(id, name) {
            Swal.fire({
                title: 'Hapus dari Daftar?',
                html: `Apakah Anda yakin ingin menghapus obat <b>${name}</b> dari daftar pelaporan?<br><small class="text-muted">Obat tidak terhapus dari master obat, hanya dihilangkan dari pelaporan.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Ya, Hapus',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/reported-medicines/${id}`,
                        type: 'DELETE',
                        success: function(res) {
                            iziToast.success({
                                title: 'Berhasil',
                                message: res.message || 'Obat dihapus dari daftar pelaporan.',
                                position: 'topRight'
                            });
                            table.ajax.reload(null, false);
                        },
                        error: function(err) {
                            iziToast.error({
                                title: 'Gagal',
                                message: 'Terjadi kesalahan saat menghapus data.',
                                position: 'topRight'
                            });
                        }
                    });
                }
            });
        }
    </script>
@endsection
