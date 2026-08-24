@extends('layouts.app')

@section('title', 'Performa Semua Kasir')

@section('style')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <style>
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

        #staffTable {
            font-size: 11px !important;
        }

        #staffTable td,
        #staffTable th {
            font-size: 11px !important;
            vertical-align: middle;
        }

        #staffTable td.text-right {
            text-align: right;
        }

        .avatar-cell {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: #E6F1FB;
            color: #0C447C;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .role-pill {
            font-size: 10px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
            background: #EEEDFE;
            color: #3C3489;
        }

        .change-up {
            color: #1a9e5c;
            font-weight: 600;
        }

        .change-down {
            color: #d63a3a;
            font-weight: 600;
        }

        .change-flat {
            color: #94a3b8;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 py-4">
        <div class="mx-auto flex flex-col gap-4">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h1 class="text-[17px] font-bold text-gray-800 leading-tight tracking-tight">Performa Semua Kasir</h1>
                    <p class="text-[12px] text-gray-400 mt-0.5">Ringkasan penjualan per pengguna</p>
                </div>
                <span class="text-[11px] text-gray-400 border border-gray-200 rounded-full px-3 py-1 bg-white">
                    {{ now()->translatedFormat('l, d M Y') }}
                </span>
            </div>

            {{-- Filter bar --}}
            <div class="bg-white border border-blue-100 rounded-xl px-4 py-3 flex flex-wrap items-center gap-3 shadow-sm">
                <span class="text-[12px] text-blue-700 font-bold flex items-center gap-1.5">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                    Periode
                </span>
                <div class="flex flex-wrap gap-2">
                    <button class="filter-btn active" onclick="setFilter(this, 'today')">Hari ini</button>
                    <button class="filter-btn" onclick="setFilter(this, 'week')">Minggu ini</button>
                    <button class="filter-btn" onclick="setFilter(this, 'month')">Bulan ini</button>
                    <button class="filter-btn" onclick="setFilter(this, 'all')">Semua</button>
                    <button class="filter-btn" onclick="setFilter(this, 'custom')">Custom</button>
                </div>
                <div id="customRange" class="flex items-center gap-2 ml-auto">
                    <input type="date" id="dateFrom" value="{{ now()->toDateString() }}">
                    <span class="text-gray-300 text-sm">—</span>
                    <input type="date" id="dateTo" value="{{ now()->toDateString() }}">
                    <button onclick="applyCustomFilter()"
                        class="text-[12px] font-medium bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-colors">
                        Terapkan
                    </button>
                </div>
            </div>

            {{-- Table --}}
            <div class="bg-white rounded-2xl shadow-sm p-5 border border-gray-100">
                <div class="flex items-center gap-2 mb-4">
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                        </svg>
                        Semua Kasir
                    </span>
                    <span class="text-xs text-gray-400">Data diperbarui real-time</span>
                </div>

                <table id="staffTable" class="w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kasir</th>
                            <th class="text-right">Penjualan</th>
                            <th class="text-right">Transaksi</th>
                            <th class="text-right">Shift Selesai</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        let startDate = '',
            endDate = '',
            Table = null;

        function resolveDates(filter, from, to) {
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
                    return [fmt(new Date(today.getFullYear(), today.getMonth(), 1)),
                        fmt(new Date(today.getFullYear(), today.getMonth() + 1, 0))
                    ];
                }
                case 'custom':
                    return [from || fmt(today), to || fmt(today)];
                default:
                    return ['', ''];
            }
        }

        function setFilter(btn, type) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('customRange').style.display = type === 'custom' ? 'flex' : 'none';
            if (type !== 'custom') {
                [startDate, endDate] = resolveDates(type, '', '');
                if (Table) Table.ajax.reload(null, false);
            }
        }

        function applyCustomFilter() {
            const from = document.getElementById('dateFrom').value;
            const to = document.getElementById('dateTo').value;
            if (!from || !to) return;
            startDate = from;
            endDate = to;
            if (Table) Table.ajax.reload(null, false);
        }

        const rp = v => 'Rp ' + parseInt(v || 0).toLocaleString('id-ID');
        const num = v => parseInt(v || 0).toLocaleString('id-ID');

        function changeHtml(val) {
            if (!val && val !== 0) return '<span class="change-flat">—</span>';
            if (val > 0) return `<span class="change-up">↑ ${val}%</span>`;
            if (val < 0) return `<span class="change-down">↓ ${Math.abs(val)}%</span>`;
            return '<span class="change-flat">0%</span>';
        }

        document.addEventListener('DOMContentLoaded', function() {
            [startDate, endDate] = resolveDates('today', '', '');

            Table = $('#staffTable').DataTable({
                responsive: true,
                autoWidth: false,
                processing: true,
                serverSide: true,
                searching: true,
                ajax: {
                    url: '{{ route('admin.staff-stats') }}',
                    data(d) {
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                language: {
                    processing: 'Memuat data...',
                    emptyTable: 'Tidak ada data',
                    zeroRecords: 'Data tidak ditemukan',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ kasir',
                    infoEmpty: 'Tidak ada data',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    search: 'Cari kasir:',
                    paginate: {
                        next: '›',
                        previous: '‹'
                    },
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '36px',
                        className: 'text-gray-400',
                    },
                    {
                        data: 'name',
                        orderable: true,
                        render: (val, type, row) =>
                            `<div class="flex items-center gap-2">
                            <div class="avatar-cell">${row.initials}</div>
                            <div>
                                <div class="font-semibold text-gray-800 leading-tight">${val}</div>
                                <div class="text-gray-400" style="font-size:10px">@${row.username}</div>
                            </div>
                            <span class="role-pill ml-1">Kasir</span>
                         </div>`,
                    },
                    {
                        data: 'filtered_sales',
                        orderable: true,
                        className: 'text-right',
                        render: val => `<div class="font-semibold text-gray-800">${rp(val)}</div>`,
                    },
                    {
                        data: 'filtered_transactions',
                        orderable: true,
                        className: 'text-right',
                        render: val => `<div class="font-semibold text-gray-700">${num(val)}</div>`,
                    },
                    {
                        data: 'shifts_completed',
                        orderable: true,
                        className: 'text-right',
                        render: val => `<div class="font-semibold text-gray-700">${num(val)}</div>`,
                    },
                ],
                order: [
                    [2, 'desc']
                ],
                pageLength: 25,
                lengthMenu: [10, 25, 50],
            });
        });
    </script>
@endsection
