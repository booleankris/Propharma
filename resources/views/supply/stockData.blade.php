@extends('layouts.app')

@section('title', 'Data Stok - ' . ($pharmacy->name ?? 'Apotek'))

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        #medicine+.select2-container .select2-selection--single {
            height: 42px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 0.5rem !important;
            display: flex;
            align-items: center;
            padding: 0 1rem !important;
        }

        #medicine+.select2-container .select2-selection--single .select2-selection__rendered {
            padding: 0;
            line-height: normal;
            font-size: 13px;
            color: #1e293b;
        }

        #medicine+.select2-container .select2-selection--single .select2-selection__arrow {
            height: 100%;
            right: 1rem;
        }

        #medicine+.select2-container--open .select2-selection--single,
        #medicine+.select2-container--focus .select2-selection--single {
            border-color: #93c5fd !important;
            box-shadow: 0 0 0 3px rgba(191, 219, 254, 0.5);
        }

        #orderItemsTable thead th {
            background-color: #f8fafc !important;
            color: #334155 !important;
            font-weight: 700 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 13px 16px !important;
            white-space: nowrap;
        }

        #orderItemsTable tbody td {
            padding: 11px 16px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            color: #334155 !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #orderItemsTable tbody tr:nth-child(even) td {
            background-color: #fafbfd !important;
        }

        #orderItemsTable tbody tr:hover td {
            background-color: #eff6ff !important;
        }

        #orderItemsTable td.cell-storage {
            background-color: #fffbeb !important;
        }

        #orderItemsTable tbody tr:hover td.cell-storage {
            background-color: #fef3c7 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 6px 12px !important;
            border-radius: 6px !important;
            margin: 0 3px !important;
            font-size: 13px !important;
            border: 1px solid #e2e8f0 !important;
            background: #ffffff !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb !important;
            color: #fff !important;
            border: 1px solid #2563eb !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #eff6ff !important;
            color: #2563eb !important;
            border: 1px solid #bfdbfe !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            cursor: default !important;
            color: #94a3b8 !important;
            border: 1px solid #e2e8f0 !important;
            background: #f8fafc !important;
        }
    </style>
@endsection

