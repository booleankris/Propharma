@extends('layouts.app')

@section('title', 'Orders Payment')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        .dataTables_wrapper .top {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter label { font-weight: 600 !important; }

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

        #paymentTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #paymentTable tbody td {
            padding: 10px 10px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
        }

        #paymentTable tbody tr.group-odd  { background-color: #f9fafb; }
        #paymentTable tbody tr.group-even { background-color: #ffffff; }
        #paymentTable tbody tr:hover      { background-color: #f0fdf4 !important; }

        #paymentTable tbody tr.group-first td {
            border-top: 2px solid #e5e7eb !important;
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
            border-color: #16a34a;
            box-shadow: 0 0 0 3px rgba(22, 163, 74, .15);
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
            background: #16a34a;
        }

        /* ── Selesai button ── */
        .btn-selesai {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 14px;
            background: #16a34a;
            color: #fff;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, transform .1s;
            white-space: nowrap;
        }

        .btn-selesai:hover  { background: #15803d; transform: translateY(-1px); }
        .btn-selesai:active { transform: translateY(0); }
        .btn-selesai:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        /* ── Badges ── */
        .badge-done {
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

        /* Pills */
        .pesanan-pill {
            display: inline-block;
            padding: 2px 8px;
            background: #f0fdf4;
            color: #15803d;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .4px;
            font-family: monospace;
        }

        .faktur-pill {
            display: inline-block;
            padding: 2px 8px;
            background: #fefce8;
            color: #854d0e;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            font-family: monospace;
        }

        td.jumlah-cell { font-weight: 700; color: #15803d; }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body space-y-4">

            {{-- ─── Page header ─── --}}
            <div class="flex items-center gap-3">
                <div class="header-accent"></div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Orders Payment</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Pembayaran pesanan obat yang telah diterima</p>
                </div>
            </div>

            {{-- ─── Filter bar ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <div class="flex flex-wrap gap-3 items-end">

                    {{-- Date range --}}
                    <div class="flex-1 min-w-[220px]">
                        <div class="filter-label">Rentang Tanggal</div>
                        <input type="text" id="dateRange" class="filter-input"
                               placeholder="Pilih rentang tanggal..." autocomplete="off" readonly>
                    </div>

                    {{-- Creditor --}}
                    <div class="flex-1 min-w-[200px]">
                        <div class="filter-label">Kreditur</div>
                        <select id="filterCreditor" class="filter-input">
                            <option value="">-- Semua Kreditur --</option>
                            @foreach($creditors as $creditor)
                                <option value="{{ $creditor->code }}">{{ $creditor->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Search medicine --}}
                    <div class="flex-1 min-w-[200px]">
                        <div class="filter-label">Cari Obat</div>
                        <input type="text" id="searchMedicine" class="filter-input"
                               placeholder="Nama obat..." autocomplete="off">
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
                          style="background:#f0fdf4;color:#15803d;">
                        📦 Pesanan Diterima — Menunggu Pembayaran
                    </span>
                    <span class="text-xs text-gray-400">Satu tombol Selesai per No. Pesanan</span>
                </div>

                <table id="paymentTable" class="w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>No. Pesanan</th>
                            <th>No. Faktur</th>
                            <th>Kreditur</th>
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
        let startDate   = '';
        let endDate     = '';
        let searchTimer = null;
        let Table       = null;
        let groupColorMap = {};
        let groupCounter  = 0;

        // ── Flatpickr ────────────────────────────────────────────────────────
        flatpickr('#dateRange', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            locale: { rangeSeparator: ' s/d ' },
            onClose(selectedDates) {
                if (selectedDates.length === 2) {
                    startDate = selectedDates[0].toISOString().slice(0, 10);
                    endDate   = selectedDates[1].toISOString().slice(0, 10);
                    loadTable();
                }
            },
        });

        // ── Build / reload DataTable ──────────────────────────────────────────
        function loadTable() {
            groupColorMap = {};
            groupCounter  = 0;

            if (Table) {
                Table.destroy();
                $('#paymentTable tbody').empty();
            }

            Table = $('#paymentTable').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                searching: false,
                ajax: {
                    url: '{{ route('orders-payment.get') }}',
                    data: {
                        start_date:      startDate,
                        end_date:        endDate,
                        creditor_code:   $('#filterCreditor').val(),
                        search_medicine: $('#searchMedicine').val(),
                    },
                },
                language: {
                    processing:  'Memuat data...',
                    emptyTable:  'Tidak ada data pesanan',
                    zeroRecords: 'Data tidak ditemukan',
                    info:        'Menampilkan _START_ - _END_ dari _TOTAL_ data',
                    infoEmpty:   'Tidak ada data',
                    lengthMenu:  'Tampilkan _MENU_ data',
                    paginate:    { next: '›', previous: '‹' },
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
                    { data: 'tanggal', orderable: false },
                    // 2 – No. Pesanan
                    {
                        data: 'no_pesanan',
                        orderable: false,
                        render: val => `<span class="pesanan-pill">${val}</span>`,
                    },
                    // 3 – No. Faktur
                    {
                        data: 'no_faktur',
                        orderable: false,
                        render: val => `<span class="faktur-pill">${val}</span>`,
                    },
                    // 4 – Kreditur
                    { data: 'creditor', orderable: false, className: 'text-gray-500 text-xs' },
                    // 5 – Nama Obat
                    { data: 'nama_obat', orderable: false },
                    // 6 – Qty
                    {
                        data: 'qty',
                        orderable: false,
                        className: 'text-right',
                        render: val => parseInt(val).toLocaleString('id-ID'),
                    },
                    // 7 – Harga
                    { data: 'harga', orderable: false, className: 'text-right' },
                    // 8 – Jumlah (only on first row of group)
                    {
                        data: 'jumlah',
                        orderable: false,
                        className: 'text-right jumlah-cell',
                        render: (val, type, row) => row.is_first_in_group ? val : '',
                    },
                    // 9 – Aksi (only on first row of group)
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render(val, type, row) {
                            if (!row.is_first_in_group) return '';

                            if (row.status == 2) {
                                return `<span class="badge-done">✓ Selesai</span>`;
                            }

                            return `
                                <button class="btn-selesai"
                                        data-id="${row.receiving_id}"
                                        onclick="doSelesai(this, ${row.receiving_id}, '${row.no_pesanan}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Selesai
                                </button>`;
                        },
                    },
                ],
                order: [[1, 'desc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],

                drawCallback() {
                    const api = this.api();
                    groupColorMap = {};
                    groupCounter  = 0;

                    api.rows({ page: 'current' }).every(function () {
                        const row  = this.data();
                        const rid  = row.receiving_id;
                        const node = $(this.node());

                        if (!(rid in groupColorMap)) {
                            groupColorMap[rid] = (groupCounter % 2 === 0) ? 'group-odd' : 'group-even';
                            groupCounter++;
                        }

                        node.addClass(groupColorMap[rid]);
                        if (row.is_first_in_group) node.addClass('group-first');
                    });
                },
            });
        }

        // ── Selesai action ────────────────────────────────────────────────────
        function doSelesai(btn, receivingId, noPesanan) {
            iziToast.question({
                title: 'Konfirmasi Selesai',
                message: `Tandai pesanan <b>${noPesanan}</b> sebagai selesai?`,
                buttons: [
                    ['<button><b>Ya, Selesai</b></button>', function (instance, toast) {
                        instance.hide({ transitionOut: 'fadeOut' }, toast);
                        $(btn).prop('disabled', true).text('Memproses...');

                        axios.post(
                            '{{ route('orders-payment.selesai', ':id') }}'.replace(':id', receivingId),
                            {},
                            { headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }
                        )
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
                            iziToast.error({ title: 'Gagal', message: msg, position: 'topRight' });
                            $(btn).prop('disabled', false).html(`
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg> Selesai`);
                        });
                    }, true],
                    ['<button>Batal</button>', function (instance, toast) {
                        instance.hide({ transitionOut: 'fadeOut' }, toast);
                    }],
                ],
            });
        }

        // ── Filter listeners ──────────────────────────────────────────────────
        $('#filterCreditor').on('change', () => loadTable());

        $('#searchMedicine').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadTable(), 400);
        });

        $('#btnReset').on('click', function () {
            startDate = '';
            endDate   = '';
            $('#dateRange').val('');
            $('#filterCreditor').val('');
            $('#searchMedicine').val('');
            loadTable();
        });

        // ── Initial load ──────────────────────────────────────────────────────
        loadTable();
    </script>
@endsection