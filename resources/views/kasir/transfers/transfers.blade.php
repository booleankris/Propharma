@extends('layouts.app')

@section('title', 'Transfer Stok')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        .custom-pagination nav > div:first-child {
            display: none !important;
        }

        .custom-pagination nav p {
            display: none !important;
        }

        .custom-pagination nav div:has(> p) {
            display: none !important;
        }

        .custom-pagination nav {
            box-shadow: none !important;
            border: none !important;
        }
    </style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Loaded');
        const deniedTab = document.getElementById('tab-denied');
        if (deniedTab) {
            console.log('tab-denied found in DOM. Children count:', deniedTab.children.length);
            console.log('tab-denied HTML:', deniedTab.innerHTML.substring(0, 500));
        } else {
            console.log('tab-denied NOT FOUND IN DOM!');
        }
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('--- ADVANCED DOM CHECK ---');
        const panels = document.querySelectorAll('.tab-panel');
        console.log('Found ' + panels.length + ' tab-panels');
        panels.forEach(p => console.log('Panel ID:', p.id));
        console.log('Body length:', document.body.innerHTML.length);
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('--- LAST 1000 CHARS OF BODY ---');
        const html = document.body.innerHTML;
        console.log(html.substring(html.length - 1000));
    });
</script>

@endsection

