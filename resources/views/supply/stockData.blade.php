@extends('layouts.app')

@section('title', 'Data Stok - Gudang PMI')

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
            color: #475569 !important;
            font-weight: 600 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.04em !important;
            border-bottom: 1px solid #e2e8f0 !important;
            padding: 12px 16px !important;
            white-space: nowrap;
        }

        #orderItemsTable tbody td {
            padding: 12px 16px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
            color: #475569 !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        #orderItemsTable tbody tr:hover td {
            background-color: #f8fafc !important;
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
                                class="px-2 py-0.5 text-[10px] font-semibold bg-violet-50 text-violet-600 border border-violet-100 rounded-full">Gudang
                                PMI</span>
                        </div>
                        <p class="text-xs text-slate-400">Konsolidasi Beli Gudang PMI, Jual Sahabat PMI, dan Total Stok
                            Real-time</p>
                    </div>
                </div>

                <button id="exportBtn" onclick="startExportExcel()"
                    class="inline-flex items-center gap-2 px-4 h-10 rounded-lg text-sm font-semibold text-white bg-emerald-500 hover:bg-emerald-600 transition shadow-sm shrink-0 w-fit cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Export Excel</span>
                </button>
            </div>

            <!-- Export Progress Container -->
            <div id="progressContainer"
                class="hidden bg-white rounded-xl border border-blue-200/80 shadow-sm p-4 transition-all duration-300">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-600 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span class="text-sm font-semibold text-slate-700" id="progressStatus">Mempersiapkan data
                            export...</span>
                    </div>
                    <span class="text-sm font-bold text-blue-600" id="progressText">0%</span>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                    <div id="progressBar"
                        class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2.5 rounded-full transition-all duration-300"
                        style="width: 0%"></div>
                </div>
                <p class="text-xs text-slate-400 mt-1.5">Harap tunggu, proses generate file Excel sedang berjalan di latar
                    belakang.</p>
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
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Kode Obat</th>
                                <th class="px-4 py-3">Nama Obat</th>
                                <th class="px-4 py-3">Satuan</th>
                                <th class="px-4 py-3 text-center">QTY Awal</th>
                                <th class="px-4 py-3 text-center">QTY Beli</th>
                                <th class="px-4 py-3 text-center">QTY Jual (PMI)</th>
                                <th class="px-4 py-3 text-center">Stok Gudang</th>
                                <th class="px-4 py-3 text-center">Stok Pelayanan PMI</th>
                                <th class="px-4 py-3 text-center">Total Stok</th>
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

        async function startExportExcel() {
            if (isExporting) return;

            const exportBtn = document.getElementById('exportBtn');
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressStatus = document.getElementById('progressStatus');

            isExporting = true;
            exportBtn.disabled = true;
            exportBtn.innerHTML = `
                <svg class="w-4 h-4 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Memproses Export...</span>
            `;
            exportBtn.classList.add('opacity-75', 'cursor-not-allowed');

            progressContainer.classList.remove('hidden');
            progressBar.style.width = '10%';
            progressText.innerText = '10%';
            progressStatus.innerText = 'Memulai proses export...';

            try {
                let url = "{{ route('supplies.exportStockData') }}?start_date=" + encodeURIComponent(startDate) +
                    "&end_date=" + encodeURIComponent(endDate) +
                    "&medicine_id=" + encodeURIComponent(selectedMedicineId);

                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) throw new Error('Gagal memulai proses export');
                const data = await response.json();

                if (data.job_id) {
                    pollExportStatus(data.job_id);
                } else {
                    throw new Error('ID job export tidak valid');
                }
            } catch (error) {
                iziToast.error({
                    title: 'Gagal',
                    message: error.message || 'Terjadi kesalahan saat memulai export.',
                    position: 'topRight'
                });
                resetExportState();
            }
        }

        function pollExportStatus(jobId) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressStatus = document.getElementById('progressStatus');
            const progressContainer = document.getElementById('progressContainer');

            let interval = setInterval(() => {
                fetch(`/stock-data/export/status/${jobId}`)
                    .then(res => res.json())
                    .then(data => {
                        let prog = data.progress || 0;
                        progressBar.style.width = prog + "%";
                        progressText.innerText = prog + "%";

                        if (prog < 50) {
                            progressStatus.innerText = 'Mempersiapkan data dan stok...';
                        } else if (prog < 100) {
                            progressStatus.innerText = 'Menyusun file Excel...';
                        }

                        if (data.status === "completed") {
                            clearInterval(interval);
                            progressBar.style.width = "100%";
                            progressText.innerText = "100%";
                            progressStatus.innerText = 'Selesai! Mengunduh file...';

                            iziToast.success({
                                title: 'Selesai',
                                message: 'File Excel Data Stok siap diunduh!',
                                position: 'topRight'
                            });

                            setTimeout(() => {
                                progressContainer.classList.add('hidden');
                                resetExportState();
                            }, 2000);

                            if (data.file) {
                                window.location.href = data.file;
                            }
                        } else if (data.status === "failed") {
                            clearInterval(interval);
                            iziToast.error({
                                title: 'Gagal',
                                message: 'Terjadi kesalahan saat meng-generate file Excel.',
                                position: 'topRight'
                            });
                            progressContainer.classList.add('hidden');
                            resetExportState();
                        }
                    })
                    .catch(err => {
                        clearInterval(interval);
                        iziToast.error({
                            title: 'Error',
                            message: 'Gagal memeriksa status export.',
                            position: 'topRight'
                        });
                        resetExportState();
                    });
            }, 1000);
        }

        function resetExportState() {
            isExporting = false;
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    <span>Export Excel</span>
                `;
                exportBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
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
                        data: 'unit'
                    },
                    {
                        data: 'qty_start',
                        className: 'text-center font-medium text-slate-700'
                    },
                    {
                        data: 'qty_orders',
                        className: 'text-center font-semibold text-emerald-600'
                    },
                    {
                        data: 'qty_sales',
                        className: 'text-center font-semibold text-blue-600'
                    },
                    {
                        data: 'qty_storage',
                        className: 'text-center font-bold text-amber-600'
                    },
                    {
                        data: 'qty_counter',
                        className: 'text-center font-bold text-indigo-600'
                    },
                    {
                        data: 'qty_now',
                        className: 'text-center font-bold text-slate-800'
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
