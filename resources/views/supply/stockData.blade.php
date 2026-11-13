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

        /* Modal Z-Index and Stacking: ensure modal is strictly on top of everything */
        #mutationModal {
            z-index: 99999999 !important;
        }
        #mutationModal .mutation-backdrop {
            z-index: 99999998 !important;
        }
        #mutationModal .mutation-content-wrapper {
            z-index: 99999999 !important;
            position: relative;
        }
        .iziToast-wrapper {
            z-index: 100000000 !important;
        }

        /* Compact Table Layout to Fit Screen Without Horizontal Scroll */
        #orderItemsTable {
            width: 100% !important;
            table-layout: auto !important;
        }

        #orderItemsTable thead th {
            background-color: #f8fafc !important;
            color: #334155 !important;
            font-weight: 700 !important;
            font-size: 10.5px !important;
            line-height: 1.25 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.02em !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding: 8px 6px !important;
            white-space: normal !important;
            vertical-align: middle !important;
        }

        #orderItemsTable tbody td {
            padding: 7px 6px !important;
            font-size: 12px !important;
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

                <div class="mt-4 overflow-x-auto xl:overflow-x-visible">
                    <table id="orderItemsTable" class="w-full text-sm text-left text-slate-600">
                        <thead>
                            <tr>
                                <th class="w-7 text-center">#</th>
                                <th class="whitespace-nowrap">Kode</th>
                                <th class="min-w-[140px]">Nama Obat</th>
                                <th class="text-center">Satuan</th>
                                <th class="text-right leading-tight">Qty Awal<br><span class="text-[10px] text-slate-400 font-normal">{{ $canSeeWarehouse ? 'Gudang+PMI' : 'Etalase' }}</span></th>
                                <th class="text-right leading-tight">{{ $canSeeWarehouse ? 'Qty Beli' : 'Qty Masuk' }}<br><span class="text-[10px] text-slate-400 font-normal">{{ $canSeeWarehouse ? 'PBF' : 'Beli/Terima' }}</span></th>
                                <th class="text-right leading-tight">Qty Jual<br><span class="text-[10px] text-slate-400 font-normal">{{ $canSeeWarehouse ? '(PMI)' : '' }}</span></th>
                                @if($canSeeWarehouse)
                                    <th class="text-right leading-tight">Mutasi<br><span class="text-[10px] text-slate-400 font-normal">Ke PMI</span></th>
                                    <th class="text-right leading-tight">Mutasi<br><span class="text-[10px] text-slate-400 font-normal">Cabang</span></th>
                                @else
                                    <th class="text-right leading-tight">Mutasi<br><span class="text-[10px] text-slate-400 font-normal">Keluar</span></th>
                                @endif
                                <th class="text-right leading-tight">Penyesuaian<br><span class="text-[10px] text-slate-400 font-normal">SO/Adj</span></th>
                                @if($canSeeWarehouse)
                                    <th class="text-center !bg-amber-50/90 !border-b-2 !border-amber-300 leading-tight">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">Gudang</span>
                                    </th>
                                    <th class="text-center !bg-sky-50/90 !border-b-2 !border-sky-300 leading-tight">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-sky-100 text-sky-900 border border-sky-300">Pelayanan</span>
                                    </th>
                                @else
                                    <th class="text-center !bg-sky-50/90 !border-b-2 !border-sky-300 leading-tight">
                                        <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-sky-100 text-sky-900 border border-sky-300">Etalase</span>
                                    </th>
                                @endif
                                <th class="text-center !bg-emerald-50/90 !border-b-2 !border-emerald-300 leading-tight">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[11px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300">Total Stok</span>
                                </th>
                                <th class="text-center">Keterangan</th>
                                <th class="text-center whitespace-nowrap">Riwayat Mutasi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal Riwayat Mutasi -->
    <div id="mutationModal" class="fixed inset-0 hidden overflow-y-auto" style="z-index: 99999999 !important;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity mutation-backdrop" style="z-index: 99999998 !important;" onclick="closeMutationModal()"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-6 mutation-content-wrapper" style="z-index: 99999999 !important;">
            <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all w-full max-w-5xl border border-slate-200">
                <!-- Modal Header -->
                <div class="flex items-start justify-between border-b border-slate-200/80 px-6 py-4 bg-slate-50/70">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-base font-bold text-slate-900 leading-tight" id="modalMedicineName">-</h3>
                                <span id="modalMedicineCode" class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-slate-200 text-slate-700 border border-slate-300">-</span>
                                <span id="modalMedicineUnit" class="text-xs font-semibold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">-</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Audit Log Riwayat Mutasi Stok Keluar &middot; Periode: <span id="modalPeriodLabel" class="font-semibold text-slate-700">-</span>
                            </p>
                        </div>
                    </div>
                    <button type="button" onclick="closeMutationModal()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-5">
                    <!-- Loading State -->
                    <div id="modalLoading" class="py-12 flex flex-col items-center justify-center gap-3 text-slate-400">
                        <div class="w-8 h-8 border-3 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                        <span class="text-xs font-medium">Memuat riwayat mutasi...</span>
                    </div>

                    <!-- Content State -->
                    <div id="modalContent" class="space-y-5 hidden">
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <!-- Total Termutasi -->
                            <div class="p-4 rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-white shadow-sm">
                                <span class="text-[11px] font-semibold text-indigo-100 uppercase tracking-wider block">Total Termutasi</span>
                                <div class="mt-1 flex items-baseline gap-1.5">
                                    <span id="modalTotalQty" class="text-2xl font-black tabular-nums">0</span>
                                    <span id="modalUnitSuffix" class="text-xs font-medium text-indigo-200"></span>
                                </div>
                                <span id="modalTotalBreakdown" class="text-[11px] text-indigo-100/90 mt-1 block">Akumulasi seluruh tujuan</span>
                            </div>

                            <!-- Ringkasan per Tujuan -->
                            <div class="md:col-span-2 p-4 rounded-xl bg-slate-50 border border-slate-200/80 flex flex-col justify-center">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2 block">Ringkasan Mutasi per Tujuan:</span>
                                <div id="modalSummaryList" class="flex flex-wrap gap-2">
                                    <!-- Dynamic pills (e.g. Di-mutasi ke Sutomo sebanyak 400) -->
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                            <div class="max-h-[360px] overflow-y-auto overflow-x-auto">
                                <table class="w-full text-left text-xs text-slate-600">
                                    <thead class="bg-slate-100/90 text-slate-700 uppercase font-bold sticky top-0 z-10 border-b border-slate-200 text-[11px]">
                                        <tr>
                                            <th class="px-3 py-2.5 text-center w-9">#</th>
                                            <th class="px-3 py-2.5 whitespace-nowrap">No. Mutasi</th>
                                            <th class="px-3 py-2.5 whitespace-nowrap">Tanggal</th>
                                            <th class="px-3 py-2.5 whitespace-nowrap">Asal</th>
                                            <th class="px-3 py-2.5 whitespace-nowrap">Tujuan</th>
                                            <th class="px-3 py-2.5 whitespace-nowrap">Batch</th>
                                            <th class="px-3 py-2.5 text-right whitespace-nowrap">Qty</th>
                                            <th class="px-3 py-2.5 whitespace-nowrap">Di-mutasi Oleh</th>
                                        </tr>
                                    </thead>
                                    <tbody id="modalTableBody" class="divide-y divide-slate-100 bg-white">
                                        <!-- Dynamic Rows -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div id="modalEmptyState" class="hidden py-10 flex flex-col items-center justify-center gap-2 text-slate-400">
                            <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <span class="text-xs font-medium text-slate-500">Tidak ada riwayat mutasi untuk obat ini pada periode yang dipilih.</span>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                    <button type="button" onclick="closeMutationModal()" class="px-4 py-2 text-xs font-semibold rounded-lg bg-white text-slate-700 border border-slate-300 hover:bg-slate-100 transition shadow-2xs cursor-pointer">
                        Tutup
                    </button>
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
                autoWidth: false,
                scrollX: false,
                order: [[2, 'asc']],
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
                        className: 'text-center text-xs font-medium text-slate-400 px-1'
                    },
                    {
                        data: 'code',
                        className: 'font-mono text-xs font-semibold text-slate-700 whitespace-nowrap px-1',
                        render: function(data) {
                            return `<span class="inline-block px-1.5 py-0.5 bg-slate-100 text-slate-700 rounded text-[11px] font-semibold border border-slate-200/80">${data || '-'}</span>`;
                        }
                    },
                    {
                        data: 'name',
                        className: 'px-1.5',
                        render: function(data) {
                            return `<div class="font-bold text-slate-800 text-xs leading-snug">${data || '-'}</div>`;
                        }
                    },
                    {
                        data: 'unit',
                        className: 'text-center text-xs text-slate-600 px-1',
                        render: function(data) {
                            return `<span class="inline-block px-1.5 py-0.5 text-[11px] font-medium text-slate-600 bg-slate-50 rounded border border-slate-200/60">${data || '-'}</span>`;
                        }
                    },
                    {
                        data: 'qty_start',
                        orderable: false,
                        className: 'text-right font-medium text-slate-600 tabular-nums px-1',
                        render: function(data) {
                            return formatQty(data);
                        }
                    },
                    {
                        data: 'qty_orders',
                        orderable: false,
                        className: 'text-right font-semibold tabular-nums px-1',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            if (n > 0) return `<span class="text-emerald-600">+${formatQty(n)}</span>`;
                            if (n < 0) return `<span class="text-rose-600">-${formatQty(Math.abs(n))}</span>`;
                            return `<span class="text-slate-400">0</span>`;
                        }
                    },
                    {
                        data: 'qty_sales',
                        orderable: false,
                        className: 'text-right font-semibold tabular-nums px-1',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            if (n > 0) return `<span class="text-blue-600">${formatQty(n)}</span>`;
                            if (n < 0) return `<span class="text-rose-600">-${formatQty(Math.abs(n))}</span>`;
                            return `<span class="text-slate-400">0</span>`;
                        }
                    },
                    @if($canSeeWarehouse)
                    {
                        data: 'qty_transferred_pmi',
                        orderable: false,
                        className: 'text-right font-medium tabular-nums px-1',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            if (n > 0) {
                                return `<span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 text-[11px] font-semibold border border-slate-200" title="Mutasi internal Gudang PMI ke Pelayanan PMI (tidak mengurangi total stok)">${formatQty(n)}</span>`;
                            }
                            return `<span class="text-slate-400">0</span>`;
                        }
                    },
                    @endif
                    {
                        data: 'qty_transferred_branch',
                        orderable: false,
                        className: 'text-right font-semibold tabular-nums px-1',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            return `<span class="${n > 0 ? 'text-indigo-600 font-bold' : 'text-slate-400'}">${formatQty(n)}</span>`;
                        }
                    },
                    {
                        data: 'qty_adjustments',
                        orderable: false,
                        className: 'text-right font-semibold tabular-nums px-1',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            if (n > 0) return `<span class="text-emerald-600">+${formatQty(n)}</span>`;
                            if (n < 0) return `<span class="text-rose-600">-${formatQty(Math.abs(n))}</span>`;
                            return `<span class="text-slate-400">0</span>`;
                        }
                    },
                    @if($canSeeWarehouse)
                    {
                        data: 'qty_storage',
                        orderable: false,
                        className: 'text-center font-bold cell-storage tabular-nums px-1',
                        render: function(data) {
                            return `<span class="inline-block px-1.5 py-0.5 rounded text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300 shadow-2xs">${formatQty(data)}</span>`;
                        }
                    },
                    {
                        data: 'qty_counter',
                        orderable: false,
                        className: 'text-center font-bold tabular-nums px-1',
                        render: function(data) {
                            return `<span class="inline-block px-1.5 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200 shadow-2xs">${formatQty(data)}</span>`;
                        }
                    },
                    @else
                    {
                        data: 'qty_counter',
                        orderable: false,
                        className: 'text-center font-bold tabular-nums px-1',
                        render: function(data) {
                            return `<span class="inline-block px-1.5 py-0.5 rounded text-xs font-bold bg-sky-50 text-sky-800 border border-sky-200 shadow-2xs">${formatQty(data)}</span>`;
                        }
                    },
                    @endif
                    {
                        data: 'qty_now',
                        orderable: false,
                        className: 'text-center font-bold tabular-nums px-1',
                        render: function(data) {
                            let n = parseFloat(data || 0);
                            if (n <= 0) {
                                return `<span class="inline-block px-1.5 py-0.5 rounded text-xs font-black bg-rose-50 text-rose-700 border border-rose-300 shadow-2xs">${formatQty(n)}</span>`;
                            }
                            return `<span class="inline-block px-1.5 py-0.5 rounded text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200 shadow-2xs">${formatQty(n)}</span>`;
                        }
                    },
                    {
                        data: 'keterangan',
                        orderable: false,
                        className: 'text-center font-medium px-1',
                        render: function(data) {
                            if (!data) return '<span class="text-xs text-slate-300">-</span>';
                            let val = String(data).toUpperCase().trim();
                            if (val === 'CASH' || val === 'TUNAI') {
                                return `<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">${val}</span>`;
                            } else if (val === 'KREDIT') {
                                return `<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">${val}</span>`;
                            } else if (val === 'KONSINYASI') {
                                return `<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">${val}</span>`;
                            }
                            return `<span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">${val}</span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        className: 'text-center whitespace-nowrap px-1',
                        render: function(data, type, row) {
                            return `<button type="button" onclick="openMutationModal(${row.id}, '${escapeQuote(row.name)}', '${escapeQuote(row.code)}', '${escapeQuote(row.unit)}')" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 hover:text-indigo-800 transition shadow-2xs cursor-pointer" title="Lihat riwayat mutasi">
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                <span>Mutasi</span>
                            </button>`;
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

        function escapeQuote(str) {
            if (!str) return '';
            return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        function openMutationModal(medicineId, medicineName, medicineCode, medicineUnit) {
            // Close select2 and blur any active input
            try {
                $('#medicine').select2('close');
            } catch (e) {}
            if (document.activeElement && typeof document.activeElement.blur === 'function') {
                document.activeElement.blur();
            }

            // Lock background body scroll
            document.body.classList.add('overflow-hidden');

            const modal = document.getElementById('mutationModal');
            const loading = document.getElementById('modalLoading');
            const content = document.getElementById('modalContent');
            const emptyState = document.getElementById('modalEmptyState');

            document.getElementById('modalMedicineName').innerText = medicineName || '-';
            document.getElementById('modalMedicineCode').innerText = medicineCode || '-';
            document.getElementById('modalMedicineUnit').innerText = medicineUnit || '-';
            document.getElementById('modalUnitSuffix').innerText = medicineUnit || '';

            modal.classList.remove('hidden');
            loading.classList.remove('hidden');
            content.classList.add('hidden');
            emptyState.classList.add('hidden');

            let url = "{{ route('supplies.stockDataMutationHistory', ':id') }}".replace(':id', medicineId);
            url += `?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    loading.classList.add('hidden');
                    content.classList.remove('hidden');

                    document.getElementById('modalPeriodLabel').innerText = data.period_label || 'Semua Waktu';
                    document.getElementById('modalTotalQty').innerText = formatQty(data.total_qty || 0);
                    const breakdownEl = document.getElementById('modalTotalBreakdown');
                    if (breakdownEl) {
                        const pmiQty = parseInt(data.pmi_qty || 0, 10);
                        const branchQty = parseInt(data.branch_qty || 0, 10);
                        if (pmiQty > 0 || branchQty > 0) {
                            breakdownEl.innerText = `Ke PMI: ${formatQty(pmiQty)} • Keluar Cabang: ${formatQty(branchQty)}`;
                        } else {
                            breakdownEl.innerText = 'Akumulasi seluruh tujuan';
                        }
                    }

                    // Summary per destination
                    const summaryContainer = document.getElementById('modalSummaryList');
                    summaryContainer.innerHTML = '';
                    if (data.summary && data.summary.length > 0) {
                        data.summary.forEach(item => {
                            const badge = document.createElement('span');
                            badge.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-slate-200 text-slate-800 shadow-2xs';
                            badge.innerHTML = `<span class="w-2 h-2 rounded-full bg-indigo-500"></span>${item.label}`;
                            summaryContainer.appendChild(badge);
                        });
                    } else {
                        summaryContainer.innerHTML = '<span class="text-xs text-slate-400 italic">Belum ada barang termutasi</span>';
                    }

                    // Table rows
                    const tbody = document.getElementById('modalTableBody');
                    tbody.innerHTML = '';
                    if (data.logs && data.logs.length > 0) {
                        emptyState.classList.add('hidden');
                        data.logs.forEach((log, idx) => {
                            const tr = document.createElement('tr');
                            tr.className = 'hover:bg-slate-50 transition border-b border-slate-100 last:border-0';
                            const code = log.transfer_code || '-';
                            const copyBtnHtml = log.transfer_code
                                ? `<button type="button" onclick="copyTransferCode(this)" data-code="${String(code).replace(/"/g, '&quot;')}" title="Klik untuk menyalin nomor mutasi" class="group inline-flex items-center gap-1.5 px-2 py-0.5 bg-slate-100 hover:bg-indigo-50 hover:text-indigo-700 hover:border-indigo-300 rounded border border-slate-200 transition cursor-pointer select-none">
                                        <span class="pointer-events-none">${code}</span>
                                        <svg class="copy-icon pointer-events-none w-3.5 h-3.5 text-slate-400 group-hover:text-indigo-600 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg class="check-icon pointer-events-none hidden w-3.5 h-3.5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <span class="copied-label pointer-events-none hidden text-[10px] font-sans font-semibold text-emerald-700">Disalin</span>
                                   </button>`
                                : `<span class="inline-block px-2 py-0.5 bg-slate-100 rounded border border-slate-200">-</span>`;

                            tr.innerHTML = `
                                <td class="px-3 py-2.5 text-center text-slate-400 font-medium">${idx + 1}</td>
                                <td class="px-3 py-2.5 font-mono font-bold text-slate-700 whitespace-nowrap">
                                    ${copyBtnHtml}
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-slate-600">${log.formatted_date || '-'}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                        ${log.formatted_source || '-'}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        ${log.formatted_destination || '-'}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 whitespace-nowrap font-medium text-slate-700">${log.batch_name || '-'}</td>
                                <td class="px-3 py-2.5 text-right font-bold text-indigo-700 tabular-nums whitespace-nowrap">${formatQty(log.qty_number || 0)}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-slate-600 font-medium">${log.operator_name || '-'}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        emptyState.classList.remove('hidden');
                    }
                })
                .catch(err => {
                    console.error(err);
                    loading.classList.add('hidden');
                    content.classList.remove('hidden');
                    iziToast.error({
                        title: 'Gagal',
                        message: 'Tidak dapat memuat riwayat mutasi.',
                        position: 'topRight'
                    });
                });
        }

        function copyTransferCode(btn) {
            const code = btn?.getAttribute('data-code');
            if (!code) return;

            const showCopiedFeedback = () => {
                const copyIcon = btn.querySelector('.copy-icon');
                const checkIcon = btn.querySelector('.check-icon');
                const copiedLabel = btn.querySelector('.copied-label');
                if (copyIcon) copyIcon.classList.add('hidden');
                if (checkIcon) checkIcon.classList.remove('hidden');
                if (copiedLabel) copiedLabel.classList.remove('hidden');
                btn.classList.add('bg-emerald-50', 'border-emerald-300', 'text-emerald-700');

                if (typeof iziToast !== 'undefined') {
                    iziToast.success({
                        title: 'Disalin',
                        message: `Nomor mutasi ${code} berhasil disalin.`,
                        position: 'topRight',
                        timeout: 2000
                    });
                }

                if (btn._copyTimer) clearTimeout(btn._copyTimer);
                btn._copyTimer = setTimeout(() => {
                    if (copyIcon) copyIcon.classList.remove('hidden');
                    if (checkIcon) checkIcon.classList.add('hidden');
                    if (copiedLabel) copiedLabel.classList.add('hidden');
                    btn.classList.remove('bg-emerald-50', 'border-emerald-300', 'text-emerald-700');
                }, 1500);
            };

            // Try synchronous execCommand first only if Clipboard API is unavailable,
            // or fall back synchronously whenever Clipboard API rejects or is unavailable.
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
                navigator.clipboard.writeText(code)
                    .then(showCopiedFeedback)
                    .catch(() => fallbackCopyText(code, btn, showCopiedFeedback));
            } else {
                fallbackCopyText(code, btn, showCopiedFeedback);
            }
        }

        function fallbackCopyText(text, anchorEl, onSuccess) {
            const container = anchorEl?.closest('#mutationModal') || document.body;
            const prevFocus = document.activeElement;
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.top = '0';
            textarea.style.left = '-9999px';
            textarea.style.width = '2em';
            textarea.style.height = '2em';
            textarea.style.padding = '0';
            textarea.style.border = 'none';
            textarea.style.outline = 'none';
            textarea.style.boxShadow = 'none';
            textarea.style.background = 'transparent';
            textarea.style.fontSize = '12pt';

            container.appendChild(textarea);
            textarea.focus({ preventScroll: true });
            textarea.select();
            textarea.setSelectionRange(0, text.length);

            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (e) {
                copied = false;
            } finally {
                container.removeChild(textarea);
                if (prevFocus && typeof prevFocus.focus === 'function') {
                    prevFocus.focus({ preventScroll: true });
                }
            }

            if (copied && typeof onSuccess === 'function') {
                onSuccess();
            } else if (typeof iziToast !== 'undefined') {
                iziToast.warning({
                    title: 'Gagal Salin',
                    message: 'Browser memblokir salin otomatis. Silakan salin manual: ' + text,
                    position: 'topRight'
                });
            }
        }

        function closeMutationModal() {
            const modal = document.getElementById('mutationModal');
            if (modal) {
                modal.classList.add('hidden');
            }
            document.body.classList.remove('overflow-hidden');
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMutationModal();
            }
        });
    </script>
@endsection
