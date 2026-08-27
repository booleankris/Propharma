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
                        <option value="">— Pilih Tujuan Apotek —</option>
                        @foreach ($pharmacies as $pharmacy)
                            <option value="{{ $pharmacy->id }}">{{ $pharmacy->name }}</option>
                        @endforeach
                    </select>
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
                        <div
                            class="grid grid-cols-[28px_1fr_1fr_50px_110px] sm:grid-cols-[32px_1fr_1fr_60px_130px] px-3 sm:px-4 py-2 bg-slate-50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            <span>#</span>
                            <span>Batch</span>
                            <span>Obat</span>
                            <span class="text-right">Stok</span>
                            <span class="text-right">Sumber</span>
                        </div>
                        <div class="max-h-56 overflow-y-auto divide-y divide-slate-50" id="tableScroll"
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

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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

                        <div>
                            <label
                                class="block text-[11px] font-semibold text-slate-600 uppercase tracking-wider mb-1">Tujuan
                                Etalase</label>
                            <div class="flex items-center gap-2">
                                <select id="stageEtalase"
                                    class="w-full px-3 py-3 sm:py-2 bg-white border border-slate-200 rounded-lg text-base sm:text-sm text-slate-800 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/10">
                                    <option value="">— Pilih etalase —</option>
                                </select>
                                <button type="button" onclick="openEtalaseModal('create')" title="Tambah etalase"
                                    class="shrink-0 w-11 h-11 sm:w-9 sm:h-9 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:text-sky-600 flex items-center justify-center transition">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                                        <line x1="12" y1="5" x2="12" y2="19" />
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                    </svg>
                                </button>
                            </div>
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
                                <th class="py-3 px-4 w-28">Qty</th>
                                <th class="py-3 px-4 w-48">Etalase</th>
                                <th class="py-3 px-4 w-12 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody" class="divide-y divide-slate-100 text-slate-700">
                            <tr id="cartEmptyRow">
                                <td colspan="7" class="py-10 text-center text-slate-400 text-xs">Belum ada item
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
        let cartItems = JSON.parse(localStorage.getItem(CART_KEY)) ||
        []; // {batches_id, batchName, medName, unit, stock, qty, etalases_id, etalasesName, source_type}
        let etalaseList = []; // {id, name}

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
                            'dropdown-row grid grid-cols-[28px_1fr_1fr_50px_110px] sm:grid-cols-[32px_1fr_1fr_60px_130px] px-3 sm:px-4 py-2.5 text-xs cursor-pointer hover:bg-sky-50 transition items-center border-b border-slate-50';
                        row.dataset.batchesId = item.id ?? '';
                        row.dataset.batchName = item.batches_name ?? '';
                        row.dataset.medName = item.name ?? '—';
                        row.dataset.medCode = item.medicine_code ?? '—';
                        row.dataset.unit = item.unit ?? '—';
                        row.dataset.stock = item.stock ?? 0;
                        row.dataset.sourceType = srcType;

                        const isPmi = {{ getActivePharmacyId() == 1 ? 'true' : 'false' }};
                        const badgeColor = srcType === 'gudang'
                            ? 'bg-amber-50 text-amber-700 border-amber-200'
                            : 'bg-violet-50 text-violet-700 border-violet-200';
                        const badgeLabel = srcType === 'gudang' ? 'Gudang ➝ Pelayanan' : (isPmi ? 'Pelayanan ➝ Gudang' : 'Pelayanan ➝ Cabang');

                        row.innerHTML = `
                            <span class="font-mono text-slate-400 text-[10px]">${((page - 1) * (res.per_page || 10)) + i + 1}</span>
                            <span class="font-medium text-slate-700 truncate pr-2">${item.batches_name ?? '—'}</span>
                            <span class="font-nunito-bold text-[#010741] truncate pr-2">${item.name ?? '—'}</span>
                            <span class="text-right"><span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-[11px] font-semibold">${item.stock ?? 0}</span></span>
                            <span class="text-right"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold border whitespace-nowrap ${badgeColor}">${badgeLabel}</span></span>
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
                source_type: row.dataset.sourceType || 'gudang',
            };

            const isPmi = {{ getActivePharmacyId() == 1 ? 'true' : 'false' }};
            const displayLabel = stagedBatch.source_type === 'gudang' ? 'Gudang ➝ Pelayanan' : (isPmi ? 'Pelayanan ➝ Gudang' : 'Pelayanan ➝ Cabang');
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

            renderEtalaseOptions(document.getElementById('stageEtalase'), '');

            const stagingCard = document.getElementById('stagingCard');
            stagingCard.classList.remove('hidden');

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
            const etId = document.getElementById('stageEtalase').value;
            const valid = stagedBatch !== null && qty > 0 && qty <= stagedBatch.stock && etId !== '';
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
        }

        function addItemToCart() {
            const qty = parseInt(document.getElementById('stageQty').value) || 0;
            const etSelect = document.getElementById('stageEtalase');
            const etId = etSelect.value;
            const etName = etSelect.options[etSelect.selectedIndex]?.textContent ?? '—';

            if (!stagedBatch || qty <= 0 || qty > stagedBatch.stock || !etId) {
                iziToast.warning({
                    message: 'Periksa kembali qty dan etalase.'
                });
                return;
            }

            cartItems.push({
                batches_id: stagedBatch.batches_id,
                batchName: stagedBatch.batchName,
                medName: stagedBatch.medName,
                unit: stagedBatch.unit,
                stock: stagedBatch.stock,
                qty: qty,
                etalases_id: etId,
                etalasesName: etName,
                source_type: stagedBatch.source_type || 'gudang',
            });

            saveCart();
            clearStaging();
            renderCart();
            checkSubmit();

            // Refocus search input for next item
            document.getElementById('searchInput').focus();
        }

        function renderCart() {
            const body = document.getElementById('cartBody');
            document.getElementById('cartCount').textContent = cartItems.length;

            if (cartItems.length === 0) {
                body.innerHTML =
                    `<tr id="cartEmptyRow"><td colspan="7" class="py-10 text-center text-slate-400 text-xs">Belum ada item ditambahkan</td></tr>`;
                return;
            }

            body.innerHTML = '';
            cartItems.forEach((it, idx) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50/50 transition';
                const isPmi = {{ getActivePharmacyId() == 1 ? 'true' : 'false' }};
                const srcBadgeColor = it.source_type === 'gudang'
                    ? 'bg-amber-50 text-amber-700 border-amber-200'
                    : 'bg-violet-50 text-violet-700 border-violet-200';
                const srcBadgeLabel = it.source_type === 'gudang' ? 'Gudang ➝ Pelayanan' : (isPmi ? 'Pelayanan ➝ Gudang' : 'Pelayanan ➝ Cabang');
                tr.innerHTML = `
                    <td class="py-3 px-4 font-semibold text-slate-800">
                        ${it.batchName}
                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold border ${srcBadgeColor}">${srcBadgeLabel}</span>
                    </td>
                    <td class="py-3 px-4 text-slate-600">${it.medName}</td>
                    <td class="py-3 px-4 text-slate-600">${it.unit}</td>
                    <td class="py-3 px-4"><span class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-[11px] font-semibold">${it.stock}</span></td>
                    <td class="py-3 px-4">
                        <input type="number" class="w-20 px-2 py-1 bg-white border border-slate-200 rounded text-xs font-mono text-slate-800 outline-none focus:border-sky-500" min="1" max="${it.stock}" value="${it.qty}" data-idx="${idx}" onchange="updateCartQty(this)">
                    </td>
                    <td class="py-3 px-4">
                        <select class="cart-etalase-select w-full px-2 py-1 bg-white border border-slate-200 rounded text-xs text-slate-800 outline-none focus:border-sky-500" data-idx="${idx}" onchange="updateCartEtalase(this)"></select>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <button type="button" class="text-slate-400 hover:text-rose-500 transition p-1" onclick="removeCartItem(${idx})" title="Hapus">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6" />
                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                            </svg>
                        </button>
                    </td>
                `;
                body.appendChild(tr);
                renderEtalaseOptions(tr.querySelector('.cart-etalase-select'), it.etalases_id);
            });
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
        $(document).ready(function() {
            const searchInputEl = document.getElementById('searchInput');
            const stageQtyEl = document.getElementById('stageQty');
            const stageEtalaseEl = document.getElementById('stageEtalase');

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
                    } else if (e.key === 'Enter' && activeIdx >= 0) {
                        e.preventDefault();
                        selectBatch(rows[activeIdx]);
                    } else if (e.key === 'Escape') {
                        hideDropdown();
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    // If batch already staged, jump to qty field
                    if (stagedBatch) {
                        stageQtyEl.focus();
                        stageQtyEl.select();
                    }
                }
            });

            // stageQty: Enter → jump to etalase select
            stageQtyEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    stageEtalaseEl.focus();
                }
            });

            // stageEtalase: Enter → trigger addItemToCart if valid
            stageEtalaseEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const addBtn = document.getElementById('addItemBtn');
                    if (!addBtn.disabled) {
                        addItemToCart();
                    }
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

            document.getElementById('stageEtalase').addEventListener('change', checkAddItemBtn);

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
            axios.get('{{ route('etalases.index') }}')
                .then(res => {
                    etalaseList = res.data ?? [];
                    renderEtalaseOptions(document.getElementById('stageEtalase'), document.getElementById(
                        'stageEtalase').value);
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

            let autoSelectedId = selectedId;
            if (!autoSelectedId) {
                // Default to etalase id 99 ("Apotek Cabang")
                const defaultEtalase = etalaseList.find(e => e.id == 99);
                if (defaultEtalase) autoSelectedId = 99;
            }

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

        renderCart();
        checkSubmit();
        loadEtalases();

        // ── Submit ────────────────────────────────────────────────────────
        function onPharmacyChange() {
            checkSubmit();
            if (stagedBatch) {
                renderEtalaseOptions(document.getElementById('stageEtalase'), '');
                checkAddItemBtn();
            }
        }

        function checkSubmit() {
            const pharmacy = document.getElementById('pharmacySelect').value;
            const valid = cartItems.length > 0 && pharmacy !== '' &&
                cartItems.every(it => it.qty > 0 && it.qty <= it.stock && it.etalases_id);
            document.getElementById('submitBtn').disabled = !valid;
        }

        function submitTransfer() {
            const pharmacy = document.getElementById('pharmacySelect').value;

            if (!pharmacy || cartItems.length === 0) {
                iziToast.warning({
                    message: 'Pilih tujuan apotek dan tambahkan minimal satu item.'
                });
                return;
            }

            const payload = {
                _token: '{{ csrf_token() }}',
                code: document.getElementById('code_hidden').value,
                pharmacy: pharmacy,
                items: cartItems.map(it => ({
                    batches_id: it.batches_id,
                    qty: it.qty,
                    etalases_id: it.etalases_id,
                    source_type: it.source_type || 'gudang',
                })),
            };

            const btn = document.getElementById('submitBtn');
            btn.disabled = true;

            axios.post('{{ route('transfer') }}', payload)
                .then(res => {
                    localStorage.removeItem(CART_KEY); // Clear cart on success
                    iziToast.success({
                        title: 'Berhasil',
                        message: res.data.message ?? 'Transfer disimpan.'
                    });
                    setTimeout(() => window.location.reload(), 1200);
                })
                .catch(err => {
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
