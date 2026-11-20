@extends('layouts.app')

@section('title', 'Transfer Stok')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap"
        rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .dropdown-row.active {
            background-color: #f0f9ff;
        }
    </style>
@endsection

@section('content')
    <div class="pb-6 md:pb-4 px-3 sm:px-6">
        <div
            class="flex flex-col my-2 gap-4 p-4 bg-white border border-slate-200/80 rounded-xl shadow-xs md:flex-row md:items-center md:justify-between">

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
                    <h2 class="text-base font-bold text-slate-800 leading-tight">Tambah Mutasi</h2>
                    <p class="text-xs text-slate-400">Transfer Stok Obat</p>
                </div>

            </div>


            {{-- RIGHT: GRUP TOMBOL AKSI (RESPONSIF) --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">

                <div class="grid grid-cols-2 sm:flex sm:items-center gap-2">
                    <div
                        class="bg-[#2563eb] flex-wrap border border-slate-200/80 px-3 py-1.5 rounded-lg flex items-center justify-start md:justify-center gap-0 md:gap-2">
                        <span class="text-[8px] md-text-[11px] font-semibold text-white uppercase tracking-wider">Kode
                            Mutasi</span>
                        <span class="text-xs font-bold text-[#ffff00] truncate">{{ $code }}</span>
                    </div>
                    <div
                        class="bg-[#2563eb] flex-wrap border border-slate-200/80 px-3 py-1.5 rounded-lg flex items-center justify-start md:justify-center gap-0 md:gap-2">
                        <span
                            class="text-[8px] md-text-[11px] font-semibold text-[#fff] uppercase tracking-wider">Tanggal</span>
                        <span class="text-xs font-semibold text-[#ffff00] truncate">{{ $now }}</span>
                    </div>
                </div>
                <a href="{{ route('transfers.incoming') }}" class="w-full sm:w-auto">
                    <button type="button"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-[#5d4c11] bg-[#ffe277] hover:bg-#ffa800 rounded-lg shadow-[0_0_8px_rgba(5px_0_8px_rgb(160 135 11 / 55%))] hover:shadow-[0_0_12px_rgba(37,99,235,0.4)] transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#5d4c11]" viewBox="0 0 24 24"
                            fill="none" stroke="currentcolor" stroke-width="1.75" stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M7 21v-6" />
                            <path d="M20 6l-3 -3l-3 3" />
                            <path d="M17 3v18" />
                            <path d="M10 18l-3 3l-3 -3" />
                            <path d="M7 3v2" />
                            <path d="M7 9v2" />
                        </svg>
                        <span>Lihat Mutasi</span>
                    </button>
                </a>
                <a href="{{ route('transfers.requests.create') }}" class="w-full sm:w-auto">
                    <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-100 transition">
                        <span>Buat Permintaan Mutasi</span>
                    </button>
                </a>

            </div>
        </div>
    </div>
    <div class="text-slate-800 px-3 sm:px-6">




        <!-- Hidden Inputs for JS compatibility -->
        <input type="hidden" id="returdate" value="{{ $now }}">
        <input type="hidden" id="returnumber" value="{{ $code }}">
        <input type="hidden" id="code_hidden" value="{{ $code }}">

        <form id="transferForm" class="space-y-6">

            <!-- Destination Pharmacy Card -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <div
                        class="w-6 h-6 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-xs">
                        1</div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Tujuan Apotek</h2>
                </div>
                <div class="w-full">
                    <select id="pharmacySelect" onchange="onPharmacyChange()"
                        class="w-full rounded-lg font-nunito-bold border border-slate-200 bg-slate-50/50 px-3.5 py-3 sm:py-2.5 text-base sm:text-sm text-slate-800 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-500/10 outline-none transition">
                        @if($pharmacies->count() > 1)
                            <option value="">— Pilih Tujuan Apotek —</option>
                        @endif
                        @foreach ($pharmacies as $pharmacy)
                            <option value="{{ $pharmacy->id }}" {{ $pharmacies->count() === 1 ? 'selected' : '' }}>{{ $pharmacy->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Pilihan Lokasi Tujuan PMI (tampil otomatis jika tujuan SAHABAT PMI / ID 1) --}}
                <div id="pmiLocationContainer" class="hidden mt-4 pt-4 border-t border-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <label for="headerLocationSelect" class="block text-xs font-semibold text-slate-700">
                            Lokasi Tujuan di PMI <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[11px] text-slate-400">Otomatis dipakai item baru &amp; dapat diubah per item di tabel bawah</span>
                    </div>
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                        <div class="flex-1 min-w-0">
                            <select id="headerLocationSelect" onchange="onHeaderLocationChange()"
                                data-enter-next="#searchInput"
                                title="Pilih lokasi tujuan PMI"
                                class="transfer-create-location-select w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-sm text-slate-800 outline-none focus:border-sky-500 focus:bg-white transition">
                                <option value="">— Pilih Lokasi PMI —</option>
                                @foreach ($transferLocations ?? [] as $loc)
                                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" id="applyHeaderLocationAllBtn" onclick="applyHeaderLocationToAllCartItems()"
                                class="hidden px-3 py-2.5 text-xs font-semibold text-sky-700 bg-sky-50 hover:bg-sky-100 border border-sky-200 rounded-lg transition whitespace-nowrap">
                                Terapkan ke Semua Item
                            </button>
                            <button type="button" onclick="window.openManageLocationModal(document.getElementById('headerLocationSelect'))"
                                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg transition whitespace-nowrap">
                                <span>+ / Kelola Lokasi</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Item Search Card -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-4">
                    <div
                        class="w-6 h-6 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-xs">
                        2</div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Pilih Item Obat</h2>
                </div>

                <!-- Search Input -->
                <div class="relative w-full" id="searchWrapper">
                    <div class="relative">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"
                            viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="6.5" cy="6.5" r="4.5" />
                            <line x1="10.5" y1="10.5" x2="14" y2="14" />
                        </svg>
                        <input type="text" id="searchInput"
                            class="w-full pl-10 pr-4 py-3 sm:py-2.5 bg-slate-50/50 border border-slate-200 rounded-lg text-base sm:text-sm text-slate-800 placeholder-slate-400 focus:border-sky-500 focus:bg-white focus:ring-4 focus:ring-sky-500/10 outline-none transition"
                            placeholder="Cari nama batch atau nama obat..." oninput="searchBatches(this.value)"
                            autocomplete="off">
                    </div>

                    <!-- Search Dropdown -->
                    <div id="searchDropdown"
                        class="hidden absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden w-full">
                        <div class="max-h-72 overflow-y-auto divide-y divide-slate-50" id="tableScroll"
                            onscroll="handleScroll()">
                            <div id="searchResults"></div>
                        </div>
                    </div>
                </div>

                <!-- Staged Batch Card -->
                <div id="stagingCard" class="hidden mt-4 p-4 rounded-xl bg-sky-50/40 border border-sky-200/60 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-sky-600 tracking-wider">Batch Terpilih</span>
                            <div class="font-semibold text-sm text-slate-900" id="stageBatchName">—</div>
                        </div>
                        <span
                            class="font-mono text-xs px-2.5 py-1 rounded bg-white border border-sky-200 text-sky-800 font-medium"
                            id="stageMedCode">—</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200/60">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Nama Obat</div>
                            <div class="text-xs font-nunito-bold mt-0.5 truncate" id="stageMedName">—</div>
                        </div>
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200/60">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Satuan</div>
                            <div class="text-xs font-nunito-bold text-slate-800 mt-0.5" id="stageUnit">—</div>
                        </div>
                        <div class="bg-white p-2.5 rounded-lg border border-slate-200/60">
                            <div class="text-[10px] font-bold uppercase text-slate-400">Stok</div>
                            <div class="text-xs font-nunito-bold text-emerald-600 mt-0.5" id="stageStock">—</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Qty
                            Transfer</label>
                        <div class="relative">
                            <input type="number" id="stageQty"
                                class="w-full px-3 py-3 sm:py-2 bg-white border border-slate-200 rounded-lg text-base sm:text-sm font-mono text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/10"
                                placeholder="0" min="1" oninput="handleStageQtyInput(this)">
                            <span
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-mono text-slate-400 pointer-events-none"
                                id="stageQtyMax"></span>
                        </div>
                        <div class="text-xs text-rose-500 mt-1 hidden" id="stageQtyError">Melebihi stok yang tersedia
                        </div>
                    </div>

                    <div id="stageLocationContainer" class="hidden">
                        <label for="stageLocationSelect" class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">
                            Lokasi PMI <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <select id="stageLocationSelect" title="Pilih lokasi tujuan PMI untuk item ini"
                                    data-enter-next="add-item"
                                    class="transfer-stage-location-select w-full px-3 py-2.5 bg-white border border-slate-200 rounded-lg text-sm text-slate-800 outline-none focus:border-sky-500">
                                    <option value="">— Pilih Lokasi PMI —</option>
                                    @foreach ($transferLocations ?? [] as $loc)
                                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="button" onclick="window.openManageLocationModal(document.getElementById('stageLocationSelect'))"
                                class="px-3 py-2.5 bg-white hover:bg-indigo-50 border border-slate-200 hover:border-indigo-200 text-indigo-700 font-semibold text-xs rounded-lg transition whitespace-nowrap">
                                + / Kelola
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-stretch gap-2 pt-1">
                        <button type="button" id="addItemBtn" onclick="addItemToCart()" disabled
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 disabled:text-slate-400 text-white font-medium text-xs rounded-lg transition shadow-sm">
                            + Tambah ke Daftar
                        </button>
                        <button type="button" onclick="clearStaging()"
                            class="w-full sm:w-auto px-4 py-2.5 bg-white hover:bg-slate-100 border border-slate-200 text-slate-600 font-medium text-xs rounded-lg transition">
                            Batal
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transfer Item List Table Card -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-6 h-6 rounded-md bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-xs">
                            3</div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Daftar Item Transfer</h2>
                    </div>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-600 font-mono">
                        <span id="cartCount">0</span>&nbsp;Items
                    </span>
                </div>

                <div class="overflow-x-auto border border-slate-200/80 rounded-lg -mx-1">
                    <table class="w-full text-left text-xs min-w-[600px]">
                        <thead
                            class="bg-slate-50 border-b border-slate-200/80 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <tr>
                                <th class="py-3 px-4">Batch</th>
                                <th class="py-3 px-4">Obat</th>
                                <th class="py-3 px-4">Satuan</th>
                                <th class="py-3 px-4">Stok</th>
                                <th id="cartLocationTh" class="py-3 px-4 hidden">Lokasi PMI</th>
                                <th class="py-3 px-4 w-28">Qty</th>
                                <th class="py-3 px-4 w-12 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody" class="divide-y divide-slate-100 text-slate-700">
                            <tr id="cartEmptyRow">
                                <td id="cartEmptyTd" colspan="6" class="py-10 text-center text-slate-400 text-xs">Belum ada item
                                    ditambahkan</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Submit Controls -->
                <div
                    class="flex flex-col sm:flex-row items-stretch sm:justify-end gap-3 mt-6 pt-4 border-t border-slate-100">
                    <button type="button" onclick="window.location.href='{{ route('home') }}'"
                        class="w-full sm:w-auto px-4 py-2.5 bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium text-xs rounded-lg transition">
                        Batal
                    </button>
                    <button type="button" id="submitBtn" onclick="submitTransfer()" disabled
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 disabled:bg-slate-200 disabled:text-slate-400 text-white font-semibold text-xs rounded-lg shadow-sm transition">
                        Simpan Transfer
                    </button>
                </div>
            </div>

        </form>
    </div>

    <!-- Modal Etalase -->
    <div id="etalaseModal" class="hidden fixed inset-0 z-50 items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/30 backdrop-blur-sm" onclick="closeEtalaseModal()"></div>
        <div class="relative w-full max-w-sm bg-white rounded-xl p-6 shadow-xl border border-slate-100 z-10">
            <div class="flex items-center justify-between mb-4">
                <h3 id="modalTitle" class="text-sm font-semibold text-slate-900">Tambah Etalase</h3>
                <button type="button" onclick="closeEtalaseModal()" class="text-slate-400 hover:text-slate-600">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>

            <div class="mb-5">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Nama
                    Etalase</label>
                <input type="text" id="etalaseNameInput"
                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 outline-none focus:border-sky-500 focus:bg-white focus:ring-2 focus:ring-sky-500/10"
                    placeholder="Contoh: Rak A1">
            </div>

            <div class="flex items-center justify-end gap-2">
                <button type="button" onclick="closeEtalaseModal()"
                    class="px-4 py-2 bg-slate-100 text-slate-600 font-medium text-xs rounded-lg hover:bg-slate-200">
                    Batal
                </button>
                <button type="button" id="modalSaveBtn" onclick="saveEtalase()"
                    class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-medium text-xs rounded-lg shadow-sm">
                    Simpan
                </button>
            </div>
        </div>
    </div>
    @include('kasir.transfers.partials.pin_modal')
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
        let stagedBatch = null; // batch just picked, not yet added to cart
        const CART_KEY = 'transfer_cart_{{ auth()->id() ?? 0 }}_{{ getActivePharmacyId() ?? 0 }}';
        const SUBMISSION_KEY = `${CART_KEY}_submission_key`;
        const createSubmissionKey = () => window.crypto?.randomUUID?.()
            ?? `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;
        let submissionKey = localStorage.getItem(SUBMISSION_KEY) || createSubmissionKey();
        localStorage.setItem(SUBMISSION_KEY, submissionKey);
        let transferSubmitting = false;
        let cartItems = JSON.parse(localStorage.getItem(CART_KEY)) ||
        []; // {batches_id, batchName, medName, unit, stock, qty, etalases_id, etalasesName, transfer_location_id, transferLocationName, source_type}
        let etalaseList = []; // {id, name}
        let locationList = @json($transferLocations ?? []); // {id, name}
        let lastHeaderLocationId = '';

        function isPmiDestination() {
            return String(document.getElementById('pharmacySelect')?.value || '') === '1';
        }

        function findLocationNameById(locId) {
            const found = locationList.find(l => String(l.id) === String(locId));
            return found ? found.name : '';
        }

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

        function fetchData() {
            if (loading || !hasMore) return;
            loading = true;

            fetch(`{{ route('search.getbatches') }}?search=${encodeURIComponent(keyword)}&page=${page}`)
                .then(r => r.json())
                .then(res => {
                    const container = document.getElementById('searchResults');

                    if (page === 1 && (!res.data || res.data.length === 0)) {
                        container.innerHTML =
                            `<div class="p-4 text-center text-xs text-slate-400">Tidak ada batch ditemukan</div>`;
                        hasMore = false;
                        showDropdown();
                        return;
                    }

                    res.data.forEach((item, i) => {
                        const srcType = item.source_type ?? 'gudang';
                        if (cartItems.some(c => String(c.batches_id) === String(item.id) && c.source_type === srcType)) return;

                        const row = document.createElement('div');
                        row.className =
                            'dropdown-row px-4 py-3 text-xs cursor-pointer hover:bg-sky-50 transition border-b border-slate-50';
                        row.dataset.batchesId = item.id ?? '';
                        row.dataset.batchName = item.batches_name ?? '';
                        row.dataset.medName = item.name ?? '—';
                        row.dataset.medCode = item.medicine_code ?? '—';
                        row.dataset.unit = item.unit ?? '—';
                        row.dataset.stock = item.stock ?? 0;
                        row.dataset.expiredDate = item.expired_date ?? '-';
                        row.dataset.sourceType = srcType;

                        const isWarehouse = {{ isWarehousePharmacy() ? 'true' : 'false' }};
                        const badgeColor = srcType === 'gudang'
                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                            : 'bg-violet-50 text-violet-700 border-violet-200';
                        const badgeLabel = srcType === 'gudang' ? 'Gudang ➝ Pelayanan' : 'Pelayanan ➝ Cabang / Gudang';

                        row.innerHTML = `
                            <div class="flex items-center gap-1.5 min-w-0">
                                <span class="font-nunito-bold text-[#010741] text-[13px] leading-snug truncate">${item.name ?? '—'}</span>
                                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 text-xs font-bold">${item.stock ?? 0}</span>
                            </div>
                            <div class="text-slate-500 text-[11px] mt-1 truncate">${item.batches_name ?? '—'} - ${item.expired_date ?? '—'}</div>
                            <div class="mt-1.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border whitespace-nowrap ${badgeColor}">${badgeLabel}</span>
                            </div>
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

        // ── Stage a picked batch ──────────────────────────────────────────
        function selectBatch(row) {
            stagedBatch = {
                batches_id: row.dataset.batchesId,
                batchName: row.dataset.batchName,
                medName: row.dataset.medName,
                medCode: row.dataset.medCode,
                unit: row.dataset.unit,
                stock: parseInt(row.dataset.stock) || 0,
                source_type: row.dataset.sourceType || 'pelayanan',
            };

            const isWarehouse = {{ isWarehousePharmacy() ? 'true' : 'false' }};
            const displayLabel = stagedBatch.source_type === 'gudang' ? 'Gudang ➝ Pelayanan' : 'Pelayanan ➝ Cabang / Gudang';
            document.getElementById('searchInput').value = stagedBatch.medName + ' (' + displayLabel + ')';
            hideDropdown();

            document.getElementById('stageBatchName').textContent = stagedBatch.batchName;
            document.getElementById('stageMedCode').textContent = stagedBatch.medCode;
            document.getElementById('stageMedName').textContent = stagedBatch.medName;
            document.getElementById('stageUnit').textContent = stagedBatch.unit;
            document.getElementById('stageStock').textContent = stagedBatch.stock;

            document.getElementById('stageQty').value = '';
            document.getElementById('stageQty').max = stagedBatch.stock;
            document.getElementById('stageQtyMax').textContent = `/ ${stagedBatch.stock}`;
            document.getElementById('stageQtyError').classList.add('hidden');

            const stagingCard = document.getElementById('stagingCard');
            stagingCard.classList.remove('hidden');

            const stageLocContainer = document.getElementById('stageLocationContainer');
            const stageLocSelect = document.getElementById('stageLocationSelect');
            if (stageLocContainer && stageLocSelect) {
                if (isPmiDestination()) {
                    stageLocContainer.classList.remove('hidden');
                    const headerLocId = document.getElementById('headerLocationSelect')?.value || '';
                    if (headerLocId) {
                        stageLocSelect.value = headerLocId;
                    }
                    window.syncEtalaseTriggerLabel?.(stageLocSelect);
                } else {
                    stageLocContainer.classList.add('hidden');
                }
            }

            document.getElementById('addItemBtn').disabled = true;
            document.getElementById('stageQty').focus();
        }

        function handleStageQtyInput(input) {
            const val = parseInt(input.value) || 0;
            const err = document.getElementById('stageQtyError');

            if (stagedBatch && val > stagedBatch.stock) {
                input.value = stagedBatch.stock;
                err.classList.remove('hidden');
                setTimeout(() => {
                    err.classList.add('hidden');
                }, 2000);
            } else {
                err.classList.add('hidden');
            }

            checkAddItemBtn();
        }

        function checkAddItemBtn() {
            const qty = parseInt(document.getElementById('stageQty').value) || 0;
            const valid = stagedBatch !== null && qty > 0 && qty <= stagedBatch.stock;
            document.getElementById('addItemBtn').disabled = !valid;
        }

        function clearStaging() {
            stagedBatch = null;
            document.getElementById('stagingCard').classList.add('hidden');
            document.getElementById('searchInput').value = '';
        }

        // ── Cart ──────────────────────────────────────────────────────────
        function saveCart() {
            localStorage.setItem(CART_KEY, JSON.stringify(cartItems));
            submissionKey = createSubmissionKey();
            localStorage.setItem(SUBMISSION_KEY, submissionKey);
        }

        function addItemToCart() {
            const qty = parseInt(document.getElementById('stageQty').value) || 0;

            if (!stagedBatch || qty <= 0 || qty > stagedBatch.stock) {
                iziToast.warning({
                    message: 'Periksa kembali qty transfer.'
                });
                return;
            }

            const pmiDest = isPmiDestination();
            const stageLocEl = document.getElementById('stageLocationSelect');
            const headerLocEl = document.getElementById('headerLocationSelect');
            const selectedLocId = pmiDest
                ? String((stageLocEl?.value || headerLocEl?.value || '')).trim()
                : '';

            if (pmiDest && !selectedLocId) {
                if (typeof window.highlightInvalidField === 'function') {
                    window.highlightInvalidField(stageLocEl || headerLocEl, 'Pilih lokasi tujuan PMI terlebih dahulu.');
                } else {
                    iziToast.warning({ message: 'Pilih lokasi tujuan PMI terlebih dahulu.' });
                }
                return;
            }

            const selectedLocName = selectedLocId ? findLocationNameById(selectedLocId) : '';
            const existingIdx = cartItems.findIndex(c =>
                String(c.batches_id) === String(stagedBatch.batches_id)
                && (c.source_type || 'gudang') === (stagedBatch.source_type || 'gudang')
                && (!pmiDest || String(c.transfer_location_id || '') === selectedLocId)
            );
            if (existingIdx !== -1) {
                const combinedQty = cartItems[existingIdx].qty + qty;
                if (combinedQty > stagedBatch.stock) {
                    iziToast.warning({
                        message: `Total kuantiti (${combinedQty}) melebihi stok tersedia (${stagedBatch.stock}).`
                    });
                    return;
                }
                cartItems[existingIdx].qty = combinedQty;
            } else {
                cartItems.push({
                    batches_id: stagedBatch.batches_id,
                    batchName: stagedBatch.batchName,
                    medName: stagedBatch.medName,
                    unit: stagedBatch.unit,
                    stock: stagedBatch.stock,
                    qty: qty,
                    transfer_location_id: selectedLocId || null,
                    transferLocationName: selectedLocName || '',
                    source_type: stagedBatch.source_type || 'gudang',
                });
            }

            saveCart();
            clearStaging();
            renderCart();
            checkSubmit();

            // Refocus search input for next item
            document.getElementById('searchInput').focus();
        }

        function renderCart() {
            const body = document.getElementById('cartBody');
            const pmiDest = isPmiDestination();
            const locTh = document.getElementById('cartLocationTh');
            if (locTh) locTh.classList.toggle('hidden', !pmiDest);

            document.getElementById('cartCount').textContent = cartItems.length;

            if (cartItems.length === 0) {
                body.innerHTML =
                    `<tr id="cartEmptyRow"><td colspan="${pmiDest ? 7 : 6}" class="py-10 text-center text-slate-400 text-xs">Belum ada item ditambahkan</td></tr>`;
                syncApplyAllLocationButton();
                return;
            }

            body.innerHTML = '';
            cartItems.forEach((it, idx) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition';
                const srcBadgeColor = it.source_type === 'gudang'
                    ? 'bg-amber-50 text-amber-700 border-amber-200'
                    : 'bg-violet-50 text-violet-700 border-violet-200';
                const srcBadgeLabel = it.source_type === 'gudang' ? 'Gudang ➝ Pelayanan' : 'Pelayanan ➝ Cabang / Gudang';
                tr.innerHTML = `
                    <td class="py-3 px-4 font-semibold text-slate-800">
                        ${it.batchName}
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold border ${srcBadgeColor}">${srcBadgeLabel}</span>
                    </td>
                    <td class="py-3 px-4 text-slate-600">${it.medName}</td>
                    <td class="py-3 px-4 text-slate-600">${it.unit}</td>
                    <td class="py-3 px-4"><span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-[11px] font-semibold">${it.stock}</span></td>
                `;

                if (pmiDest) {
                    const locTd = document.createElement('td');
                    locTd.className = 'py-3 px-4 min-w-[200px]';
                    const wrap = document.createElement('div');
                    wrap.className = 'flex items-center gap-1.5';

                    const sel = document.createElement('select');
                    sel.className = 'transfer-cart-location-select w-full rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-700 outline-none focus:border-sky-500';
                    sel.dataset.idx = String(idx);
                    sel.dataset.enterNext = 'row-qty';
                    sel.title = 'Pilih lokasi PMI untuk item ini';

                    const emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.textContent = '— Pilih Lokasi PMI —';
                    sel.appendChild(emptyOpt);

                    locationList.forEach(loc => {
                        const opt = document.createElement('option');
                        opt.value = String(loc.id);
                        opt.textContent = loc.name;
                        if (String(it.transfer_location_id || '') === String(loc.id)) {
                            opt.selected = true;
                        }
                        sel.appendChild(opt);
                    });

                    sel.addEventListener('change', function () {
                        updateCartLocation(this);
                    });

                    const manageBtn = document.createElement('button');
                    manageBtn.type = 'button';
                    manageBtn.title = 'Tambah / ubah / hapus lokasi PMI';
                    manageBtn.textContent = '+';
                    manageBtn.className = 'px-2 py-1.5 text-xs font-bold bg-white border border-slate-200 hover:bg-indigo-50 hover:border-indigo-200 text-indigo-700 rounded-lg transition shrink-0';
                    manageBtn.onclick = () => window.openManageLocationModal?.(sel);

                    wrap.append(sel, manageBtn);
                    locTd.appendChild(wrap);
                    tr.appendChild(locTd);
                }

                const qtyTd = document.createElement('td');
                qtyTd.className = 'py-3 px-4';
                qtyTd.innerHTML = `<input type="number" class="cart-qty-input w-20 px-2 py-1 bg-white border border-slate-200 rounded text-xs font-mono text-slate-800 outline-none focus:border-sky-500" min="1" max="${it.stock}" value="${it.qty}" data-idx="${idx}" onchange="updateCartQty(this)">`;

                const delTd = document.createElement('td');
                delTd.className = 'py-3 px-4 text-center';
                delTd.innerHTML = `
                    <button type="button" class="text-slate-400 hover:text-rose-500 transition p-1" onclick="removeCartItem(${idx})" title="Hapus">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg>
                    </button>`;

                tr.append(qtyTd, delTd);
                body.appendChild(tr);
            });

            window.initEtalaseSearchableSelects?.(body);
            syncApplyAllLocationButton();
        }

        function updateCartQty(input) {
            const idx = parseInt(input.dataset.idx);
            const item = cartItems[idx];
            let val = parseInt(input.value) || 0;

            if (val > item.stock) {
                val = item.stock;
                iziToast.warning({
                    message: `Qty melebihi stok tersedia (${item.stock}).`
                });
            }
            if (val < 1) val = 1;

            input.value = val;
            item.qty = val;
            saveCart();
            checkSubmit();
        }

        function updateCartEtalase(select) {
            const idx = parseInt(select.dataset.idx);
            cartItems[idx].etalases_id = select.value;
            cartItems[idx].etalasesName = select.options[select.selectedIndex]?.textContent ?? '—';
            saveCart();
            checkSubmit();
        }

        function updateCartLocation(select) {
            const idx = parseInt(select.dataset.idx);
            if (!cartItems[idx]) return;
            cartItems[idx].transfer_location_id = select.value || null;
            cartItems[idx].transferLocationName = select.value ? (select.options[select.selectedIndex]?.textContent ?? '') : '';
            saveCart();
            syncApplyAllLocationButton();
            checkSubmit();
        }

        function syncApplyAllLocationButton() {
            const btn = document.getElementById('applyHeaderLocationAllBtn');
            if (!btn) return;
            const headerLocId = String(document.getElementById('headerLocationSelect')?.value || '');
            const shouldShow = isPmiDestination()
                && headerLocId !== ''
                && cartItems.length > 0
                && cartItems.some(it => String(it.transfer_location_id || '') !== headerLocId);
            btn.classList.toggle('hidden', !shouldShow);
        }

        function onHeaderLocationChange() {
            const headerLocId = String(document.getElementById('headerLocationSelect')?.value || '');
            const headerLocName = headerLocId ? findLocationNameById(headerLocId) : '';

            const stageLoc = document.getElementById('stageLocationSelect');
            if (stageLoc && headerLocId) {
                stageLoc.value = headerLocId;
                window.syncEtalaseTriggerLabel?.(stageLoc);
            }

            if (headerLocId && cartItems.length > 0) {
                let changed = false;
                cartItems.forEach(it => {
                    const curr = String(it.transfer_location_id || '');
                    if (!curr || (lastHeaderLocationId && curr === lastHeaderLocationId)) {
                        it.transfer_location_id = headerLocId;
                        it.transferLocationName = headerLocName;
                        changed = true;
                    }
                });
                if (changed) {
                    saveCart();
                    renderCart();
                } else {
                    syncApplyAllLocationButton();
                }
            } else {
                syncApplyAllLocationButton();
            }

            lastHeaderLocationId = headerLocId;
            checkSubmit();
        }

        function applyHeaderLocationToAllCartItems() {
            const headerLocId = String(document.getElementById('headerLocationSelect')?.value || '');
            if (!headerLocId || !cartItems.length) return;
            const headerLocName = findLocationNameById(headerLocId);
            cartItems.forEach(it => {
                it.transfer_location_id = headerLocId;
                it.transferLocationName = headerLocName;
            });
            saveCart();
            renderCart();
            checkSubmit();
            iziToast.success({ message: `Lokasi "${headerLocName}" diterapkan ke semua item.` });
        }

        function syncPmiLocationUI() {
            const pmiDest = isPmiDestination();
            const container = document.getElementById('pmiLocationContainer');
            if (container) container.classList.toggle('hidden', !pmiDest);

            const stageLocContainer = document.getElementById('stageLocationContainer');
            if (stageLocContainer && stagedBatch) {
                stageLocContainer.classList.toggle('hidden', !pmiDest);
            }

            if (pmiDest) {
                const headerLocId = String(document.getElementById('headerLocationSelect')?.value || '');
                if (headerLocId) {
                    let updated = false;
                    cartItems.forEach(it => {
                        if (!it.transfer_location_id) {
                            it.transfer_location_id = headerLocId;
                            it.transferLocationName = findLocationNameById(headerLocId);
                            updated = true;
                        }
                    });
                    if (updated) saveCart();
                }
            }

            window.initEtalaseSearchableSelects?.(document.getElementById('pmiLocationContainer'));
            window.initEtalaseSearchableSelects?.(document.getElementById('stageLocationContainer'));
            renderCart();
            checkSubmit();
        }

        window.addEventListener('transfer-locations-updated', function (ev) {
            const freshList = ev?.detail?.list;
            if (Array.isArray(freshList)) {
                locationList = freshList;
                const validIds = new Set(locationList.map(l => String(l.id)));
                let dirty = false;
                cartItems.forEach(it => {
                    if (it.transfer_location_id) {
                        if (!validIds.has(String(it.transfer_location_id))) {
                            it.transfer_location_id = null;
                            it.transferLocationName = '';
                            dirty = true;
                        } else {
                            const updatedName = findLocationNameById(it.transfer_location_id);
                            if (updatedName && it.transferLocationName !== updatedName) {
                                it.transferLocationName = updatedName;
                                dirty = true;
                            }
                        }
                    }
                });
                if (dirty) saveCart();
                renderCart();
                checkSubmit();
            }
        });

        function removeCartItem(idx) {
            cartItems.splice(idx, 1);
            saveCart();
            renderCart();
            checkSubmit();
        }

        // ── Dropdown helpers ──────────────────────────────────────────────
        function showDropdown() {
            document.getElementById('searchDropdown').classList.remove('hidden');
        }

        function hideDropdown() {
            document.getElementById('searchDropdown').classList.add('hidden');
        }

        function handleScroll() {
            const el = document.getElementById('tableScroll');
            if (el.scrollTop + el.clientHeight >= el.scrollHeight - 5) fetchData();
        }

        // ── Keyboard nav / outside click ─────────────────────────────────
        function focusAfterPharmacySelected() {
            if (!document.getElementById('pharmacySelect')?.value) return;
            setTimeout(() => {
                if (isPmiDestination()) {
                    const headerLocSel = document.getElementById('headerLocationSelect');
                    const target = headerLocSel?._comboboxTrigger || headerLocSel;
                    if (target) {
                        target.focus();
                        if (!headerLocSel.value && headerLocSel._comboboxTrigger) {
                            headerLocSel._comboboxTrigger.click();
                        }
                        return;
                    }
                }
                document.getElementById('searchInput')?.focus();
            }, 20);
        }

        $(document).ready(function() {
            const pharmacySelectEl = document.getElementById('pharmacySelect');
            const searchInputEl = document.getElementById('searchInput');
            const stageQtyEl = document.getElementById('stageQty');
            const cartBodyEl = document.getElementById('cartBody');

            if (pharmacySelectEl) {
                pharmacySelectEl.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (pharmacySelectEl.value) {
                            focusAfterPharmacySelected();
                        }
                    }
                });
            }

            // Search input: Arrow/Enter for dropdown nav, Enter to jump to qty if batch staged & dropdown closed
            searchInputEl.addEventListener('keydown', function(e) {
                const rows = document.querySelectorAll('#searchResults .dropdown-row');
                const dropdown = document.getElementById('searchDropdown');
                const dropdownVisible = !dropdown.classList.contains('hidden') && rows.length;

                if (dropdownVisible) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        activeIdx = Math.min(activeIdx + 1, rows.length - 1);
                        updateActive(rows);
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        activeIdx = Math.max(activeIdx - 1, 0);
                        updateActive(rows);
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        const chosenRow = activeIdx >= 0 ? rows[activeIdx] : rows[0];
                        if (chosenRow) selectBatch(chosenRow);
                    } else if (e.key === 'Escape') {
                        hideDropdown();
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (stagedBatch) {
                        stageQtyEl.focus();
                        stageQtyEl.select();
                        return;
                    }
                    if (!searchInputEl.value.trim() && cartItems.length > 0) {
                        const submitBtn = document.getElementById('submitBtn');
                        if (submitBtn && !submitBtn.disabled) {
                            submitBtn.focus();
                        } else if (isPmiDestination()) {
                            const emptyLocSel = [...document.querySelectorAll('.transfer-cart-location-select')].find(s => !s.value);
                            if (emptyLocSel) {
                                const trigger = emptyLocSel._comboboxTrigger || emptyLocSel;
                                trigger.focus();
                                if (emptyLocSel._comboboxTrigger) emptyLocSel._comboboxTrigger.click();
                            }
                        }
                    }
                }
            });

            // stageQty: Enter → langsung pilih lokasi jika PMI & kosong, atau tambah ke daftar
            stageQtyEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const qtyVal = parseInt(stageQtyEl.value) || 0;
                    if (!stagedBatch || qtyVal <= 0) {
                        stageQtyEl.focus();
                        stageQtyEl.select();
                        return;
                    }
                    if (isPmiDestination()) {
                        const stageLocSel = document.getElementById('stageLocationSelect');
                        if (stageLocSel && !stageLocSel.value) {
                            const trigger = stageLocSel._comboboxTrigger || stageLocSel;
                            trigger.focus();
                            if (stageLocSel._comboboxTrigger) stageLocSel._comboboxTrigger.click();
                            return;
                        }
                    }
                    const addBtn = document.getElementById('addItemBtn');
                    if (addBtn && !addBtn.disabled) {
                        addItemToCart();
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    clearStaging();
                    searchInputEl.focus();
                }
            });

            // Tabel item transfer: Enter di qty pindah ke baris berikutnya atau tombol Simpan Transfer
            cartBodyEl?.addEventListener('keydown', function(e) {
                if (e.key !== 'Enter') return;
                const qtyInput = e.target.closest('.cart-qty-input');
                if (!qtyInput) return;
                e.preventDefault();
                updateCartQty(qtyInput);
                const allQty = [...cartBodyEl.querySelectorAll('.cart-qty-input')];
                const pos = allQty.indexOf(qtyInput);
                if (pos >= 0 && pos < allQty.length - 1) {
                    allQty[pos + 1].focus();
                    allQty[pos + 1].select();
                } else {
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.focus();
                    }
                }
            });

            // Shortcut Ctrl/Cmd + Enter untuk langsung submit transfer
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn && !submitBtn.disabled && !transferSubmitting) {
                        e.preventDefault();
                        submitTransfer();
                    }
                }
            });

            // Fokus awal halaman sesuai field pertama yang belum terisi
            setTimeout(() => {
                if (!pharmacySelectEl?.value) {
                    pharmacySelectEl?.focus();
                } else if (isPmiDestination() && !document.getElementById('headerLocationSelect')?.value) {
                    const headerLocSel = document.getElementById('headerLocationSelect');
                    (headerLocSel?._comboboxTrigger || headerLocSel)?.focus();
                } else {
                    searchInputEl?.focus();
                }
            }, 60);

            function updateActive(rows) {
                rows.forEach(r => r.classList.remove('active'));
                if (activeIdx >= 0) {
                    rows[activeIdx].classList.add('active');
                    rows[activeIdx].scrollIntoView({
                        block: 'nearest'
                    });
                }
            }

            document.addEventListener('click', function(e) {
                const inp = document.getElementById('searchInput');
                const drop = document.getElementById('searchDropdown');
                if (!inp.contains(e.target) && !drop.contains(e.target)) hideDropdown();
            });
        });

        // ── Etalase ───────────────────────────────────────────────────────
        let etalaseMode = 'create';
        let editingEtalaseId = null;

        function loadEtalases() {
            const destPharmacyId = document.getElementById('pharmacySelect')?.value || '';
            axios.get('{{ route('etalases.index') }}', {
                    params: { pharmacy_id: destPharmacyId }
                })
                .then(res => {
                    etalaseList = res.data ?? [];
                    renderEtalaseOptions(document.getElementById('stageEtalase'), document.getElementById(
                        'stageEtalase').value);
                    checkAddItemBtn();
                    document.querySelectorAll('.cart-etalase-select').forEach(sel => {
                        renderEtalaseOptions(sel, sel.value);
                    });
                })
                .catch(() => iziToast.error({
                    title: 'Error',
                    message: 'Gagal memuat etalase.'
                }));
        }

        function renderEtalaseOptions(selectEl, selectedId) {
            if (!selectEl) return;
            selectEl.innerHTML = '<option value="">— Pilih etalase —</option>';

            // Default ke etalase paling awal jika belum ada yang terpilih
            let autoSelectedId = selectedId || (etalaseList.length > 0 ? etalaseList[0].id : '');

            etalaseList.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.name;
                if (String(e.id) === String(autoSelectedId)) opt.selected = true;
                selectEl.appendChild(opt);
            });
        }

        function openEtalaseModal(mode) {
            etalaseMode = mode;
            document.getElementById('etalaseNameInput').value = '';
            editingEtalaseId = null;
            document.getElementById('modalTitle').textContent = 'Tambah Etalase';

            const modal = document.getElementById('etalaseModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                const handleEnter = (e) => {
                    if (e.key === 'Enter') saveEtalase();
                };
                document.getElementById('etalaseNameInput').onkeydown = handleEnter;
                document.getElementById('etalaseNameInput').focus();
            }, 50);
        }

        function closeEtalaseModal() {
            const modal = document.getElementById('etalaseModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.getElementById('etalaseNameInput').onkeydown = null;
        }

        function saveEtalase() {
            const name = document.getElementById('etalaseNameInput').value.trim();
            const destPharmacyId = document.getElementById('pharmacySelect')?.value || '';

            if (!name) {
                iziToast.warning({
                    message: 'Nama etalase wajib diisi.'
                });
                document.getElementById('etalaseNameInput').focus();
                return;
            }

            const btn = document.getElementById('modalSaveBtn');
            btn.disabled = true;

            axios.post('{{ route('etalases.store') }}', {
                    name,
                    pharmacy_id: destPharmacyId || null,
                    _token: '{{ csrf_token() }}'
                })
                .then(res => {
                    const saved = res.data;
                    closeEtalaseModal();
                    loadEtalases();
                    if (stagedBatch) {
                        setTimeout(() => {
                            document.getElementById('stageEtalase').value = saved.id;
                            checkAddItemBtn();
                        }, 50);
                    }
                    iziToast.success({
                        message: 'Etalase ditambahkan.'
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

        syncPmiLocationUI();

        // ── Submit ────────────────────────────────────────────────────────
        function onPharmacyChange() {
            syncPmiLocationUI();
            if (document.getElementById('pharmacySelect')?.value) {
                focusAfterPharmacySelected();
            }
        }

        function checkSubmit() {
            const pharmacy = document.getElementById('pharmacySelect').value;
            const pmiDest = isPmiDestination();
            const valid = cartItems.length > 0 && pharmacy !== '' &&
                cartItems.every(it => it.qty > 0 && it.qty <= it.stock && (!pmiDest || Boolean(it.transfer_location_id)));
            document.getElementById('submitBtn').disabled = !valid;
        }

        async function submitTransfer() {
            if (transferSubmitting) return;
            const pharmacy = document.getElementById('pharmacySelect').value;

            if (!pharmacy || cartItems.length === 0) {
                iziToast.warning({
                    message: 'Pilih tujuan apotek dan tambahkan minimal satu item.'
                });
                return;
            }

            if (isPmiDestination() && cartItems.some(it => !it.transfer_location_id)) {
                iziToast.warning({
                    message: 'Pilih lokasi tujuan PMI untuk setiap item sebelum menyimpan transfer.'
                });
                return;
            }

            // Wajib PIN pribadi sebelum mutasi keluar
            const pinResult = await window.requestTransferPin({
                title: 'PIN Mutasi Keluar',
                subtitle: 'Masukkan PIN pribadi Anda untuk mengirim mutasi ini.',
            });
            if (!pinResult) return;
            if (transferSubmitting) return;

            const payload = {
                _token: '{{ csrf_token() }}',
                pin: pinResult.pin,
                code: document.getElementById('code_hidden').value,
                submission_key: submissionKey,
                pharmacy: pharmacy,
                items: cartItems.map(it => ({
                    batches_id: it.batches_id,
                    qty: it.qty,
                    etalases_id: it.etalases_id || null,
                    transfer_location_id: isPmiDestination() ? (it.transfer_location_id || null) : null,
                    source_type: it.source_type || 'gudang',
                })),
            };

            const btn = document.getElementById('submitBtn');
            transferSubmitting = true;
            btn.disabled = true;

            axios.post('{{ route('transfer') }}', payload)
                .then(res => {
                    localStorage.removeItem(CART_KEY); // Clear cart on success
                    localStorage.removeItem(SUBMISSION_KEY);
                    const actorName = pinResult.user?.name ? ` (oleh ${pinResult.user.name})` : '';
                    iziToast.success({
                        title: 'Berhasil',
                        message: (res.data.message ?? 'Transfer disimpan.') + actorName
                    });
                    setTimeout(() => window.location.reload(), 1200);
                })
                .catch(err => {
                    transferSubmitting = false;
                    const msg = err.response?.data?.message ?? 'Gagal menyimpan transfer.';
                    iziToast.error({
                        title: 'Error',
                        message: msg
                    });
                    btn.disabled = false;
                });
        }
    </script>
@endsection
