@extends('layouts.app')

@section('title', 'History Perubahan Harga')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
        /* ── DataTable chrome ── */
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

        #historyTable thead th {
            background-color: #f8fafc !important;
            font-weight: 600 !important;
            font-size: 12px !important;
            text-transform: uppercase !important;
            border-bottom: 2px solid #e5e7eb !important;
            white-space: nowrap;
        }

        #historyTable tbody td {
            padding: 11px 10px !important;
            font-size: 13px !important;
            vertical-align: middle !important;
        }

        #historyTable tbody tr:hover {
            background-color: #f1f5f9 !important;
        }

        /* ── Price direction badges ── */
        .badge-up {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 10px;
            background: #dcfce7;
            color: #166534;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-down {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 10px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-same {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 10px;
            background: #f3f4f6;
            color: #6b7280;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* ── Summary cards ── */
        .stat-card {
            background: #fff;
            border-radius: 16px;
            padding: 18px 22px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .07);
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .stat-card .icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .stat-card .label {
            font-size: 12px;
            color: #6b7280;
            font-weight: 500;
        }

        .stat-card .value {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            line-height: 1.2;
        }

        /* ── Search bar ── */
        .filter-input {
            width: 100%;
            height: 46px;
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
            font-weight: 700;
            color: #334155;
            margin-bottom: 7px;
            letter-spacing: .01em;
        }

        .medicine-filter-shell {
            position: relative;
            display: flex;
            align-items: center;
        }

        .medicine-filter-shell .filter-input {
            height: 46px;
            padding-left: 42px;
            padding-right: 38px;
            border-color: #dbe3ef;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        }

        .medicine-filter-shell .filter-input:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, .12), 0 4px 12px rgba(15, 23, 42, .05);
        }

        .medicine-search-icon {
            position: absolute;
            left: 14px;
            width: 17px;
            height: 17px;
            color: #94a3b8;
            pointer-events: none;
        }

        .medicine-options-panel {
            top: calc(100% + 8px);
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 16px 38px rgba(15, 23, 42, .16), 0 3px 8px rgba(15, 23, 42, .06);
        }

        .medicine-option {
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 62px;
            padding: 10px 14px;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color .12s ease;
        }

        .medicine-option:last-child { border-bottom: 0; }
        .medicine-option:hover, .medicine-option.is-active { background: #eff6ff; }

        .medicine-option-mark {
            display: flex;
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #eff6ff;
            color: #2563eb;
        }

        .medicine-option.is-active .medicine-option-mark { background: #dbeafe; }

        .medicine-option-copy { min-width: 0; flex: 1; }
        .medicine-option-name { overflow: hidden; color: #1e293b; font-size: 13px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
        .medicine-option-code { margin-top: 2px; color: #64748b; font-size: 11px; }
        .medicine-option-enter { color: #94a3b8; font-size: 11px; }

        /* ── Price value styling ── */
        .price-old {
            font-weight: 600;
            color: #64748b;
        }

        .price-new {
            font-weight: 700;
            color: #1d4ed8;
        }

        .price-current {
            font-size: 12px;
            color: #6b7280;
        }
    </style>
@endsection

@section('content')
    <section class="section px-4">
        <div class="section-body space-y-4">

            {{-- ─── Page header ─── --}}
            <div
                class="flex flex-col gap-4 p-5 bg-white border border-slate-200/80 rounded-xl shadow-sm md:flex-row md:items-center md:justify-between">

                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center justify-center w-11 h-11 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 leading-tight">History Perubahan Harga</h2>
                        <p class="text-xs text-slate-400">Riwayat perubahan harga obat oleh Superadmin & Manager</p>
                    </div>
                </div>

            </div>

            {{-- ─── Summary cards ─── --}}
            <div class=" hidden grid-cols-2 md:grid-cols-4 gap-3" id="summaryCards">
                <div class="stat-card">
                    <div class="icon" style="background:#eff6ff">📋</div>
                    <div>
                        <div class="label">Total Perubahan</div>
                        <div class="value" id="statTotal">–</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background:#dcfce7">📈</div>
                    <div>
                        <div class="label">Harga Naik</div>
                        <div class="value" id="statUp" style="color:#16a34a">–</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background:#fee2e2">📉</div>
                    <div>
                        <div class="label">Harga Turun</div>
                        <div class="value" id="statDown" style="color:#dc2626">–</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon" style="background:#fef9c3">🕐</div>
                    <div>
                        <div class="label">Perubahan Terakhir</div>
                        <div class="value text-sm" id="statLast" style="font-size:13px">–</div>
                    </div>
                </div>
            </div>

            {{-- ─── Filter bar ─── --}}
            <div class="bg-white border border-slate-200/80 rounded-2xl shadow-sm p-5">
                <div class="flex flex-wrap gap-3 items-end">

                    {{-- Medicine search --}}
                    <div class="flex-1 min-w-[240px] relative">
                        <div class="filter-label">Cari Obat</div>
                        <div class="medicine-filter-shell">
                            <svg class="medicine-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-4-4"></path></svg>
                            <input type="text" id="searchMedicine" class="filter-input" placeholder="Cari nama atau kode obat..."
                                autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="medicineOptions">
                        </div>
                        <input type="hidden" id="selectedMedicineId">
                        <div id="medicineOptions" class="medicine-options-panel hidden absolute z-30 w-full max-h-72 overflow-y-auto" role="listbox" aria-label="Pilihan obat"></div>
                    </div>

                    {{-- Date range --}}
                    <div class="flex-1 min-w-[220px]">
                        <div class="filter-label">Rentang Tanggal</div>
                        <input type="text" id="dateRange" class="filter-input" placeholder="Pilih rentang tanggal..."
                            autocomplete="off" readonly>
                    </div>

                    {{-- Reset --}}
                    <div>
                        <button id="btnReset"
                            class="h-[46px] px-4 rounded-lg border border-gray-300 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Reset Filter
                        </button>
                    </div>

                </div>
            </div>

            {{-- ─── DataTable ─── --}}
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <table id="historyTable" class="w-full">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Tanggal</th>
                            <th>Kode Obat</th>
                            <th>Nama Obat</th>
                            <th>Satuan</th>
                            <th>Harga Lama</th>
                            <th>Harga Baru</th>
                            <th>Diubah Oleh</th>
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
        // ════════════════════════════════════════
        // STATE
        // ════════════════════════════════════════
        let startDate = '';
        let endDate = '';
        let historyTable = null;
        let medicineLookupTimer = null;
        let activeMedicineOption = -1;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
            }[char]));
        }

        function closeMedicineOptions() {
            document.getElementById('medicineOptions').classList.add('hidden');
            document.getElementById('searchMedicine').setAttribute('aria-expanded', 'false');
            activeMedicineOption = -1;
        }

        function setActiveMedicineOption(index) {
            const buttons = [...document.querySelectorAll('#medicineOptions .medicine-option')];
            if (!buttons.length) return;
            activeMedicineOption = (index + buttons.length) % buttons.length;
            buttons.forEach((button, i) => {
                const isActive = i === activeMedicineOption;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
            buttons[activeMedicineOption].scrollIntoView({ block: 'nearest' });
        }

        function renderMedicineOptions(items) {
            const options = document.getElementById('medicineOptions');
            if (!items.length) {
                options.innerHTML = '<div class="px-4 py-5 text-center"><div class="text-sm font-semibold text-slate-700">Obat tidak ditemukan</div><div class="mt-1 text-xs text-slate-400">Coba kata kunci atau kode yang berbeda.</div></div>';
            } else {
                options.innerHTML = items.map(item => `
                    <button type="button" role="option" aria-selected="false" class="medicine-option w-full text-left" data-id="${escapeHtml(item.id)}" data-code="${escapeHtml(item.code)}" data-name="${escapeHtml(item.name)}">
                        <span class="medicine-option-mark"><svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 3h4v5.5l4.6 7.9A3 3 0 0 1 16 21H8a3 3 0 0 1-2.6-4.6L10 8.5V3Z"></path><path d="M9 14h6"></path></svg></span>
                        <span class="medicine-option-copy"><span class="medicine-option-name block">${escapeHtml(item.name)}</span><span class="medicine-option-code block">Kode ${escapeHtml(item.code || '-')}</span></span>
                        <span class="medicine-option-enter">Pilih ↵</span>
                    </button>`).join('');
            }
            options.classList.remove('hidden');
            document.getElementById('searchMedicine').setAttribute('aria-expanded', 'true');
            activeMedicineOption = -1;
        }

        function searchMasterMedicines(term) {
            clearTimeout(medicineLookupTimer);
            medicineLookupTimer = setTimeout(async () => {
                if (term.trim().length < 1) {
                    closeMedicineOptions();
                    return;
                }
                try {
                    const response = await axios.get("{{ route('receiving.revision.searchMasterMedicine') }}", {
                        params: { search: term.trim(), history_filter: 1 }
                    });
                    // Ignore a slower response if the user has typed a newer query.
                    if (document.getElementById('searchMedicine').value.trim() !== term.trim()) return;
                    renderMedicineOptions(response.data.data || []);
                } catch (error) {
                    closeMedicineOptions();
                }
            }, 250);
        }

        // ════════════════════════════════════════
        // SUMMARY CARDS  – computed from current
        // page data after every draw
        // ════════════════════════════════════════
        function updateSummaryCards() {
            // Use the full recordsTotal from the last Ajax response
            const info = historyTable.page.info();
            document.getElementById('statTotal').textContent = info.recordsTotal.toLocaleString('id-ID');

            // Count up/down from visible rows (good enough for overview)
            let up = 0,
                down = 0,
                lastDate = '–';

            historyTable.rows({
                page: 'current'
            }).data().each(function(row) {
                if (row.direction && row.direction.includes('Naik')) up++;
                if (row.direction && row.direction.includes('Turun')) down++;
            });

            document.getElementById('statUp').textContent = up;
            document.getElementById('statDown').textContent = down;

            // Last changed_at from first visible row (already sorted DESC)
            const first = historyTable.row(0).data();
            if (first) lastDate = first.changed_at ?? '–';
            document.getElementById('statLast').textContent = lastDate;
        }

        // ════════════════════════════════════════
        // DOM READY
        // ════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', function() {

            // ── Date range picker ──
            flatpickr('#dateRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                allowInput: false,
                onClose: function(selectedDates) {
                    if (selectedDates.length === 2) {
                        startDate = flatpickr.formatDate(selectedDates[0], 'Y-m-d');
                        endDate = flatpickr.formatDate(selectedDates[1], 'Y-m-d');
                    } else {
                        startDate = '';
                        endDate = '';
                    }
                    historyTable.ajax.reload(null, false);
                }
            });

            // ── DataTable ──
            historyTable = $('#historyTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('receiving.gethistory') }}",
                    data: function(d) {
                        d.medicine_id = document.getElementById('selectedMedicineId').value;
                        d.start_date = startDate;
                        d.end_date = endDate;
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '40px'
                    },
                    {
                        data: 'changed_at',
                        width: '130px'
                    },
                    {
                        data: 'medicine_code',
                        width: '110px'
                    },
                    {
                        data: 'medicine_name'
                    },
                    {
                        data: 'medicine_unit',
                        width: '80px'
                    },
                    {
                        // Harga lama (sebelum diubah)
                        data: 'old_price_fmt',
                        render: (data) => `<span class="price-old">${data}</span>`,
                        orderable: false
                    },
                    {
                        // Harga baru (setelah diubah)
                        data: 'new_price_fmt',
                        render: (data, type, row) => {
                            const badge = row.direction && !row.direction.includes('badge-same') ? ` ${row.direction}` : '';
                            return `<div class="flex items-center gap-1.5"><span class="price-new">${data}</span>${badge}</div>`;
                        },
                        orderable: false
                    },

                    {
                        data: 'changed_by',
                        orderable: false
                    },
                ],
                order: [
                    [1, 'desc']
                ], // newest first
                pageLength: 25,
                language: {
                    search: 'Cari:',
                    lengthMenu: 'Tampilkan _MENU_ data',
                    info: 'Menampilkan _START_–_END_ dari _TOTAL_ data',
                    infoEmpty: 'Tidak ada data',
                    emptyTable: 'Tidak ada riwayat harga ditemukan',
                    paginate: {
                        previous: '‹',
                        next: '›',
                    }
                },
                drawCallback: function() {
                    updateSummaryCards();
                }
            });

            // ── Medicine search and select ──
            document.getElementById('searchMedicine').addEventListener('input', function() {
                const hadSelection = !!document.getElementById('selectedMedicineId').value;
                document.getElementById('selectedMedicineId').value = '';
                if (hadSelection) historyTable.ajax.reload(null, false);
                searchMasterMedicines(this.value);
            });

            document.getElementById('searchMedicine').addEventListener('keydown', function(event) {
                const options = document.getElementById('medicineOptions');
                const isOpen = !options.classList.contains('hidden');
                const optionCount = options.querySelectorAll('.medicine-option').length;

                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    if (!isOpen || !optionCount) return;
                    event.preventDefault();
                    const step = event.key === 'ArrowDown' ? 1 : -1;
                    setActiveMedicineOption(activeMedicineOption === -1
                        ? (step === 1 ? 0 : optionCount - 1)
                        : activeMedicineOption + step);
                } else if (event.key === 'Enter' && isOpen && optionCount) {
                    event.preventDefault();
                    const index = activeMedicineOption === -1 ? 0 : activeMedicineOption;
                    options.querySelectorAll('.medicine-option')[index]?.click();
                } else if (event.key === 'Escape' && isOpen) {
                    event.preventDefault();
                    closeMedicineOptions();
                }
            });

            document.getElementById('medicineOptions').addEventListener('click', function(event) {
                const option = event.target.closest('.medicine-option');
                if (!option) return;
                document.getElementById('selectedMedicineId').value = option.dataset.id;
                document.getElementById('searchMedicine').value = `${option.dataset.code} - ${option.dataset.name}`;
                closeMedicineOptions();
                historyTable.ajax.reload(null, false);
            });

            document.addEventListener('click', function(event) {
                if (!event.target.closest('#searchMedicine') && !event.target.closest('#medicineOptions')) {
                    closeMedicineOptions();
                }
            });

            // ── Reset button ──
            document.getElementById('btnReset').addEventListener('click', function() {
                document.getElementById('searchMedicine').value = '';
                document.getElementById('selectedMedicineId').value = '';
                closeMedicineOptions();
                document.getElementById('dateRange').value = '';
                startDate = '';
                endDate = '';
                // Re-initialise flatpickr clear
                document.getElementById('dateRange')._flatpickr?.clear();
                historyTable.ajax.reload(null, false);
            });

        });
    </script>
@endsection
