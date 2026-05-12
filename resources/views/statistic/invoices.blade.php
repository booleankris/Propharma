@extends('layouts.app')

@section('title', 'Klaim Tagihan')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* ── DataTable overrides ── */
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter label {
            font-weight: 600 !important;
        }

        .dataTables_filter input {
            width: 260px !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
            outline: none !important;
        }

        .dataTables_length select {
            padding: 4px 23px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }

        #invoiceTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #invoiceTable tbody td {
            padding: 10px 10px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
        }

        /* Group highlight: rows of the same transaction share a subtle stripe */
        #invoiceTable tbody tr.group-odd {
            background-color: #f9fafb;
        }

        #invoiceTable tbody tr.group-even {
            background-color: #ffffff;
        }

        #invoiceTable tbody tr:hover {
            background-color: #eff6ff !important;
        }

        /* First-row-of-group top border to visually separate resep groups */
        #invoiceTable tbody tr.group-first td {
            border-top: 2px solid #e5e7eb !important;
        }

        /* ── Badges ── */
        .badge-claimed {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 3px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-pending {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 3px 10px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }

        /* ── Filter inputs ── */
        .filter-input {
            width: 100%;
            border-radius: 10px;
            border: 1px solid #d1d5db;
            background: #fff;
            padding: 10px 14px;
            font-size: 13px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .filter-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .15);
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        /* ── Header accent ── */
        .header-accent {
            width: 4px;
            height: 32px;
            border-radius: 4px;
            flex-shrink: 0;
            background: #2563eb;
        }

        /* ── Klaim button ── */
        .btn-klaim {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 14px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, transform .1s;
            white-space: nowrap;
        }

        .btn-klaim:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        .btn-klaim:active {
            transform: translateY(0);
        }

        .btn-klaim:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        /* Nomor resep pill */
        .resep-pill {
            display: inline-block;
            padding: 2px 8px;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            font-family: monospace;
        }

        /* Jumlah shows only on first row of group */
        td.jumlah-cell {
            font-weight: 700;
            color: #1d4ed8;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body space-y-4">

            {{-- ─── Page header ─── --}}
            <div class="flex items-center gap-3">
                <div class="header-accent"></div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Klaim Tagihan</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Klaim tagihan obat kredit berdasarkan nomor resep</p>
                </div>
            </div>

            {{-- ─── Filter bar ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <div class="flex flex-wrap gap-3 items-end">

                    {{-- Date range --}}
                    <div class="flex-1 min-w-[220px]">
                        <div class="filter-label">Rentang Tanggal</div>
                        <input type="text" id="dateRange" class="filter-input" placeholder="Pilih rentang tanggal..."
                            autocomplete="off" readonly>
                    </div>

                    {{-- Debtor --}}
                    <div class="flex-1 min-w-[200px]">
                        <div class="filter-label">Debitur</div>
                        <select id="filterDebtor" class="filter-input">
                            <option value="">-- Semua Debitur --</option>
                            @foreach ($debtors as $debtor)
                                <option value="{{ $debtor->id }}">{{ $debtor->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search medicine --}}
                    <div class="flex-1 min-w-[200px]">
                        <div class="filter-label">Cari Obat</div>
                        <input type="text" id="searchMedicine" class="filter-input" placeholder="Nama obat..."
                            autocomplete="off">
                    </div>

                    {{-- Reset --}}
                    <div>
                        <button id="btnReset"
                            class="px-4 py-2.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Reset Filter
                        </button>
                    </div>

                </div>
            </div>

            {{-- ─── DataTable ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">

                <div class="flex items-center gap-2 mb-4">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                        style="background:#eff6ff;color:#1d4ed8;">
                        🧾 Tagihan Kredit
                    </span>
                    <span class="text-xs text-gray-400">Satu tombol Klaim per Nomor Resep</span>
                </div>

                <table id="invoiceTable" class="w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Nomor Resep</th>
                            <th>Dokter</th>
                            <th>Debitur</th>
                            <th>Nama Obat</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Harga</th>
                            <th class="text-right">Jumlah</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-[13px]"></tbody>
                </table>
            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let startDate = '';
        let endDate = '';
        let searchTimer = null;
        let Table = null;

        // ── Group color tracker (alternates per transaction_id) ──────────────
        let groupColorMap = {};
        let groupCounter = 0;

        // ── Flatpickr ────────────────────────────────────────────────────────
        flatpickr('#dateRange', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: {
                rangeSeparator: ' s/d '
            },
            onClose(selectedDates) {
                if (selectedDates.length === 2) {
                    startDate = selectedDates[0].toISOString().slice(0, 10);
                    endDate = selectedDates[1].toISOString().slice(0, 10);
                    loadTable();
                }
            },
        });

        // ── Build / reload DataTable ──────────────────────────────────────────
        function loadTable() {
            groupColorMap = {};
            groupCounter = 0;

            if (Table) {
                Table.destroy();
                $('#invoiceTable tbody').empty();
            }

            Table = $('#invoiceTable').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                searching: false,
                ajax: {
                    url: '{{ route('invoices.get') }}',
                    data: {
                        start_date: startDate,
                        end_date: endDate,
                        debtor_id: $('#filterDebtor').val(),
                        search_medicine: $('#searchMedicine').val(),
                    },
                },
                language: {
                    processing: 'Memuat data...',
                    emptyTable: 'Tidak ada data tagihan',
                    zeroRecords: 'Data tidak ditemukan',
                    info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    paginate: {
                        next: '›',
                        previous: '‹'
                    },
                },
                columns: [
                    // 0 – No
                    {
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px',
                        className: 'text-gray-400 text-xs',
                    },
                    // 1 – Tanggal
                    {
                        data: 'tanggal',
                        orderable: false
                    },
                    // 2 – Nomor Resep
                    {
                        data: 'nomor_resep',
                        orderable: false,
                        render: val => `<span class="resep-pill">${val}</span>`,
                    },
                    // 3 – Dokter
                    {
                        data: 'dokter',
                        orderable: false
                    },
                    // 4 – Debitur
                    {
                        data: 'debtor',
                        orderable: false,
                        className: 'text-gray-500 text-xs'
                    },
                    // 5 – Nama Obat
                    {
                        data: 'nama_obat',
                        orderable: false
                    },
                    // 6 – Qty
                    {
                        data: 'qty',
                        orderable: false,
                        className: 'text-right',
                        render: val => parseInt(val).toLocaleString('id-ID'),
                    },
                    // 7 – Harga
                    {
                        data: 'harga',
                        orderable: false,
                        className: 'text-right'
                    },
                    // 8 – Jumlah (only shown on first row of group)
                    {
                        data: 'jumlah',
                        orderable: false,
                        className: 'text-right jumlah-cell',
                        render(val, type, row) {
                            return row.is_first_in_group ? val : '';
                        },
                    },
                    // 9 – Aksi (Klaim button, only on first row of group)
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render(val, type, row) {
                            if (!row.is_first_in_group) return '';

                            if (row.status == 2) {
                                return `<span class="badge-claimed">✓ Diklaim</span>`;
                            }

                            return `
                                <button class="btn-klaim"
                                        data-id="${row.transaction_id}"
                                        onclick="doKlaim(this, ${row.transaction_id})">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Klaim
                                </button>`;
                        },
                    },
                ],
                order: [
                    [1, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],

                // ── After each draw: apply group row coloring ────────────────
                drawCallback() {
                    const api = this.api();
                    groupColorMap = {};
                    groupCounter = 0;

                    api.rows({
                        page: 'current'
                    }).every(function() {
                        const row = this.data();
                        const tid = row.transaction_id;
                        const node = $(this.node());

                        // Assign alternating color class per transaction group
                        if (!(tid in groupColorMap)) {
                            groupColorMap[tid] = (groupCounter % 2 === 0) ? 'group-odd' : 'group-even';
                            groupCounter++;
                        }

                        node.addClass(groupColorMap[tid]);
                        if (row.is_first_in_group) node.addClass('group-first');
                    });
                },
            });
        }

        // ── Klaim action ──────────────────────────────────────────────────────
        function doKlaim(btn, transactionId) {
            iziToast.question({
                title: 'Konfirmasi Klaim',
                message: 'Klaim semua item dalam resep ini?',
                buttons: [
                    ['<button><b>Ya, Klaim</b></button>', function(instance, toast) {
                        instance.hide({
                            transitionOut: 'fadeOut'
                        }, toast);

                        $(btn).prop('disabled', true).text('Memproses...');

                        axios.post('{{ route('invoices.klaim', ':id') }}'.replace(':id',
                            transactionId), {}, {
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                            })
                            .then(res => {
                                iziToast.success({
                                    title: 'Berhasil',
                                    message: res.data.message,
                                    position: 'topRight',
                                });
                                loadTable();
                            })
                            .catch(err => {
                                const msg = err.response?.data?.message ?? 'Terjadi kesalahan.';
                                iziToast.error({
                                    title: 'Gagal',
                                    message: msg,
                                    position: 'topRight'
                                });
                                $(btn).prop('disabled', false).html(`
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg> Klaim`);
                            });
                    }, true],
                    ['<button>Batal</button>', function(instance, toast) {
                        instance.hide({
                            transitionOut: 'fadeOut'
                        }, toast);
                    }],
                ],
            });
        }

        // ── Filter listeners ──────────────────────────────────────────────────
        $('#filterDebtor').on('change', () => loadTable());

        $('#searchMedicine').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadTable(), 400);
        });

        $('#btnReset').on('click', function() {
            startDate = '';
            endDate = '';
            $('#dateRange').val('');
            $('#filterDebtor').val('');
            $('#searchMedicine').val('');
            loadTable();
        });

        // ── Initial load ──────────────────────────────────────────────────────
        loadTable();
    </script>
@endsection
