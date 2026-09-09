@extends('layouts.app')
@section('title', 'Tracking Pesanan')

@section('style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .tp-page {
            background: #F8FAFC;
            min-height: 100%;
            padding: 24px;
        }

        .tp-card {
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .tp-header {
            padding: 20px 24px;
            border-bottom: 1px solid #E2E8F0;
        }

        .tp-title {
            font-size: 18px;
            font-weight: 700;
            color: #0F172A;
            margin: 0;
        }

        .tp-subtitle {
            font-size: 13px;
            color: #64748B;
            margin-top: 2px;
        }

        .tp-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            padding: 16px 24px;
            border-bottom: 1px solid #E2E8F0;
        }

        /* status pill toggle */
        .tp-status-group {
            display: inline-flex;
            background: #F1F5F9;
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
        }

        /* Container Status */
        .tp-status-group {
            position: relative;
            /* Wajib relative agar glider mengacu ke container ini */
            display: inline-flex;
            background: #F1F5F9;
            border-radius: 10px;
            padding: 3px;
            gap: 2px;
            isolation: isolate;
            /* Memastikan z-index terisolasi dengan rapi */
        }

        /* Gelembung Bergerak (Glider / Bubble) */
        .tp-status-glider {
            position: absolute;
            top: 3px;
            left: 0;
            height: calc(100% - 6px);
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .08), 0 1px 2px rgba(15, 23, 42, .04);
            /* Transisi kenyal & mulus menyerupai gelembung */
            transition: transform 0.35s cubic-bezier(0.34, 1.25, 0.64, 1),
                width 0.35s cubic-bezier(0.34, 1.25, 0.64, 1);
            z-index: 1;
            pointer-events: none;
        }

        /* Tombol Status */
        .tp-status-btn {
            position: relative;
            z-index: 2;
            /* Agar teks tombol ada di atas glider */
            border: none;
            background: transparent !important;
            /* Background aktif ditangani oleh glider */
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #64748B;
            border-radius: 8px;
            cursor: pointer;
            transition: color 0.25s ease;
            outline: none !important;
        }

        .tp-status-btn:hover {
            color: #0F172A;
        }

        /* Warna teks sesuai status aktif */
        .tp-status-btn.active {
            color: #0F172A;
        }

        .tp-status-btn.active[data-status="2"] {
            color: #059669;
        }

        .tp-status-btn.active[data-status="1"] {
            color: #D97706;
        }

        .tp-search-input {
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 8px 12px 8px 34px;
            font-size: 13px;
            color: #0F172A;
            width: 200px;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='6.5' cy='6.5' r='4.5'/%3E%3Cpath d='m10 10 3.5 3.5'/%3E%3C/svg%3E") no-repeat 10px center;
            transition: all 0.2s ease;
        }

        .tp-search-input:focus {
            outline: none;
            border-color: #0D9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .12);
            width: 240px;
        }

        .tp-date-input {
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            color: #0F172A;
            width: 200px;
            background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2364748B' stroke-width='1.6'%3E%3Crect x='2' y='3' width='12' height='11' rx='2'/%3E%3Cpath d='M5 1.5v3M11 1.5v3M2 6.5h12'/%3E%3C/svg%3E") no-repeat right 12px center;
        }

        .tp-date-input:focus {
            outline: none;
            border-color: #0D9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .12);
        }

        .tp-reset {
            border: 1px solid #E2E8F0;
            background: #fff;
            color: #64748B;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 10px;
            cursor: pointer;
        }

        .tp-reset:hover {
            color: #0F172A;
            border-color: #CBD5E1;
        }

        /* table */
        #ordersTrackingTable {
            width: 100% !important;
            border-collapse: collapse;
        }

        #ordersTrackingTable thead th {
            background: #F8FAFC;
            color: #64748B;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            text-align: left;
            padding: 12px 24px;
            border-bottom: 1px solid #E2E8F0;
            white-space: nowrap;
        }

        #ordersTrackingTable tbody td {
            padding: 13px 24px;
            font-size: 13.5px;
            color: #1E293B;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }

        #ordersTrackingTable tbody tr:hover {
            background: #F8FAFC;
        }

        #ordersTrackingTable .col-code {
            font-family: ui-monospace, monospace;
            font-size: 12.5px;
            color: #475569;
        }

        #ordersTrackingTable .col-num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .tp-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .tp-badge .dot {
            width: 6px;
            height: 6px;
            border-radius: 999px;
        }

        .tp-badge.received {
            background: #ECFDF5;
            color: #059669;
        }

        .tp-badge.received .dot {
            background: #059669;
        }

        .tp-badge.pending {
            background: #FFFBEB;
            color: #D97706;
        }

        .tp-badge.pending .dot {
            background: #D97706;
        }

        /* datatables chrome */
        .dataTables_wrapper {
            padding: 0 24px 20px;
        }

        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: flex-end;
            padding: 16px 0 12px;
        }

        .dataTables_filter label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #64748B;
            font-weight: 500;
        }

        .dataTables_filter input {
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            width: 240px;
            outline: none;
        }

        .dataTables_filter input:focus {
            border-color: #0D9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, .12);
        }

        .dataTables_info {
            color: #64748B;
            font-size: 13px;
            padding-top: 14px !important;
        }

        .dataTables_paginate {
            padding-top: 10px !important;
        }

        .dataTables_paginate .paginate_button {
            border: 1px solid transparent !important;
            border-radius: 8px !important;
            padding: 6px 11px !important;
            margin-left: 2px !important;
            font-size: 13px !important;
            color: #475569 !important;
        }

        .dataTables_paginate .paginate_button.current {
            background: #eafeff!important;
            color: #fff !important;
            border-color: #0D9488 !important;
        }

        .dataTables_paginate .paginate_button:hover:not(.current) {
            background: #F1F5F9 !important;
        }

        .dataTables_paginate .paginate_button.disabled {
            color: #CBD5E1 !important;
        }

        .dataTables_processing {
            color: #64748B;
        }
    </style>
