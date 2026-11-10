@extends('layouts.app')

@section('title', 'Master Jasa')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <style>
        /* ============ Design tokens (netral / monokrom, konsisten) ============ */
        .ui-page {
            --ui-text: #0f172a;
            --ui-text-muted: #64748b;
            --ui-text-soft: #94a3b8;
            --ui-border: #e2e8f0;
            --ui-border-strong: #cbd5e1;
            --ui-surface: #ffffff;
            --ui-surface-muted: #f8fafc;
            --ui-accent: #0f172a;
        }

        .ui-card {
            background: var(--ui-surface);
            border: 1px solid var(--ui-border);
            border-radius: 18px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        /* ---------- Tombol: satu gaya, ukuran seragam ---------- */
        .ui-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 40px;
            padding: 0 16px;
            border-radius: 12px;
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }

        .ui-btn:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .ui-btn--primary {
            background: var(--ui-accent);
            border-color: var(--ui-accent);
            color: #ffffff;
        }

        .ui-btn--primary:hover:not(:disabled) {
            background: #1e293b;
            border-color: #1e293b;
        }

        .ui-btn--secondary {
            background: var(--ui-surface);
            border-color: var(--ui-border);
            color: #334155;
        }

        .ui-btn--secondary:hover:not(:disabled) {
            background: var(--ui-surface-muted);
            border-color: var(--ui-border-strong);
            color: var(--ui-text);
        }

        .ui-btn--ghost {
            background: transparent;
            border-color: transparent;
            color: var(--ui-text-muted);
        }

        .ui-btn--ghost:hover:not(:disabled) {
            background: #f1f5f9;
            color: var(--ui-text);
        }

        .ui-btn--danger {
            background: var(--ui-surface);
            border-color: var(--ui-border);
            color: var(--ui-text-muted);
        }

        .ui-btn--danger:hover:not(:disabled) {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        /* ---------- Form control ---------- */
        .ui-label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
        }

        .ui-input {
            width: 100%;
            height: 40px;
            padding: 0 14px;
            border: 1px solid var(--ui-border);
            border-radius: 12px;
            background: var(--ui-surface);
            font-size: 13px;
            color: var(--ui-text);
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .ui-input::placeholder {
            color: var(--ui-text-soft);
        }

        .ui-input:focus {
            outline: none;
            border-color: #94a3b8;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, .18);
        }

        .ui-input--prefix {
            padding-left: 40px;
        }

        .ui-prefix {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 12px;
            font-weight: 600;
            color: var(--ui-text-soft);
            pointer-events: none;
        }

        .ui-hint {
            margin-top: 6px;
            font-size: 11px;
            color: var(--ui-text-soft);
        }

        /* ---------- Badge & nominal ---------- */
        .ui-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border: 1px solid var(--ui-border);
            border-radius: 999px;
            background: #f1f5f9;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
        }

        .ui-amount {
            font-weight: 600;
            color: var(--ui-text);
            font-variant-numeric: tabular-nums;
        }

        /* ---------- Aksi pada tabel ---------- */
        .ui-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid var(--ui-border);
            border-radius: 10px;
            background: var(--ui-surface);
            color: var(--ui-text-muted);
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }

        .ui-icon-btn:hover {
            background: var(--ui-surface-muted);
            border-color: var(--ui-border-strong);
            color: var(--ui-text);
        }

        .ui-icon-btn--danger:hover {
            background: #fef2f2;
            border-color: #fecaca;
            color: #dc2626;
        }

        /* ---------- Tabel ---------- */
        #table-data {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            color: #334155;
        }

        #table-data thead th {
            padding: 11px 20px;
            background: #f8fafc;
            border-bottom: 1px solid var(--ui-border);
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--ui-text-muted);
            text-align: left;
            white-space: nowrap;
        }

        #table-data tbody td {
            padding: 13px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        #table-data tbody tr {
            cursor: pointer;
            transition: background-color .15s ease;
        }

        #table-data tbody tr:hover {
            background: var(--ui-surface-muted);
        }

        #table-data tbody tr.is-selected {
            background: #f1f5f9;
        }

        #table-data tbody tr:last-child td {
            border-bottom: none;
        }

        #table-data tbody td.ui-cell-center {
            text-align: center;
        }

        /* ---------- DataTables wrapper (netral) ---------- */
        #table-data_wrapper .dt-bottom {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 20px;
            border-top: 1px solid #f1f5f9;
        }

        #table-data_wrapper .dataTables_info {
            padding: 0 !important;
            font-size: 12px;
            color: var(--ui-text-muted);
        }

        #table-data_wrapper .dataTables_paginate {
            padding: 0 !important;
        }

        #table-data_wrapper .dataTables_paginate .paginate_button {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            margin-left: 4px !important;
            padding: 5px 10px !important;
            border: 1px solid var(--ui-border) !important;
            border-radius: 9px !important;
            background: var(--ui-surface) !important;
            color: #475569 !important;
            font-size: 12px !important;
            font-weight: 600;
            cursor: pointer;
        }

        #table-data_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--ui-surface-muted) !important;
            border-color: var(--ui-border-strong) !important;
            color: var(--ui-text) !important;
        }

        #table-data_wrapper .dataTables_paginate .paginate_button.current,
        #table-data_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: var(--ui-accent) !important;
            border-color: var(--ui-accent) !important;
            color: #ffffff !important;
        }

        #table-data_wrapper .dataTables_paginate .paginate_button.disabled,
        #table-data_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background: var(--ui-surface) !important;
            border-color: var(--ui-border) !important;
            color: var(--ui-text-soft) !important;
            cursor: not-allowed;
        }

        #table-data_wrapper .dataTables_processing {
            border: 1px solid var(--ui-border) !important;
            border-radius: 12px !important;
            background: #ffffff !important;
            color: var(--ui-text-muted) !important;
            font-size: 12px !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .08) !important;
        }

        /* ---------- Layout ---------- */
        .ui-header {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding: 18px 20px;
            margin-bottom: 20px;
        }

        .ui-header-main {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .ui-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ui-title {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            line-height: 1.25;
            color: var(--ui-text);
        }

        .ui-subtitle {
            margin: 3px 0 0;
            font-size: 12px;
            line-height: 1.5;
            color: var(--ui-text-muted);
        }

        .ui-icon-box {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
            flex-shrink: 0;
            border: 1px solid var(--ui-border);
            border-radius: 14px;
            background: #f8fafc;
            color: #475569;
        }

        .ui-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 20px;
        }

        .ui-toolbar {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .ui-toolbar-tools {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ui-search {
            position: relative;
            width: 100%;
        }

        .ui-search svg {
            position: absolute;
            left: 13px;
            top: 50%;
            width: 15px;
            height: 15px;
            transform: translateY(-50%);
            color: var(--ui-text-soft);
            pointer-events: none;
        }

        .ui-search .ui-input {
            padding-left: 36px;
        }

        .ui-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .ui-card-title {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            color: var(--ui-text);
        }

        .ui-card-subtitle {
            margin: 3px 0 0;
            font-size: 12px;
            color: var(--ui-text-muted);
        }

        .ui-form-body {
            padding: 20px;
        }

        .ui-field {
            margin-bottom: 16px;
        }

        .ui-field:last-of-type {
            margin-bottom: 0;
        }

        .ui-form-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .ui-form-actions-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ui-form-actions-row .ui-btn {
            flex: 1;
        }

        @media (min-width: 640px) {
            .ui-toolbar {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }

            .ui-search {
                width: 240px;
            }
        }

        @media (min-width: 768px) {
            .ui-header {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }

        @media (min-width: 1024px) {
            .ui-grid {
                grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
                align-items: start;
            }
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 ui-page">
        <div class="section-body">

            {{-- Header --}}
            <div class="ui-card ui-header">
                <div class="ui-header-main">
                    <div class="ui-icon-box">
                        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.7">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="ui-title">Master Jasa</h2>
                        <p class="ui-subtitle">
                            Kelola daftar jasa / tindakan dokter. Harga <strong>0</strong> berarti jasa tidak berbayar.
                        </p>
                    </div>
                </div>
                <div class="ui-header-actions">
                    <a href="{{ route('master-data.export', ['type' => 'doctor-services']) }}"
                        class="ui-btn ui-btn--secondary">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-8-4 4 4m0 0 4-4m-4 4V4" />
                        </svg>
                        Export CSV
                    </a>
                </div>
            </div>

            <div class="ui-grid">

                {{-- Daftar Jasa --}}
                <div class="ui-card overflow-hidden">
                    <div class="ui-toolbar">
                        <div>
                            <h3 class="ui-card-title">Daftar Jasa</h3>
                            <p class="ui-card-subtitle">Klik salah satu baris untuk mengubah data.</p>
                        </div>
                        <div class="ui-toolbar-tools">
                            <div class="ui-search">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m21 21-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                                </svg>
                                <input id="searchInput" type="text" class="ui-input"
                                    placeholder="Cari jasa..." autocomplete="off">
                            </div>
                            <span id="rowBadge" class="ui-badge">0 data</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="table-data">
                            <thead>
                                <tr>
                                    <th style="width: 64px;">#</th>
                                    <th>Nama Jasa</th>
                                    <th style="width: 160px;">Harga</th>
                                    <th style="width: 96px; text-align: center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>

                {{-- Form --}}
                <div class="ui-card overflow-hidden">
                    <div class="ui-card-head">
                        <div>
                            <h3 id="formTitle" class="ui-card-title">Tambah Jasa</h3>
                            <p id="formSubtitle" class="ui-card-subtitle">Lengkapi data di bawah ini.</p>
                        </div>
                        <span id="modeBadge" class="ui-badge">Baru</span>
                    </div>

                    <form id="doctorServiceForm" action="{{ route('doctor-services.store') }}" method="POST"
                        class="ui-form-body" autocomplete="off">
                        @csrf
                        <input type="hidden" id="doctor_service_id" name="id">

                        <div class="ui-field">
                            <label for="name" class="ui-label">Nama Jasa</label>
                            <input id="name" name="name" type="text" class="ui-input"
                                placeholder="Contoh: Jasa Konsultasi Dokter">
                        </div>

                        <div class="ui-field">
                            <label for="price" class="ui-label">Harga</label>
                            <div class="relative">
                                <span class="ui-prefix">Rp</span>
                                <input id="price" name="price" type="number" min="0" step="1" value="0"
                                    class="ui-input ui-input--prefix">
                            </div>
                            <p class="ui-hint">Isi 0 bila jasa tidak berbayar (gratis).</p>
                        </div>

                        <div class="ui-form-actions">
                            <button type="button" id="submitForm" class="ui-btn ui-btn--primary">
                                <span id="submitLabel">Simpan</span>
                            </button>

                            <div class="ui-form-actions-row">
                                <button type="button" id="cancelEdit" class="ui-btn ui-btn--secondary">Batal</button>
                                <button type="button" id="deleteData" class="ui-btn ui-btn--danger" disabled>
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Hapus
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
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

    <script>
        let tableData;
        let selectedData = null;
        const form = document.getElementById('doctorServiceForm');

        function toast(type, message) {
            iziToast[type]({
                title: type === 'success' ? 'Berhasil' : 'Gagal',
                message: message,
                position: 'topRight',
                timeout: 3000,
                progressBar: true,
                transitionIn: 'fadeInDown',
                transitionOut: 'fadeOutUp'
            });
        }

        function formatRupiah(value) {
            return 'Rp ' + Number(value || 0).toLocaleString('id-ID');
        }

        $(function() {

            tableData = $('#table-data').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                searching: false,
                dom: '<"dt-top"t><"dt-bottom"ip>',
                ajax: '{{ route('doctor-services.index') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'ui-cell-center',
                        width: '64px'
                    },
                    {
                        data: 'name',
                        className: 'font-semibold'
                    },
                    {
                        data: 'price_raw',
                        orderable: false,
                        searchable: false,
                        width: '160px',
                        render: function(data) {
                            const value = Number(data || 0);

                            return value > 0 ?
                                '<span class="ui-amount">' + formatRupiah(value) + '</span>' :
                                '<span class="ui-badge">Gratis</span>';
                        }
                    },
                    {
                        data: 'action',
                        orderable: false,
                        searchable: false,
                        className: 'ui-cell-center',
                        width: '96px'
                    }
                ],
                order: [
                    [1, 'asc']
                ],
                language: {
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ jasa',
                    infoEmpty: 'Belum ada data',
                    infoFiltered: '',
                    zeroRecords: 'Tidak ada jasa yang cocok',
                    processing: 'Memuat...',
                    paginate: {
                        first: 'Awal',
                        last: 'Akhir',
                        next: 'Lanjut',
                        previous: 'Kembali'
                    }
                }
            });

            tableData.on('xhr.dt', function(e, settings, json) {
                if (!json) return;
                $('#rowBadge').text((json.recordsFiltered ?? 0) + ' data');
            });

            let searchTimer = null;
            $('#searchInput').on('input', function() {
                const keyword = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function() {
                    tableData.search(keyword).draw();
                }, 250);
            });

            $('#table-data tbody').on('click', 'tr', function() {
                const rowData = tableData.row(this).data();
                if (!rowData) return;

                selectedData = rowData;
                $('#table-data tbody tr').removeClass('is-selected');
                $(this).addClass('is-selected');
                fillForm(rowData);
            });

            $('#cancelEdit').on('click', resetForm);
            $('#deleteData').on('click', function() {
                delete_data($('#doctor_service_id').val());
            });
            $('#submitForm').on('click', handleSubmit);

            [document.getElementById('name'), document.getElementById('price')].forEach(function(input) {
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        handleSubmit();
                    }
                });
            });
        });

        // ---------- Mode form ----------
        function setMode(mode) {
            const isEdit = mode === 'edit';

            $('#formTitle').text(isEdit ? 'Ubah Jasa' : 'Tambah Jasa');
            $('#formSubtitle').text(isEdit ? 'Perbarui nama atau harga jasa.' : 'Lengkapi data di bawah ini.');
            $('#modeBadge').text(isEdit ? 'Mode Ubah' : 'Baru');
            $('#submitLabel').text(isEdit ? 'Simpan Perubahan' : 'Simpan');
            $('#deleteData').prop('disabled', !isEdit);
        }

        function fillForm(data) {
            $('#doctor_service_id').val(data.id);
            $('#name').val(data.name);
            $('#price').val(Number(data.price_raw || 0));
            setMode('edit');
            $('#name').trigger('focus');
        }

        function resetForm() {
            form.reset();
            $('#doctor_service_id').val('');
            $('#price').val(0);
            $('#table-data tbody tr').removeClass('is-selected');
            selectedData = null;
            setMode('create');
        }

        // ---------- Ambil data terbaru (tombol Edit pada tabel) ----------
        function edit_data(id) {
            axios.get('/doctor-services/' + id, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) {
                    fillForm(res.data);
                })
                .catch(function() {
                    toast('error', 'Gagal mengambil data jasa.');
                });
        }

        // ---------- Hapus ----------
        function delete_data(id) {
            if (!id) {
                toast('error', 'Pilih jasa yang ingin dihapus.');
                return;
            }

            swal({
                title: 'Hapus data ini?',
                text: 'Jasa yang dihapus tidak dapat dikembalikan.',
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(function(yes) {
                if (!yes) return;

                $.ajax({
                    url: '/doctor-services/' + id,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(res) {
                        toast('success', res.message);
                        resetForm();
                        tableData.ajax.reload(null, false);
                    },
                    error: function() {
                        toast('error', 'Gagal menghapus data.');
                    }
                });
            });
        }

        // ---------- Simpan / Ubah ----------
        function handleSubmit() {
            const submitBtn = $('#submitForm');
            if (submitBtn.prop('disabled')) return;

            const formData = new FormData(form);
            const id = document.getElementById('doctor_service_id').value;

            if (!formData.get('price')) {
                formData.set('price', 0);
            }

            const url = id ? `/doctor-services/${id}` : form.action;
            if (id) formData.append('_method', 'PUT');

            submitBtn.prop('disabled', true);
            $('#submitLabel').text('Menyimpan...');

            axios({
                    method: 'POST',
                    url: url,
                    data: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then(function(res) {
                    toast('success', res.data.message);
                    resetForm();
                    tableData.ajax.reload(null, false);
                })
                .catch(function(err) {
                    let msg = 'Gagal menyimpan data.';
                    if (err.response && err.response.status === 422) {
                        msg = Object.values(err.response.data.errors).flat().join('<br>');
                    }
                    toast('error', msg);
                })
                .finally(function() {
                    submitBtn.prop('disabled', false);
                    $('#submitLabel').text($('#doctor_service_id').val() ? 'Simpan Perubahan' : 'Simpan');
                });
        }

    </script>
@endsection
