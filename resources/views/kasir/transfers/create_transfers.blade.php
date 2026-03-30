@extends('layouts.app')

@section('title', 'Transfer Stok')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
        }

        /* ── Layout shell ── */
        .transfer-shell {
            min-height: 100vh;
            background: #f0f2f5;
            padding: 28px 20px;
        }

        .transfer-card {
            margin: 0 auto;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, .07);
            overflow: hidden;
        }

        /* ── Header bar ── */
        .transfer-header {
            background: #0f172a;
            padding: 22px 28px;
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .transfer-header-icon {
            width: 38px;
            height: 38px;
            background: rgba(59, 130, 246, .2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .transfer-header h1 {
            color: #f8fafc;
            font-size: 15px;
            font-weight: 600;
            margin: 0;
        }

        .transfer-header span {
            font-family: 'DM Mono', monospace;
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
            display: block;
        }

        /* ── Meta row ── */
        .meta-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .meta-cell {
            padding: 14px 28px;
            border-right: 1px solid #f1f5f9;
        }

        .meta-cell:last-child {
            border-right: none;
        }

        .meta-label {
            font-size: 10px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 4px;
        }

        .meta-value {
            font-family: 'DM Mono', monospace;
            font-size: 13px;
            color: #1e293b;
            font-weight: 500;
        }

        /* ── Section blocks ── */
        .section-block {
            padding: 22px 28px;
            border-bottom: 1px solid #f1f5f9;
        }

        .section-block:last-child {
            border-bottom: none;
        }

        .section-label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .08em;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #f1f5f9;
        }

        /* ── Search input ── */
        .search-wrapper {
            position: relative;
        }

        .search-wrapper svg.icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            color: #94a3b8;
            pointer-events: none;
        }

        .search-input {
            width: 100%;
            padding: 10px 12px 10px 38px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            color: #1e293b;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }

        .search-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .1);
        }

        /* ── Dropdown ── */
        .search-dropdown {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 9999;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, .11);
            overflow: hidden;
            display: none;
        }

        .dropdown-head {
            display: grid;
            grid-template-columns: 28px 1fr 1fr 90px;
            padding: 8px 14px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .dropdown-head span {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .dropdown-scroll {
            max-height: 230px;
            overflow-y: auto;
        }

        .dropdown-row {
            display: grid;
            grid-template-columns: 28px 1fr 1fr 90px;
            padding: 9px 14px;
            cursor: pointer;
            transition: background .1s;
            border-bottom: 1px solid #f8fafc;
            align-items: center;
        }

        .dropdown-row:last-child {
            border-bottom: none;
        }

        .dropdown-row:hover,
        .dropdown-row.active {
            background: #eff6ff;
        }

        .dropdown-row span {
            font-size: 12.5px;
            color: #334155;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-right: 8px;
        }

        .dropdown-row .num {
            font-size: 11px;
            color: #cbd5e1;
            font-family: 'DM Mono', monospace;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #f0fdf4;
            color: #16a34a;
            font-size: 11.5px;
            font-weight: 600;
            font-family: 'DM Mono', monospace;
            padding: 3px 8px;
            border-radius: 6px;
        }

        .dropdown-empty {
            padding: 20px;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
        }

        /* ── Selected batch card ── */
        .batch-card {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            display: none;
            gap: 12px;
            flex-direction: column;
        }

        .batch-card.visible {
            display: flex;
        }

        .batch-card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .batch-card-name {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        .batch-card-code {
            font-family: 'DM Mono', monospace;
            font-size: 11px;
            color: #64748b;
            background: #e2e8f0;
            padding: 2px 8px;
            border-radius: 5px;
        }

        .batch-card-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .batch-meta-item {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 10px;
        }

        .batch-meta-item .lbl {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 3px;
        }

        .batch-meta-item .val {
            font-size: 13px;
            color: #1e293b;
            font-weight: 500;
            font-family: 'DM Mono', monospace;
        }

        .batch-meta-item .val.green {
            color: #16a34a;
        }

        /* ── Field group ── */
        .field-group {
            display: grid;
            gap: 12px;
        }

        .field-group.cols-2 {
            grid-template-columns: 1fr 1fr;
        }

        .field-wrap label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 5px;
        }

        .field-input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: 13px;
            font-family: 'DM Sans', sans-serif;
            color: #1e293b;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
            outline: none;
        }

        .field-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .1);
        }

        .field-input[readonly] {
            background: #f8fafc;
            color: #64748b;
            cursor: default;
        }

        /* ── Qty transfer input ── */
        .qty-wrap {
            position: relative;
        }

        .qty-wrap .qty-max {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 11px;
            color: #94a3b8;
            font-family: 'DM Mono', monospace;
            pointer-events: none;
        }

        /* ── Progress bar ── */
        .stock-bar-wrap {
            margin-top: 6px;
        }

        .stock-bar-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 5px;
        }

        .stock-bar-labels .used {
            color: #ef4444;
            font-weight: 600;
        }

        .stock-bar-labels .remain {
            color: #16a34a;
            font-weight: 600;
        }

        .stock-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
        }

        .stock-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #ef4444);
            border-radius: 99px;
            transition: width .3s ease;
            width: 0%;
        }

        /* ── Error hint ── */
        .qty-error {
            font-size: 11px;
            color: #ef4444;
            margin-top: 4px;
            display: none;
        }

        /* ── Buttons ── */
        .btn-row {
            display: flex;
            gap: 10px;
            padding: 20px 28px;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 10px 20px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            border: none;
            transition: opacity .15s, transform .1s;
        }

        .btn:active {
            transform: scale(.97);
        }

        .btn-primary {
            background: #0f172a;
            color: #fff;
        }

        .btn-primary:hover {
            opacity: .85;
        }

        .btn-primary:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .btn-ghost {
            background: #fff;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
        }

        .btn-ghost:hover {
            background: #f1f5f9;
        }

        /* ── Fade-in animation ── */
        @keyframes fadeSlideIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .batch-card.visible {
            animation: fadeSlideIn .2s ease forwards;
        }
    </style>
