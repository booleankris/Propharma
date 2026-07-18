@extends('layouts.app')

@section('title', 'Parameters')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* ── Reset & Base ─────────────────────────────────── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        /* ── Page Layout ──────────────────────────────────── */
        .param-page {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        /* ── Page Header ──────────────────────────────────── */
        .param-page-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .param-page-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a202c;
            margin: 0;
        }

        .param-page-sub {
            font-size: 0.8125rem;
            color: #718096;
            margin-top: 2px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #4a5568;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 7px 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-back:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            color: #2d3748;
        }

        .btn-back svg {
            width: 14px;
            height: 14px;
        }

        /* ── Stat Cards Row ───────────────────────────────── */
        .param-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }

        .param-stat-card {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
        }

        .param-stat-label {
            font-size: 0.6875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #a0aec0;
            margin-bottom: 4px;
        }

        .param-stat-value {
            font-size: 1.375rem;
            font-weight: 600;
            color: #2d3748;
            line-height: 1;
        }

        .param-stat-value.small {
            font-size: 0.875rem;
            font-weight: 500;
            padding-top: 4px;
        }

        /* ── Two-column Layout ────────────────────────────── */
        .param-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            gap: 16px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .param-layout {
                grid-template-columns: 1fr;
            }
        }

        /* ── Card Shell ───────────────────────────────────── */
        .param-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
        }

        .param-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            border-bottom: 1px solid #e2e8f0;
            gap: 12px;
        }

        .param-card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            color: #2d3748;
        }

        .param-card-title svg {
            width: 15px;
            height: 15px;
            color: #a0aec0;
            flex-shrink: 0;
        }

        .param-card-body {
            padding: 18px;
        }

        .param-card-body.no-pad {
            padding: 0;
        }

        /* ── Edit Badge ───────────────────────────────────── */
        .edit-badge {
            display: none;
            font-size: 0.6875rem;
            font-weight: 600;
            background: #ebf8ff;
            color: #2b6cb0;
            padding: 3px 10px;
            border-radius: 20px;
            border: 1px solid #bee3f8;
        }

        .edit-badge.visible {
            display: inline-block;
        }

        /* ── Search Input ─────────────────────────────────── */
        .search-wrap {
            position: relative;
        }

        .search-wrap input {
            width: 200px;
            height: 32px;
            font-size: 0.8125rem;
            padding: 0 10px 0 30px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #f7fafc;
            color: #2d3748;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .search-wrap input:focus {
            border-color: #63b3ed;
            box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.2);
            background: #fff;
        }

        .search-wrap input::placeholder {
            color: #a0aec0;
        }

        .search-icon {
            position: absolute;
            left: 9px;
            top: 50%;
            transform: translateY(-50%);
            width: 13px;
            height: 13px;
            color: #a0aec0;
            pointer-events: none;
        }

        /* ── DataTable Overrides ──────────────────────────── */
        #table-data_wrapper .dataTables_filter,
        #table-data_wrapper .dataTables_length {
            display: none;
            /* we use our own search */
        }

        #table-data_wrapper .dataTables_info,
        #table-data_wrapper .dataTables_paginate {
            padding: 10px 18px;
            font-size: 0.75rem;
            color: #718096;
        }

        #table-data_wrapper .paginate_button {
            font-size: 0.75rem !important;
            padding: 3px 8px !important;
            border-radius: 6px !important;
        }

        #table-data {
            width: 100% !important;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        #table-data thead th {
            background: #f7fafc;
            color: #718096;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            border-top: none;
            white-space: nowrap;
        }

        #table-data tbody tr {
            cursor: pointer;
            transition: background 0.1s;
        }

        #table-data tbody tr:hover {
            background: #f7fafc;
        }

        #table-data tbody tr.selected-row {
            background: #ebf8ff;
        }

        #table-data tbody td {
            padding: 10px 12px;
            color: #2d3748;
            border-bottom: 1px solid #f0f4f8;
        }

        #table-data tbody tr:last-child td {
            border-bottom: none;
        }

        #table-data.dataTable thead .sorting,
        #table-data.dataTable thead .sorting_asc,
        #table-data.dataTable thead .sorting_desc {
            background-image: none;
            padding-right: 12px;
        }

        /* ── Form ─────────────────────────────────────────── */
        .form-section {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .form-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-field label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #4a5568;
        }

        .form-field input[type="number"] {
            height: 36px;
            font-size: 0.8125rem;
            padding: 0 10px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            color: #2d3748;
            outline: none;
            width: 100%;
            transition: border-color 0.15s, box-shadow 0.15s;
        }

        .form-field input[type="number"]:focus {
            border-color: #63b3ed;
            box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.2);
        }

        .form-field input[type="number"]::placeholder {
            color: #cbd5e0;
        }

        /* Select2 overrides */
        .select2-container--default .select2-selection--single {
            height: 36px !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            background: #fff !important;
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: 10px !important;
            font-size: 0.8125rem !important;
            color: #2d3748 !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }

        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #63b3ed !important;
            box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.2) !important;
        }

        .select2-dropdown {
            border: 1px solid #e2e8f0 !important;
            border-radius: 8px !important;
            font-size: 0.8125rem !important;
        }

        /* ── Selected Info Pill ───────────────────────────── */
        .selected-pill {
            display: none;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            font-size: 0.8rem;
            color: #2c5282;
        }

        .selected-pill.visible {
            display: flex;
        }

        .selected-pill svg {
            width: 13px;
            height: 13px;
            flex-shrink: 0;
        }

        .selected-pill strong {
            font-weight: 600;
        }

        /* ── Form Hint ────────────────────────────────────── */
        .form-hint {
            font-size: 0.75rem;
            color: #718096;
            padding: 8px 12px;
            background: #f7fafc;
            border-radius: 8px;
            line-height: 1.5;
            border: 1px solid #e2e8f0;
        }

        /* ── Divider ──────────────────────────────────────── */
        .form-divider {
            height: 1px;
            background: #f0f4f8;
        }

        /* ── Button Group ─────────────────────────────────── */
        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 2px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 36px;
            padding: 0 16px;
            font-size: 0.8125rem;
            font-weight: 500;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #4a5568;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            white-space: nowrap;
        }

        .btn:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }

        .btn svg {
            width: 13px;
            height: 13px;
        }

        .btn-primary {
            background: #2b6cb0;
            color: #fff;
            border-color: #2b6cb0;
        }

        .btn-primary:hover {
            background: #2c5282;
            border-color: #2c5282;
        }

        .btn-danger {
            color: #c53030;
            border-color: #fed7d7;
            background: #fff;
        }

        .btn-danger:hover {
            background: #fff5f5;
            border-color: #fc8181;
        }

        .btn-ghost {
            color: #718096;
            border-color: transparent;
            background: transparent;
        }

        .btn-ghost:hover {
            background: #f7fafc;
            border-color: #e2e8f0;
            color: #4a5568;
        }

        .btn.hidden {
            display: none;
        }
    </style>
