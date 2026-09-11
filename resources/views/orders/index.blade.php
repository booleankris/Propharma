@extends('layouts.app')

@section('title', 'Daftar Pesanan')

@section('style')
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .flatpickr-input {
            background: white !important;
        }

        .flatpickr-calendar {
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .text-end {
            text-align: right !important;
        }

        #orderItemsTable tbody td {
            vertical-align: middle !important;
            padding-top: 10px !important;
            padding-bottom: 10px !important;
        }

        /* Split Button Group Base (Lebih tegas dan jelas di atas background putih) */
        .btn-split-group {
            display: inline-flex;
            align-items: stretch;
            border-radius: 9px;
            overflow: hidden;
            font-family: inherit;
            vertical-align: middle;
            transition: all 0.15s ease-in-out;
            white-space: nowrap;
        }

        /* AKSI SEKUNDER (DITERIMA) - Kontras Tinggi di Background Putih Sesuai Mockup */
        .btn-split-white {
            background-color: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important; /* Border slate-300 yang tegas & jelas */
            box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.06);
        }
        .btn-split-white:hover {
            border-color: #94a3b8 !important; /* Slate-400 saat hover */
            box-shadow: 0 2px 5px 0 rgba(15, 23, 42, 0.1);
        }
        .btn-split-white .btn-split-main {
            color: #1e293b !important; /* Slate-800 gelap tebal */
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 6px 13px !important;
            background-color: #ffffff;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background-color 0.15s;
        }
        .btn-split-white .btn-split-main:hover {
            background-color: #f8fafc !important;
        }
        .btn-split-white .btn-split-main svg {
            color: #334155 !important;
        }
        .btn-split-white .btn-split-toggle {
            background-color: #ffffff !important;
            border: none;
            border-left: 1.5px solid #cbd5e1 !important; /* Divider vertikal tegas */
            color: #334155 !important;
            padding: 0 10px !important;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s, color 0.15s;
        }
        .btn-split-white .btn-split-toggle:hover {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
        }

        /* AKSI PRIMER (DIPESAN) - Emerald Solid Sesuai Mockup */
        .btn-split-emerald {
            background-color: #10b981 !important;
            border: 1px solid #059669 !important;
            box-shadow: 0 1px 3px 0 rgba(16, 185, 129, 0.25), 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-split-emerald:hover {
            background-color: #059669 !important;
            border-color: #047857 !important;
            box-shadow: 0 2px 5px 0 rgba(16, 185, 129, 0.35);
        }
        .btn-split-emerald .btn-split-main {
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 6px 13px !important;
            background-color: transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background-color 0.15s;
        }
        .btn-split-emerald .btn-split-main:hover {
            background-color: rgba(0, 0, 0, 0.06);
        }
        .btn-split-emerald .btn-split-toggle {
            background-color: rgba(0, 0, 0, 0.09) !important;
            border: none;
            border-left: 1px solid rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
            padding: 0 10px !important;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s;
        }
        .btn-split-emerald .btn-split-toggle:hover {
            background-color: rgba(0, 0, 0, 0.2) !important;
        }

        /* STATUS PENDING - Blue Solid */
        .btn-split-blue {
            background-color: #2563eb !important;
            border: 1px solid #1d4ed8 !important;
            box-shadow: 0 1px 3px 0 rgba(37, 99, 235, 0.25);
        }
        .btn-split-blue:hover {
            background-color: #1d4ed8 !important;
            border-color: #1e40af !important;
            box-shadow: 0 2px 5px 0 rgba(37, 99, 235, 0.35);
        }
        .btn-split-blue .btn-split-main {
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            padding: 6px 13px !important;
            background-color: transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: background-color 0.15s;
        }
        .btn-split-blue .btn-split-main:hover {
            background-color: rgba(0, 0, 0, 0.06);
        }
        .btn-split-blue .btn-split-toggle {
            background-color: rgba(0, 0, 0, 0.09) !important;
            border: none;
            border-left: 1px solid rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
            padding: 0 10px !important;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s;
        }
        .btn-split-blue .btn-split-toggle:hover {
            background-color: rgba(0, 0, 0, 0.2) !important;
        }

        /* Action Dropdown Menu */
        .action-dropdown-menu {
            animation: fadeInDropdown 0.12s ease-out;
        }
        @keyframes fadeInDropdown {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
    </style>
@endsection

@section('content')
    <section class="section px-4 py-2 font-poppins text-[#1c1c1c]">
        <div class="section-body flex flex-col gap-5">

            <div
                class="flex flex-col gap-4 p-4 bg-white border border-slate-200/80 rounded-xl shadow-xs md:flex-row md:items-center md:justify-between">

                {{-- LEFT: JUDUL & IKON HEADER --}}
                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 leading-tight">Daftar Pesanan</h2>
                        <p class="text-xs text-slate-400">Daftar dan riwayat pesanan apotek</p>
                    </div>
                </div>

                {{-- RIGHT: GRUP TOMBOL AKSI (RESPONSIF) --}}
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">

                    {{-- 1. TOMBOL KEMBALI (Secondary / Ghost Outline) --}}
                    <button id="back" type="button"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300 transition-all duration-150">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        <span>Kembali</span>
                    </button>

                    {{-- 2. TOMBOL TRACKING PESANAN (Soft Emerald + Ikon Truk Pengiriman) --}}
                    <a href="{{ route('orders-tracking.index') }}" class="w-full sm:w-auto">
                        <button type="button"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-300/80 hover:bg-emerald-100/80 hover:border-emerald-400 rounded-lg shadow-[0_0_8px_rgba(16,185,129,0.15)] hover:shadow-[0_0_12px_rgba(16,185,129,0.3)] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-emerald-400">
                          
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-600" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-truck-loading">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M2 3h1a2 2 0 0 1 2 2v10a2 2 0 0 0 2 2h15" />
                                <path d="M9 9a3 3 0 0 1 3 -3h4a3 3 0 0 1 3 3v2a3 3 0 0 1 -3 3h-4a3 3 0 0 1 -3 -3l0 -2" />
                                <path d="M7 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                                <path d="M16 19a2 2 0 1 0 4 0a2 2 0 1 0 -4 0" />
                            </svg>
                            <span>Tracking Pesanan</span>
                        </button>
                    </a>

                    {{-- 3. TOMBOL BUAT BARU (Primary Blue + Ikon Plus + Soft Blue Glow) --}}
                    <a href="{{ route('orders.create') }}" class="w-full sm:w-auto">
                        <button type="button"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-[0_0_8px_rgba(37,99,235,0.25)] hover:shadow-[0_0_12px_rgba(37,99,235,0.4)] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Buat Baru</span>
                        </button>
                    </a>

                </div>
            </div>

            <div class="w-full p-5 bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label
                            class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</label>
                        <input type="text" id="todayDate"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm transition-all focus:outline-none"
                            value="{{ $now }}" readonly autocomplete="off">
                    </div>
                    <div>
                        <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cari Nomor
                            SPB</label>
                        <input type="text" id="searchInput"
                            class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm transition-all focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ketik nomor SPB / Nomor Terima..." oninput="searchOrderCode(this.value)" autocomplete="off">
                    </div>
                    <div>
                        <label class="block mb-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wider">Filter
                            Tanggal</label>
                        <input type="text" id="dateRange"
                            class="w-full rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm transition-all focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Pilih rentang tanggal..." autocomplete="off">
                    </div>
                </div>
            </div>

            <div class="w-full p-5 bg-white rounded-xl shadow-sm border border-gray-100 overflow-x-auto">
                <table id="orderItemsTable" class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs text-gray-500 uppercase tracking-wider">
                            <th class="pb-3 font-semibold">#</th>
                            <th class="pb-3 font-semibold">Date</th>
                            <th class="pb-3 font-semibold">SPB & NOMOR TERIMA</th>
                            <th class="pb-3 font-semibold text-center">Status</th>
                            <th class="pb-3 font-semibold">Total</th>
                            <th class="pb-3 font-semibold">Total PPN</th>
                            <th class="pb-3 font-semibold text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm"></tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        let startDate = '';
        let endDate = '';

        flatpickr("#dateRange", {
            mode: "range",
            dateFormat: "Y-m-d",
            onClose: function(selectedDates, dateStr) {

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
        $('#back').click(function() {
            window.location.href = "{{ route('home') }}";
        });
    </script>
    <script>
        let orderItemsTable;

        document.addEventListener('DOMContentLoaded', function() {
            orderItemsTable = $('#orderItemsTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.orderlist') }}",
                    data: function(d) {
                        d.order_code = $('#searchInput').val();
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [
                    {
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'date',
                        name: 'updated_at'
                    },
                    {
                        data: 'code',
                        name: 'code',
                        defaultContent: '-'
                    },
                    {
                        data: 'status_order',
                        name: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'total',
                        name: 'total',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'total_ppn',
                        name: 'total_ppn',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false,
                        searchable: false
                    },
                ],
                order: [[1, 'desc']],
                searching: false,
                info: false,
            });
        });

        // Search SPB with debouncing
        let searchTimeout = null;
        function searchOrderCode(data) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (orderItemsTable) {
                    orderItemsTable.ajax.reload();
                }
            }, 300);
        }

        // Double click row to open order based on its status
        $('#orderItemsTable tbody').on('dblclick', 'tr', function(e) {
            if ($(e.target).closest('button, a, .action-dropdown-container, .action-dropdown-menu').length) return;

            const data = orderItemsTable ? orderItemsTable.row(this).data() : null;
            if (!data) return;

            if (data.status == 0) {
                window.location.href = "{{ route('orders.create') }}?order_id=" + data.id;
            } else if (data.status == 1 || data.status == 2) {
                window.location.href = "/receive/" + data.id;
            } else if (data.status == 3) {
                window.location.href = "/orders/" + data.id + "/revision";
            }
        });

        function deleteEmptyOrder(orderId, orderCode) {
            const title = 'Hapus BPBA / Pesanan?';
            const text = 'Apakah Anda yakin ingin menghapus ' + (orderCode && orderCode !== '0' ? orderCode : 'pesanan ini') + ' yang kosong? Data yang sudah dihapus tidak dapat dikembalikan.';

            const proceedDelete = () => {
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

                fetch(`/orders/${orderId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json().then(data => ({ status: response.status, body: data })))
                .then(({ status, body }) => {
                    if (body.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil',
                                text: body.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                        } else {
                            alert(body.message);
                        }
                        if (typeof orderItemsTable !== 'undefined') {
                            orderItemsTable.ajax.reload(null, false);
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: body.message || 'Gagal menghapus pesanan.'
                            });
                        } else {
                            alert(body.message || 'Gagal menghapus pesanan.');
                        }
                    }
                })
                .catch(err => {
                    console.error('Error deleting order:', err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Kesalahan Sistem',
                            text: 'Terjadi kesalahan saat menghapus pesanan.'
                        });
                    } else {
                        alert('Terjadi kesalahan saat menghapus pesanan.');
                    }
                });
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: title,
                    text: text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e11d48',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        proceedDelete();
                    }
                });
            } else {
                if (confirm(text)) {
                    proceedDelete();
                }
            }
        }

        // Dropdown menu handler for action buttons in DataTable
        $(document).on('click', '.btn-action-dropdown', function(e) {
            e.stopPropagation();
            const btn = $(this);
            const menu = btn.siblings('.action-dropdown-menu');
            const isVisible = !menu.hasClass('hidden');

            // Close all other dropdowns first
            $('.action-dropdown-menu').addClass('hidden');

            if (!isVisible) {
                // Position fixed menu right below button, aligned to the right edge
                const rect = btn[0].getBoundingClientRect();
                const menuWidth = 180;
                
                let top = rect.bottom + 6;
                let left = rect.right - menuWidth;

                // Ensure it stays within viewport
                if (left < 10) left = 10;
                if (top + 200 > window.innerHeight) {
                    top = rect.top - 180; // open upwards if close to bottom
                }

                menu.css({
                    top: top + 'px',
                    left: left + 'px',
                    width: menuWidth + 'px'
                }).removeClass('hidden');
            }
        });

        // Close dropdown when clicking outside or scrolling
        $(document).on('click', function() {
            $('.action-dropdown-menu').addClass('hidden');
        });

        $(window).on('scroll resize', function() {
            $('.action-dropdown-menu').addClass('hidden');
        });

        $('#orderItemsTable').closest('.overflow-x-auto').on('scroll', function() {
            $('.action-dropdown-menu').addClass('hidden');
        });
    </script>
@endsection