@endsection

@section('content')
    <section class="transfer-shell">
        <div class="transfer-card">

            {{-- Header --}}
            <div class="transfer-header">
                <div class="transfer-header-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M12 5l7 7-7 7" />
                    </svg>
                </div>
                <div>
                    <h1>Transfer Stok</h1>
                    <span>{{ $code }}</span>
                </div>
            </div>

            {{-- Meta --}}
            <div class="meta-row">

                <div class="meta-cell">
                    <div class="meta-label">Tanggal</div>
                    <input type="text" id="returdate" value="{{ $now }}" readonly
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                </div>
                <div class="meta-cell">
                    <div class="meta-label">Kode Mutasi</div>
                    <input type="text" id="returnumber" value="{{ $code }}" readonly
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 text-gray-500 px-3 py-2 text-[13px] focus:outline-none">
                </div>
            </div>

            <form method="POST" action="{{ route('transfer') }}" id="transferForm">
                @csrf
                <input type="hidden" name="batches_id" id="batches_id">
                <input type="hidden" name="code" id="code_hidden" value="{{ $code }}">
                <input type="hidden" name="status" value="pending">
                <input type="hidden" name="etalases_id" id="etalases_id">

                {{-- Step 1: Pilih Batch --}}
                <div class="section-block">
                    <div class="section-label">Pilih Batch</div>

                    <div class="search-wrapper" id="searchWrapper">
                        <svg class="icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="6.5" cy="6.5" r="4.5" />
                            <line x1="10.5" y1="10.5" x2="14" y2="14" />
                        </svg>
                        <input type="text" id="searchInput" class="search-input"
                            placeholder="Cari nama batch atau nama obat..." oninput="searchBatches(this.value)"
                            autocomplete="off">

                        {{-- Dropdown --}}
                        <div id="searchDropdown" class="search-dropdown">
                            <div class="dropdown-head">
                                <span>#</span>
                                <span>Batch</span>
                                <span>Obat</span>
                                <span>Stok</span>
                            </div>
                            <div class="dropdown-scroll" id="tableScroll" onscroll="handleScroll()">
                                <div id="searchResults"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Selected batch card --}}
                    <div class="batch-card" id="batchCard" style="margin-top:14px;">
                        <div class="batch-card-row">
                            <div class="batch-card-name" id="cardBatchName">—</div>
                            <div class="batch-card-code" id="cardMedCode">—</div>
                        </div>
                        <div class="batch-card-meta">
                            <div class="batch-meta-item">
                                <div class="lbl">Nama Obat</div>
                                <div class="val" id="cardMedName">—</div>
                            </div>
                            <div class="batch-meta-item">
                                <div class="lbl">Satuan</div>
                                <div class="val" id="cardUnit">—</div>
                            </div>
                            <div class="batch-meta-item">
                                <div class="lbl">Stok Tersedia</div>
                                <div class="val green" id="cardStock">—</div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="section-block" id="etalaseSection">
                    <div class="section-label mb-2">Tujuan Etalase</div>

                    <div class="flex items-center gap-2 w-full">
                        <select id="etalaseSelect" onchange="onEtalaseChange(this)" required
                            class="flex-1 min-w-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                            <option value="">— Pilih etalase —</option>
                        </select>
                        <button type="button" onclick="openEtalaseModal('create')" title="Tambah etalase baru"
                            class="flex-shrink-0 flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-gray-400 hover:bg-gray-50 transition-colors">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round">
                                <line x1="12" y1="5" x2="12" y2="19" />
                                <line x1="5" y1="12" x2="19" y2="12" />
                            </svg>
                        </button>
                        <button type="button" id="editEtalaseBtn" onclick="openEtalaseModal('edit')"
                            title="Edit etalase"
                            class="hidden flex-shrink-0 items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-gray-400 hover:bg-gray-50 transition-colors">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                        </button>
                    </div>
                </div>
                {{-- Step 2: Qty Transfer --}}
                <div class="section-block" id="qtySection" style="display:none;">
                    <div class="section-label">Jumlah Transfer</div>

                    <div class="field-group cols-2">
                        <div class="field-wrap">
                            <label>Stok Gudang</label>
                            <input type="text" id="stockDisplay" class="field-input" readonly placeholder="—">
                        </div>
                        <div class="field-wrap">
                            <label>Qty Transfer</label>
                            <div class="qty-wrap">
                                <input type="number" id="qtyInput" name="qty" class="field-input" placeholder="0"
                                    min="1" oninput="handleQtyInput(this)" required>
                                <span class="qty-max" id="qtyMax"></span>
                            </div>
                            <div class="qty-error" id="qtyError">Melebihi stok yang tersedia</div>
                        </div>
                    </div>

                    <div class="stock-bar-wrap" id="stockBarWrap" style="display:none;">
                        <div class="stock-bar-labels">
                            <span>Digunakan: <span class="used" id="barUsed">0</span></span>
                            <span>Sisa: <span class="remain" id="barRemain">0</span></span>
                        </div>
                        <div class="stock-bar">
                            <div class="stock-bar-fill" id="stockBarFill"></div>
                        </div>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="btn-row">
                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                        Simpan Transfer
                    </button>
                    <button type="button" class="btn btn-ghost" onclick="window.location.href='{{ route('home') }}'">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                        Batal
                    </button>
                </div>

            </form>
        </div>
    </section>
    <div id="etalaseModal"
        style="display:none; position:fixed; inset:0; z-index:99999; align-items:center; justify-content:center;">
        <div style="position:absolute; inset:0; background:rgba(0,0,0,.45);" onclick="closeEtalaseModal()"></div>
        <div
            style="position:relative; background:#fff; border-radius:16px; padding:28px; width:100%; max-width:380px; box-shadow:0 20px 50px rgba(0,0,0,.18);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 id="modalTitle" style="font-size:15px; font-weight:600; color:#1e293b; margin:0;">Tambah Etalase</h3>
                <button type="button" onclick="closeEtalaseModal()"
                    style="background:none; border:none; cursor:pointer; color:#94a3b8; padding:2px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <div class="field-wrap" style="margin-bottom:14px;">
                <label>Nama Etalase</label>
                <input type="text" id="etalaseNameInput" class="field-input" placeholder="Contoh: Rak A1">
            </div>


            <div style="display:flex; gap:8px;">
                <button type="button" class="btn btn-primary" id="modalSaveBtn" onclick="saveEtalase()"
                    style="flex:1; justify-content:center;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.5" stroke-linecap="round">
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Simpan
                </button>
                <button type="button" class="btn btn-ghost" onclick="closeEtalaseModal()">Batal</button>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>

    <script>
        // ── State ─────────────────────────────────────────────────────────
        let page = 1;
        let keyword = '';
        let loading = false;
        let hasMore = true;
        let activeIdx = -1;
        let maxStock = 0;
        let selectedBatch = null;

        // ── Search ────────────────────────────────────────────────────────
        function searchBatches(value) {
            keyword = value.trim();
            page = 1;
            hasMore = true;
            activeIdx = -1;

            if (keyword.length < 1) {
                hideDropdown();
                return;
            }

            document.getElementById('searchResults').innerHTML = '';
            fetchData();
        }

        // ── Fetch ─────────────────────────────────────────────────────────
        function fetchData() {
            if (loading || !hasMore) return;
            loading = true;

            fetch(`{{ route('search.getbatches') }}?search=${encodeURIComponent(keyword)}&page=${page}`)
                .then(r => r.json())
                .then(res => {
                    const container = document.getElementById('searchResults');

                    if (page === 1 && (!res.data || res.data.length === 0)) {
                        container.innerHTML = `<div class="dropdown-empty">Tidak ada batch ditemukan</div>`;
                        hasMore = false;
                        showDropdown();
                        return;
                    }

                    res.data.forEach((item, i) => {
                        const row = document.createElement('div');
                        console.log(item);
                        row.className = 'dropdown-row';
                        row.dataset.batchesId = item.id ?? '';
                        row.dataset.batchName = item.batches_name ?? '';
                        row.dataset.medName = item.name ?? '—';
                        row.dataset.medCode = item.medicine_code ?? '—';
                        row.dataset.unit = item.unit ?? '—';
                        row.dataset.stock = item.stock ?? 0;

                        row.innerHTML = `
                            <span class="num">${((page - 1) * (res.per_page || 10)) + i + 1}</span>
                            <span>${item.batches_name ?? '—'}</span>
                            <span>${item.name ?? '—'}</span>
                            <span><span class="stock-badge">${item.stock ?? 0}</span></span>
                        `;
                        row.addEventListener('click', () => selectBatch(row));
                        container.appendChild(row);
                    });

                    hasMore = res.current_page < res.last_page;
                    page++;
                    showDropdown();
                })
                .catch(() => {
                    iziToast.error({
                        title: 'Error',
                        message: 'Gagal memuat data batch.'
                    });
                })
                .finally(() => {
                    loading = false;
                });
        }

        // ── Select batch ──────────────────────────────────────────────────
        function selectBatch(row) {
            console.log('SELECTED:', row.dataset);
            selectedBatch = {
                batches_id: row.dataset.batchesId,
                batchName: row.dataset.batchName,
                medName: row.dataset.medName,
                medCode: row.dataset.medCode,
                unit: row.dataset.unit,
                stock: parseInt(row.dataset.stock) || 0,
            };

            // Fill hidden + search input
            document.getElementById('batches_id').value = selectedBatch.batches_id;
            document.getElementById('searchInput').value = selectedBatch.batchName;
            hideDropdown();

            // Fill card
            document.getElementById('cardBatchName').textContent = selectedBatch.batchName;
            document.getElementById('cardMedCode').textContent = selectedBatch.medCode;
            document.getElementById('cardMedName').textContent = selectedBatch.medName;
            document.getElementById('cardUnit').textContent = selectedBatch.unit;
            document.getElementById('cardStock').textContent = selectedBatch.stock;
            document.getElementById('batchCard').classList.add('visible');

            // Show qty section
            maxStock = selectedBatch.stock;
            document.getElementById('stockDisplay').value = maxStock;
            document.getElementById('qtyMax').textContent = `/ ${maxStock}`;
            document.getElementById('qtyInput').max = maxStock;
            document.getElementById('qtyInput').value = '';
            document.getElementById('stockBarWrap').style.display = 'none';
            document.getElementById('qtySection').style.display = 'block';
            document.getElementById('qtyError').style.display = 'none';
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('etalaseSelect').focus();
            // Reset bar
            updateBar(0);
        }

        // ── Qty handler ───────────────────────────────────────────────────
        function handleQtyInput(input) {
            const val = parseInt(input.value) || 0;
            const err = document.getElementById('qtyError');

            if (val > maxStock) {
                input.value = maxStock;
                updateBar(maxStock);
                err.style.display = 'block';
                setTimeout(() => {
                    err.style.display = 'none';
                }, 2000);
            } else {
                err.style.display = 'none';
                updateBar(val);
            }

            const valid = val > 0 && val <= maxStock && selectedBatch !== null;
            document.getElementById('submitBtn').disabled = !valid;
        }

        function updateBar(used) {
            if (maxStock <= 0) return;
            const pct = Math.min((used / maxStock) * 100, 100);
            const remain = Math.max(maxStock - used, 0);

            document.getElementById('stockBarFill').style.width = pct + '%';
            document.getElementById('barUsed').textContent = used;
            document.getElementById('barRemain').textContent = remain;
            document.getElementById('stockBarWrap').style.display = used > 0 ? 'block' : 'none';
        }

        // ── Dropdown helpers ──────────────────────────────────────────────
        function showDropdown() {
            document.getElementById('searchDropdown').style.display = 'block';
        }

        function hideDropdown() {
            document.getElementById('searchDropdown').style.display = 'none';
        }

        function handleScroll() {
            const el = document.getElementById('tableScroll');
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 5) fetchData();
        }

        // ── Keyboard nav ──────────────────────────────────────────────────
        $(document).ready(function() {
            document.getElementById('searchInput').addEventListener('keydown', function(e) {
                const rows = document.querySelectorAll('#searchResults .dropdown-row');
                const dropdown = document.getElementById('searchDropdown');
                if (dropdown.style.display === 'none' || !rows.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIdx = Math.min(activeIdx + 1, rows.length - 1);
                    updateActive(rows);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIdx = Math.max(activeIdx - 1, 0);
                    updateActive(rows);
                } else if (e.key === 'Enter' && activeIdx >= 0) {
                    e.preventDefault();
                    selectBatch(rows[activeIdx]);
                } else if (e.key === 'Escape') {
                    hideDropdown();
                }
            });

            function updateActive(rows) {
                rows.forEach(r => r.classList.remove('active'));
                if (activeIdx >= 0) {
                    rows[activeIdx].classList.add('active');
                    rows[activeIdx].scrollIntoView({
                        block: 'nearest'
                    });
                }
            }

            // Outside click
            document.addEventListener('click', function(e) {
                const inp = document.getElementById('searchInput');
                const drop = document.getElementById('searchDropdown');
                if (!inp.contains(e.target) && !drop.contains(e.target)) hideDropdown();
            });

            // Form submit guard
            document.getElementById('transferForm').addEventListener('submit', function(e) {
                const qty = parseInt(document.getElementById('qtyInput').value) || 0;
                if (!selectedBatch || qty <= 0 || qty > maxStock) {
                    e.preventDefault();
                    iziToast.warning({
                        title: 'Perhatian',
                        message: 'Periksa kembali data transfer.'
                    });
                }
            });
        });
        // ── Etalase ───────────────────────────────────────────────────────
        let etalaseMode = 'create';
        let editingEtalaseId = null;

        function loadEtalases(selectId = null) {
            axios.get('{{ route('etalases.index') }}')
                .then(res => {
                    const select = document.getElementById('etalaseSelect');
                    const current = selectId ?? select.value;
                    select.innerHTML = '<option value="">— Pilih etalase —</option>';
                    (res.data ?? []).forEach(e => {
                        const opt = document.createElement('option');
                        opt.value = e.id;
                        opt.textContent = e.name;
                        if (String(e.id) === String(current)) opt.selected = true;
                        select.appendChild(opt);
                    });
                    onEtalaseChange(select);
                })
                .catch(() => iziToast.error({
                    title: 'Error',
                    message: 'Gagal memuat etalase.'
                }));
        }

        function onEtalaseChange(select) {
            const val = select.value;
            document.getElementById('etalases_id').value = val;
            document.getElementById('editEtalaseBtn').style.display = val ? 'inline-flex' : 'none';
            checkSubmit();
            if (select.value) {
                const qty = document.getElementById('qtyInput');
                qty.focus();
            }
        }

        function openEtalaseModal(mode) {
            etalaseMode = mode;
            document.getElementById('etalaseNameInput').value = '';
            editingEtalaseId = null;

            if (mode === 'edit') {
                const select = document.getElementById('etalaseSelect');
                const selectedOpt = select.options[select.selectedIndex];
                editingEtalaseId = select.value;
                document.getElementById('modalTitle').textContent = 'Edit Etalase';
                // Optionally pre-fill name from option text
                document.getElementById('etalaseNameInput').value = selectedOpt?.textContent ?? '';
            } else {
                document.getElementById('modalTitle').textContent = 'Tambah Etalase';
            }

            const modal = document.getElementById('etalaseModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                const handleEnter = (e) => {
                    if (e.key === 'Enter') saveEtalase();
                };
                document.getElementById('etalaseNameInput').onkeydown = handleEnter;
                document.getElementById('etalaseNameInput').focus();
            }, 50);
        }

        function closeEtalaseModal() {
            document.getElementById('etalaseModal').style.display = 'none';
            document.getElementById('etalaseNameInput').onkeydown = null;

        }

        function saveEtalase() {
            const name = document.getElementById('etalaseNameInput').value.trim();

            if (!name) {
                iziToast.warning({
                    message: 'Nama etalase wajib diisi.'
                });
                document.getElementById('etalaseNameInput').focus();
                return;
            }

            const btn = document.getElementById('modalSaveBtn');
            btn.disabled = true;

            const isEdit = etalaseMode === 'edit';
            const url = isEdit ?
                `{{ url('etalases') }}/${editingEtalaseId}` :
                '{{ route('etalases.store') }}';
            const method = isEdit ? 'put' : 'post';

            axios[method](url, {
                    name,
                    _token: '{{ csrf_token() }}'
                })
                .then(res => {
                    const saved = res.data;
                    closeEtalaseModal();
                    loadEtalases(saved.id);
                    iziToast.success({
                        message: isEdit ? 'Etalase diperbarui.' : 'Etalase ditambahkan.'
                    });
                })
                .catch(err => {
                    const msg = err.response?.data?.message ?? 'Gagal menyimpan etalase.';
                    iziToast.error({
                        title: 'Error',
                        message: msg
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                });
        }

        loadEtalases();

        function checkSubmit() {
            const qty = parseInt(document.getElementById('qtyInput')?.value) || 0;
            const etId = document.getElementById('etalases_id').value;
            const valid = selectedBatch !== null && qty > 0 && qty <= maxStock && etId !== '';
            document.getElementById('submitBtn').disabled = !valid;
        }
    </script>
@endsection
