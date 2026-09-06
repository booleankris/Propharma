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
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1">Cari Obat</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Nama obat / kode..." class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-indigo-500 transition w-44 sm:w-52">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm">
                        Filter
                    </button>
                    @if(request()->anyFilled(['start_date', 'end_date', 'expired_date', 'search']))
                        <a href="{{ route('transfers.incoming') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3.5 py-2 rounded-lg text-xs font-semibold transition flex items-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            <div class="flex items-center">
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

            window.toggleDetails = function(id) {
                const detailEl = document.getElementById('details-' + id);
                const chevronEl = document.getElementById('chevron-' + id);

                if (!detailEl) return;

                const isHidden = detailEl.classList.contains('hidden');
                if (isHidden) {
                    detailEl.classList.remove('hidden');
                    if (chevronEl) chevronEl.classList.add('rotate-180');
                } else {
                    detailEl.classList.add('hidden');
                    if (chevronEl) chevronEl.classList.remove('rotate-180');
                }
            };
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
                    $fromName = $firstItem?->sourceBatch?->pharmacy?->name 
                        ?? ($firstItem?->source_type === 'gudang' ? 'GUDANG PMI' : ($transfer->users?->pharmacy?->name ?? 'Gudang / Asal'));
                    $toName = $firstItem?->batches?->pharmacy?->name ?? 'Apotek Tujuan';
                    [$statusLabel, $statusClass] = $statusMap[$transfer->status] ?? ['—', ''];
                    $hasPendingItems = $transfer->items->contains('status', 0);
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

                            {{-- Creator User --}}
                            @if($transfer->users)
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 bg-slate-100/70 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Oleh: <strong class="font-semibold text-slate-700">{{ $transfer->users->name }}</strong></span>
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
                                <form method="POST" action="{{ route('transfers.deny', $transfer) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold rounded-lg transition shadow-sm" onclick="return confirm('Apakah Anda yakin ingin membatalkan pengiriman mutasi ini? Stok obat akan dikembalikan.')">
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
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-100/60 border-b border-slate-200/60 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <tr>
                                        <th class="py-3 px-5 text-left">Obat</th>
                                        <th class="py-3 px-4 text-left">Batch & Expired</th>
                                        <th class="py-3 px-4 text-left">Etalase</th>
                                        <th class="py-3 px-4 text-center">Jumlah Qty</th>
                                        <th class="py-3 px-4 text-center">Status Item</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($transfer->items as $item)
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="py-3 px-5">
                                                <div class="font-semibold text-slate-800">
                                                    {{ $item->batches?->medicines?->name ?? '—' }}
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                    {{ $item->batches?->medicines?->code ?? '—' }}
                                                    @if($item->batches?->medicines?->unit)
                                                        · <span class="text-slate-500">{{ $item->batches->medicines->unit }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-600">
                                                <div class="font-medium text-slate-700">{{ $item->batches?->name ?? '—' }}</div>
                                                <div class="text-[10px] text-slate-400">
                                                    Exp: {{ safeDateFormat($item->batches?->expired_date) }}
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-600">
                                                <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-700 text-[11px]">
                                                    {{ $item->etalases?->name ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-mono text-xs font-bold border border-emerald-200">
                                                    {{ $item->qty }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                @php [$iLabel, $iClass] = $statusMap[$item->status] ?? ['—', '']; @endphp
                                                <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full border {{ $iClass }}">
                                                    {{ $iLabel }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-6 text-center text-slate-400 text-xs">Tidak ada item obat pada transfer ini</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
                    $fromName = $firstItem?->sourceBatch?->pharmacy?->name 
                        ?? ($firstItem?->source_type === 'gudang' ? 'GUDANG PMI' : ($transfer->users?->pharmacy?->name ?? 'Gudang / Asal'));
                    $toName = $firstItem?->batches?->pharmacy?->name ?? 'Apotek Tujuan';
                    [$statusLabel, $statusClass] = $statusMap[$transfer->status] ?? ['—', ''];
                    $hasPendingItems = $transfer->items->contains('status', 0);
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

                            {{-- Creator User --}}
                            @if($transfer->users)
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 bg-slate-100/70 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Dikirim: <strong class="font-semibold text-slate-700">{{ $transfer->users->name }}</strong></span>
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
                                <form method="POST" action="{{ route('transfers.accept', $transfer) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg transition shadow-sm" onclick="return confirm('Apakah Anda yakin ingin menerima semua obat yang masih pending pada mutasi ini?')">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Terima Semua
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('transfers.deny', $transfer) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg transition shadow-sm" onclick="return confirm('Apakah Anda yakin ingin menolak semua obat pada mutasi ini?')">
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
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-100/60 border-b border-slate-200/60 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <tr>
                                        <th class="py-3 px-5 text-left">Obat</th>
                                        <th class="py-3 px-4 text-left">Batch & Expired</th>
                                        <th class="py-3 px-4 text-left">Etalase</th>
                                        <th class="py-3 px-4 text-center">Jumlah Qty</th>
                                        <th class="py-3 px-4 text-center">Status Item</th>
                                        <th class="py-3 px-4 text-center w-48">Aksi Konfirmasi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($transfer->items as $item)
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="py-3 px-5">
                                                <div class="font-semibold text-slate-800">
                                                    {{ $item->batches?->medicines?->name ?? '—' }}
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                    {{ $item->batches?->medicines?->code ?? '—' }}
                                                    @if($item->batches?->medicines?->unit)
                                                        · <span class="text-slate-500">{{ $item->batches->medicines->unit }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-600">
                                                <div class="font-medium text-slate-700">{{ $item->batches?->name ?? '—' }}</div>
                                                <div class="text-[10px] text-slate-400">
                                                    Exp: {{ safeDateFormat($item->batches?->expired_date) }}
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-600">
                                                <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-700 text-[11px]">
                                                    {{ $item->etalases?->name ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-mono text-xs font-bold border border-emerald-200">
                                                    {{ $item->qty }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                @php [$iLabel, $iClass] = $statusMap[$item->status] ?? ['—', '']; @endphp
                                                <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full border {{ $iClass }}">
                                                    {{ $iLabel }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                @if ($item->status === 0)
                                                    <div class="flex items-center justify-center gap-2">
                                                        <form method="POST" action="{{ route('transfers.acceptItem', $item) }}">
                                                            @csrf
                                                            <button type="submit"
                                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-[11px] font-semibold rounded-lg transition shadow-sm"
                                                                onclick="return confirm('Terima obat {{ $item->batches?->medicines?->name }} (Qty: {{ $item->qty }})?')">
                                                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                                    <polyline points="20 6 9 17 4 12" />
                                                                </svg>
                                                                Terima
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="{{ route('transfers.denyItem', $item) }}">
                                                            @csrf
                                                            <button type="submit"
                                                                class="inline-flex items-center gap-1 px-3 py-1.5 bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 text-[11px] font-semibold rounded-lg transition shadow-sm"
                                                                onclick="return confirm('Tolak obat {{ $item->batches?->medicines?->name }}?')">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                                                    <line x1="18" y1="6" x2="6" y2="18" />
                                                                    <line x1="6" y1="6" x2="18" y2="18" />
                                                                </svg>
                                                                Tolak
                                                            </button>
                                                        </form>
                                                    </div>
                                                @else
                                                    <span class="text-[11px] text-slate-400 font-medium italic">Telah diproses</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-6 text-center text-slate-400 text-xs">Tidak ada item obat pada transfer ini</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
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
        
<script>    console.log('ANDA TOLOL')</script>
<script>
    console.log('--- DEBUG DENIED DATA ---');
    console.log('Total:', {{ $denied->total() }});
    console.log('Items Count:', {{ count($denied->items()) }});
    console.log('Items:', @json($denied->items()));
</script>
<div id="tab-denied" class="tab-panel hidden space-y-4">
            @forelse($denied as $transfer)
                @php try { @endphp
                @php
                    $firstItem = $transfer->items->first();
                    $fromName = $firstItem?->sourceBatch?->pharmacy?->name 
                        ?? ($firstItem?->source_type === 'gudang' ? 'GUDANG PMI' : ($transfer->users?->pharmacy?->name ?? 'Gudang / Asal'));
                    $toName = $firstItem?->batches?->pharmacy?->name ?? 'Apotek Tujuan';
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

                            {{-- Creator User --}}
                            @if($transfer->users)
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 bg-slate-100/70 px-2.5 py-1 rounded-lg">
                                    <svg class="w-3.5 h-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0" />
                                        <path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2" />
                                    </svg>
                                    <span>Oleh: <strong class="font-semibold text-slate-700">{{ $transfer->users->name }}</strong></span>
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
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-100/60 border-b border-slate-200/60 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                    <tr>
                                        <th class="py-3 px-5 text-left">Obat</th>
                                        <th class="py-3 px-4 text-left">Batch & Expired</th>
                                        <th class="py-3 px-4 text-left">Etalase</th>
                                        <th class="py-3 px-4 text-center">Jumlah Qty</th>
                                        <th class="py-3 px-4 text-center">Status Item</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($deniedItemsList as $item)
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="py-3 px-5">
                                                <div class="font-semibold text-slate-800">
                                                    {{ $item->batches?->medicines?->name ?? '—' }}
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                    {{ $item->batches?->medicines?->code ?? '—' }}
                                                    @if($item->batches?->medicines?->unit)
                                                        · <span class="text-slate-500">{{ $item->batches->medicines->unit }}</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-600">
                                                <div class="font-medium text-slate-700">{{ $item->batches?->name ?? '—' }}</div>
                                                <div class="text-[10px] text-slate-400">
                                                    Exp: {{ safeDateFormat($item->batches?->expired_date) }}
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-slate-600">
                                                <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-700 text-[11px]">
                                                    {{ $item->etalases?->name ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-mono text-xs font-bold border border-rose-200">
                                                    {{ $item->qty }}
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <span class="text-[10px] font-semibold px-2.5 py-0.5 rounded-full border bg-rose-50 text-rose-700 border-rose-200">
                                                    Ditolak
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="py-6 text-center text-slate-400 text-xs">Tidak ada item yang ditolak</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @php } catch (\Throwable $e) { 
                    echo '<div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-mono break-all">';
                    echo '<b>ERROR Rendering Transfer (Code: ' . ($transfer->code ?? 'N/A') . ')</b><br>';
                    echo $e->getMessage() . '<br>Line: ' . $e->getLine() . '<br>File: ' . basename($e->getFile());
                    echo '</div>'; 
                } @endphp
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
                        const response = await fetch(exportBtn.href, {
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

@endsection
