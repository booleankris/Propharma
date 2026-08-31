@extends('layouts.app')

@section('title', 'Performa Penjualan')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <style>
        .stat-card {
            border-radius: 14px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border: 1px solid transparent;
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .prog-track {
            height: 5px;
            border-radius: 99px;
            background: #CECBF6;
            overflow: hidden;
            margin-top: 6px;
        }

        .prog-fill {
            height: 100%;
            border-radius: 99px;
            background: #534AB7;
            transition: width .4s ease;
        }

        #invoiceTable {
            font-size: 10px !important;
            font-family: "Poppins" !important;
        }

        #invoiceTable td {
            font-size: 10px !important;
        }

        .dataTables_wrapper .dataTables_length select {
            padding-right: 22px;
            padding-left: 9px;
        }

        .filter-btn {
            font-size: 12px;
            font-weight: 500;
            padding: 5px 14px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #64748b;
            cursor: pointer;
            transition: all .15s;
            white-space: nowrap;
        }

        .filter-btn:hover {
            background: #f8fafc;
        }

        .filter-btn.active {
            background: #185FA5;
            color: #fff;
            border-color: #185FA5;
        }

        #customRange {
            display: none;
            align-items: center;
            gap: 6px;
        }

        #customRange input[type="date"] {
            height: 32px;
            font-size: 12px;
            padding: 0 8px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #fff;
            color: #1e293b;
            outline: none;
        }

        #customRange input[type="date"]:focus {
            border-color: #378ADD;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 py-1">
        <div class="mx-auto flex flex-col gap-5">
            <div class="grid grid-cols-2 gap-5">
                <div>
                    {{-- ── Profile Card ──────────────────────────────── --}}
                    <div class="bg-white mb-2 rounded-2xl overflow-hidden shadow-[0_2px_5px_#b9cfffa1]">

                        <div class="flex items-center gap-4 px-5 pt-5 pb-4">
                            <div
                                class="w-12 h-12 rounded-full border-2 border-blue-200 bg-blue-50 flex items-center
                            justify-center text-blue-800 font-semibold text-sm flex-shrink-0">
                                {{ strtoupper(substr($salesData->pharmacy->name, 0, 2)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[15px] font-semibold text-gray-800 truncate leading-tight">
                                    {{ $salesData->pharmacy->name }}
                                </p>
                                <p class="text-[13px] text-gray-400 mt-0.5">{{ $salesData->pharmacy->address }}</p>
                            </div>
                            <span
                                class="inline-flex items-center gap-1.5 text-[11px] font-semibold
                             bg-[#EEEDFE] text-[#3C3489] px-3 py-1.5 rounded-full flex-shrink-0">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                                </svg>
                                {{ auth()->user()->getRoleNames()->first() ?? '—' }}
                            </span>
                        </div>

                        <div class="grid grid-cols-3 divide-x divide-gray-100 border-t border-gray-100">
                            <div class="flex flex-col gap-0.5 px-5 py-3">
                                <span class="text-[11px] text-gray-400">Username</span>
                                <span
                                    class="text-[13px] font-medium text-gray-700 truncate">{{ auth()->user()->username }}</span>
                            </div>
                            <div class="flex flex-col gap-0.5 px-5 py-3">
                                <span class="text-[11px] text-gray-400">Role</span>
                                <span
                                    class="text-[13px] font-medium text-gray-700">{{ auth()->user()->getRoleNames()->first() ?? '—' }}</span>
                            </div>
                            <div class="flex flex-col gap-0.5 px-5 py-3">
                                <span class="text-[11px] text-gray-400">Status</span>
                                <span
                                    class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-green-800 mt-0.5 w-fit">
                                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
                                    Aktif
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="my-2">
                        <div>
                            <p class="text-[10.5px] font-semibold uppercase tracking-widest mb-3 px-0.5"
                                style="color:#94a3b8; letter-spacing:0.08em;">
                                Performa penjualan
                            </p>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">

                                {{-- Today — Blue --}}
                                <div class="stat-card" style="background:#E6F1FB; border-color:#B5D4F4;">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11.5px] font-semibold" style="color:#0C447C;">Hari ini</span>
                                        <div class="stat-icon" style="background:#B5D4F4; color:#0C447C;">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                                <line x1="16" y1="2" x2="16" y2="6" />
                                                <line x1="8" y1="2" x2="8" y2="6" />
                                                <line x1="3" y1="10" x2="21" y2="10" />
                                                <line x1="12" y1="15" x2="12" y2="15" />
                                            </svg>
                                        </div>
                                    </div>
                                    <p class="text-[15px] font-semibold leading-none" style="color:#042C53;">
                                        Rp {{ number_format($salesData->today, 0, ',', '.') }}
                                    </p>
                                    @if ($salesData->today_change >= 0)
                                        <p class="text-[11px] font-medium flex items-center gap-1" style="color:#27500A;">
                                            ↑ {{ $salesData->today_change }}% vs kemarin
                                        </p>
                                    @else
                                        <p class="text-[11px] font-medium flex items-center gap-1" style="color:#791F1F;">
                                            ↓ {{ abs($salesData->today_change) }}% vs kemarin
                                        </p>
                                    @endif
                                </div>

                                {{-- This Month — Teal --}}
                                <div class="stat-card" style="background:#E1F5EE; border-color:#9FE1CB;">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11.5px] font-semibold" style="color:#085041;">Bulan ini</span>
                                        <div class="stat-icon" style="background:#9FE1CB; color:#085041;">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                                <line x1="16" y1="2" x2="16" y2="6" />
                                                <line x1="8" y1="2" x2="8" y2="6" />
                                                <line x1="3" y1="10" x2="21" y2="10" />
                                                <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01" />
                                            </svg>
                                        </div>
                                    </div>
                                    <p class="text-[15px] font-semibold leading-none" style="color:#04342C;">
                                        Rp {{ number_format($salesData->this_month, 0, ',', '.') }}
                                    </p>
                                    @if ($salesData->month_change >= 0)
                                        <p class="text-[11px] font-medium" style="color:#27500A;">↑
                                            {{ $salesData->month_change }}%
                                            vs bulan lalu</p>
                                    @else
                                        <p class="text-[11px] font-medium" style="color:#791F1F;">↓
                                            {{ abs($salesData->month_change) }}% vs bulan lalu</p>
                                    @endif
                                </div>

                                {{-- All Time — Amber --}}
                                <div class="stat-card" style="background:#FAEEDA; border-color:#FAC775;">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11.5px] font-semibold" style="color:#633806;">Total
                                            semua</span>
                                        <div class="stat-icon" style="background:#FAC775; color:#633806;">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <ellipse cx="12" cy="5" rx="9" ry="3" />
                                                <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" />
                                                <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />
                                            </svg>
                                        </div>
                                    </div>
                                    <p class="text-[15px] font-semibold leading-none" style="color:#412402;">
                                        Rp {{ number_format($salesData->all_time, 0, ',', '.') }}
                                    </p>
                                    <p class="text-[11px]" style="color:#854F0B;">Sejak bergabung</p>
                                </div>

                                {{-- Shifts — Purple --}}
                                <div class="stat-card" style="background:#EEEDFE; border-color:#CECBF6;">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[11.5px] font-semibold" style="color:#3C3489;">Shift
                                            selesai</span>
                                        <div class="stat-icon" style="background:#CECBF6; color:#3C3489;">
                                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10" />
                                                <polyline points="12 6 12 12 16 14" />
                                            </svg>
                                        </div>
                                    </div>
                                    <p class="text-[15px] font-semibold leading-none" style="color:#26215C;">
                                        {{ $salesData->shifts_completed }}
                                    </p>
                                    <p class="text-[11px]" style="color:#534AB7;">Bulan ini</p>

                                </div>

                            </div>
                        </div>

                        {{-- ── Transaction Summary ───────────────────────── --}}
                        <div class="my-3">
                            <p class="text-[10.5px] font-semibold uppercase tracking-widest mb-3 px-0.5"
                                style="color:#94a3b8; letter-spacing:0.08em;">
                                Ringkasan transaksi bulan ini
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                                <div class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-sm">
                                    <div class="flex items-center gap-2 text-[12px] text-gray-400">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14 2 14 8 20 8" />
                                        </svg>
                                        Total transaksi
                                    </div>
                                    <p class="text-[22px] font-semibold text-gray-800 leading-none">
                                        {{ number_format($salesData->total_transactions) }}
                                    </p>
                                    <p class="text-[11px] text-gray-400">
                                        Rata-rata {{ number_format($salesData->avg_transactions_per_day, 1) }} / hari
                                    </p>
                                </div>

                                <div class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-sm">
                                    <div class="flex items-center gap-2 text-[12px] text-gray-400">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round">
                                            <line x1="12" y1="1" x2="12" y2="23" />
                                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                                        </svg>
                                        Rata-rata per transaksi
                                    </div>
                                    <p class="text-[22px] font-semibold text-gray-800 leading-none">
                                        Rp {{ number_format($salesData->avg_per_transaction, 0, ',', '.') }}
                                    </p>
                                    @if ($salesData->avg_change >= 0)
                                        <p class="text-[11px] font-medium" style="color:#27500A;">↑
                                            {{ $salesData->avg_change }}%
                                            vs
                                            bulan lalu</p>
                                    @else
                                        <p class="text-[11px] font-medium" style="color:#791F1F;">↓
                                            {{ abs($salesData->avg_change) }}% vs bulan lalu</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    {{-- ── Date Filter ───────────────────────────────── --}}
                    <div
                        class="mb-3 border border-[#8cccff] rounded-xl px-4 py-3 flex flex-wrap items-center gap-3 shadow-[0_2px_9px_#1edbff17]">
                        <span class="text-[12px] text-[#2c61bd] font-bold flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                            Periode
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <button class="filter-btn active" onclick="setProfileFilter(this, 'today')">Hari ini</button>
                            <button class="filter-btn" onclick="setProfileFilter(this, 'week')">Minggu ini</button>
                            <button class="filter-btn" onclick="setProfileFilter(this, 'month')">Bulan ini</button>
                            <button class="filter-btn" onclick="setProfileFilter(this, 'all')">Semua</button>
                            <button class="filter-btn" onclick="setProfileFilter(this, 'custom')">Custom</button>
                        </div>
                        <div id="customRange" class="flex items-center gap-2 ml-auto">
                            <input type="date" id="dateFrom" name="date_from"
                                value="{{ request('date_from', now()->toDateString()) }}">
                            <span class="text-gray-300 text-sm">—</span>
                            <input type="date" id="dateTo" name="date_to"
                                value="{{ request('date_to', now()->toDateString()) }}">
                            <button onclick="applyCustomFilter()"
                                class="text-[12px] font-medium bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-colors">
                                Terapkan
                            </button>
                        </div>
                    </div>
                    {{-- ─── DataTable ─── --}}
                    <div class="bg-white rounded-2xl shadow-sm p-5">

                        <div class="flex items-center gap-2 mb-4">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold"
                                style="background:#eff6ff;color:#1d4ed8;">
                                Transaksi Terselesaikan
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
                                </tr>
                            </thead>
                            <tbody class="text-[13px]"></tbody>
                        </table>
                    </div>
                </div>
            </div>



            {{-- ── Sales Performance ─────────────────────────── --}}


        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>

    <script>
        let startDate = '';
        let endDate = '';
        let Table = null;
        let groupColorMap = {};
        let groupCounter = 0;

        // ── Resolve initial dates from URL params ────────────────────────────
        function resolveDates(filter, dateFrom, dateTo) {
            const today = new Date();
            const fmt = d => d.toISOString().split('T')[0];

            switch (filter) {
                case 'today':
                    return [fmt(today), fmt(today)];
                case 'week': {
                    const mon = new Date(today);
                    mon.setDate(today.getDate() - today.getDay() + 1);
                    const sun = new Date(mon);
                    sun.setDate(mon.getDate() + 6);
                    return [fmt(mon), fmt(sun)];
                }
                case 'month': {
                    const first = new Date(today.getFullYear(), today.getMonth(), 1);
                    const last = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    return [fmt(first), fmt(last)];
                }
                case 'custom':
                    return [dateFrom || fmt(today), dateTo || fmt(today)];
                case 'all':
                default:
                    return ['', ''];
            }
        }

        // ── Filter button click ──────────────────────────────────────────────
        function setProfileFilter(btn, type) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const customRange = document.getElementById('customRange');
            customRange.style.display = type === 'custom' ? 'flex' : 'none';

            if (type !== 'custom') {
                const [from, to] = resolveDates(type, '', '');
                startDate = from;
                endDate = to;
                reloadTable();
            }
        }

        // ── Custom date apply ────────────────────────────────────────────────
        function applyCustomFilter() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            if (!from || !to) {
                iziToast.warning({
                    title: 'Peringatan',
                    message: 'Pilih tanggal mulai dan akhir.'
                });
                return;
            }
            startDate = from;
            endDate = to;
            reloadTable();
        }

        // ── Reload table without destroying DOM ──────────────────────────────
        function reloadTable() {
            if (!Table) return;
            Table.ajax.reload(null, false);
        }

        // ── Init on DOM ready ────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            const filter = params.get('filter') || 'today';
            const dateFrom = params.get('date_from') || '';
            const dateTo = params.get('date_to') || '';

            // Sync filter button active state
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('onclick')?.includes(`'${filter}'`)) {
                    btn.classList.add('active');
                }
            });

            // Show custom range if needed
            if (filter === 'custom') {
                document.getElementById('customRange').style.display = 'flex';
                if (dateFrom) document.getElementById('dateFrom').value = dateFrom;
                if (dateTo) document.getElementById('dateTo').value = dateTo;
            }

            // Resolve startDate/endDate
            [startDate, endDate] = resolveDates(filter, dateFrom, dateTo);

            // ── Init DataTable ───────────────────────────────────────────────
            Table = $('#invoiceTable').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                searching: false,
                ajax: {
                    url: '{{ route('invoices.getall') }}',
                    data(d) {
                        d.start_date = startDate;
                        d.end_date = endDate;
                    },
                },
                language: {
                    processing: 'Memuat data...',
                    emptyTable: 'Tidak ada data tagihan',
                    zeroRecords: 'Data tidak ditemukan',
                    info: 'Menampilkan _START_ – _END_ dari _TOTAL_ data',
                    infoEmpty: 'Coba Ubah Filter Periode',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    paginate: {
                        next: '›',
                        previous: '‹'
                    },
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px',
                        className: 'text-gray-400 text-xs',
                    },
                    {
                        data: 'tanggal',
                        orderable: false
                    },
                    {
                        data: 'nomor_resep',
                        orderable: false,
                        render: val => `<span class="resep-pill">${val}</span>`,
                    },
                    {
                        data: 'dokter',
                        orderable: false
                    },
                    {
                        data: 'debtor',
                        orderable: false,
                    },
                    {
                        data: 'nama_obat',
                        orderable: false
                    },
                    {
                        data: 'qty',
                        orderable: false,
                        className: 'text-right',
                        render: val => parseInt(val).toLocaleString('id-ID'),
                    },
                    {
                        data: 'harga',
                        orderable: false,
                        className: 'text-right'
                    },
                    {
                        data: 'jumlah',
                        orderable: false,
                        className: 'text-right jumlah-cell',
                        render: (val, type, row) => row.is_first_in_group ? val : '',
                    },

                ],
                order: [
                    [1, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],

                drawCallback() {
                    groupColorMap = {};
                    groupCounter = 0;

                    this.api().rows({
                        page: 'current'
                    }).every(function() {
                        const row = this.data();
                        const tid = row.transaction_id;
                        const node = $(this.node());

                        if (!(tid in groupColorMap)) {
                            groupColorMap[tid] = groupCounter % 2 === 0 ? 'group-odd' :
                                'group-even';
                            groupCounter++;
                        }
                        node.addClass(groupColorMap[tid]);
                        if (row.is_first_in_group) node.addClass('group-first');
                    });
                },
            });
        });
    </script>
@endsection