@endsection

@section('content')
    <div class="tp-page">
        <div class="tp-card">
            <div class="tp-header">
                <div class="flex gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-truck-loading">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M2 3h1a2 2 0 0 1 2 2v10a2 2 0 0 0 2 2h15" />
                        <path d="M9 9a3 3 0 0 1 3 -3h4a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-4a3 3 0 0 1 -3 -3l0 -2" />
                        <path d="M7 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                        <path d="M16 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                    </svg>
                    <h5 class="tp-title">
                        Tracking Pesanan</h5>
                </div>


                <div class="tp-subtitle">Pantau status seluruh item pesanan, diterima atau belum, diurutkan berdasarkan
                    tanggal</div>
            </div>

            <div class="tp-toolbar">
                <div class="tp-status-group" id="filterStatusGroup">
                    <div class="tp-status-glider"></div>
                    <button type="button" class="tp-status-btn active" data-status="">Semua</button>
                    <button type="button" class="tp-status-btn" data-status="0">Dipesan</button>
                    <button type="button" class="tp-status-btn" data-status="2">Diterima</button>
                </div>

                <div style="display:flex; align-items:center; gap:8px; flex-wrap: wrap;">
                    <input type="text" id="filterMedicine" class="tp-search-input" placeholder="Cari nama obat...">
                    <select id="filterCreditor" class="select2" style="width: 200px;">
                        <option value="">Semua PBF</option>
                        @foreach($creditors as $creditor)
                            <option value="{{ $creditor->code }}">{{ $creditor->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" id="filterDateRange" class="tp-date-input" placeholder="Pilih rentang tanggal">
                    <button type="button" class="tp-reset" id="filterReset">Reset</button>
                </div>
            </div>

            @hasanyrole('HO|administrator|manager|Manager|operator|Operator|Gudang PMI')
                <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-200" id="consolidationPanel">
                    <div class="flex items-start gap-3" id="consolidationHint">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <div class="text-xs text-slate-600 flex-1">
                            <p class="font-semibold text-slate-800 text-sm">Konsolidasi Item BPBA (Faktur Gabungan)</p>
                            <p class="mt-0.5">Gunakan fitur ini jika PBF mengirimkan pesanan dari beberapa BPBA dalam <strong>satu faktur fisik</strong>. Centang item pada tabel (minimal dari 2 BPBA dan 1 PBF yang sama) untuk menggabungkannya ke BPBA baru.</p>
                            <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded text-[11px] font-medium bg-amber-50 text-amber-800 border border-amber-200">
                                <svg class="w-3 h-3 text-amber-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>
                                BPBA baru khusus penerimaan faktur fisik — bukan pesanan ulang ke PBF. Sisa pesanan asli tetap tercatat.
                            </span>
                        </div>
                    </div>

                    <div id="consolidationBox" style="display:none;" class="mt-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-semibold text-slate-700 uppercase tracking-wider" id="consolidationCount">0 Item Dipilih</span>
                            <span class="text-[11px] text-slate-500">Tentukan jumlah yang akan dipindahkan ke faktur gabungan:</span>
                        </div>
                        <div id="consolidationSelection" class="space-y-2 mb-3"></div>
                        <div class="flex items-center gap-2 pt-2 border-t border-slate-200">
                            <button type="button" id="consolidateOrders" class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Gabungkan Item ke BPBA Baru
                            </button>
                            <button type="button" id="clearConsolidation" class="inline-flex items-center gap-1 px-3 py-2 bg-white hover:bg-slate-100 text-slate-700 text-xs font-medium border border-slate-300 rounded-lg transition-all">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                Kosongkan Pilihan
                            </button>
                        </div>
                        <p id="consolidationMessage" role="status" class="text-xs text-red-600 font-medium mt-2 empty:hidden"></p>
                    </div>
                </div>
            @endhasanyrole
            <table id="ordersTrackingTable">
                <thead>
                    <tr>
                        <th>Pilih / Sisa</th>
                        <th>No. SP</th>
                        <th>Kode Order</th>
                        <th>Tanggal</th>
                        <th>Obat</th>
                        <th>Creditor</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
    @php
        $canConsolidate = auth()->check() && auth()->user()->hasAnyRole(['HO', 'administrator', 'manager', 'Manager', 'operator', 'Operator', 'Gudang PMI']);
    @endphp
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        // Effect
        document.addEventListener('DOMContentLoaded', function() {
            const statusGroup = document.getElementById('filterStatusGroup');
            const glider = statusGroup.querySelector('.tp-status-glider');
            const buttons = statusGroup.querySelectorAll('.tp-status-btn');

            // Fungsi untuk memindahkan gelembung ke tombol aktif
            function moveGlider(activeBtn) {
                if (!activeBtn || !glider) return;

                // Hitung posisi dan lebar tombol aktif relatif terhadap container
                const left = activeBtn.offsetLeft;
                const width = activeBtn.offsetWidth;

                glider.style.transform = `translateX(${left}px)`;
                glider.style.width = `${width}px`;
            }

            // Set posisi awal glider saat halaman pertama kali dimuat
            const initialActive = statusGroup.querySelector('.tp-status-btn.active') || buttons[0];
            moveGlider(initialActive);

            // Event listener saat tombol status diklik
            buttons.forEach(btn => {
                btn.addEventListener('click', function() {
                    buttons.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    moveGlider(this);
                });
            });

            // Sesuaikan ulang posisi jika window di-resize
            window.addEventListener('resize', function() {
                const currentActive = statusGroup.querySelector('.tp-status-btn.active');
                moveGlider(currentActive);
            });
        });


        // JS
        $(function() {
            let activeStatus = '';
            let dateFrom = '';
            let dateTo = '';
            let activeCreditor = '';
            let activeMedicine = '';
            const selectedItems = new Map();
            let consolidationKey = '{{ (string) \Illuminate\Support\Str::uuid() }}';
            const canConsolidate = {{ $canConsolidate ? 'true' : 'false' }};
            const escapeText = value => $('<div>').text(value ?? '').html();
            function renderSelection() {
                const box = $('#consolidationSelection').empty();
                if (selectedItems.size === 0) {
                    $('#consolidationBox').hide();
                    return;
                }
                $('#consolidationBox').show();
                $('#consolidationCount').text(`${selectedItems.size} Item Dipilih`);

                const creditors = new Set();
                const orders = new Set();

                selectedItems.forEach((item, id) => {
                    creditors.add(item.creditor_code);
                    orders.add(item.order_id);

                    const row = $('<div>').addClass('flex flex-wrap items-center justify-between gap-3 bg-white p-2.5 rounded-lg border border-slate-200 text-xs shadow-xs');

                    const left = $('<div>').addClass('flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 flex-1');
                    $('<span>').addClass('font-mono font-bold bg-slate-100 text-slate-700 px-2 py-0.5 rounded text-[11px]').text(item.order_code).appendTo(left);
                    $('<span>').addClass('font-semibold text-slate-800').text(item.medicine_name).appendTo(left);
                    $('<span>').addClass('text-slate-500 text-[11px]').text(`(${item.creditor_name || '-'})`).appendTo(left);
                    $('<span>').addClass('text-blue-600 font-medium text-[11px]').text(`Sisa: ${item.remaining}`).appendTo(left);
                    left.appendTo(row);

                    const right = $('<div>').addClass('flex items-center gap-2');
                    $('<label>').addClass('text-[11px] text-slate-600 font-medium').text('Pindahkan:').appendTo(right);
                    $('<input>', {type: 'number', min: 0.0001, max: item.remaining, step: 'any', 'aria-label': 'Jumlah dipindahkan'})
                        .val(item.moveQuantity)
                        .addClass('w-20 px-2 py-1 text-xs border border-slate-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500')
                        .on('input', function () {
                            const val = parseFloat(this.value);
                            item.moveQuantity = isNaN(val) ? 0 : val;
                            validateSelection(creditors, orders);
                        }).appendTo(right);

                    $('<button>', {type: 'button', class: 'text-red-500 hover:text-red-700 hover:bg-red-50 p-1 rounded font-bold text-xs', title: 'Hapus dari pilihan'})
                        .html('&times; Batal')
                        .on('click', () => {
                            selectedItems.delete(id);
                            renderSelection();
                            table.rows().invalidate().draw(false);
                        }).appendTo(right);

                    row.appendTo(box);
                });

                validateSelection(creditors, orders);
            }

            function validateSelection(creditors, orders) {
                const message = $('#consolidationMessage');
                const btn = $('#consolidateOrders');
                const items = [...selectedItems.values()];

                if (creditors.size > 1) {
                    message.text('Perhatian: Semua item harus berasal dari satu PBF yang sama!').removeClass('hidden').show();
                    btn.prop('disabled', true);
                    return false;
                }
                if (orders.size < 2) {
                    message.text('Pilih item dari minimal 2 BPBA berbeda untuk digabungkan ke satu faktur penerimaan.').removeClass('hidden').show();
                    btn.prop('disabled', true);
                    return false;
                }
                if (items.some(i => !Number.isFinite(i.moveQuantity) || i.moveQuantity <= 0 || i.moveQuantity > i.remaining)) {
                    message.text('Periksa jumlah dipindahkan: harus lebih dari 0 dan tidak boleh melebihi sisa pesanan.').removeClass('hidden').show();
                    btn.prop('disabled', true);
                    return false;
                }
                message.empty().addClass('hidden').hide();
                btn.prop('disabled', false);
                return true;
            }

            let dateRange = flatpickr("#filterDateRange", {
                mode: "range",
                dateFormat: "Y-m-d",
                onClose: function(selectedDates) {
                    if (selectedDates.length === 1) {
                        // single day selected
                        dateFrom = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                        dateTo = dateFrom;
                        table.ajax.reload();
                    } else if (selectedDates.length === 2) {
                        // range selected
                        dateFrom = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                        dateTo = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                        table.ajax.reload();
                    }
                }
            });

            let table = $('#ordersTrackingTable').DataTable({
                processing: true,
                serverSide: true,
                dom: '<"top">rt<"bottom"lip>',
                language: {
                    search: "",
                    searchPlaceholder: "Cari kode, obat, creditor...",
                    emptyTable: "Tidak ada data pesanan",
                    zeroRecords: "Tidak ditemukan hasil yang cocok",
                    processing: "Memuat...",
                    paginate: {
                        previous: "Prev",
                        next: "Next"
                    }
                },
                ajax: {
                    url: "{{ route('orders-tracking.data') }}",
                    data: function(d) {
                        d.status = activeStatus;
                        d.date_from = dateFrom;
                        d.date_to = dateTo;
                        d.creditor_code = activeCreditor;
                        d.medicine_name = activeMedicine;
                    }
                },
                order: [
                    [2, 'desc']
                ],
                columns: [{
                        data: 'remaining', orderable: false, searchable: false,
                        render: function(value, type, row) {
                            if (type !== 'display') return value;
                            return `${canConsolidate && row.can_consolidate ? `<input type="checkbox" class="consolidate-item" aria-label="Pilih item" ${selectedItems.has(row.id) ? 'checked' : ''}> ` : ''}${value}`;
                        }
                    }, {
                        data: 'sp_code',
                        name: 'sp_code', // Just to make sure it doesn't break search if relation is complex, though Yajra handles some
                        className: 'col-code'
                    },
                    {
                        data: 'order_code',
                        name: 'orders.code',
                        className: 'col-code',
                        render: function(value, type, row) {
                            return type === 'display' ? `${escapeText(value)}<br><small>${escapeText(row.movement_note)}</small>` : value;
                        }
                    },
                    {
                        data: 'order_date',
                        name: 'orders.date'
                    },
                    {
                        data: 'medicine_name',
                        name: 'medicines.name'
                    },
                    {
                        data: 'creditor_name',
                        name: 'creditors.name'
                    },
                    {
                        data: 'quantity',
                        name: 'order_items.quantity',
                        className: 'col-num'
                    },
                    {
                        data: 'total',
                        name: 'order_items.total',
                        className: 'col-num'
                    },
                    {
                        data: 'status_label',
                        name: 'orders.status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    }
                ]
            });

            $('#ordersTrackingTable').on('change', '.consolidate-item', function () {
                const row = table.row($(this).closest('tr')).data();
                if (this.checked) selectedItems.set(row.id, {...row, moveQuantity: row.remaining});
                else selectedItems.delete(row.id);
                renderSelection();
            });
            $('#clearConsolidation').on('click', function () {
                selectedItems.clear(); renderSelection(); table.ajax.reload(null, false);
            });
            $('#consolidateOrders').on('click', async function () {
                const items = [...selectedItems.values()];
                const message = $('#consolidationMessage').text('');
                if (new Set(items.map(item => item.order_id)).size < 2 || new Set(items.map(item => item.creditor_code)).size !== 1) {
                    message.text('Pilih minimal dua BPBA dengan PBF yang sama.'); return;
                }
                if (items.some(item => !Number.isFinite(item.moveQuantity) || item.moveQuantity < 1 || item.moveQuantity > item.remaining)) {
                    message.text('Periksa jumlah: minimal 1 dan tidak boleh melebihi sisa pesanan.'); return;
                }
                if (!confirm(`Pindahkan ${items.length} item ke BPBA baru? Kuantitas aktif pada BPBA asal akan berkurang. Stok belum berubah.`)) return;
                $(this).prop('disabled', true);
                try {
                    const response = await fetch('{{ route('orders-tracking.consolidate') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({request_key: consolidationKey, items: items.map(item => ({id: item.id, quantity: item.moveQuantity}))})
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Konsolidasi gagal.');
                    window.location.href = data.redirect;
                } catch (error) {
                    message.text(error.message); $(this).prop('disabled', false);
                }
            });
            renderSelection();

            let medicineSearchTimer;
            $('#filterMedicine').on('input', function() {
                clearTimeout(medicineSearchTimer);
                const val = $(this).val();
                medicineSearchTimer = setTimeout(function() {
                    activeMedicine = val;
                    table.ajax.reload();
                }, 300);
            });

            $('#filterStatusGroup .tp-status-btn').on('click', function() {
                $('#filterStatusGroup .tp-status-btn').removeClass('active');
                $(this).addClass('active');
                activeStatus = $(this).data('status').toString();
                table.ajax.reload();
            });

            $('#filterCreditor').select2({
                placeholder: "Semua PBF",
                allowClear: true
            }).on('change', function() {
                activeCreditor = $(this).val();
                table.ajax.reload();
            });

            $('#filterReset').on('click', function() {
                $('#filterStatusGroup .tp-status-btn').removeClass('active');
                $('#filterStatusGroup .tp-status-btn[data-status=""]').addClass('active');
                $('#filterCreditor').val(null).trigger('change.select2');
                $('#filterMedicine').val('');
                activeMedicine = '';
                activeStatus = '';
                dateFrom = '';
                dateTo = '';
                activeCreditor = '';
                dateRange.clear();
                table.ajax.reload();
            });

            window.cancelConsolidation = async function(orderId) {
                if (typeof Swal !== 'undefined') {
                    const result = await Swal.fire({
                        title: 'Batalkan Konsolidasi BPBA?',
                        text: 'Kuantitas pesanan akan dikembalikan ke BPBA asal dan BPBA konsolidasi akan dihapus.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Ya, Batalkan',
                        cancelButtonText: 'Kembali'
                    });
                    if (!result.isConfirmed) return;
                } else if (!confirm('Apakah Anda yakin ingin membatalkan konsolidasi ini? Kuantitas pesanan akan dikembalikan ke BPBA asal dan BPBA konsolidasi akan dihapus.')) {
                    return;
                }

                try {
                    const response = await fetch('{{ route('orders-tracking.cancel-consolidation') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ order_id: orderId })
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Gagal membatalkan konsolidasi.'));
                    if (typeof Swal !== 'undefined') {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: data.message,
                            confirmButtonColor: '#2563eb'
                        });
                    } else {
                        alert(data.message);
                    }
                    table.ajax.reload(null, false);
                } catch (error) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: error.message,
                            confirmButtonColor: '#2563eb'
                        });
                    } else {
                        alert(error.message);
                    }
                }
            };
        });
    </script>
@endsection