@endsection

@section('content')
    <section class="section param-page">

        {{-- PAGE HEADER --}}
        <div class="param-page-header">
            <div>
                <h1 class="param-page-title">Parameters</h1>
                <p class="param-page-sub">Manage pricing parameters per debitur</p>
            </div>
            <button class="btn-back" onclick="window.location.href='{{ route('home') }}'">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 3L5 8l5 5" />
                </svg>
                Back to home
            </button>
        </div>

        {{-- STAT CARDS --}}
        <div class="param-stats" id="statCards">
            <div class="param-stat-card">
                <div class="param-stat-label">Total records</div>
                <div class="param-stat-value" id="statTotal">—</div>
            </div>
            <div class="param-stat-card">
                <div class="param-stat-label">Debitur configured</div>
                <div class="param-stat-value" id="statDebtors">—</div>
            </div>
            <div class="param-stat-card">
                <div class="param-stat-label">Last updated</div>
                <div class="param-stat-value small" id="statUpdated">—</div>
            </div>
        </div>

        {{-- TWO-COLUMN LAYOUT --}}
        <div class="param-layout">

            {{-- TABLE CARD --}}
            <div class="param-card">
                <div class="param-card-header">
                    <div class="param-card-title">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="1" y="1" width="14" height="14" rx="2" />
                            <path d="M1 5h14M5 5v10" />
                        </svg>
                        Parameter data
                    </div>
                    <div class="search-wrap">
                        <svg class="search-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor"
                            stroke-width="1.5">
                            <circle cx="6.5" cy="6.5" r="4.5" />
                            <path d="M10 10l3.5 3.5" />
                        </svg>
                        <input type="text" id="tableSearch" placeholder="Search debitur…">
                    </div>
                </div>
                <div class="param-card-body no-pad">
                    <div style="overflow-x:auto;">
                        <table id="table-data" class="min-w-full">
                            <thead>
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
            </div>

            {{-- FORM CARD --}}
            <div class="param-card">
                <div class="param-card-header">
                    <div class="param-card-title">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="2" y="1" width="12" height="14" rx="2" />
                            <path d="M5 5h6M5 8h6M5 11h3" />
                        </svg>
                        <span id="formTitle">New parameter</span>
                    </div>
                    <span class="edit-badge" id="editBadge">Editing</span>
                </div>

                <div class="param-card-body">
                    <form id="parameterForm" class="form-section" autocomplete="off">
                        @csrf
                        <input type="hidden" id="parameter_id">

                        {{-- Selected info pill --}}
                        <div class="selected-pill" id="selectedPill">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                                <circle cx="8" cy="5" r="3" />
                                <path d="M2 14c0-3.31 2.69-6 6-6s6 2.69 6 6" />
                            </svg>
                            Editing: <strong id="selectedName">—</strong>
                        </div>

                        {{-- Debitur --}}
                        <div class="form-field">
                            <label for="debtor_id">Debitur</label>
                            <select id="debtor_id" class="w-full">
                                <option value="">— Select debitur —</option>
                                @foreach ($debtors as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-divider"></div>

                        {{-- Row 1 --}}
                        <div class="form-row-2">
                            <div class="form-field">
                                <label for="receipt">Resep tunai</label>
                                <input id="receipt" type="number" step="0.01" placeholder="0.00">
                            </div>
                            <div class="form-field">
                                <label for="pdu">UPDS</label>
                                <input id="pdu" type="number" step="0.01" placeholder="0.00">
                            </div>
                        </div>

                        {{-- Row 2 --}}
                        <div class="form-row-2">
                            <div class="form-field">
                                <label for="otc">HV / OTC</label>
                                <input id="otc" type="number" step="0.01" placeholder="0.00">
                            </div>
                            <div class="form-field">
                                <label for="credit">Resep kredit</label>
                                <input id="credit" type="number" step="0.01" placeholder="0.00">
                            </div>
                        </div>

                        {{-- Row 3 --}}
                        <div class="form-row-2">
                            <div class="form-field">
                                <label for="embalas">Embalase</label>
                                <input id="embalas" type="number" placeholder="0">
                            </div>
                            <div class="form-field">
                                <label for="service">Jasa racik</label>
                                <input id="service" type="number" placeholder="0">
                            </div>
                        </div>

                        {{-- Row 4 --}}
                        <div class="form-row-2">
                            <div class="form-field">
                                <label for="rounding">Pembulatan</label>
                                <input id="rounding" type="number" placeholder="0">
                            </div>
                        </div>

                        <div class="form-divider"></div>

                        {{-- Hint --}}
                        <div class="form-hint" id="formHint">
                            Select a row to edit, or fill in the fields to create a new record.
                        </div>

                        {{-- Buttons --}}
                        <div class="btn-group">
                            <button type="button" id="submitForm" class="btn btn-primary">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l4 4 6-6" />
                                </svg>
                                <span id="submitLabel">Save parameter</span>
                            </button>
                            <button type="button" id="cancelEdit" class="btn btn-ghost hidden">
                                Cancel
                            </button>
                            <button type="button" id="deleteData" class="btn btn-danger hidden">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 4h10M6 4V2h4v2M5 4v9a1 1 0 001 1h4a1 1 0 001-1V4" />
                                </svg>
                                Delete
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

            // ── Select2 ──────────────────────────────────────
            $('#debtor_id').select2({
                placeholder: '— Select debitur —',
                width: '100%'
            });

            // ── DataTable ─────────────────────────────────────
            var tableData = $('#table-data').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('parameters.index') }}',
                    dataSrc: function(json) {
                        // Update stat cards from server response
                        if (json.stats) {
                            $('#statTotal').text(json.stats.total ?? json.recordsTotal ?? '—');
                            $('#statDebtors').text(json.stats.debtors ?? '—');
                            $('#statUpdated').text(json.stats.updated ?? 'Today');
                        } else {
                            $('#statTotal').text(json.recordsTotal ?? '—');
                        }
                        return json.data;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px'
                    }, {
                        data: 'debtor_name',
                        searchable: true,
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
                ],
                language: {
                    processing: '<span style="color:#718096;font-size:13px;">Loading…</span>',
                    emptyTable: '<span style="color:#a0aec0;font-size:13px;">No records found</span>',
                    paginate: {
                        previous: '‹',
                        next: '›'
                    }
                }
            });

            // ── Custom search ─────────────────────────────────
            $('#tableSearch').on('keyup', function() {
                tableData.search(this.value).draw();
            });

            // ── Row click ─────────────────────────────────────
            $('#table-data tbody').on('click', 'tr', function() {
                var rowData = tableData.row(this).data();
                if (!rowData) return;

                $('#table-data tbody tr').removeClass('selected-row');
                $(this).addClass('selected-row');

                $('#parameter_id').val(rowData.id);
                $('#debtor_id').val(rowData.debtor_id).trigger('change');
                $('#receipt').val(rowData.receipt);
                $('#pdu').val(rowData.pdu);
                $('#otc').val(rowData.otc);
                $('#credit').val(rowData.credit);
                $('#embalas').val(rowData.embalas);
                $('#service').val(rowData.service);
                $('#rounding').val(rowData.rounding);

                setEditMode(true, rowData.debtor_name);
            });

            // ── Submit / Update ───────────────────────────────
            $('#submitForm').on('click', function() {
                var id = $('#parameter_id').val();
                var url = id ? '/parameters/' + id : '/parameters';

                var payload = {
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

                if (!payload.debtor_id) {
                    iziToast.warning({
                        title: 'Required',
                        message: 'Please select a debitur.'
                    });
                    $('#debtor_id').next('.select2-container').find('.select2-selection').css(
                        'border-color', '#fc8181');
                    return;
                }

                axios.post(url, payload)
                    .then(function(res) {
                        iziToast.success({
                            title: 'Success',
                            message: res.data.message
                        });
                        resetForm();
                        tableData.ajax.reload(null, false);
                    })
                    .catch(function(err) {
                        var msg = err.response?.data?.message || 'Failed to save. Please try again.';
                        iziToast.error({
                            title: 'Error',
                            message: msg
                        });
                    });
            });

            // ── Delete ────────────────────────────────────────
            $('#deleteData').on('click', function() {
                var id = $('#parameter_id').val();
                var name = $('#selectedName').text();
                if (!id) return;

                swal({
                    title: 'Delete parameter?',
                    text: 'This will permanently remove the record for "' + name + '".',
                    icon: 'warning',
                    buttons: ['Cancel', 'Delete'],
                    dangerMode: true,
                }).then(function(ok) {
                    if (!ok) return;
                    axios.delete('/parameters/' + id)
                        .then(function(res) {
                            iziToast.success({
                                title: 'Deleted',
                                message: res.data.message
                            });
                            resetForm();
                            tableData.ajax.reload(null, false);
                        })
                        .catch(function() {
                            iziToast.error({
                                title: 'Error',
                                message: 'Failed to delete.'
                            });
                        });
                });
            });

            // ── Cancel ────────────────────────────────────────
            $('#cancelEdit').on('click', resetForm);

            // ── Enter key navigation ──────────────────────────
            var paramInputs = [
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
                for (var i = index + 1; i < inputs.length; i++) {
                    var el = inputs[i];
                    if (el && !el.disabled && !el.readOnly && el.offsetParent !== null && el.tabIndex !== -1) {
                        return el;
                    }
                }
                return null;
            }

            paramInputs.forEach(function(input, index) {
                if (!input) return;
                input.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    var next = getNextFocusable(paramInputs, index);
                    if (next) next.focus();
                    else $('#submitForm').trigger('click');
                });
            });

            $('#debtor_id').on('select2:select', function() {
                var index = paramInputs.findIndex(function(el) {
                    return el && el.id === 'debtor_id';
                });
                var next = getNextFocusable(paramInputs, index);
                if (next) setTimeout(function() {
                    next.focus();
                }, 100);
                // clear red border
                $(this).next('.select2-container').find('.select2-selection').css('border-color', '');
            });

            // ── Helpers ───────────────────────────────────────
            function setEditMode(editing, debtorName) {
                if (editing) {
                    $('#formTitle').text('Edit parameter');
                    $('#editBadge').addClass('visible');
                    $('#selectedPill').addClass('visible');
                    $('#selectedName').text(debtorName || '—');
                    $('#cancelEdit').removeClass('hidden');
                    $('#deleteData').removeClass('hidden');
                    $('#submitLabel').text('Update parameter');
                    $('#formHint').text('Editing record for "' + debtorName + '". Click Update to save changes.');
                } else {
                    $('#formTitle').text('New parameter');
                    $('#editBadge').removeClass('visible');
                    $('#selectedPill').removeClass('visible');
                    $('#selectedName').text('—');
                    $('#cancelEdit').addClass('hidden');
                    $('#deleteData').addClass('hidden');
                    $('#submitLabel').text('Save parameter');
                    $('#formHint').text('Select a row to edit, or fill in the fields to create a new record.');
                }
            }

            function resetForm() {
                $('#parameterForm')[0].reset();
                $('#parameter_id').val('');
                $('#debtor_id').val('').trigger('change');
                $('#table-data tbody tr').removeClass('selected-row');
                setEditMode(false);
            }

            window.resetForm = resetForm; // expose if needed

        });
    </script>
@endsection
