@extends('layouts.app')

@section('title', 'Riwayat Pembelian Obat')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/datatables/media/css/jquery.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        table.dataTable thead th,
        table.dataTable thead td {
            padding: 21px 18px !important;
            background: #ffffff !important;
            border-bottom: 1px solid #111 !important;
        }

        .dataTables_wrapper .top {
            font-family: "Poppins";
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 12px !important;
        }

        .dataTables_filter {
            display: block !important;
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

        .dataTables_length {
            display: block !important;
        }

        .dataTables_length select {
            padding: 4px 23px !important;
            border-radius: 6px !important;
            border: 1px solid #d1d5db !important;
        }

        #historyTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
        }

        #historyTable tbody td {
            padding: 12px 10px !important;
            font-size: 14px !important;
            vertical-align: middle !important;
        }

        #historyTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        .dataTables_paginate .paginate_button {
            padding: 6px 12px !important;
            border-radius: 6px !important;
            background: #f3f3f3;
            margin: 0 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #2563eb;
            color: #fff !important;
            border: 1px solid #2563eb;
            margin: 0 4px;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            cursor: default !important;
            color: #666 !important;
            border: 1px solid transparent !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        .paginate_button.previous {
            background: #ffd7d7 !important;
        }

        .paginate_button.next {
            background: #c4ffcf !important;
            font-family: 'Poppins';
            font-size: 14px;
        }

        .dropdown-table {
            width: 100%;
            position: absolute;
            z-index: 999999;
            top: 52px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, .12);
            border: 1px solid #e5e7eb;
            max-height: 320px;
            overflow-y: auto;
            display: none;
            z-index: 9999;
        }

        .dropdown-table tbody tr.active {
            background-color: #dbeafe;
        }

        .dropdown-table table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 14px;
        }

        .dropdown-table thead th {
            position: sticky;
            top: 0;
            background: #f9fafb;
            color: #374151;
            font-weight: 600;
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: .04em;
        }

        .dropdown-table tbody tr {
            transition: background-color .15s ease, transform .05s ease;
            cursor: pointer;
        }

        .dropdown-table tbody tr:hover {
            background-color: #f3f4f6;
        }

        .dropdown-table tbody tr:active {
            transform: scale(0.995);
        }

        .dropdown-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #111827;
            vertical-align: middle;
        }

        .dropdown-table tbody tr:last-child td {
            border-bottom: none;
        }

        .dropdown-table td:first-child {
            width: 40px;
            color: #6b7280;
            font-size: 13px;
        }

        .dropdown-table td:last-child {
            color: #6b7280;
            font-size: 13px;
        }

        .dropdown-table .empty-row {
            text-align: center;
            padding: 16px;
            color: #9ca3af;
            font-style: italic;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body">
            <div class="card shadow-md rounded-2xl p-6 bg-white">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                        <path d="M9 5a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2" />
                    </svg>
                    <h2 class="text-lg font-semibold text-gray-800">Riwayat Penerimaan Obat</h2>
                </div>

                <div class="flex flex-wrap items-end gap-4 py-2">
                    <div class="flex-1 min-w-[200px]">
                        <div class="py-1 text-[13px] font-bold">Tanggal</div>
                        <input type="text" id="dateRange" placeholder="Pilih rentang tanggal..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                            autocomplete="off">
                    </div>

                    <div class="flex-[2] min-w-[300px]">
                        <div class="py-1 text-[13px] font-bold">Cari Obat...</div>
                        <div class="relative flex items-center gap-2">
                            <div class="relative flex-1">
                                <input autofocus type="text" id="searchInput"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    placeholder="Cari nama atau kode obat..." oninput="searchMedicineData(this.value)"
                                    autocomplete="off">

                                <div id="searchDropdown" class="dropdown-table" style="display:none;">
                                    <table class="table table-sm table-bordered mb-0 w-full text-[13px]">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Code</th>
                                                <th>Nama Obat</th>
                                            </tr>
                                        </thead>
                                    </table>

                                    <div id="tableScroll" style="max-height: 250px; overflow-y: auto;"
                                        onscroll="handleScroll()">
                                        <table class="table table-sm table-bordered mb-0 w-full text-[13px]">
                                            <tbody id="searchResults"></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <button type="button" id="searchButton"
                                class="flex items-center justify-center gap-1.5 h-[38px] px-5 shrink-0 rounded-lg bg-[#1678df] text-white text-[12px] font-bold font-['Montserrat'] hover:bg-blue-600 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-300">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                    <path d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"></path>
                                    <path d="M21 21l-6 -6"></path>
                                </svg>
                                <span>Cari</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto p-3">
                    <table id="historyTable" class="min-w-full text-sm text-left text-gray-600">
                        <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Kode Terima</th>
                                <th class="px-4 py-3">No. Invoice</th>
                                <th class="px-4 py-3">Nama Obat</th>
                                <th class="px-4 py-3">Qty Diterima</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="px-4 py-3">Distributor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/datatables/media/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery-ui-dist/jquery-ui.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-datatables.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        let loading = false;
        let historyTable;
        let startDate = '';
        let endDate = '';
        var searchMedicine = '';

        let keyword = '';
        let page = 1;
        let hasMore = true;
        let activeIndex = -1;

        let searchDebounceTimer = null;
        let searchRequestId = 0;
        let searchAbortController = null;

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
                    document.getElementById('searchInput').focus();
                }
            });

            historyTable = $('#historyTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('medicine-order-history.data') }}",
                    data: function(d) {
                        d.searchMedicine = searchMedicine;
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
                        data: 'date'
                    },
                    {
                        data: 'receiving_code'
                    },
                    {
                        data: 'invoice_number'
                    },
                    {
                        data: 'medicine_name'
                    },
                    {
                        data: 'qty_received'
                    },
                    {
                        data: 'total'
                    },
                    {
                        data: 'creditor_name'
                    },
                ],
                paging: true,
                searching: false,
                info: false,
            });
        });

        function searchMedicineData(value) {
            clearTimeout(searchDebounceTimer);
            const trimmed = value.trim();

            if (trimmed.length < 1) {
                document.getElementById('searchDropdown').style.display = 'none';
                document.getElementById('searchResults').innerHTML = '';
                searchMedicine = '';
                if (searchAbortController) searchAbortController.abort();
                return;
            }

            searchDebounceTimer = setTimeout(() => {
                keyword = trimmed;
                page = 1;
                hasMore = true;
                activeIndex = -1;
                document.getElementById('searchResults').innerHTML = '';
                fetchData();
            }, 300);
        }

        function fetchData() {
            if (!hasMore) return;

            if (searchAbortController) searchAbortController.abort();
            searchAbortController = new AbortController();

            const thisRequestId = ++searchRequestId;
            const thisKeyword = keyword;
            const thisPage = page;

            loading = true;

            fetch(`{{ route('medicine-order-history.searchmedicine') }}?search=${encodeURIComponent(thisKeyword)}&page=${thisPage}`, {
                    signal: searchAbortController.signal
                })
                .then(res => res.json())
                .then(res => {
                    if (thisRequestId !== searchRequestId) return;

                    const tbody = document.getElementById('searchResults');

                    if (thisPage === 1 && res.data.length === 0) {
                        tbody.innerHTML = `
                    <tr>
                        <td colspan="3" class="empty-row">No data found</td>
                    </tr>`;
                        hasMore = false;
                        document.getElementById('searchDropdown').style.display = 'block';
                        return;
                    }

                    res.data.forEach((item, index) => {
                        tbody.insertAdjacentHTML('beforeend', `
                    <tr data-item='${JSON.stringify(item)}' tabindex="0">
                        <td>${((thisPage - 1) * res.per_page) + index + 1}</td>
                        <td>${item.code ?? '-'}</td>
                        <td>${item.name ?? '-'}</td>
                    </tr>
                `);
                    });

                    hasMore = res.current_page < res.last_page;
                    page = thisPage + 1;

                    document.getElementById('searchDropdown').style.display = 'block';
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    console.error('Search failed:', err);
                })
                .finally(() => {
                    if (thisRequestId === searchRequestId) loading = false;
                });
        }

        function handleScroll() {
            const container = document.getElementById('tableScroll');
            if (container.scrollTop + container.clientHeight >= container.scrollHeight - 5) {
                fetchData();
            }
        }

        function selectRow(row) {
            const item = JSON.parse(row.dataset.item);

            document.getElementById('searchInput').value = item.name ?? '';
            document.getElementById('searchDropdown').style.display = 'none';

            searchMedicine = item.id;

            document.getElementById('searchButton').focus();
        }

        // --- NAVIGATION & ENTER KEY LOGIC ---
        document.addEventListener('keydown', function(e) {
            const dropdown = document.getElementById('searchDropdown');
            const isDropdownVisible = dropdown && dropdown.style.display === 'block';

            if (e.key === 'Enter' && document.activeElement.id === 'searchInput') {
                e.preventDefault();

                if (isDropdownVisible && activeIndex >= 0) {
                    const rows = document.querySelectorAll('#searchResults tr');
                    selectRow(rows[activeIndex]);
                } else {
                    searchMedicine = document.getElementById('searchInput').value;
                    if (dropdown) dropdown.style.display = 'none';

                    document.getElementById('searchButton').focus();
                }
                return;
            }

            if (!isDropdownVisible) return;

            const rows = document.querySelectorAll('#searchResults tr');
            if (!rows.length) return;

            if (['ArrowDown', 'ArrowUp'].includes(e.key)) {
                e.preventDefault();
            }

            if (e.key === 'ArrowDown') {
                activeIndex = Math.min(activeIndex + 1, rows.length - 1);
                updateActiveRow(rows);
            }

            if (e.key === 'ArrowUp') {
                activeIndex = Math.max(activeIndex - 1, 0);
                updateActiveRow(rows);
            }
        });

        function updateActiveRow(rows) {
            rows.forEach(r => r.classList.remove('active'));
            if (activeIndex >= 0) {
                rows[activeIndex].classList.add('active');
                rows[activeIndex].scrollIntoView({
                    block: 'nearest'
                });
            }
        }

        // --- MOUSE EVENTS FOR DROPDOWN ---
        document.getElementById('searchResults').addEventListener('mouseover', function(e) {
            const row = e.target.closest('tr');
            if (!row) return;

            const rows = [...this.children];
            rows.forEach(r => r.classList.remove('active'));

            row.classList.add('active');
            activeIndex = rows.indexOf(row);
        });

        document.getElementById('searchResults').addEventListener('click', function(e) {
            const row = e.target.closest('tr');
            if (row) {
                selectRow(row);
                e.stopPropagation();
            }
        });

        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('searchDropdown');
            const searchInput = document.getElementById('searchInput');

            if (dropdown && dropdown.style.display === 'block') {
                if (!searchInput.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.style.display = 'none';
                }
            }
        });

        // --- THE ONLY PLACE THE TABLE RELOADS ---
        const searchBtn = document.getElementById('searchButton');

        searchBtn.addEventListener('click', function() {
            const searchInputVal = document.getElementById('searchInput').value.trim();

            if (!searchInputVal) {
                searchMedicine = '';
            } else if (!searchMedicine || isNaN(searchMedicine)) {
                searchMedicine = searchInputVal;
            }

            historyTable.ajax.reload();
            document.getElementById('searchDropdown').style.display = 'none';
        });

        searchBtn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();

                const searchInputVal = document.getElementById('searchInput').value;
                if (searchInputVal && !searchMedicine) {
                    searchMedicine = searchInputVal;
                } else if (!searchInputVal) {
                    searchMedicine = '';
                }

                historyTable.ajax.reload();
                document.getElementById('searchDropdown').style.display = 'none';
            }
        });
    </script>
@endsection