@section('content')
    <div class="min-h-screen bg-slate-50/50 pb-12 pt-2 px-4 sm:px-6 lg:px-8">
        <div class="mx-auto space-y-4">

            <div
                class="flex flex-col gap-4 p-5 bg-white border border-slate-200/80 rounded-xl shadow-sm md:flex-row md:items-center md:justify-between">

                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                            <path d="M12 12l8 -4.5" />
                            <path d="M12 12l0 9" />
                            <path d="M12 12l-8 -4.5" />
                            <path d="M16 5.25l-8 4.5" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-slate-800 leading-tight">Data Stok</h2>
                            <span
                                class="px-2 py-0.5 text-[10px] font-semibold bg-violet-50 text-violet-600 border border-violet-100 rounded-full">
                                {{ $pharmacy->name ?? ($canSeeWarehouse ? 'Gudang PMI' : 'Cabang') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400">
                            @if($canSeeWarehouse)
                                Konsolidasi Beli Gudang PMI, Jual Sahabat PMI, dan Total Stok Real-time
                            @else
                                Ringkasan Pembelian, Penjualan, dan Stok Fisik Real-time Cabang
                            @endif
                        </p>
                    </div>
                </div>

                <button id="exportBtn" onclick="startExportExcel()"
                    class="inline-flex items-center gap-2 px-4 h-10 rounded-lg text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 transition shadow-sm shrink-0 w-fit cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span id="exportBtnText">Export Excel (.xlsx)</span>
                </button>
            </div>

            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Rentang
                            Tanggal</label>
                        <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                            class="w-full h-[42px] rounded-lg border border-slate-300 bg-white px-4 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                            autocomplete="off">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Pilih
                            Obat</label>
                        <select id="medicine" class="w-full"></select>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table id="orderItemsTable" class="w-full text-sm text-left text-slate-600">
                        <thead>
                            <tr>
                                <th class="px-4 py-3.5 text-center w-12">#</th>
                                <th class="px-4 py-3.5">Kode Obat</th>
                                <th class="px-4 py-3.5">Nama Obat</th>
                                <th class="px-4 py-3.5 text-center">Satuan</th>
                                <th class="px-4 py-3.5 text-right">QTY Awal</th>
                                <th class="px-4 py-3.5 text-right">QTY Beli</th>
                                <th class="px-4 py-3.5 text-right">
                                    {{ $canSeeWarehouse ? 'QTY Jual (PMI)' : 'QTY Jual' }}
                                </th>
                                <th class="px-4 py-3.5 text-right">Sisa Stok</th>
                                @if($canSeeWarehouse)
                                    <th class="px-4 py-3.5 text-center !bg-amber-50/80 !border-b-2 !border-amber-300">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-amber-100 text-amber-900 font-bold text-xs border border-amber-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-building-warehouse">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M3 21v-13l9 -4l9 4v13" />
                                                <path d="M13 13h4v8h-10v-6h6" />
                                                <path d="M13 21v-9a1 1 0 0 0 -1 -1h-2a1 1 0 0 0 -1 1v3" />
                                            </svg>
                                            Stok Gudang
                                        </span>
                                    </th>
                                    <th class="px-4 py-3.5 text-center !bg-sky-50/80 !border-b-2 !border-sky-300">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-sky-100 text-sky-900 font-bold text-xs border border-sky-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-building-store">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M3 21l18 0" />
                                                <path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" />
                                                <path d="M5 21l0 -10.15" />
                                                <path d="M19 21l0 -10.15" />
                                                <path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" />
                                            </svg>
                                            Pelayanan
                                        </span>
                                    </th>
                                @else
                                    <th class="px-4 py-3.5 text-center !bg-sky-50/80 !border-b-2 !border-sky-300">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-sky-100 text-sky-900 font-bold text-xs border border-sky-300">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-building-store">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                                <path d="M3 21l18 0" />
                                                <path d="M3 7v1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1m0 1a3 3 0 0 0 6 0v-1h-18l2 -4h14l2 4" />
                                                <path d="M5 21l0 -10.15" />
                                                <path d="M19 21l0 -10.15" />
                                                <path d="M9 21v-4a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v4" />
                                            </svg>
                                            Stok Etalase
                                        </span>
                                    </th>
                                @endif
                                <th class="px-4 py-3.5 text-center !bg-emerald-50/80 !border-b-2 !border-emerald-300">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-900 font-bold text-xs border border-emerald-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icon-tabler-chart-bar">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                            <path d="M3 13a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v6a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path d="M15 9a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v10a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path d="M9 5a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v14a1 1 0 0 1 -1 1h-4a1 1 0 0 1 -1 -1z" />
                                            <path d="M4 20h14" />
                                        </svg>
                                        Total Stok
                                    </span>
                                </th>
                                <th class="px-4 py-3.5 text-center">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let orderItemsTable;
        let startDate = '';
        let endDate = '';
        let selectedMedicineId = '';
        let isExporting = false;

        function formatQty(val) {
            let num = parseFloat(val);
            if (isNaN(num)) return '0';
            return num.toLocaleString('id-ID');
        }

        function startExportExcel() {
            if (isExporting) return;

            const exportBtn = document.getElementById('exportBtn');
            const exportBtnText = document.getElementById('exportBtnText');

            isExporting = true;
            if (exportBtn) {
                exportBtn.disabled = true;
                exportBtn.classList.add('opacity-75', 'cursor-not-allowed');
            }
            if (exportBtnText) {
                exportBtnText.innerText = 'Menyiapkan File Excel...';
            }

            iziToast.info({
                title: 'Export Excel',
                message: 'Sedang menyusun data stok ke file Excel berformat rapi. Unduhan akan segera dimulai otomatis...',
                position: 'topRight',
                timeout: 4000
            });

            let url = "{{ route('supplies.printstockdata') }}?start_date=" + encodeURIComponent(startDate) +
                "&end_date=" + encodeURIComponent(endDate) +
                "&medicine_id=" + encodeURIComponent(selectedMedicineId);

            window.location.href = url;

            setTimeout(() => {
                isExporting = false;
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                }
                if (exportBtnText) {
                    exportBtnText.innerText = 'Export Excel (.xlsx)';
                }
            }, 4500);
        }

        document.addEventListener('DOMContentLoaded', function() {
            flatpickr("#dateRange", {
                mode: "range",
                dateFormat: "Y-m-d",
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], "Y-m-d");
                        endDate = flatpickr.formatDate(selectedDates[1], "Y-m-d");
                    } else {
                        startDate = '';
                        endDate = '';
                    }

                    orderItemsTable.ajax.reload();
                }
            });

            $('#medicine').select2({
                placeholder: 'Cari nama atau kode obat...',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: "{{ route('supplies.medicineSelect') }}",
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return {
                            q: params.term
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data.map(function(item) {
                                return {
                                    id: item.id,
                                    text: item.code + ' - ' + item.name
                                };
                            })
                        };
                    },
                    cache: true
                }
            }).on('change', function() {
                selectedMedicineId = $(this).val() || '';
                orderItemsTable.ajax.reload();
            });

            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('supplies.getStockData') }}",
                    data: function(d) {
                        d.medicine_id = selectedMedicineId;
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [
                    {
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        className: 'text-center text-xs font-medium text-slate-400'
                    },
                    {
                        data: 'code',
                        className: 'font-mono text-xs font-semibold text-slate-700 whitespace-nowrap',
                        render: function(data) {
                            return `<span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-700 rounded border border-slate-200/80">${data || '-'}</span>`;
                        }
                    },
                    {
                        data: 'name',
                        render: function(data) {
                            return `<div class="font-bold text-slate-800 text-[13px] leading-tight">${data || '-'}</div>`;
                        }
                    },
                    {
                        data: 'unit',
                        className: 'text-center',
                        render: function(data) {
                            return `<span class="inline-block px-2 py-0.5 text-xs font-medium text-slate-600 bg-slate-50 rounded border border-slate-200/60">${data || '-'}</span>`;
                        }
                    },
                    {
                        data: 'qty_start',
                        className: 'text-right font-medium text-slate-600 tabular-nums',
                        render: function(data) {
                            return formatQty(data);
                        }
                    },
                    {
                        data: 'qty_orders',
                        className: 'text-right font-semibold tabular-nums',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            return `<span class="${n > 0 ? 'text-emerald-600' : 'text-slate-400'}">${n > 0 ? '+' : ''}${formatQty(data)}</span>`;
                        }
                    },
                    {
                        data: 'qty_sales',
                        className: 'text-right font-semibold tabular-nums',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            return `<span class="${n > 0 ? 'text-blue-600' : 'text-slate-400'}">${n > 0 ? '-' : ''}${formatQty(data)}</span>`;
                        }
                    },
                    {
                        data: 'qty_remaining',
                        className: 'text-right font-bold text-slate-700 tabular-nums',
                        render: function(data) {
                            return formatQty(data);
                        }
                    },
                    @if($canSeeWarehouse)
                    {
                        data: 'qty_storage',
                        className: 'text-center font-bold cell-storage tabular-nums',
                        render: function(data) {
                            return `<span class="inline-flex items-center justify-center min-w-[56px] px-2.5 py-1 rounded-md text-xs font-bold bg-amber-100/90 text-amber-900 border border-amber-300 shadow-2xs">${formatQty(data)}</span>`;
                        }
                    },
                    {
                        data: 'qty_counter',
                        className: 'text-center font-bold tabular-nums',
                        render: function(data) {
                            return `<span class="inline-flex items-center justify-center min-w-[56px] px-2.5 py-1 rounded-md text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200 shadow-2xs">${formatQty(data)}</span>`;
                        }
                    },
                    @else
                    {
                        data: 'qty_counter',
                        className: 'text-center font-bold tabular-nums',
                        render: function(data) {
                            return `<span class="inline-flex items-center justify-center min-w-[56px] px-2.5 py-1 rounded-md text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200 shadow-2xs">${formatQty(data)}</span>`;
                        }
                    },
                    @endif
                    {
                        data: 'qty_now',
                        className: 'text-center font-bold tabular-nums',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            if (n <= 0) {
                                return `<span class="inline-flex items-center gap-1 justify-center min-w-[62px] px-2.5 py-1 rounded-md text-xs font-black bg-rose-50 text-rose-700 border border-rose-300 shadow-2xs"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>${formatQty(n)}</span>`;
                            }
                            return `<span class="inline-flex items-center justify-center min-w-[62px] px-2.5 py-1 rounded-md text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs">${formatQty(n)}</span>`;
                        }
                    },
                    {
                        data: 'keterangan',
                        className: 'text-center font-medium',
                        render: function(data) {
                            if (!data) return '<span class="text-xs text-slate-300">-</span>';
                            let val = String(data).toUpperCase().trim();
                            if (val === 'CASH' || val === 'TUNAI') {
                                return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">${val}</span>`;
                            } else if (val === 'KREDIT') {
                                return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">${val}</span>`;
                            } else if (val === 'KONSINYASI') {
                                return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">${val}</span>`;
                            }
                            return `<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-600 border border-slate-200">${val}</span>`;
                        }
                    }
                ],
                paging: true,
                searching: false,
                info: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                language: {
                    processing: 'Memuat data...',
                    emptyTable: 'Tidak ada data tersedia',
                    zeroRecords: 'Tidak ada data yang cocok',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                    infoEmpty: 'Menampilkan 0 data',
                    infoFiltered: '(disaring dari _MAX_ total data)',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    paginate: {
                        first: 'Pertama',
                        last: 'Terakhir',
                        next: 'Selanjutnya',
                        previous: 'Sebelumnya',
                    }
                },
            });
        });
    </script>
@endsection
