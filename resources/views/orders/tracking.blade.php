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

        .tp-date-input {
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 13px;
            color: #0F172A;
            width: 220px;
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
                    <button type="button" class="tp-status-btn" data-status="1">Dipesan</button>
                    <button type="button" class="tp-status-btn" data-status="2">Diterima</button>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <input type="text" id="filterDateRange" class="tp-date-input" placeholder="Pilih rentang tanggal">
                    <button type="button" class="tp-reset" id="filterReset">Reset</button>
                </div>
            </div>

            <table id="ordersTrackingTable">
                <thead>
                    <tr>
                        <th>Kode Order</th>
                        <th>Tanggal</th>
                        <th>Obat</th>
                        <th>Creditor</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@section('scripts')
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
                    }
                },
                order: [
                    [1, 'desc']
                ],
                columns: [{
                        data: 'order_code',
                        name: 'orders.code',
                        className: 'col-code'
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
                ]
            });

            $('#filterStatusGroup .tp-status-btn').on('click', function() {
                $('#filterStatusGroup .tp-status-btn').removeClass('active');
                $(this).addClass('active');
                activeStatus = $(this).data('status').toString();
                table.ajax.reload();
            });

            $('#filterReset').on('click', function() {
                $('#filterStatusGroup .tp-status-btn').removeClass('active');
                $('#filterStatusGroup .tp-status-btn[data-status=""]').addClass('active');
                activeStatus = '';
                dateFrom = '';
                dateTo = '';
                dateRange.clear();
                table.ajax.reload();
            });
        });
    </script>
@endsection
