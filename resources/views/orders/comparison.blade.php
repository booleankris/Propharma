@extends('layouts.app')
@section('title', 'Rekonsiliasi Pesanan vs Penerimaan: ' . $order->code)

@section('content')
<div class="min-h-screen bg-slate-50/50 py-6 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-6">

        <!-- TOP BAR & BREADCRUMB -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3">
                <a href="{{ url()->previous() ?: route('receiving.index') }}" 
                   class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-left" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M5 12l14 0" />
                        <path d="M5 12l6 6" />
                        <path d="M5 12l6 -6" />
                    </svg>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Rekonsiliasi Pesanan vs Penerimaan</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
                            {{ $order->status == 3 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($order->status == 2 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200') }}">
                            {{ $order->status == 3 ? 'Selesai Diterima' : ($order->status == 2 ? 'Sebagian Diterima' : ($order->status == 1 ? 'Dipesan' : 'Draft')) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-2">
                        <span>No. SPB: <strong class="text-slate-700 font-mono">{{ $order->code }}</strong></span>
                        <span>•</span>
                        <span>Apotek: <strong class="text-slate-700">{{ $order->pharmacy->name ?? '-' }}</strong></span>
                        <span>•</span>
                        <span>Tgl Pesan: <strong class="text-slate-700">{{ $order->date ?? '-' }}</strong></span>
                    </p>
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <button onclick="window.print()" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 shadow-sm transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-printer" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" />
                        <path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" />
                        <path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z" />
                    </svg>
                    <span>Cetak Laporan</span>
                </button>
                @if($order->status != 3)
                <a href="{{ route('receiving.receive', $order->id) }}" 
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 shadow-sm shadow-blue-500/20 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-package-import" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 21l-8 -4.5v-9l8 4.5" />
                        <path d="M12 12l8 -4.5v4.5" />
                        <path d="M12 12v9" />
                        <path d="M12 3l8 4.5l-8 4.5l-8 -4.5z" />
                        <path d="M16 19h6" />
                        <path d="M19 16l3 3l-3 3" />
                    </svg>
                    <span>Lanjutkan Penerimaan</span>
                </a>
                @endif
            </div>
        </div>

        <!-- 4 SUMMARY STAT CARDS (Clean, Soft & Professional) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- CARD 1: TOTAL ITEM DIPESAN -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-package" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                        <path d="M12 12l8 -4.5" />
                        <path d="M12 12l0 9" />
                        <path d="M12 12l-8 -4.5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Item Dipesan</p>
                    <p class="text-xl font-bold text-slate-800 mt-0.5">{{ $totalItems }} <span class="text-xs font-normal text-slate-500">Obat ({{ number_format($totalOrderedQty, 0, ',', '.') }} Qty)</span></p>
                </div>
            </div>

            <!-- CARD 2: LENGKAP DATANG -->
            <div class="bg-white p-4 rounded-2xl border border-emerald-100/80 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M9 12l2 2l4 -4" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wider">Lengkap Datang</p>
                    <p class="text-xl font-bold text-emerald-800 mt-0.5">{{ $fullCount }} <span class="text-xs font-normal text-emerald-600">Obat Sesuai</span></p>
                </div>
            </div>

            <!-- CARD 3: DATANG SEBAGIAN (KURANG) -->
            <div class="bg-white p-4 rounded-2xl border border-amber-100/80 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-clock-hour-4" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 12l3 2" />
                        <path d="M12 7v5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-amber-700 uppercase tracking-wider">Datang Sebagian</p>
                    <p class="text-xl font-bold text-amber-800 mt-0.5">{{ $partialCount }} <span class="text-xs font-normal text-amber-600">Obat Kurang</span></p>
                </div>
            </div>

            <!-- CARD 4: TIDAK / BELUM DATANG -->
            <div class="bg-white p-4 rounded-2xl border border-rose-100/80 shadow-sm flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-x" width="22" height="22" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M10 10l4 4m0 -4l-4 4" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-rose-700 uppercase tracking-wider">Belum / Tidak Datang</p>
                    <p class="text-xl font-bold text-rose-800 mt-0.5">{{ $zeroCount }} <span class="text-xs font-normal text-rose-600">Obat Kosong</span></p>
                </div>
            </div>
        </div>

        <!-- MAIN COMPARISON TABLE SECTION -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            
            <!-- FILTER & SEARCH TOOLBAR -->
            <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-slate-50/50">
                
                <!-- STATUS TABS -->
                <div class="inline-flex items-center p-1 rounded-xl bg-slate-200/60 text-xs font-semibold text-slate-600 gap-1 overflow-x-auto">
                    <button type="button" onclick="filterStatus('all', this)" class="filter-tab px-3 py-1.5 rounded-lg bg-white text-slate-800 shadow-sm transition-all">
                        Semua ({{ $totalItems }})
                    </button>
                    <button type="button" onclick="filterStatus('full', this)" class="filter-tab px-3 py-1.5 rounded-lg hover:text-slate-900 transition-all text-emerald-700">
                        Lengkap ({{ $fullCount }})
                    </button>
                    <button type="button" onclick="filterStatus('partial', this)" class="filter-tab px-3 py-1.5 rounded-lg hover:text-slate-900 transition-all text-amber-700">
                        Sebagian ({{ $partialCount }})
                    </button>
                    <button type="button" onclick="filterStatus('zero', this)" class="filter-tab px-3 py-1.5 rounded-lg hover:text-slate-900 transition-all text-rose-700">
                        Tidak Datang ({{ $zeroCount }})
                    </button>
                </div>

                <!-- RIGHT CONTROLS: PBF DROPDOWN & LIVE SEARCH -->
                <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap">
                    @if(count($creditors) > 1)
                    <div class="relative min-w-[180px]">
                        <select id="creditorFilter" onchange="applyFilters()" 
                                class="w-full text-xs font-medium rounded-xl border border-slate-200 bg-white px-3 py-2 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <option value="">Semua PBF / Distributor</option>
                            @foreach($creditors as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif

                    <!-- LIVE SEARCH INPUT -->
                    <div class="relative min-w-[220px] w-full sm:w-auto">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-search" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M10 10m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0" />
                                <path d="M21 21l-6 -6" />
                            </svg>
                        </div>
                        <input type="text" id="liveSearchInput" oninput="applyFilters()" placeholder="Cari nama / kode obat..."
                               class="w-full pl-9 pr-8 py-2 text-xs rounded-xl border border-slate-200 bg-white placeholder-slate-400 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        <button type="button" id="clearSearchBtn" onclick="clearSearch()" class="hidden absolute inset-y-0 right-0 pr-2.5 flex items-center text-slate-400 hover:text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-x" width="14" height="14" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M18 6l-12 12" />
                                <path d="M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- COMPARISON TABLE -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 border-collapse" id="comparisonTable">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                            <th class="py-3 px-4 text-center w-12">#</th>
                            <th class="py-3 px-4 min-w-[220px]">Obat & Distributor (PBF)</th>
                            <th class="py-3 px-4 text-center min-w-[130px] bg-slate-100/50">Pesanan Awal</th>
                            <th class="py-3 px-4 text-center min-w-[180px] bg-blue-50/30">Realisasi Diterima</th>
                            <th class="py-3 px-4 text-center min-w-[110px]">Selisih (Sisa)</th>
                            <th class="py-3 px-4 text-center w-28">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        @forelse($processedItems as $index => $item)
                        <tr class="comparison-row hover:bg-slate-50/80 transition-colors" 
                            data-status="{{ $item->status_key }}"
                            data-creditor="{{ $item->creditor_code }}"
                            data-search="{{ strtolower($item->medicine_name . ' ' . $item->medicine_code . ' ' . $item->creditor_name . ' ' . $item->sp_code) }}">
                            
                            <!-- 1. NO -->
                            <td class="py-3.5 px-4 text-center text-slate-400 font-mono text-[11px]">
                                {{ $index + 1 }}
                            </td>

                            <!-- 2. OBAT & DISTRIBUTOR -->
                            <td class="py-3.5 px-4">
                                <div class="flex flex-col">
                                    <span class="font-bold text-slate-800 text-[13px] leading-tight">{{ $item->medicine_name }}</span>
                                    <div class="flex items-center gap-1.5 mt-1 text-[11px] text-slate-400 flex-wrap">
                                        <span class="font-mono px-1.5 py-0.5 bg-slate-100 rounded text-slate-600">{{ $item->medicine_code }}</span>
                                        <span>•</span>
                                        <span class="text-slate-600 font-medium">{{ $item->creditor_name }}</span>
                                        @if($item->sp_code && $item->sp_code !== '-')
                                        <span>•</span>
                                        <span class="text-blue-600 font-mono text-[10px] bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100">{{ $item->sp_code }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- 3. PESANAN AWAL -->
                            <td class="py-3.5 px-4 text-center bg-slate-50/40">
                                <div class="flex flex-col items-center justify-center">
                                    <span class="text-sm font-bold text-slate-800 tabular-nums">
                                        {{ number_format($item->qty_ordered, 0, ',', '.') }}
                                        <span class="text-[11px] font-normal text-slate-500">{{ $item->pack ? 'Pack/Box' : ($item->unit ?: 'Satuan') }}</span>
                                    </span>
                                    <span class="text-[11px] text-slate-400 tabular-nums mt-0.5">
                                        @ Rp {{ number_format($item->raw_price, 0, ',', '.') }}
                                    </span>
                                    <span class="text-[11px] font-semibold text-slate-600 tabular-nums">
                                        Total: Rp {{ number_format($item->ordered_subtotal, 0, ',', '.') }}
                                    </span>
                                </div>
                            </td>

                            <!-- 4. REALISASI PENERIMAAN -->
                            <td class="py-3.5 px-4 bg-blue-50/10">
                                @if($item->qty_received > 0)
                                <div class="flex flex-col">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-bold text-slate-800 tabular-nums">
                                            {{ number_format($item->qty_received, 0, ',', '.') }}
                                            <span class="text-[11px] font-normal text-slate-500">{{ $item->pack ? 'Pack/Box' : ($item->unit ?: 'Satuan') }}</span>
                                        </span>
                                        <span class="text-[11px] font-semibold text-slate-700 tabular-nums">
                                            Rp {{ number_format($item->received_subtotal, 0, ',', '.') }}
                                        </span>
                                    </div>
                                    
                                    <!-- FAKTUR & BATCH TAGS -->
                                    <div class="mt-1.5 space-y-1">
                                        @foreach($item->fakturs as $f)
                                        <div class="inline-flex items-center gap-1.5 text-[10px] px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600 font-mono">
                                            <span class="text-slate-400">Fak:</span>
                                            <strong class="text-slate-700">{{ $f['invoice_number'] }}</strong>
                                            <span class="text-slate-300">|</span>
                                            <span class="text-slate-400">Batch:</span>
                                            <span>{{ $f['batch'] }}</span>
                                            <span class="text-slate-300">|</span>
                                            <span>{{ $f['qty'] }} Qty</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @else
                                <div class="text-center text-slate-400 italic text-[11px]">
                                    Belum ada penerimaan
                                </div>
                                @endif
                            </td>

                            <!-- 5. SELISIH (SISA) -->
                            <td class="py-3.5 px-4 text-center">
                                @if($item->qty_diff == 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    0 (Pas)
                                </span>
                                @elseif($item->qty_diff > 0)
                                <div class="flex flex-col items-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 tabular-nums">
                                        -{{ number_format($item->qty_diff, 0, ',', '.') }} {{ $item->pack ? 'Pack' : '' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400 mt-0.5">Kurang {{ number_format($item->qty_diff, 0, ',', '.') }}</span>
                                </div>
                                @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200 tabular-nums">
                                    +{{ abs($item->qty_diff) }} (Lebih)
                                </span>
                                @endif
                            </td>

                            <!-- 6. STATUS -->
                            <td class="py-3.5 px-4 text-center">
                                @if($item->status_key === 'full')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Lengkap
                                </span>
                                @elseif($item->status_key === 'partial')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    Sebagian
                                </span>
                                @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    Tidak Datang
                                </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                <div class="flex flex-col items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-slate-300 stroke-1 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" />
                                    </svg>
                                    <p class="text-sm font-medium text-slate-500">Tidak ada item obat di pesanan ini</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse

                        <!-- NO SEARCH RESULTS PLACEHOLDER (Hidden by default) -->
                        <tr id="noResultsRow" class="hidden">
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                <p class="text-sm font-medium text-slate-500">Tidak ada obat yang cocok dengan filter pencarian.</p>
                                <button type="button" onclick="resetAllFilters()" class="mt-2 text-xs font-semibold text-blue-600 hover:underline">
                                    Reset Semua Filter
                                </button>
                            </td>
                        </tr>
                    </tbody>

                    <!-- FOOTER TOTALS -->
                    @if($totalItems > 0)
                    <tfoot class="bg-slate-50/80 border-t-2 border-slate-200 font-bold text-slate-800 text-xs">
                        <tr>
                            <td colspan="2" class="py-3 px-4 text-right uppercase tracking-wider text-[11px] text-slate-500">
                                Total Rekapitulasi:
                            </td>
                            <td class="py-3 px-4 text-center tabular-nums text-slate-800 bg-slate-100/60">
                                <div>{{ number_format($totalOrderedQty, 0, ',', '.') }} Qty</div>
                                <div class="text-[11px] font-medium text-slate-600 mt-0.5">Rp {{ number_format($totalOrderedValue, 0, ',', '.') }}</div>
                            </td>
                            <td class="py-3 px-4 text-center tabular-nums text-blue-900 bg-blue-50/40">
                                <div>{{ number_format($totalReceivedQty, 0, ',', '.') }} Qty</div>
                                <div class="text-[11px] font-medium text-blue-700 mt-0.5">Rp {{ number_format($totalReceivedValue, 0, ',', '.') }}</div>
                            </td>
                            <td class="py-3 px-4 text-center tabular-nums text-rose-700">
                                <div>{{ number_format(max(0, $totalOrderedQty - $totalReceivedQty), 0, ',', '.') }} Qty Sisa</div>
                                <div class="text-[10px] font-normal text-slate-400 mt-0.5">Rp {{ number_format(max(0, $totalOrderedValue - $totalReceivedValue), 0, ',', '.') }}</div>
                            </td>
                            <td class="py-3 px-4 text-center text-[11px] text-slate-500">
                                {{ $fullCount }}/{{ $totalItems }} Lengkap
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>

        </div>

    </div>
</div>

<script>
    let currentStatus = 'all';

    function filterStatus(status, element) {
        currentStatus = status;

        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.classList.remove('bg-white', 'text-slate-800', 'shadow-sm');
            tab.classList.add('text-slate-600');
        });

        if (element) {
            element.classList.add('bg-white', 'text-slate-800', 'shadow-sm');
            element.classList.remove('text-slate-600');
        }

        applyFilters();
    }

    function applyFilters() {
        const query = (document.getElementById('liveSearchInput')?.value || '').toLowerCase().trim();
        const creditor = document.getElementById('creditorFilter')?.value || '';
        const clearBtn = document.getElementById('clearSearchBtn');

        if (clearBtn) {
            clearBtn.classList.toggle('hidden', query.length === 0);
        }

        const rows = document.querySelectorAll('.comparison-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowCreditor = row.getAttribute('data-creditor');
            const rowSearch = row.getAttribute('data-search') || '';

            const matchStatus = (currentStatus === 'all') || (rowStatus === currentStatus);
            const matchCreditor = (!creditor) || (rowCreditor === creditor);
            const matchQuery = (!query) || (rowSearch.includes(query));

            if (matchStatus && matchCreditor && matchQuery) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noResults = document.getElementById('noResultsRow');
        if (noResults) {
            noResults.classList.toggle('hidden', visibleCount > 0 || rows.length === 0);
        }
    }

    function clearSearch() {
        const input = document.getElementById('liveSearchInput');
        if (input) {
            input.value = '';
            input.focus();
        }
        applyFilters();
    }

    function resetAllFilters() {
        const searchInput = document.getElementById('liveSearchInput');
        if (searchInput) searchInput.value = '';

        const creditorFilter = document.getElementById('creditorFilter');
        if (creditorFilter) creditorFilter.value = '';

        const firstTab = document.querySelector('.filter-tab');
        if (firstTab) filterStatus('all', firstTab);
        else applyFilters();
    }
</script>

<style>
@media print {
    body {
        background: white !important;
        font-size: 10px !important;
    }
    .min-h-screen {
        padding: 0 !important;
    }
    button, .filter-tab, #creditorFilter, #liveSearchInput, a[href*="receiving"] {
        display: none !important;
    }
    .shadow-sm, .border {
        box-shadow: none !important;
    }
}
</style>
@endsection