@section('content')
    <div class="pb-6 md:pb-4 px-3 sm:px-6">
        <div
            class="flex flex-col my-2 gap-4 p-4 bg-white border border-slate-200/80 rounded-xl shadow-xs md:flex-row md:items-center md:justify-between">

            {{-- LEFT: JUDUL & IKON HEADER --}}
            <div class="flex items-center gap-3">
                <div
                    class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-blue-600" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M7 21v-6" />
                        <path d="M20 6l-3 -3l-3 3" />
                        <path d="M17 3v18" />
                        <path d="M10 18l-3 3l-3 -3" />
                        <path d="M7 3v2" />
                        <path d="M7 9v2" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800 leading-tight">Daftar Transfer Stok</h2>
                    <p class="text-xs text-slate-400">Riwayat & Mutasi Obat</p>
                </div>
            </div>

            {{-- RIGHT: TOMBOL AKSI --}}
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
                <a href="{{ route('transfers.requests.create') }}" class="w-full sm:w-auto">
                    <button type="button" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-100 transition">
                        <span>Permintaan Mutasi</span>
                    </button>
                </a>
                <a href="{{ route('transfers.create') }}" class="w-full sm:w-auto">
                    <button type="button"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                            stroke-linecap="round">
                            <line x1="12" y1="5" x2="12" y2="19" />
                            <line x1="5" y1="12" x2="19" y2="12" />
                        </svg>
                        <span>Buat Transfer Baru</span>
                    </button>
                </a>
            </div>
        </div>
    </div>
    <div class="text-slate-800 px-3 sm:px-6">

        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs sm:text-sm font-medium flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700 font-bold ml-2">×</button>
            </div>
        @endif

        {{-- Requests use a separate approval stage before a stock transfer exists. --}}
        <section class="mb-6 grid grid-cols-1 xl:grid-cols-2 gap-4 items-start">
            <details class="bg-white border border-amber-200 rounded-2xl overflow-hidden group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 sm:px-5 py-4 bg-amber-50/70 hover:bg-amber-50">
                    <div class="flex items-center gap-3"><span class="grid place-items-center w-9 h-9 rounded-xl bg-white text-amber-700 border border-amber-100 font-bold">↓</span><div><h3 class="font-bold text-slate-800 text-sm">Permintaan Masuk</h3><p class="text-xs text-slate-500 mt-0.5">Tinjau item sebelum menyetujui mutasi keluar.</p></div></div>
                    <div class="flex items-center gap-2"><span class="shrink-0 rounded-full bg-amber-100 text-amber-800 px-2.5 py-1 text-xs font-bold">{{ $pendingRequests->count() }} permintaan</span><span class="text-amber-700 transition-transform group-open:rotate-180">⌄</span></div>
                </summary>
                <div class="p-3 sm:p-4 space-y-3">
                    @forelse ($pendingRequests as $requestTransfer)
                        <article class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                            <div class="flex flex-wrap items-start justify-between gap-2 p-3 sm:p-4">
                                <div><div class="font-bold text-sm text-slate-800">{{ $requestTransfer->code }}</div><div class="text-xs text-slate-500 mt-1">Dari {{ $requestTransfer->destinationPharmacy?->name ?? 'Cabang' }} · {{ $requestTransfer->created_at?->format('d M Y H:i') }}</div></div>
                                <span class="rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-2.5 py-1 text-[10px] font-bold">{{ $requestTransfer->items->count() }} item</span>
                            </div>
                            <details class="mx-3 sm:mx-4 mb-3 sm:mb-4 rounded-lg border border-slate-200 overflow-hidden group">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100"><span>Lihat daftar item</span><span class="text-[10px] text-slate-400">{{ $requestTransfer->items->count() }} item <span class="inline-block transition-transform group-open:rotate-180">⌄</span></span></summary>
                                <div class="max-h-56 overflow-y-auto divide-y divide-slate-100">
                                    @foreach ($requestTransfer->items as $requestedItem)
                                        <div class="flex items-center justify-between gap-3 px-3 py-2.5 text-xs"><div class="min-w-0"><div class="font-semibold text-slate-700 truncate">{{ $requestedItem->requestedMedicine?->name ?? 'Obat' }}</div><div class="text-[10px] text-slate-400 mt-0.5">Batch {{ $requestedItem->sourceBatch?->name ?? '—' }} · {{ $requestedItem->requestedMedicine?->unit ?? '' }}@if($requestedItem->transferLocation) · <span class="text-sky-700 font-semibold">Lokasi: {{ $requestedItem->transferLocation->name }}</span>@endif</div></div><span class="shrink-0 font-mono font-bold text-slate-700">× {{ rtrim(rtrim(number_format((float) ($requestedItem->original_qty ?? $requestedItem->qty), 2, ',', '.'), '0'), ',') }}</span></div>
                                    @endforeach
                                </div>
                            </details>
                            <div class="flex flex-wrap gap-2 px-3 sm:px-4 pb-3 sm:pb-4">
                                <form method="POST" action="{{ route('transfers.requests.approve', $requestTransfer) }}" class="pin-required" data-confirm="Setujui permintaan ini? Stok akan dikurangi sekarang." data-pin-title="PIN Mutasi Keluar" data-pin-subtitle="Masukkan PIN pribadi Anda untuk menyetujui & mengeluarkan stok.">@csrf<button type="submit" class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2.5 text-xs font-semibold">Setujui & Mutasi Keluar</button></form>
                                <form method="POST" action="{{ route('transfers.requests.deny', $requestTransfer) }}" data-modern-confirm="Tolak permintaan mutasi {{ $requestTransfer->code }}? Stok tidak berubah." data-confirm-title="Tolak Permintaan Mutasi" data-confirm-ok="Ya, Tolak">@csrf<button type="submit" class="rounded-lg bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 px-3.5 py-2.5 text-xs font-semibold">Tolak</button></form>
                            </div>
                        </article>
                    @empty
                        <div class="flex items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-5"><span class="grid place-items-center w-9 h-9 rounded-full bg-white border border-slate-200 text-slate-400">✓</span><div><p class="text-xs font-semibold text-slate-600">Tidak ada permintaan menunggu</p><p class="text-[11px] text-slate-400 mt-0.5">Permintaan baru dari cabang lain akan muncul di sini.</p></div></div>
                    @endforelse
                </div>
            </details>

            <details class="bg-white border border-indigo-200 rounded-2xl overflow-hidden group">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 sm:px-5 py-4 bg-indigo-50/60 hover:bg-indigo-50">
                    <div class="flex items-center gap-3"><span class="grid place-items-center w-9 h-9 rounded-xl bg-white text-indigo-700 border border-indigo-100 font-bold">↑</span><div><h3 class="font-bold text-slate-800 text-sm">Permintaan Saya (Belum Diterima)</h3><p class="text-xs text-slate-500 mt-0.5">Hanya menampilkan permintaan yang masih menunggu. Riwayat diterima/ditolak tergabung di daftar mutasi di bawah.</p></div></div>
                    <div class="flex items-center gap-2"><span class="shrink-0 rounded-full bg-indigo-100 text-indigo-800 px-2.5 py-1 text-xs font-bold">{{ $submittedRequests->count() }} aktif</span><span class="text-indigo-700 transition-transform group-open:rotate-180">⌄</span></div>
                </summary>
                <div class="p-3 sm:p-4 space-y-3">
                    @forelse ($submittedRequests as $requestTransfer)
                        @php
                            [$requestLabel, $requestClass] = match ((int) $requestTransfer->request_status) {
                                0 => ['Menunggu ' . ($requestTransfer->sourcePharmacy?->name ?? 'pengirim'), 'bg-amber-50 text-amber-700 border-amber-100'],
                                1 => ['Disetujui · menunggu penerimaan', 'bg-emerald-50 text-emerald-700 border-emerald-100'],
                                default => ['Ditolak', 'bg-rose-50 text-rose-700 border-rose-100'],
                            };
                        @endphp
                        <article class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                            <div class="flex flex-wrap items-start justify-between gap-2 p-3 sm:p-4"><div><div class="font-bold text-sm text-slate-800">{{ $requestTransfer->code }}</div><div class="text-xs text-slate-500 mt-1">Ke {{ $requestTransfer->sourcePharmacy?->name ?? 'Cabang' }} · {{ $requestTransfer->created_at?->format('d M Y H:i') }}</div></div><div class="flex items-center gap-2"><span class="rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $requestClass }}">{{ $requestLabel }}</span>@if((int) $requestTransfer->request_status === 1)<button type="button" onclick="window.switchTab('accepted')" class="rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-2.5 py-1 text-[10px] font-semibold transition">Terima di Mutasi Masuk →</button>@endif</div></div>
                            <details class="mx-3 sm:mx-4 mb-3 sm:mb-4 rounded-lg border border-slate-200 overflow-hidden group"><summary class="flex cursor-pointer list-none items-center justify-between gap-3 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100"><span>Lihat daftar item</span><span class="text-[10px] text-slate-400">{{ $requestTransfer->items->count() }} item <span class="inline-block transition-transform group-open:rotate-180">⌄</span></span></summary><div class="max-h-56 overflow-y-auto divide-y divide-slate-100">
                                @foreach ($requestTransfer->items as $requestedItem)
                                    <div class="flex items-center justify-between gap-3 px-3 py-2.5 text-xs"><div class="min-w-0"><div class="font-semibold text-slate-700 truncate">{{ $requestedItem->requestedMedicine?->name ?? $requestedItem->batches?->medicines?->name ?? 'Obat' }}</div><div class="text-[10px] text-slate-400 mt-0.5">Batch {{ $requestedItem->sourceBatch?->name ?? '—' }} · {{ $requestedItem->requestedMedicine?->unit ?? '' }}@if($requestedItem->transferLocation) · <span class="text-sky-700 font-semibold">Lokasi: {{ $requestedItem->transferLocation->name }}</span>@endif</div></div><span class="shrink-0 font-mono font-bold text-slate-700">× {{ rtrim(rtrim(number_format((float) ($requestedItem->original_qty ?? $requestedItem->qty), 2, ',', '.'), '0'), ',') }}</span></div>
                                @endforeach
                            </div></details>
                        </article>
                    @empty
                        <div class="flex items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/70 px-4 py-5"><span class="grid place-items-center w-9 h-9 rounded-full bg-white border border-slate-200 text-indigo-400">✓</span><div><p class="text-xs font-semibold text-slate-600">Tidak ada permintaan yang masih menunggu</p><p class="text-[11px] text-slate-400 mt-0.5">Riwayat permintaan yang sudah diterima atau ditolak tampil di tab Mutasi Masuk &amp; Ditolak di bawah.</p></div></div>
                    @endforelse
                </div>
            </details>
        </section>
        @if (session('message'))
            <div class="mb-4 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs sm:text-sm font-medium flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('message') }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700 font-bold ml-2">×</button>
            </div>
        @endif

        {{-- Filter & Export Panel --}}
        <div class="mb-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <form action="{{ route('transfers.incoming') }}" method="GET" class="flex flex-wrap items-end gap-3 flex-1">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Mulai Tanggal</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Expired Date</label>
                    <input type="date" name="expired_date" value="{{ request('expired_date') }}" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Cari Obat / Lokasi</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama obat, kode, lokasi..." class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 transition w-44 sm:w-52">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Status Penerimaan</label>
                    <select name="status" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 transition">
                        <option value="" {{ !request()->filled('status') ? 'selected' : '' }}>Semua Status</option>
                        <option value="unreceived" {{ request('status') === 'unreceived' ? 'selected' : '' }}>Belum Diterima (Pending)</option>
                        <option value="received" {{ request('status') === 'received' ? 'selected' : '' }}>Sudah Diterima</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm">
                        Filter
                    </button>
                    @if(request()->anyFilled(['start_date', 'end_date', 'expired_date', 'search', 'status']))
                        <a href="{{ route('transfers.incoming') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="window.openManageEtalaseModal()"
                    class="inline-flex items-center gap-1.5 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 px-3.5 py-2 rounded-xl text-xs font-semibold transition shadow-sm">
                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                    <span>Kelola Etalase</span>
                </button>
                <button type="button" onclick="window.openManageLocationModal()"
                    class="inline-flex items-center gap-1.5 bg-white hover:bg-sky-50 text-sky-700 border border-sky-200 px-3.5 py-2 rounded-xl text-xs font-semibold transition shadow-sm">
                    <svg class="w-4 h-4 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-6-5.686-6-10a6 6 0 1112 0c0 4.314-6 10-6 10z" />
                        <circle cx="12" cy="11" r="2" />
                    </svg>
                    <span>Kelola Lokasi PMI</span>
                </button>
                <a href="{{ route('transfers.export', request()->all()) }}" id="export-excel-btn" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition shadow-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                        <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                        <path d="M8 11h8v7h-8z" />
                        <path d="M8 15h8" />
                        <path d="M11 11v7" />
                    </svg>
                    <span>Export Excel</span>
                </a>
            </div>
        </div>

        {{-- Progress Bar for Export --}}
        <div id="progressContainer" class="hidden mb-6 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <p class="font-semibold text-slate-700 mb-2 text-xs">Menyiapkan Berkas Excel...</p>
            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                <div id="progressBar" class="h-2.5 bg-emerald-500 rounded-full transition-all duration-300" style="width: 0%;"></div>
            </div>
            <p id="progressText" class="text-[11px] mt-2 text-slate-500 font-medium">0%</p>
        </div>

        <script>
            window.switchTab = function(key) {
                document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
                    b.classList.add('text-slate-500');
                });

                const panel = document.getElementById('tab-' + key);
                if (panel) panel.classList.remove('hidden');

                const activeBtn = document.getElementById('tab-btn-' + key);
                if (activeBtn) {
                    activeBtn.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
                    activeBtn.classList.remove('text-slate-500');
                }

                // Save active tab to URL hash
                if (window.location.hash !== '#' + key) {
                    history.replaceState(null, null, '#' + key + window.location.search);
                }
            };
            function switchTab(key) { window.switchTab(key); }

            window.toggleDetails = async function(id) {
                const detailEl = document.getElementById('details-' + id);
                const chevronEl = document.getElementById('chevron-' + id);

                if (!detailEl) return;

                const isHidden = detailEl.classList.contains('hidden');
                if (isHidden) {
                    detailEl.classList.remove('hidden');
                    if (chevronEl) chevronEl.classList.add('rotate-180');
                    
                    // AJAX Load
                    const container = detailEl.querySelector('.ajax-items-container');
                    if (container && !container.dataset.loaded) {
                        try {
                            const url = container.dataset.url;
                            const response = await fetch(url, {
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'text/html'
                                }
                            });
                            
                            if (response.ok) {
                                const html = await response.text();
                                container.innerHTML = html;
                                container.dataset.loaded = 'true';
                                const firstSelect = container.querySelector('.item-etalase-select');
                                const transferId = firstSelect?.dataset.transferId;
                                const bulkSelect = transferId ? document.querySelector(`.transfer-bulk-etalase-select[data-transfer-id="${transferId}"]`) : null;
                                if (bulkSelect && bulkSelect.value) {
                                    container.querySelectorAll('.item-etalase-select').forEach((sel) => {
                                        if (!sel.value && String(sel.dataset.itemStatus || '0') === '0') {
                                            sel.value = bulkSelect.value === 'default'
                                                ? (sel.dataset.defaultEtalaseId || 'default')
                                                : bulkSelect.value;
                                        }
                                    });
                                }
                                window.initEtalaseSearchableSelects?.(container);
                            } else {
                                container.innerHTML = '<div class="text-rose-500 p-4 text-center">Gagal memuat item mutasi.</div>';
                            }
                        } catch (e) {
                            container.innerHTML = '<div class="text-rose-500 p-4 text-center">Terjadi kesalahan koneksi.</div>';
                        }
                    }
                } else {
                    detailEl.classList.add('hidden');
                    if (chevronEl) chevronEl.classList.remove('rotate-180');
                }
            };

            // Global delegation for ajax forms inside dynamically loaded content
            document.addEventListener('submit', async function(e) {
                if (e.target && e.target.classList.contains('ajax-form')) {
                    const btn = e.target.querySelector('button[type="button"], button[type="submit"]');
                    if (btn && btn.dataset.confirm) {
                        e.preventDefault();
                        const ok = await window.confirmTransferAction({ message: btn.dataset.confirm });
                        if (ok) {
                            btn.innerHTML = 'Memproses...';
                            btn.classList.add('opacity-50', 'pointer-events-none');
                            e.target.submit();
                        }
                    } else if (btn) {
                        btn.innerHTML = 'Memproses...';
                        btn.classList.add('opacity-50', 'pointer-events-none');
                    }
                }
            });

            document.addEventListener('click', async function(e) {
                const btn = e.target.closest('.ajax-submit-btn');
                if (btn) {
                    e.preventDefault();
                    const form = btn.closest('form');
                    if (btn.dataset.confirm) {
                        const ok = await window.confirmTransferAction({ message: btn.dataset.confirm });
                        if (ok) {
                            btn.innerHTML = 'Memproses...';
                            btn.classList.add('opacity-50', 'pointer-events-none');
                            form.submit();
                        }
                    } else {
                        btn.innerHTML = 'Memproses...';
                        btn.classList.add('opacity-50', 'pointer-events-none');
                        form.submit();
                    }
                }
            });
            function toggleDetails(id) { window.toggleDetails(id); }

            window.initActiveTab = function() {
                let activeTab = 'pending';
                const hash = window.location.hash.replace('#', '');
                const validTabs = ['pending', 'accepted', 'denied'];
                
                if (validTabs.includes(hash)) {
                    activeTab = hash;
                } else {
                    const urlParams = new URLSearchParams(window.location.search);
                    if (urlParams.has('accepted_page')) {
                        activeTab = 'accepted';
                    } else if (urlParams.has('denied_page')) {
                        activeTab = 'denied';
                    } else if (urlParams.has('pending_page')) {
                        activeTab = 'pending';
                    }
                }
                window.switchTab(activeTab);
            };

            window.addEventListener('hashchange', window.initActiveTab);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', window.initActiveTab);
            } else {
                window.initActiveTab();
            }
        </script>

        {{-- Tabs --}}
        <div class="flex gap-1.5 mb-6 bg-slate-100/80 p-1.5 rounded-2xl w-fit overflow-x-auto max-w-full">
            @foreach ([['pending', 'Mutasi Keluar', $pending->total()], ['accepted', 'Mutasi Masuk', $accepted->total()], ['denied', 'Ditolak', $denied->total()]] as [$key, $label, $totalCount])
                <button onclick="switchTab('{{ $key }}')" id="tab-btn-{{ $key }}"
                    class="tab-btn px-4 py-2.5 rounded-xl text-xs font-semibold transition flex items-center gap-2 whitespace-nowrap
                {{ $key === 'pending' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                    {{ $label }}
                    <span
                        class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 rounded-full text-[10px] font-bold
                {{ $key === 'pending' ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-600' }}">
                        {{ $totalCount }}
                    </span>
                </button>
            @endforeach
        </div>

        @php
            $statusMap = [
                0 => ['Pending', 'bg-amber-50 text-amber-700 border-amber-200'],
                1 => ['Diterima', 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                2 => ['Ditolak', 'bg-rose-50 text-rose-700 border-rose-200'],
            ];
        @endphp

        {{-- ========================================================================= --}}
        {{-- TAB 1: Mutasi Keluar (Pending)                                            --}}
        {{-- ========================================================================= --}}
        <div id="tab-pending" class="tab-panel space-y-4">
            @forelse($pending as $transfer)
                @php
                    $firstItem = $transfer->items->first();
                    $fromName = $transfer->sourcePharmacy?->name
                        ?? $firstItem?->sourceBatch?->pharmacy?->name
                        ?? ($firstItem?->source_type === 'gudang' ? 'GUDANG PMI' : ($transfer->users?->pharmacy?->name ?? 'Gudang / Asal'));
                    $toName = $transfer->destinationPharmacy?->name
                        ?? $firstItem?->batches?->pharmacy?->name
                        ?? 'Apotek Tujuan';
                    [$statusLabel, $statusClass] = $statusMap[$transfer->status] ?? ['—', ''];
                    $hasPendingItems = $transfer->items->contains('status', 0);
                    $destPharmacyId = (int) ($transfer->destination_pharmacy_id ?: ($firstItem?->batches?->pharmacy_id ?? 0));
                    $isPmiCard = isPmiPharmacy($destPharmacyId);
                    $pmiLocNames = $isPmiCard ? $transfer->items->pluck('transferLocation.name')->filter()->unique()->values() : collect();
                @endphp

                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm transition-all hover:shadow-md overflow-hidden">
                    {{-- Summary Header Row --}}
                    <div class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white">
                        <div class="flex flex-wrap items-center gap-3">
                            {{-- Code & Date --}}
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-slate-800 font-bold tracking-tight">
                                    {{ $transfer->code }}
                                </span>
                                @if ($transfer->is_request)
                                    <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold uppercase tracking-wide">
                                        Permintaan
                                    </span>
                                @endif
                                <span class="text-xs text-slate-400 font-medium">
                                    {{ $transfer->created_at->format('d M Y, H:i') }}
                                </span>
                            </div>

                            {{-- Transfer Route Direction --}}
                            <div class="flex items-center gap-1.5 px-3 py-1 bg-indigo-50/70 border border-indigo-100 rounded-lg text-xs text-slate-700">
                                <span class="font-semibold text-slate-700">{{ $fromName }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-indigo-500 shrink-0" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 12l14 0" />
                                    <path d="M13 18l6 -6" />
                                    <path d="M13 6l6 6" />
                                </svg>
                                <span class="font-bold text-indigo-700">{{ $toName }}</span>
                            </div>

                            @if($isPmiCard)
                                <div class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg border {{ $pmiLocNames->isNotEmpty() ? 'text-sky-700 bg-sky-50 border-sky-200' : 'text-amber-700 bg-amber-50 border-amber-200' }}"
                                    data-transfer-location-badge="{{ $transfer->id }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 21s-6-5.686-6-10a6 6 0 1112 0c0 4.314-6 10-6 10z" />
                                        <circle cx="12" cy="11" r="2" />
                                    </svg>
                                    <span>Lokasi: <strong class="font-semibold">{{ $pmiLocNames->isNotEmpty() ? $pmiLocNames->implode(', ') : 'Belum dipilih' }}</strong></span>
                                </div>
                            @endif

                            {{-- Pelaku Mutasi (PIN pribadi; fallback akun login) --}}
                            @if($transfer->sender_display_name)
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 bg-slate-100/70 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Dikirim oleh: <strong class="font-semibold text-slate-700">{{ $transfer->sender_display_name }}</strong></span>
                                </div>
                            @endif
                            @if($transfer->receiver_display_name)
                                <div class="flex items-center gap-1.5 text-xs text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Diterima: <strong class="font-semibold text-emerald-800">{{ $transfer->receiver_display_name }}</strong></span>
                                </div>
                            @endif
                        </div>

                        {{-- Action Buttons & Toggle Extend --}}
                        <div class="flex flex-wrap items-center gap-2.5 justify-between sm:justify-end border-t lg:border-t-0 pt-3 lg:pt-0 border-slate-100">
                            {{-- Status Badge --}}
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>

                            {{-- Batalkan Mutasi (Sender Cancel) --}}
                            @if ($hasPendingItems)
                                <form method="POST" action="{{ route('transfers.deny', $transfer) }}" class="inline"
                                    data-modern-confirm="Apakah Anda yakin ingin membatalkan pengiriman mutasi ini? Stok obat akan dikembalikan."
                                    data-confirm-title="Batalkan Mutasi"
                                    data-confirm-ok="Ya, Batalkan">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold rounded-lg transition shadow-sm">
                                        <svg class="w-3.5 h-3.5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Batalkan Mutasi
                                    </button>
                                </form>
                            @endif

                            {{-- Print Button --}}
                            <a href="{{ route('transfers.print', $transfer->id) }}" target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition shadow-sm">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak
                            </a>

                            {{-- Extend / Collapse Button --}}
                            <button type="button" onclick="toggleDetails('pending-{{ $transfer->id }}')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                                <span id="label-pending-{{ $transfer->id }}">Lihat Barang ({{ $transfer->items->count() }})</span>
                                <svg id="chevron-pending-{{ $transfer->id }}" class="w-4 h-4 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Collapsible Items Table --}}
                    <div id="details-pending-{{ $transfer->id }}" class="hidden border-t border-slate-100 bg-slate-50/40">
                        <div class="ajax-items-container w-full" data-url="{{ route('transfers.items', $transfer->id) }}">
                            <div class="p-6 flex flex-col items-center justify-center gap-3 text-slate-400">
                                <svg class="animate-spin w-6 h-6 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs font-medium">Memuat data item...</span>
                            </div>
                        </div>
                    </div></div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">Tidak ada data transfer keluar</p>
                    <p class="text-xs text-slate-400 mt-0.5">Semua pengiriman mutasi stok keluar akan tampil di sini</p>
                </div>
            @endforelse

            {{-- Single Unified Pagination for Mutasi Keluar --}}
            @if ($pending->hasPages())
                <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        Menampilkan <span class="font-semibold text-slate-700">{{ $pending->firstItem() }}</span> - <span class="font-semibold text-slate-700">{{ $pending->lastItem() }}</span> dari <span class="font-semibold text-slate-700">{{ $pending->total() }}</span> mutasi keluar
                    </p>
                    <div class="custom-pagination">
                        {{ $pending->links() }}
                    </div>
                </div>
            @endif
        </div>

        {{-- ========================================================================= --}}
        {{-- TAB 2: Mutasi Masuk (Accepted)                                            --}}
        {{-- ========================================================================= --}}
        <div id="tab-accepted" class="tab-panel hidden space-y-4">

            @forelse($accepted as $transfer)
                @php
                    $firstItem = $transfer->items->first();
                    $fromName = $transfer->sourcePharmacy?->name
                        ?? $firstItem?->sourceBatch?->pharmacy?->name
                        ?? ($firstItem?->source_type === 'gudang' ? 'GUDANG PMI' : ($transfer->users?->pharmacy?->name ?? 'Gudang / Asal'));
                    $toName = $transfer->destinationPharmacy?->name
                        ?? $firstItem?->batches?->pharmacy?->name
                        ?? 'Apotek Tujuan';
                    [$statusLabel, $statusClass] = $statusMap[$transfer->status] ?? ['—', ''];
                    $hasPendingItems = $transfer->items->contains('status', 0);
                    $destPharmacyId = (int) ($transfer->destination_pharmacy_id ?: ($firstItem?->batches?->pharmacy_id ?? 0));
                    $isPmiCard = isPmiPharmacy($destPharmacyId);
                    $pmiLocNames = $isPmiCard ? $transfer->items->pluck('transferLocation.name')->filter()->unique()->values() : collect();
                @endphp

                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm transition-all hover:shadow-md overflow-hidden">
                    {{-- Summary Header Row --}}
                    <div class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white">
                        <div class="flex flex-wrap items-center gap-3">
                            {{-- Code & Date --}}
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-slate-800 font-bold tracking-tight">
                                    {{ $transfer->code }}
                                </span>
                                @if ($transfer->is_request)
                                    <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold uppercase tracking-wide">
                                        Permintaan
                                    </span>
                                @endif
                                <span class="text-xs text-slate-400 font-medium">
                                    {{ $transfer->created_at->format('d M Y, H:i') }}
                                </span>
                            </div>

                            {{-- Transfer Route Direction --}}
                            <div class="flex items-center gap-1.5 px-3 py-1 bg-emerald-50 border border-emerald-100 rounded-lg text-xs text-slate-700">
                                <span class="font-semibold text-slate-700">{{ $fromName }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-600 shrink-0" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 12l14 0" />
                                    <path d="M13 18l6 -6" />
                                    <path d="M13 6l6 6" />
                                </svg>
                                <span class="font-bold text-emerald-800">{{ $toName }}</span>
                            </div>

                            @if($isPmiCard)
                                <div class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg border {{ $pmiLocNames->isNotEmpty() ? 'text-sky-700 bg-sky-50 border-sky-200' : 'text-amber-700 bg-amber-50 border-amber-200' }}"
                                    data-transfer-location-badge="{{ $transfer->id }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 21s-6-5.686-6-10a6 6 0 1112 0c0 4.314-6 10-6 10z" />
                                        <circle cx="12" cy="11" r="2" />
                                    </svg>
                                    <span>Lokasi: <strong class="font-semibold">{{ $pmiLocNames->isNotEmpty() ? $pmiLocNames->implode(', ') : 'Belum dipilih' }}</strong></span>
                                </div>
                            @endif

                            {{-- Pelaku Mutasi (PIN pribadi; fallback akun login) --}}
                            @if($transfer->sender_display_name)
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 bg-slate-100/70 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Dikirim oleh: <strong class="font-semibold text-slate-700">{{ $transfer->sender_display_name }}</strong></span>
                                </div>
                            @endif
                            @if($transfer->receiver_display_name)
                                <div class="flex items-center gap-1.5 text-xs text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Diterima: <strong class="font-semibold text-emerald-800">{{ $transfer->receiver_display_name }}</strong></span>
                                </div>
                            @endif
                        </div>

                        {{-- Action Buttons, Status & Toggle Extend --}}
                        <div class="flex flex-wrap items-center gap-2.5 justify-between sm:justify-end border-t lg:border-t-0 pt-3 lg:pt-0 border-slate-100">
                            {{-- Status Badge --}}
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>

                            {{-- Terima Semua & Tolak Semua Button --}}
                            @if ($hasPendingItems)
                                <div class="inline-flex items-center gap-1.5">
                                    <select class="transfer-bulk-etalase-select text-xs bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-700 outline-none focus:border-emerald-500 max-w-[170px] transition"
                                        data-transfer-id="{{ $transfer->id }}" title="Pilih etalase tujuan penerima">
                                        <option value="">— Pilih Etalase —</option>
                                        @if (count($etalases ?? []) > 0)
                                            <option value="default" selected>Default (Riwayat / Acak)</option>
                                        @endif
                                        @foreach ($etalases ?? [] as $et)
                                            <option value="{{ $et->id }}">{{ $et->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" onclick="window.openManageEtalaseModal(this)"
                                        title="Tambah / ubah / hapus etalase cabang"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold bg-white border border-slate-200 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 text-slate-600 rounded-lg transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>Etalase</span>
                                    </button>
                                </div>
                                @if (isPmiPharmacy($transfer->destination_pharmacy_id ?? getActivePharmacyId()) && count($transferLocations ?? []) > 0)
                                    <div class="inline-flex items-center gap-1.5">
                                        <select class="transfer-bulk-location-select text-xs bg-white border border-slate-200 rounded-lg px-2.5 py-1.5 text-slate-700 outline-none focus:border-sky-500 max-w-[170px] transition"
                                            data-transfer-id="{{ $transfer->id }}" title="Ubah lokasi tujuan PMI untuk semua item pending">
                                            <option value="">— Lokasi PMI —</option>
                                            @foreach ($transferLocations ?? [] as $loc)
                                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" onclick="window.openManageLocationModal(this)"
                                            title="Tambah / ubah / hapus lokasi PMI"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold bg-white border border-slate-200 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 text-slate-600 rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            <span>Lokasi</span>
                                        </button>
                                    </div>
                                @endif
                                <form method="POST" action="{{ route('transfers.accept', $transfer) }}" class="inline pin-required"
                                    data-accept-transfer-id="{{ $transfer->id }}"
                                    data-require-etalase="{{ isWarehousePharmacy() ? '0' : '1' }}"
                                    data-confirm="Apakah Anda yakin ingin menerima semua obat yang masih pending pada mutasi ini?"
                                    data-pin-title="PIN Terima Mutasi"
                                    data-pin-subtitle="Masukkan PIN pribadi Anda untuk menerima mutasi {{ $transfer->code }}.">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Terima Semua
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('transfers.deny', $transfer) }}" class="inline"
                                    data-modern-confirm="Apakah Anda yakin ingin menolak semua obat pada mutasi ini?"
                                    data-confirm-title="Tolak Semua Item"
                                    data-confirm-ok="Ya, Tolak Semua">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg transition shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Tolak Semua
                                    </button>
                                </form>
                            @endif

                            {{-- Print Button --}}
                            <a href="{{ route('transfers.print', $transfer->id) }}" target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition shadow-sm">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak
                            </a>

                            {{-- Extend / Collapse Button --}}
                            <button type="button" onclick="toggleDetails('accepted-{{ $transfer->id }}')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                                <span id="label-accepted-{{ $transfer->id }}">Lihat Barang ({{ $transfer->items->count() }})</span>
                                <svg id="chevron-accepted-{{ $transfer->id }}" class="w-4 h-4 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Collapsible Items Table --}}
                    <div id="details-accepted-{{ $transfer->id }}" class="hidden border-t border-slate-100 bg-slate-50/40">
                        <div class="ajax-items-container w-full" data-url="{{ route('transfers.items', $transfer->id) }}">
                            <div class="p-6 flex flex-col items-center justify-center gap-3 text-slate-400">
                                <svg class="animate-spin w-6 h-6 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs font-medium">Memuat data item...</span>
                            </div>
                        </div>
                    </div></div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">Tidak ada transfer masuk</p>
                    <p class="text-xs text-slate-400 mt-0.5">Semua mutasi stok yang ditujukan ke unit ini akan tampil di sini</p>
                </div>
            @endforelse

            {{-- Single Unified Pagination for Mutasi Masuk --}}
            @if ($accepted->hasPages())
                <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        Menampilkan <span class="font-semibold text-slate-700">{{ $accepted->firstItem() }}</span> - <span class="font-semibold text-slate-700">{{ $accepted->lastItem() }}</span> dari <span class="font-semibold text-slate-700">{{ $accepted->total() }}</span> mutasi masuk
                    </p>
                    <div class="custom-pagination">
                        {{ $accepted->links() }}
                    </div>
                </div>
            @endif
        </div>

        {{-- ========================================================================= --}}
        {{-- TAB 3: Ditolak (Denied)                                                   --}}
        {{-- ========================================================================= --}}
        <div id="tab-denied" class="tab-panel hidden space-y-4">
            @forelse($denied as $transfer)
                
                @php
                    $firstItem = $transfer->items->first();
                    $fromName = $transfer->sourcePharmacy?->name
                        ?? $firstItem?->sourceBatch?->pharmacy?->name
                        ?? ($firstItem?->source_type === 'gudang' ? 'GUDANG PMI' : ($transfer->users?->pharmacy?->name ?? 'Gudang / Asal'));
                    $toName = $transfer->destinationPharmacy?->name
                        ?? $firstItem?->batches?->pharmacy?->name
                        ?? 'Apotek Tujuan';
                    $destPharmacyId = (int) ($transfer->destination_pharmacy_id ?: ($firstItem?->batches?->pharmacy_id ?? 0));
                    $isPmiCard = isPmiPharmacy($destPharmacyId);
                    $pmiLocNames = $isPmiCard ? $transfer->items->pluck('transferLocation.name')->filter()->unique()->values() : collect();
                @endphp

                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm transition-all hover:shadow-md overflow-hidden">
                    {{-- Summary Header Row --}}
                    <div class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white">
                        <div class="flex flex-wrap items-center gap-3">
                            {{-- Code & Date --}}
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs px-2.5 py-1 bg-slate-100 border border-slate-200 rounded-lg text-slate-800 font-bold tracking-tight">
                                    {{ $transfer->code }}
                                </span>
                                @if ($transfer->is_request)
                                    <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 text-[10px] font-bold uppercase tracking-wide">
                                        Permintaan
                                    </span>
                                @endif
                                <span class="text-xs text-slate-400 font-medium">
                                    {{ $transfer->created_at->format('d M Y, H:i') }}
                                </span>
                            </div>

                            {{-- Transfer Route Direction --}}
                            <div class="flex items-center gap-1.5 px-3 py-1 bg-rose-50 border border-rose-100 rounded-lg text-xs text-slate-700">
                                <span class="font-semibold text-slate-700">{{ $fromName }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-rose-500 shrink-0" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 12l14 0" />
                                    <path d="M13 18l6 -6" />
                                    <path d="M13 6l6 6" />
                                </svg>
                                <span class="font-bold text-rose-700">{{ $toName }}</span>
                            </div>

                            @if($isPmiCard)
                                <div class="flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-lg border {{ $pmiLocNames->isNotEmpty() ? 'text-sky-700 bg-sky-50 border-sky-200' : 'text-amber-700 bg-amber-50 border-amber-200' }}"
                                    data-transfer-location-badge="{{ $transfer->id }}">
                                    <svg class="w-3.5 h-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 21s-6-5.686-6-10a6 6 0 1112 0c0 4.314-6 10-6 10z" />
                                        <circle cx="12" cy="11" r="2" />
                                    </svg>
                                    <span>Lokasi: <strong class="font-semibold">{{ $pmiLocNames->isNotEmpty() ? $pmiLocNames->implode(', ') : 'Belum dipilih' }}</strong></span>
                                </div>
                            @endif

                            {{-- Pelaku Mutasi (PIN pribadi; fallback akun login) --}}
                            @if($transfer->sender_display_name)
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 bg-slate-100/70 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Dikirim oleh: <strong class="font-semibold text-slate-700">{{ $transfer->sender_display_name }}</strong></span>
                                </div>
                            @endif
                            @if($transfer->receiver_display_name)
                                <div class="flex items-center gap-1.5 text-xs text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Diterima: <strong class="font-semibold text-emerald-800">{{ $transfer->receiver_display_name }}</strong></span>
                                </div>
                            @endif
                        </div>

                        {{-- Action Buttons, Status & Toggle Extend --}}
                        <div class="flex flex-wrap items-center gap-2.5 justify-between sm:justify-end border-t lg:border-t-0 pt-3 lg:pt-0 border-slate-100">
                            {{-- Status Badge --}}
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border bg-rose-50 text-rose-700 border-rose-200">
                                Ditolak
                            </span>

                            {{-- Print Button --}}
                            <a href="{{ route('transfers.print', $transfer->id) }}" target="_blank"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition shadow-sm">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                Cetak
                            </a>

                            {{-- Extend / Collapse Button --}}
                            @php
                                $deniedItemsList = $transfer->items->where('status', 2);
                                if ($deniedItemsList->isEmpty()) {
                                    $deniedItemsList = $transfer->items;
                                }
                            @endphp
                            <button type="button" onclick="toggleDetails('denied-{{ $transfer->id }}')"
                                class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-lg transition">
                                <span id="label-denied-{{ $transfer->id }}">Lihat Barang ({{ $deniedItemsList->count() }})</span>
                                <svg id="chevron-denied-{{ $transfer->id }}" class="w-4 h-4 text-slate-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Collapsible Items Table --}}
                    <div id="details-denied-{{ $transfer->id }}" class="hidden border-t border-slate-100 bg-slate-50/40">
                        <div class="ajax-items-container w-full" data-url="{{ route('transfers.items', $transfer->id) }}">
                            <div class="p-6 flex flex-col items-center justify-center gap-3 text-slate-400">
                                <svg class="animate-spin w-6 h-6 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs font-medium">Memuat data item...</span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm py-16 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-slate-700">Tidak ada transfer ditolak</p>
                    <p class="text-xs text-slate-400 mt-0.5">Semua mutasi yang ditolak akan tampil di sini</p>
                </div>
            @endforelse

            {{-- Single Unified Pagination for Ditolak --}}
            @if ($denied->hasPages())
                <div class="p-4 bg-white rounded-2xl border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <p class="text-xs text-slate-500">
                        Menampilkan <span class="font-semibold text-slate-700">{{ $denied->firstItem() }}</span> - <span class="font-semibold text-slate-700">{{ $denied->lastItem() }}</span> dari <span class="font-semibold text-slate-700">{{ $denied->total() }}</span> mutasi ditolak
                    </p>
                    <div class="custom-pagination">
                        {{ $denied->links() }}
                    </div>
                </div>
            @endif
        </div>

    </div>

    @include('kasir.transfers.partials.pin_modal')

@endsection

@section('scripts')
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Export Loading State
            const exportBtn = document.getElementById('export-excel-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    
                    exportBtn.innerHTML = `
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Menyiapkan Export...</span>
                    `;
                    exportBtn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');

                    try {
                        const filterForm = exportBtn.closest('.mb-6')?.querySelector('form');
                        const exportUrl = new URL('{{ route('transfers.export') }}', window.location.origin);
                        if (filterForm) {
                            const formData = new FormData(filterForm);
                            for (const [key, val] of formData.entries()) {
                                if (val !== null && String(val).trim() !== '') {
                                    exportUrl.searchParams.set(key, String(val).trim());
                                }
                            }
                        }

                        const response = await fetch(exportUrl.toString(), {
                            headers: { 'Accept': 'application/json' }
                        });
                        if (!response.ok) throw new Error('Gagal mengunduh');
                        const data = await response.json();
                        
                        if (data.job_id) {
                            iziToast.info({
                                title: 'Memproses',
                                message: 'Export sedang disiapkan di background...',
                                position: 'topRight'
                            });
                            document.getElementById('progressContainer').classList.remove('hidden');
                            pollExportStatus(data.job_id);
                        }
                    } catch (error) {
                        iziToast.error({
                            title: 'Gagal',
                            message: 'Terjadi kesalahan saat memulai export.',
                            position: 'topRight'
                        });
                        resetExportButton();
                    }
                });

                function resetExportButton() {
                    exportBtn.innerHTML = `
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                            <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                            <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                            <path d="M8 11h8v7h-8z" />
                            <path d="M8 15h8" />
                            <path d="M11 11v7" />
                        </svg>
                        <span>Export Excel</span>
                    `;
                    exportBtn.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
                }

                function pollExportStatus(jobId) {
                    const progressBar = document.getElementById('progressBar');
                    const progressText = document.getElementById('progressText');
                    
                    let interval = setInterval(() => {
                        fetch(`/transfers/export/status/${jobId}`)
                            .then(res => res.json())
                            .then(data => {
                                progressBar.style.width = data.progress + "%";
                                progressText.innerText = data.progress + "%";
                                
                                if (data.status === "completed") {
                                    clearInterval(interval);
                                    iziToast.success({
                                        title: 'Selesai',
                                        message: 'File Excel siap diunduh!',
                                        position: 'topRight'
                                    });
                                    document.getElementById('progressContainer').classList.add('hidden');
                                    progressBar.style.width = "0%";
                                    progressText.innerText = "0%";
                                    resetExportButton();
                                    
                                    // Trigger download
                                    window.location.href = data.file;
                                } else if (data.status === "failed") {
                                    clearInterval(interval);
                                    iziToast.error({
                                        title: 'Gagal',
                                        message: 'Gagal men-generate file Excel.',
                                        position: 'topRight'
                                    });
                                    document.getElementById('progressContainer').classList.add('hidden');
                                    resetExportButton();
                                }
                            })
                            .catch(err => {
                                clearInterval(interval);
                                document.getElementById('progressContainer').classList.add('hidden');
                                resetExportButton();
                            });
                    }, 2000);
                }
            }
        });
    </script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Loaded');
        const deniedTab = document.getElementById('tab-denied');
        if (deniedTab) {
            console.log('tab-denied found in DOM. Children count:', deniedTab.children.length);
            console.log('tab-denied HTML:', deniedTab.innerHTML.substring(0, 500));
        } else {
            console.log('tab-denied NOT FOUND IN DOM!');
        }
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('--- ADVANCED DOM CHECK ---');
        const panels = document.querySelectorAll('.tab-panel');
        console.log('Found ' + panels.length + ' tab-panels');
        panels.forEach(p => console.log('Panel ID:', p.id));
        console.log('Body length:', document.body.innerHTML.length);
    });
</script>


<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('--- LAST 1000 CHARS OF BODY ---');
        const html = document.body.innerHTML;
        console.log(html.substring(html.length - 1000));
    });
</script>

@endsection
