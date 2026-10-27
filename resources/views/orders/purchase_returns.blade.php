@extends('layouts.app')

@section('title', 'Monitoring Retur Beli')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/select2/dist/css/select2.min.css') }}" />
    <style>
        .select2-container--default .select2-selection--single {
            height: 42px !important;
            border-radius: 0.85rem !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #f8fafc !important;
            display: flex !important;
            align-items: center !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
            font-size: 0.8125rem !important;
            font-weight: 500 !important;
            padding-left: 0.875rem !important;
            line-height: 40px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
        }
        .select2-dropdown {
            border-radius: 0.85rem !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 14px 30px -4px rgba(0, 0, 0, 0.12) !important;
            z-index: 10050 !important;
        }
        .interactive-card {
            transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .interactive-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px -5px rgba(0, 0, 0, 0.08);
        }
        .custom-tab-active {
            color: #2563eb !important;
            border-bottom: 2px solid #2563eb !important;
            font-weight: 700 !important;
        }
        .custom-tab-inactive {
            color: #64748b !important;
            border-bottom: 2px solid transparent !important;
            font-weight: 500 !important;
        }
    </style>
@endsection

@section('content')
<section class="section px-4 pb-12">
    <div class="section-body space-y-4">

        {{-- ─── Header Panel ─── --}}
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-orange-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-orange-500/20">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-800 tracking-tight">Monitoring Retur Beli</h1>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            Pembelian
                        </span>
                        @if(!empty($branchContext['activePharmacy']['name']))
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                {{ $branchContext['activePharmacy']['name'] }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pusat pemantauan pengembalian barang ke distributor (PBF), jadwal pengambilan, dan pemotongan tagihan faktur
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('finance.purchaseReturns.export') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export Excel
                </a>
                @hasanyrole('administrator|General Manager|Gudang PMI')
                <button type="button" onclick="openCreateReturnModal()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 rounded-xl shadow-md shadow-blue-500/25 transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    + Input Retur Baru
                </button>
                @endhasanyrole
            </div>
        </div>

        {{-- ─── Navigation Tabs ─── --}}
        <div class="bg-white rounded-2xl px-5 border border-slate-200/80 shadow-xs flex items-center gap-6 overflow-x-auto">
            <button type="button" onclick="switchMainTab('monitoring')"
                    id="tabBtn-monitoring"
                    class="py-3.5 text-xs flex items-center gap-2 transition-all {{ $activeTab === 'monitoring' ? 'custom-tab-active' : 'custom-tab-inactive' }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                </svg>
                <span>Monitoring Retur Beli</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700">
                    {{ $stats['total_items'] ?? count($items) }}
                </span>
            </button>
            <button type="button" onclick="switchMainTab('approaching')"
                    id="tabBtn-approaching"
                    class="py-3.5 text-xs flex items-center gap-2 transition-all {{ $activeTab === 'approaching' ? 'custom-tab-active' : 'custom-tab-inactive' }}">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>Mendekati Masa Retur (Ketentuan PBF)</span>
                @if(!empty($approachingStats['total_count']))
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                        {{ $approachingStats['total_count'] }}
                    </span>
                @endif
            </button>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 1: MONITORING RETUR BELI
        ═══════════════════════════════════════════════════════════════ --}}
        <div id="tabContent-monitoring" class="{{ $activeTab === 'monitoring' ? '' : 'hidden' }} space-y-4">
            
            {{-- ─── 5 Summary Cards ─── --}}
            <div id="monitoringSummaryCards" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
                {{-- Card 1: Total Item Diretur --}}
                <div onclick="filterByStatus('')" class="interactive-card cursor-pointer bg-white p-4 rounded-3xl border border-slate-200/80 shadow-xs flex flex-col justify-between hover:border-slate-400 transition-all">
                    <div>
                        <div class="text-[11px] font-semibold text-slate-500">Total Item Diretur</div>
                        <div class="text-xl font-black text-slate-800 mt-1">
                            {{ $stats['total_items'] ?? 0 }} <span class="text-xs font-semibold text-slate-400">Obat</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-medium text-slate-500 mt-2">
                        Nilai: Rp {{ number_format($stats['total_value'] ?? 0, 0, ',', '.') }}
                    </div>
                </div>

                {{-- Card 2: Diajukan Retur --}}
                <div onclick="filterByStatus('diajukan')" class="interactive-card cursor-pointer bg-white p-4 rounded-3xl border border-amber-200 shadow-xs flex flex-col justify-between bg-amber-50/10 hover:border-amber-400 transition-all">
                    <div>
                        <div class="text-[11px] font-semibold text-amber-700">Diajukan Retur</div>
                        <div class="text-xl font-black text-amber-600 mt-1">
                            {{ $stats['diajukan'] ?? 0 }} <span class="text-xs font-semibold text-amber-400">Item</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-medium text-amber-600 mt-2">
                        Menunggu konfirmasi
                    </div>
                </div>

                {{-- Card 3: Sudah Dikonfirmasi --}}
                <div onclick="filterByStatus('dikonfirmasi')" class="interactive-card cursor-pointer bg-white p-4 rounded-3xl border border-blue-200 shadow-xs flex flex-col justify-between bg-blue-50/10 hover:border-blue-400 transition-all">
                    <div>
                        <div class="text-[11px] font-semibold text-blue-700">Sudah Dikonfirmasi</div>
                        <div class="text-xl font-black text-blue-600 mt-1">
                            {{ $stats['dikonfirmasi'] ?? 0 }} <span class="text-xs font-semibold text-blue-400">Item</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-medium text-blue-600 mt-2">
                        Stok telah dipotong
                    </div>
                </div>

                {{-- Card 4: Sudah Diambil (Baru) --}}
                <div onclick="filterByStatus('sudah_diambil')" class="interactive-card cursor-pointer bg-white p-4 rounded-3xl border border-cyan-200 shadow-xs flex flex-col justify-between bg-cyan-50/10 hover:border-cyan-400 transition-all">
                    <div>
                        <div class="text-[11px] font-semibold text-cyan-700">Sudah Diambil</div>
                        <div class="text-xl font-black text-cyan-600 mt-1">
                            {{ $stats['sudah_diambil'] ?? 0 }} <span class="text-xs font-semibold text-cyan-400">Item</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-medium text-cyan-600 mt-2">
                        Barang diambil PBF
                    </div>
                </div>

                {{-- Card 5: Sudah Potong Tagihan --}}
                <div onclick="filterByStatus('sudah_potong')" class="interactive-card cursor-pointer bg-white p-4 rounded-3xl border border-emerald-200 shadow-xs flex flex-col justify-between bg-emerald-50/10 hover:border-emerald-400 transition-all">
                    <div>
                        <div class="text-[11px] font-semibold text-emerald-700">Sudah Potong Tagihan</div>
                        <div class="text-xl font-black text-emerald-600 mt-1">
                            {{ $stats['sudah_potong'] ?? 0 }} <span class="text-xs font-semibold text-emerald-400">Item</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-medium text-emerald-600 mt-2">
                        Faktur telah dikurangi
                    </div>
                </div>

                {{-- Card 6: Barang Kembali PMI --}}
                <div onclick="filterByStatus('barang_kembali_pmi')" class="interactive-card cursor-pointer bg-white p-4 rounded-3xl border border-purple-200 shadow-xs flex flex-col justify-between bg-purple-50/10 hover:border-purple-400 transition-all">
                    <div>
                        <div class="text-[11px] font-semibold text-purple-700">Barang Kembali PMI</div>
                        <div class="text-xl font-black text-purple-600 mt-1">
                            {{ $stats['barang_kembali_pmi'] ?? 0 }} <span class="text-xs font-semibold text-purple-400">Item</span>
                        </div>
                    </div>
                    <div class="text-[11px] font-medium text-purple-600 mt-2">
                        Pengembalian PMI
                    </div>
                </div>
            </div>

            {{-- ─── Search & Filters Bar ─── --}}
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs">
                <form id="monitoringFilterForm" method="GET" action="{{ route('finance.purchaseReturns.index') }}">
                    <input type="hidden" name="tab" value="monitoring">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                        {{-- Search Input --}}
                        <div class="md:col-span-4 relative">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text"
                                   name="search"
                                   id="monitoringSearchInput"
                                   value="{{ $filters['search'] ?? '' }}"
                                   placeholder="Cari nama obat, no batch, no faktur..."
                                   autocomplete="off"
                                   onkeyup="onMonitoringSearchKeyUp(this.value)"
                                   class="w-full pl-10 pr-4 h-10 text-xs font-medium rounded-2xl border border-slate-300 bg-slate-50/60 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition-all">
                        </div>

                        {{-- PBF Select --}}
                        <div class="md:col-span-3">
                            <select name="creditor_code" id="monitoringCreditorCode" class="w-full">
                                <option value="">— Semua PBF —</option>
                                @foreach($creditors as $c)
                                    <option value="{{ $c->code }}" {{ ($filters['creditor_code'] ?? '') === (string)$c->code ? 'selected' : '' }}>
                                        {{ $c->name }} ({{ $c->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status Select --}}
                        <div class="md:col-span-3">
                            <select name="status" id="monitoringStatus" class="w-full h-10 text-xs rounded-2xl border border-slate-300 bg-slate-50/60 px-3 font-medium text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500" onchange="submitMonitoringFilter()">
                                <option value="">— Semua Status Retur —</option>
                                <option value="diajukan" {{ ($filters['status'] ?? '') === 'diajukan' ? 'selected' : '' }}>Diajukan (Belum Konfirmasi)</option>
                                <option value="dikonfirmasi" {{ ($filters['status'] ?? '') === 'dikonfirmasi' ? 'selected' : '' }}>Sudah Dikonfirmasi (Stok Dipotong)</option>
                                <option value="sudah_diambil" {{ ($filters['status'] ?? '') === 'sudah_diambil' ? 'selected' : '' }}>Sudah Diambil (Barang Diambil PBF)</option>
                                <option value="sudah_potong" {{ ($filters['status'] ?? '') === 'sudah_potong' ? 'selected' : '' }}>Sudah Potong Tagihan</option>
                                <option value="barang_kembali_pmi" {{ ($filters['status'] ?? '') === 'barang_kembali_pmi' ? 'selected' : '' }}>Barang Kembali PMI</option>
                                <option value="belum_potong" {{ ($filters['status'] ?? '') === 'belum_potong' ? 'selected' : '' }}>Belum Potong Tagihan</option>
                                <option value="belum_ambil" {{ ($filters['status'] ?? '') === 'belum_ambil' ? 'selected' : '' }}>Belum Diambil PBF</option>
                            </select>
                        </div>

                        {{-- Reason Select / Action --}}
                        <div class="md:col-span-2 flex items-center gap-2">
                            <select name="reason" id="monitoringReason" class="w-full h-10 text-xs rounded-2xl border border-slate-300 bg-slate-50/60 px-3 font-medium text-slate-700 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500" onchange="submitMonitoringFilter()">
                                <option value="">— Semua Alasan —</option>
                                <option value="ED DEKAT" {{ ($filters['reason'] ?? '') === 'ED DEKAT' ? 'selected' : '' }}>ED DEKAT</option>
                                <option value="Barang Rusak" {{ ($filters['reason'] ?? '') === 'Barang Rusak' ? 'selected' : '' }}>Barang Rusak</option>
                                <option value="Salah Barang" {{ ($filters['reason'] ?? '') === 'Salah Barang' ? 'selected' : '' }}>Salah Barang</option>
                                <option value="Salah Pesan" {{ ($filters['reason'] ?? '') === 'Salah Pesan' ? 'selected' : '' }}>Salah Pesan</option>
                                <option value="Recall" {{ ($filters['reason'] ?? '') === 'Recall' ? 'selected' : '' }}>Recall</option>
                                <option value="Retur Pabrik" {{ ($filters['reason'] ?? '') === 'Retur Pabrik' ? 'selected' : '' }}>Retur Pabrik</option>
                                <option value="Double" {{ ($filters['reason'] ?? '') === 'Double' ? 'selected' : '' }}>Double</option>
                                <option value="dlsbnya" {{ ($filters['reason'] ?? '') === 'dlsbnya' ? 'selected' : '' }}>dlsbnya</option>
                            </select>

                            @if(!empty($filters['search']) || !empty($filters['creditor_code']) || !empty($filters['status']) || !empty($filters['reason']))
                                <a href="{{ route('finance.purchaseReturns.index') }}?tab=monitoring"
                                   class="h-10 px-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-xs font-semibold transition-all flex items-center justify-center flex-shrink-0"
                                   title="Reset filter">
                                    ✕
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            {{-- ─── Data Table Container ─── --}}
            <div id="monitoringTableContainer" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="py-3.5 px-4 w-12 text-center">NO</th>
                                <th class="py-3.5 px-4 min-w-[200px]">NAMA OBAT & PABRIK</th>
                                <th class="py-3.5 px-4 text-center">QTY</th>
                                <th class="py-3.5 px-4">BATCH & ED</th>
                                <th class="py-3.5 px-4">FAKTUR / PBF</th>
                                <th class="py-3.5 px-4 text-center">STATUS</th>
                                <th class="py-3.5 px-4">PENGAMBILAN & POTONG</th>
                                <th class="py-3.5 px-4 text-center min-w-[190px]">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse($items as $idx => $it)
                                @php
                                    $st = $it->status ?? 'diajukan';
                                    $badge = match($st) {
                                        'dikonfirmasi' => ['label' => 'Sudah Dikonfirmasi', 'cls' => 'bg-blue-100 text-blue-800 border-blue-200'],
                                        'sudah_diambil' => ['label' => 'Sudah Diambil', 'cls' => 'bg-cyan-100 text-cyan-800 border-cyan-200'],
                                        'sudah_potong' => ['label' => 'Sudah Potong Tagihan', 'cls' => 'bg-emerald-100 text-emerald-800 border-emerald-200'],
                                        'barang_kembali_pmi' => ['label' => 'Barang Kembali PMI', 'cls' => 'bg-purple-100 text-purple-800 border-purple-200'],
                                        default => ['label' => 'Diajukan Retur', 'cls' => 'bg-amber-100 text-amber-800 border-amber-200'],
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-4 text-center text-slate-400 font-medium">
                                        {{ $items->firstItem() + $idx }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-800">{{ $it->medicine_name }}</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">
                                            Pabrik: {{ $it->factory_name ?? ($it->medicine && $it->medicine->factory ? $it->medicine->factory->name : '-') }}
                                        </div>
                                        @if(!empty($it->reason))
                                            <span class="inline-block mt-1 px-1.5 py-0.2 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                                {{ $it->reason }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="font-black text-blue-600 text-sm">
                                            {{ (float)$it->qty }} <span class="text-xs font-semibold text-slate-500">{{ $it->unit ?? 'BOX' }}</span>
                                        </div>
                                        @if($it->unit_price > 0)
                                            <div class="text-[10px] text-slate-400 mt-0.5">
                                                @ Rp {{ number_format($it->unit_price, 0, ',', '.') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-700">{{ $it->batch_number ?: '-' }}</div>
                                        <div class="text-[11px] font-medium {{ $it->expired_date && \Carbon\Carbon::parse($it->expired_date)->isPast() ? 'text-rose-600 font-bold' : 'text-slate-400' }} mt-0.5">
                                            ED: {{ $it->formatted_expired_date }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-700">
                                            {{ $it->purchaseReturn ? $it->purchaseReturn->invoice_number : '-' }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            {{ $it->purchaseReturn && $it->purchaseReturn->creditor ? $it->purchaseReturn->creditor->name : ($it->purchaseReturn ? $it->purchaseReturn->creditor_code : '-') }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $badge['cls'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ str_contains($badge['cls'], 'blue') ? 'bg-blue-600' : (str_contains($badge['cls'], 'cyan') ? 'bg-cyan-600' : (str_contains($badge['cls'], 'emerald') ? 'bg-emerald-600' : (str_contains($badge['cls'], 'purple') ? 'bg-purple-600' : 'bg-amber-600'))) }}"></span>
                                            {{ $badge['label'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-[11px]">
                                        <div class="text-slate-600">
                                            <span class="text-slate-400">Diambil:</span> 
                                            @if($st === 'sudah_diambil' || !empty($it->formatted_pickup_date))
                                                <span class="font-bold text-slate-800">{{ $it->formatted_pickup_date ?: $it->formatted_status_date }}</span>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </div>
                                        <div class="text-slate-600 mt-0.5">
                                            <span class="text-slate-400">Bukti:</span> {{ $it->deduction_note ?: '-' }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                            {{-- Ubah Status Button --}}
                                            <button type="button"
                                                    onclick="openChangeStatusModal({{ json_encode($it) }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold text-blue-700 bg-blue-50 hover:bg-blue-600 hover:text-white border border-blue-200 transition-all">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                </svg>
                                                Ubah Status
                                            </button>

                                            {{-- Konfirmasi Quick Button if Diajukan --}}
                                            @if($st === 'diajukan')
                                                <button type="button"
                                                        onclick="openConfirmItemModal({{ json_encode($it) }})"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-600 hover:text-white border border-emerald-200 transition-all"
                                                        title="Konfirmasi & Potong Stok Sekarang">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    Konfirmasi
                                                </button>
                                            @endif

                                            {{-- Edit Item Modal Button --}}
                                            <button type="button"
                                                    onclick="openEditItemModal({{ json_encode($it) }})"
                                                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-all"
                                                    title="Edit Rincian">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </button>

                                            {{-- Hapus Item Button --}}
                                            <button type="button"
                                                    onclick="deleteItem({{ $it->id }})"
                                                    class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 transition-all"
                                                    title="Hapus Pengajuan">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-12 text-center text-slate-400">
                                        <div class="w-12 h-12 mx-auto mb-3 text-slate-300">
                                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <p class="font-medium text-slate-600">Belum ada data retur beli yang cocok.</p>
                                        <p class="text-xs text-slate-400 mt-1">Gunakan tombol "+ Input Retur Baru" di kanan atas untuk membuat pengajuan retur.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination Links --}}
                @if($items->hasPages())
                    <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-between">
                        <div class="text-xs text-slate-500 font-medium">
                            Menampilkan {{ $items->firstItem() }} - {{ $items->lastItem() }} dari {{ $items->total() }} item
                        </div>
                        <div>
                            {{ $items->links() }}
                        </div>
                    </div>
                @endif
            </div>

        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 2: MENDEKATI MASA RETUR (KETENTUAN PBF)
        ═══════════════════════════════════════════════════════════════ --}}
        <div id="tabContent-approaching" class="{{ $activeTab === 'approaching' ? '' : 'hidden' }} space-y-4">
            
            {{-- Urgency Stats Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                <div class="bg-white p-4 rounded-3xl border border-rose-200 shadow-xs bg-rose-50/20">
                    <div class="text-xs font-semibold text-rose-700">Urgent (Bulan Ini)</div>
                    <div class="text-2xl font-black text-rose-600 mt-1">
                        {{ $approachingStats['urgent_count'] ?? 0 }} <span class="text-xs font-normal text-rose-400">Batch</span>
                    </div>
                    <p class="text-[11px] text-rose-500 mt-1">Batas waktu retur berakhir bulan ini</p>
                </div>
                <div class="bg-white p-4 rounded-3xl border border-amber-200 shadow-xs bg-amber-50/20">
                    <div class="text-xs font-semibold text-amber-700">Mendekati Batas Retur</div>
                    <div class="text-2xl font-black text-amber-600 mt-1">
                        {{ $approachingStats['warning_count'] ?? 0 }} <span class="text-xs font-normal text-amber-400">Batch</span>
                    </div>
                    <p class="text-[11px] text-amber-500 mt-1">Perlu segera disiapkan untuk retur</p>
                </div>
                <div class="bg-white p-4 rounded-3xl border border-emerald-200 shadow-xs bg-emerald-50/20">
                    <div class="text-xs font-semibold text-emerald-700">Eligible (Bisa Diretur Sekarang)</div>
                    <div class="text-2xl font-black text-emerald-600 mt-1">
                        {{ $approachingStats['eligible_count'] ?? 0 }} <span class="text-xs font-normal text-emerald-400">Batch</span>
                    </div>
                    <p class="text-[11px] text-emerald-500 mt-1">Memenuhi ketentuan retur distributor</p>
                </div>
            </div>

            {{-- Approaching Filters Bar --}}
            <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs">
                <form id="approachingFilterForm" method="GET" action="{{ route('finance.purchaseReturns.index') }}">
                    <input type="hidden" name="tab" value="approaching">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                        <div class="md:col-span-5 relative">
                            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text"
                                   name="approaching_search"
                                   value="{{ $approachingFilters['search'] ?? '' }}"
                                   placeholder="Cari nama obat, pabrik, atau no batch..."
                                   class="w-full pl-10 pr-4 h-10 text-xs font-medium rounded-2xl border border-slate-300 bg-slate-50/60 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        </div>
                        <div class="md:col-span-4">
                            <select name="approaching_creditor" class="w-full h-10 text-xs rounded-2xl border border-slate-300 bg-slate-50/60 px-3 font-medium text-slate-700">
                                <option value="">— Semua PBF —</option>
                                @foreach($creditors as $c)
                                    <option value="{{ $c->code }}" {{ ($approachingFilters['creditor_code'] ?? '') === (string)$c->code ? 'selected' : '' }}>
                                        {{ $c->name }} ({{ $c->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-3 flex items-center gap-2">
                            <button type="submit"
                                    class="flex-1 h-10 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <span>Filter</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Approaching Items Table --}}
            <div class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-100 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                                <th class="py-3.5 px-4 w-12 text-center">NO</th>
                                <th class="py-3.5 px-4 min-w-[220px]">NAMA OBAT & PABRIK</th>
                                <th class="py-3.5 px-4">BATCH & ED</th>
                                <th class="py-3.5 px-4 text-center">STOK GUDANG</th>
                                <th class="py-3.5 px-4">KETENTUAN PBF</th>
                                <th class="py-3.5 px-4 text-center">STATUS KELAYAKAN</th>
                                <th class="py-3.5 px-4 text-center">AKSI</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-xs">
                            @forelse($approachingItems as $aIdx => $ap)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="py-3 px-4 text-center text-slate-400 font-medium">
                                        {{ $aIdx + 1 }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-800">{{ $ap['medicine_name'] ?? '-' }}</div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">
                                            Pabrik: {{ $ap['factory_name'] ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-700">{{ $ap['batch_number'] ?? '-' }}</div>
                                        <div class="text-[11px] font-bold text-rose-600 mt-0.5">
                                            ED: {{ !empty($ap['expired_date']) ? \Carbon\Carbon::parse($ap['expired_date'])->format('d/m/Y') : '-' }}
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="font-black text-sm text-slate-800">{{ (float)($ap['stock'] ?? 0) }}</span>
                                        <span class="text-xs text-slate-400">{{ $ap['unit'] ?? 'BOX' }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-[11px]">
                                        <div class="font-bold text-slate-700">{{ $ap['creditor_name'] ?? $ap['creditor_code'] }}</div>
                                        <div class="text-slate-500 mt-0.5">{{ $ap['rule_text'] ?? '-' }}</div>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $ap['badge_class'] ?? 'bg-slate-100 text-slate-700 border-slate-200' }}">
                                            {{ $ap['eligibility_label'] ?? 'Mendekati' }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button type="button"
                                                onclick="applyReturnFromAlert({{ json_encode($ap) }})"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 shadow-sm transition-all">
                                            <span>+ Buat Retur</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        <p class="font-medium text-slate-600">Tidak ada obat yang mendekati batas waktu retur saat ini.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════
     MODAL 1: INPUT RETUR BARU
═══════════════════════════════════════════════════════════════ --}}
<div id="createReturnModal" class="fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
    <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden my-8">
        {{-- Modal Header --}}
        <div class="px-6 py-4.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-white/15 flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold">Input Pengajuan Retur Pembelian</h2>
                    <p class="text-xs text-blue-100">Pilih dari faktur penerimaan atau input manual obat yang ingin diretur</p>
                </div>
            </div>
            <button type="button" onclick="closeCreateReturnModal()" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all">
                ✕
            </button>
        </div>

        <form id="createReturnForm" onsubmit="submitCreateReturnForm(event)" class="p-6 space-y-4">
            @csrf
            {{-- Tabs Source Type --}}
            <div class="flex items-center gap-2 p-1.5 bg-slate-100 rounded-2xl w-fit">
                <button type="button" onclick="setEntrySource('invoice')" id="sourceBtn-invoice"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-blue-700 shadow-xs">
                    Dari Faktur Penerimaan
                </button>
                <button type="button" onclick="setEntrySource('manual')" id="sourceBtn-manual"
                        class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 transition-all">
                    Input Obat Manual
                </button>
            </div>

            {{-- Source 1: Faktur Search --}}
            <div id="sectionInvoiceSearch" class="space-y-3">
                <label class="block text-xs font-bold text-slate-700">Cari Faktur / Penerimaan BPBA</label>
                <div class="relative">
                    <input type="text" id="invoiceSearchInput" placeholder="Ketik nomor faktur atau nama distributor..."
                           onkeyup="onSearchInvoiceKeyUp(this.value)"
                           class="w-full h-11 pl-4 pr-10 text-xs rounded-2xl border border-slate-300 focus:ring-2 focus:ring-blue-100 focus:border-blue-500 font-medium">
                    <span id="invoiceSearchSpinner" class="hidden absolute right-3.5 top-1/2 -translate-y-1/2 text-blue-600 text-xs">
                        Memuat...
                    </span>
                </div>
                {{-- Dropdown Invoice Search Results --}}
                <div id="invoiceResultsList" class="hidden max-h-48 overflow-y-auto divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white shadow-lg text-xs">
                </div>
            </div>

            {{-- General Info Form --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Nomor Faktur / Referensi *</label>
                    <input type="text" name="invoice_number" id="form_invoice_number" required
                           class="w-full h-10 text-xs rounded-xl border border-slate-300 px-3 font-semibold focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Distributor (PBF) *</label>
                    <select name="creditor_code" id="form_creditor_code" required class="w-full h-10 text-xs rounded-xl border border-slate-300 px-3 font-medium">
                        <option value="">— Pilih Distributor —</option>
                        @foreach($creditors as $c)
                            <option value="{{ $c->code }}">{{ $c->name }} ({{ $c->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 mb-1">Tanggal Faktur</label>
                    <input type="date" name="invoice_date" id="form_invoice_date" value="{{ date('Y-m-d') }}"
                           class="w-full h-10 text-xs rounded-xl border border-slate-300 px-3 font-medium">
                </div>
            </div>

            {{-- Manual Medicine Search if manual mode --}}
            <div id="sectionManualMedSearch" class="hidden space-y-2 p-3.5 bg-slate-50 rounded-2xl border border-slate-200">
                <label class="block text-xs font-bold text-slate-700">+ Tambah Obat ke Daftar Retur</label>
                <div class="relative">
                    <input type="text" id="medSearchInput" placeholder="Ketik nama obat untuk dicari..."
                           onkeyup="onSearchMedKeyUp(this.value)"
                           class="w-full h-10 pl-3 pr-10 text-xs rounded-xl border border-slate-300 focus:ring-2 focus:ring-blue-100 font-medium">
                </div>
                <div id="medResultsList" class="hidden max-h-40 overflow-y-auto divide-y divide-slate-100 rounded-xl border border-slate-200 bg-white shadow-md text-xs">
                </div>
            </div>

            {{-- Items List to Return --}}
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-700">Daftar Item yang Diretur</span>
                    <span id="selectedItemsCounter" class="text-[11px] font-semibold text-blue-600">0 Item Dipilih</span>
                </div>
                <div class="border border-slate-200 rounded-2xl overflow-hidden max-h-60 overflow-y-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="p-2.5 w-8 text-center">PILIH</th>
                                <th class="p-2.5 min-w-[180px]">NAMA OBAT</th>
                                <th class="p-2.5">BATCH & ED</th>
                                <th class="p-2.5 text-center w-20">QTY</th>
                                <th class="p-2.5">HARGA SATUAN</th>
                                <th class="p-2.5">ALASAN</th>
                                <th class="p-2.5 w-10 text-center"></th>
                            </tr>
                        </thead>
                        <tbody id="returnItemsTableBody" class="divide-y divide-slate-100">
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400">
                                    Belum ada item dipilih. Cari faktur atau tambah obat manual di atas.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-[11px] font-bold text-slate-600 mb-1">Catatan Pengajuan Retur</label>
                <input type="text" name="notes" id="form_notes" placeholder="Contoh: Barang retur ED dekat sesuai kesepakatan sales..."
                       class="w-full h-10 text-xs rounded-xl border border-slate-300 px-3 font-medium">
            </div>

            {{-- Form Footer Actions --}}
            <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" onclick="closeCreateReturnModal()"
                        class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all">
                    Batal
                </button>
                <button type="submit" id="btnSubmitCreateReturn"
                        class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-500/20 transition-all">
                    Simpan Pengajuan Retur
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     MODAL 2: UBAH STATUS RETUR
═══════════════════════════════════════════════════════════════ --}}
<div id="changeStatusModal" class="fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 bg-blue-600 text-white flex items-center justify-between">
            <h3 class="text-sm font-bold">Ubah Status Item Retur</h3>
            <button type="button" onclick="closeChangeStatusModal()" class="text-white hover:text-slate-200">✕</button>
        </div>
        <form id="changeStatusForm" onsubmit="submitChangeStatus(event)" class="p-5 space-y-3.5 text-xs">
            @csrf
            <input type="hidden" id="status_item_id">
            
            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                <div id="status_medicine_name" class="font-bold text-slate-800"></div>
                <div id="status_batch_info" class="text-[11px] text-slate-500 mt-0.5"></div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Pilih Status Baru *</label>
                <select id="status_select" required class="w-full h-10 rounded-xl border border-slate-300 px-3 font-semibold text-slate-800">
                    <option value="diajukan">Diajukan Retur (Menunggu)</option>
                    <option value="dikonfirmasi">Sudah Dikonfirmasi (Stok Dipotong)</option>
                    <option value="sudah_diambil">Sudah Diambil (Barang Diambil PBF)</option>
                    <option value="sudah_potong">Sudah Potong Tagihan (Faktur Dikurangi)</option>
                    <option value="barang_kembali_pmi">Barang Kembali PMI</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Tanggal Status *</label>
                <input type="date" id="status_date" required class="w-full h-10 rounded-xl border border-slate-300 px-3">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Catatan Tambahan / Bukti</label>
                <input type="text" id="status_notes" placeholder="No CN / bukti potong / nama sales..." class="w-full h-10 rounded-xl border border-slate-300 px-3">
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeChangeStatusModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold shadow-sm">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     MODAL 3: KONFIRMASI RETUR (QUICK CUT STOCK)
═══════════════════════════════════════════════════════════════ --}}
<div id="confirmItemModal" class="fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 bg-emerald-600 text-white flex items-center justify-between">
            <h3 class="text-sm font-bold">Konfirmasi Pengambilan & Potong Stok</h3>
            <button type="button" onclick="closeConfirmItemModal()" class="text-white hover:text-slate-200">✕</button>
        </div>
        <form id="confirmItemForm" onsubmit="submitConfirmItem(event)" class="p-5 space-y-3.5 text-xs">
            @csrf
            <input type="hidden" id="confirm_item_id">
            
            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                <div id="confirm_medicine_name" class="font-bold text-emerald-900"></div>
                <div id="confirm_qty_info" class="text-[11px] text-emerald-700 mt-0.5"></div>
            </div>

            <p class="text-slate-600 text-xs">
                Tindakan ini akan memotong stok batch obat di kartu stok secara permanen dan mencatat pengurangan saldo.
            </p>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Tanggal Konfirmasi *</label>
                <input type="date" id="confirm_date" required class="w-full h-10 rounded-xl border border-slate-300 px-3">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Catatan Konfirmasi</label>
                <input type="text" id="confirm_notes" placeholder="Nama sales penjemput barang..." class="w-full h-10 rounded-xl border border-slate-300 px-3">
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeConfirmItemModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold shadow-sm">
                    Konfirmasi Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     MODAL 4: EDIT RINCIAN ITEM
═══════════════════════════════════════════════════════════════ --}}
<div id="editItemModal" class="fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 bg-slate-800 text-white flex items-center justify-between">
            <h3 class="text-sm font-bold">Edit Rincian Item Retur</h3>
            <button type="button" onclick="closeEditItemModal()" class="text-white hover:text-slate-200">✕</button>
        </div>
        <form id="editItemForm" onsubmit="submitEditItem(event)" class="p-5 space-y-3 text-xs">
            @csrf
            <input type="hidden" id="edit_item_id">

            <div class="grid grid-cols-2 gap-2.5">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Qty Retur</label>
                    <input type="number" step="any" min="0.01" id="edit_qty" required class="w-full h-10 rounded-xl border border-slate-300 px-3">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Harga Satuan (Rp)</label>
                    <input type="number" step="any" min="0" id="edit_unit_price" required class="w-full h-10 rounded-xl border border-slate-300 px-3">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Alasan Retur</label>
                <select id="edit_reason" class="w-full h-10 rounded-xl border border-slate-300 px-3 font-medium">
                    <option value="ED DEKAT">ED DEKAT</option>
                    <option value="Barang Rusak">Barang Rusak</option>
                    <option value="Salah Barang">Salah Barang</option>
                    <option value="Salah Pesan">Salah Pesan</option>
                    <option value="Recall">Recall</option>
                    <option value="Retur Pabrik">Retur Pabrik</option>
                    <option value="Double">Double</option>
                    <option value="dlsbnya">dlsbnya</option>
                </select>
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Tanggal Diambil PBF</label>
                <input type="date" id="edit_pickup_date" class="w-full h-10 rounded-xl border border-slate-300 px-3">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Bukti Potong Tagihan / No. CN</label>
                <input type="text" id="edit_deduction_note" placeholder="Nomor nota kredit / bukti potong..." class="w-full h-10 rounded-xl border border-slate-300 px-3">
            </div>

            <div class="pt-2 flex justify-end gap-2">
                <button type="button" onclick="closeEditItemModal()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold shadow-sm">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="{{ asset('templates/library/select2/dist/js/select2.min.js') }}"></script>
    <script>
        // Tab switching
        window.switchMainTab = function(tabName) {
            document.getElementById('tabContent-monitoring').classList.toggle('hidden', tabName !== 'monitoring');
            document.getElementById('tabContent-approaching').classList.toggle('hidden', tabName !== 'approaching');
            
            const btnM = document.getElementById('tabBtn-monitoring');
            const btnA = document.getElementById('tabBtn-approaching');
            
            if (tabName === 'monitoring') {
                btnM.className = 'py-3.5 text-xs flex items-center gap-2 transition-all custom-tab-active';
                btnA.className = 'py-3.5 text-xs flex items-center gap-2 transition-all custom-tab-inactive';
            } else {
                btnM.className = 'py-3.5 text-xs flex items-center gap-2 transition-all custom-tab-inactive';
                btnA.className = 'py-3.5 text-xs flex items-center gap-2 transition-all custom-tab-active';
            }

            const url = new URL(window.location.href);
            url.searchParams.set('tab', tabName);
            window.history.replaceState({}, '', url.toString());
        };

        // Live Search debounced on Monitoring
        let monitoringSearchTimer = null;
        window.onMonitoringSearchKeyUp = function(val) {
            clearTimeout(monitoringSearchTimer);
            monitoringSearchTimer = setTimeout(function() {
                submitMonitoringFilter();
            }, 300);
        };

        window.submitMonitoringFilter = function() {
            const form = document.getElementById('monitoringFilterForm');
            if (!form) return;
            const params = new URLSearchParams(new FormData(form)).toString();
            const url = "{{ route('finance.purchaseReturns.index') }}?" + params;

            const tableContainer = document.getElementById('monitoringTableContainer');
            if (tableContainer) tableContainer.style.opacity = '0.4';

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newTable = doc.getElementById('monitoringTableContainer');
                    if (newTable && tableContainer) {
                        tableContainer.innerHTML = newTable.innerHTML;
                    }
                    const newCards = doc.getElementById('monitoringSummaryCards');
                    const oldCards = document.getElementById('monitoringSummaryCards');
                    if (newCards && oldCards) {
                        oldCards.innerHTML = newCards.innerHTML;
                    }
                    window.history.replaceState({}, '', url);
                    if (tableContainer) tableContainer.style.opacity = '1';
                })
                .catch(err => {
                    console.error('Filter error:', err);
                    if (tableContainer) tableContainer.style.opacity = '1';
                });
        };

        // Modal Create Return
        let returnItemsData = [];
        let currentSource = 'invoice';

        window.openCreateReturnModal = function() {
            returnItemsData = [];
            renderReturnItemsTable();
            document.getElementById('createReturnForm').reset();
            document.getElementById('form_invoice_date').value = new Date().toISOString().split('T')[0];
            setEntrySource('invoice');
            const modal = document.getElementById('createReturnModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        };

        window.closeCreateReturnModal = function() {
            const modal = document.getElementById('createReturnModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        window.setEntrySource = function(src) {
            currentSource = src;
            const btnInv = document.getElementById('sourceBtn-invoice');
            const btnMan = document.getElementById('sourceBtn-manual');
            const secInv = document.getElementById('sectionInvoiceSearch');
            const secMan = document.getElementById('sectionManualMedSearch');

            if (src === 'invoice') {
                btnInv.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-blue-700 shadow-xs';
                btnMan.className = 'px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 transition-all';
                secInv.classList.remove('hidden');
                secMan.classList.add('hidden');
            } else {
                btnMan.className = 'px-4 py-2 rounded-xl text-xs font-bold transition-all bg-white text-blue-700 shadow-xs';
                btnInv.className = 'px-4 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 transition-all';
                secInv.classList.add('hidden');
                secMan.classList.remove('hidden');
            }
        };

        // Invoice search
        let invSearchTimer = null;
        window.onSearchInvoiceKeyUp = function(q) {
            clearTimeout(invSearchTimer);
            if (!q || q.length < 2) {
                document.getElementById('invoiceResultsList').classList.add('hidden');
                return;
            }
            invSearchTimer = setTimeout(() => {
                document.getElementById('invoiceSearchSpinner').classList.remove('hidden');
                fetch("{{ route('finance.purchaseReturns.searchInvoices') }}?query=" + encodeURIComponent(q))
                    .then(res => res.json())
                    .then(results => {
                        document.getElementById('invoiceSearchSpinner').classList.add('hidden');
                        const list = document.getElementById('invoiceResultsList');
                        if (!results || results.length === 0) {
                            list.innerHTML = '<div class="p-3 text-slate-400 text-center">Faktur tidak ditemukan.</div>';
                        } else {
                            list.innerHTML = results.map(r => `
                                <div onclick="selectInvoiceRow(${r.id}, '${escapeHtml(r.invoice_number)}', '${r.invoice_date || ''}', '${r.creditor_code || ''}')"
                                     class="p-2.5 hover:bg-blue-50 cursor-pointer flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-slate-800">${escapeHtml(r.invoice_number)}</div>
                                        <div class="text-[11px] text-slate-500">${escapeHtml(r.creditor_name || r.creditor_code)}</div>
                                    </div>
                                    <span class="text-[10px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-lg border border-blue-200">
                                        Pilih Faktur
                                    </span>
                                </div>
                            `).join('');
                        }
                        list.classList.remove('hidden');
                    })
                    .catch(() => {
                        document.getElementById('invoiceSearchSpinner').classList.add('hidden');
                    });
            }, 300);
        };

        window.selectInvoiceRow = function(id, invNum, invDate, credCode) {
            document.getElementById('invoiceResultsList').classList.add('hidden');
            document.getElementById('invoiceSearchInput').value = invNum;
            document.getElementById('form_invoice_number').value = invNum;
            if (invDate) document.getElementById('form_invoice_date').value = invDate.split('T')[0];
            if (credCode) document.getElementById('form_creditor_code').value = credCode;

            // Fetch items in invoice
            fetch("{{ url('/finance/purchase-returns/invoice-items') }}/" + id)
                .then(res => res.json())
                .then(data => {
                    returnItemsData = (data.items || []).map(it => ({
                        selected: true,
                        medicine_id: it.medicine_id,
                        medicine_name: it.medicine_name,
                        unit: it.unit || 'BOX',
                        batch_number: it.batch || '-',
                        expired_date: it.expired_date || '',
                        max_qty: Number(it.qty_received || it.qty || 1),
                        qty: Number(it.qty_received || it.qty || 1),
                        unit_price: Number(it.unit_price || it.raw_price || 0),
                        reason: 'ED DEKAT',
                        factory_id: it.factory_id || null,
                        factory_name: it.factory_name || null,
                        return_policy: it.return_policy || null,
                    }));
                    renderReturnItemsTable();
                });
        };

        // Manual med search
        let medSearchTimer = null;
        window.onSearchMedKeyUp = function(q) {
            clearTimeout(medSearchTimer);
            if (!q || q.length < 2) {
                document.getElementById('medResultsList').classList.add('hidden');
                return;
            }
            medSearchTimer = setTimeout(() => {
                fetch("{{ route('finance.purchaseReturns.searchMedicines') }}?query=" + encodeURIComponent(q))
                    .then(res => res.json())
                    .then(results => {
                        const list = document.getElementById('medResultsList');
                        if (!results || results.length === 0) {
                            list.innerHTML = '<div class="p-3 text-slate-400 text-center">Obat tidak ditemukan.</div>';
                        } else {
                            list.innerHTML = results.map(m => `
                                <div onclick="addManualMedRow(${m.id}, '${escapeHtml(m.name)}', '${m.unit || 'BOX'}', ${m.raw_price || 0}, ${m.factory_id || 'null'}, '${escapeHtml(m.factory_name || '')}')"
                                     class="p-2.5 hover:bg-blue-50 cursor-pointer flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-slate-800">${escapeHtml(m.name)}</div>
                                        <div class="text-[11px] text-slate-500">Pabrik: ${escapeHtml(m.factory_name || '-')} | Satuan: ${m.unit || 'BOX'}</div>
                                    </div>
                                    <span class="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-200">
                                        + Tambah
                                    </span>
                                </div>
                            `).join('');
                        }
                        list.classList.remove('hidden');
                    });
            }, 300);
        };

        window.addManualMedRow = function(id, name, unit, price, factoryId, factoryName) {
            document.getElementById('medResultsList').classList.add('hidden');
            document.getElementById('medSearchInput').value = '';
            returnItemsData.push({
                selected: true,
                medicine_id: id,
                medicine_name: name,
                unit: unit || 'BOX',
                batch_number: '',
                expired_date: '',
                max_qty: 9999,
                qty: 1,
                unit_price: price || 0,
                reason: 'ED DEKAT',
                factory_id: factoryId,
                factory_name: factoryName,
                return_policy: null,
            });
            renderReturnItemsTable();
        };

        window.renderReturnItemsTable = function() {
            const tbody = document.getElementById('returnItemsTableBody');
            const counter = document.getElementById('selectedItemsCounter');
            
            if (returnItemsData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="7" class="py-8 text-center text-slate-400">Belum ada item dipilih. Cari faktur atau tambah obat manual di atas.</td></tr>`;
                counter.innerText = '0 Item Dipilih';
                return;
            }

            const selectedCount = returnItemsData.filter(i => i.selected).length;
            counter.innerText = `${selectedCount} Item Dipilih`;

            tbody.innerHTML = returnItemsData.map((it, idx) => `
                <tr class="hover:bg-slate-50/70">
                    <td class="p-2.5 text-center">
                        <input type="checkbox" ${it.selected ? 'checked' : ''} onchange="toggleItemSelect(${idx}, this.checked)" class="rounded text-blue-600 focus:ring-blue-500">
                    </td>
                    <td class="p-2.5">
                        <div class="font-bold text-slate-800">${escapeHtml(it.medicine_name)}</div>
                        <div class="text-[10px] text-slate-400">Pabrik: ${escapeHtml(it.factory_name || '-')}</div>
                    </td>
                    <td class="p-2.5">
                        <input type="text" value="${escapeHtml(it.batch_number)}" placeholder="No Batch" onchange="updateItemField(${idx}, 'batch_number', this.value)" class="h-8 text-xs w-28 rounded-lg border border-slate-300 px-2 font-medium">
                        <input type="date" value="${it.expired_date ? it.expired_date.split('T')[0] : ''}" onchange="updateItemField(${idx}, 'expired_date', this.value)" class="h-8 text-xs w-32 rounded-lg border border-slate-300 px-2 mt-1">
                    </td>
                    <td class="p-2.5 text-center">
                        <input type="number" step="any" min="0.01" value="${it.qty}" onchange="updateItemField(${idx}, 'qty', this.value)" class="h-8 text-xs w-16 text-center rounded-lg border border-slate-300 font-bold text-blue-600">
                        <div class="text-[10px] text-slate-400 mt-0.5">${it.unit || 'BOX'}</div>
                    </td>
                    <td class="p-2.5">
                        <input type="number" step="any" min="0" value="${it.unit_price}" onchange="updateItemField(${idx}, 'unit_price', this.value)" class="h-8 text-xs w-24 rounded-lg border border-slate-300 px-2 font-medium">
                    </td>
                    <td class="p-2.5">
                        <select onchange="updateItemField(${idx}, 'reason', this.value)" class="h-8 text-xs rounded-lg border border-slate-300 px-2 font-medium">
                            <option value="ED DEKAT" ${it.reason === 'ED DEKAT' ? 'selected' : ''}>ED DEKAT</option>
                            <option value="Barang Rusak" ${it.reason === 'Barang Rusak' ? 'selected' : ''}>Barang Rusak</option>
                            <option value="Salah Barang" ${it.reason === 'Salah Barang' ? 'selected' : ''}>Salah Barang</option>
                            <option value="Salah Pesan" ${it.reason === 'Salah Pesan' ? 'selected' : ''}>Salah Pesan</option>
                            <option value="Recall" ${it.reason === 'Recall' ? 'selected' : ''}>Recall</option>
                            <option value="Retur Pabrik" ${it.reason === 'Retur Pabrik' ? 'selected' : ''}>Retur Pabrik</option>
                            <option value="Double" ${it.reason === 'Double' ? 'selected' : ''}>Double</option>
                            <option value="dlsbnya" ${it.reason === 'dlsbnya' ? 'selected' : ''}>dlsbnya</option>
                        </select>
                    </td>
                    <td class="p-2.5 text-center">
                        <button type="button" onclick="removeReturnItemRow(${idx})" class="text-rose-500 hover:text-rose-700 font-bold text-xs p-1">
                            ✕
                        </button>
                    </td>
                </tr>
            `).join('');
        };

        window.toggleItemSelect = function(idx, val) {
            returnItemsData[idx].selected = val;
            renderReturnItemsTable();
        };

        window.updateItemField = function(idx, field, val) {
            returnItemsData[idx][field] = val;
        };

        window.removeReturnItemRow = function(idx) {
            returnItemsData.splice(idx, 1);
            renderReturnItemsTable();
        };

        // Submit Create Return
        window.submitCreateReturnForm = function(e) {
            e.preventDefault();
            const selectedItems = returnItemsData.filter(i => i.selected);
            if (selectedItems.length === 0) {
                iziToast.warning({ title: 'Peringatan', message: 'Pilih minimal satu item obat untuk diretur.' });
                return;
            }

            const payload = {
                invoice_number: document.getElementById('form_invoice_number').value,
                creditor_code: document.getElementById('form_creditor_code').value,
                invoice_date: document.getElementById('form_invoice_date').value,
                notes: document.getElementById('form_notes').value,
                items: selectedItems,
            };

            const btn = document.getElementById('btnSubmitCreateReturn');
            btn.disabled = true;
            btn.innerText = 'Menyimpan...';

            fetch("{{ route('finance.purchaseReturns.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                btn.disabled = false;
                btn.innerText = 'Simpan Pengajuan Retur';
                if (data.success !== false) {
                    iziToast.success({ title: 'Berhasil', message: 'Pengajuan retur berhasil disimpan.' });
                    closeCreateReturnModal();
                    setTimeout(() => window.location.reload(), 600);
                } else {
                    iziToast.error({ title: 'Gagal', message: data.message || 'Gagal menyimpan retur.' });
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerText = 'Simpan Pengajuan Retur';
                iziToast.error({ title: 'Error', message: 'Terjadi kesalahan sistem saat menyimpan.' });
            });
        };

        // Quick Apply Return from Approaching Alert
        window.applyReturnFromAlert = function(ap) {
            openCreateReturnModal();
            setEntrySource('manual');
            document.getElementById('form_invoice_number').value = ap.invoice_number && ap.invoice_number !== '-' ? ap.invoice_number : '';
            if (ap.invoice_date) document.getElementById('form_invoice_date').value = ap.invoice_date.split('T')[0];
            if (ap.creditor_code) document.getElementById('form_creditor_code').value = ap.creditor_code;
            document.getElementById('form_notes').value = `Pengajuan retur ED sesuai ketentuan: ${ap.rule_text || ''}`;
            
            returnItemsData = [{
                selected: true,
                medicine_id: ap.medicine_id,
                medicine_name: ap.medicine_name,
                unit: ap.unit || 'BOX',
                batch_number: ap.batch_number || '',
                expired_date: ap.expired_date || '',
                max_qty: Number(ap.stock || 9999),
                qty: Number(ap.stock || 1),
                unit_price: Number(ap.unit_price || 0),
                reason: 'ED DEKAT',
                factory_id: ap.factory_id,
                factory_name: ap.factory_name,
                return_policy: null,
            }];
            renderReturnItemsTable();
        };

        // Modal Ubah Status
        window.openChangeStatusModal = function(it) {
            document.getElementById('status_item_id').value = it.id;
            document.getElementById('status_medicine_name').innerText = it.medicine_name;
            document.getElementById('status_batch_info').innerText = `Batch: ${it.batch_number || '-'} | ED: ${it.formatted_expired_date || '-'}`;
            document.getElementById('status_select').value = it.status && it.status !== 'diajukan' ? it.status : 'dikonfirmasi';
            
            // Tanggal status konsisten dengan tanggal diambil / status saat ini
            let defaultDate = new Date().toISOString().split('T')[0];
            if (it.pickup_date) {
                defaultDate = it.pickup_date.split('T')[0];
            } else if (it.status_date) {
                defaultDate = it.status_date.split('T')[0];
            }
            document.getElementById('status_date').value = defaultDate;
            document.getElementById('status_notes').value = it.notes || '';

            const m = document.getElementById('changeStatusModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        };

        window.filterByStatus = function(statusVal) {
            const sel = document.getElementById('monitoringStatus');
            if (sel) {
                sel.value = statusVal;
                submitMonitoringFilter();
            }
        };

        window.closeChangeStatusModal = function() {
            const m = document.getElementById('changeStatusModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        };

        window.submitChangeStatus = function(e) {
            e.preventDefault();
            const id = document.getElementById('status_item_id').value;
            const payload = {
                status: document.getElementById('status_select').value,
                status_date: document.getElementById('status_date').value,
                notes: document.getElementById('status_notes').value,
            };

            fetch(`{{ url('/finance/purchase-returns/items') }}/${id}/change-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Terjadi kesalahan sistem saat mengubah status.');
                }
                closeChangeStatusModal();
                iziToast.success({ title: 'Berhasil', message: data.message || 'Status retur berhasil diperbarui.' });
                setTimeout(() => window.location.reload(), 500);
            })
            .catch((err) => {
                iziToast.error({ title: 'Gagal', message: err.message || 'Terjadi kesalahan saat mengubah status.' });
            });
        };

        // Modal Konfirmasi (Quick Cut Stock)
        window.openConfirmItemModal = function(it) {
            document.getElementById('confirm_item_id').value = it.id;
            document.getElementById('confirm_medicine_name').innerText = it.medicine_name;
            document.getElementById('confirm_qty_info').innerText = `Qty: ${it.qty} ${it.unit || 'BOX'} | Batch: ${it.batch_number || '-'}`;
            document.getElementById('confirm_date').value = new Date().toISOString().split('T')[0];
            document.getElementById('confirm_notes').value = '';

            const m = document.getElementById('confirmItemModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        };

        window.closeConfirmItemModal = function() {
            const m = document.getElementById('confirmItemModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        };

        window.submitConfirmItem = function(e) {
            e.preventDefault();
            const id = document.getElementById('confirm_item_id').value;
            const payload = {
                confirmed_date: document.getElementById('confirm_date').value,
                notes: document.getElementById('confirm_notes').value,
            };

            fetch(`{{ url('/finance/purchase-returns/items') }}/${id}/confirm`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Terjadi kesalahan saat konfirmasi retur.');
                }
                closeConfirmItemModal();
                iziToast.success({ title: 'Berhasil', message: data.message || 'Retur dikonfirmasi & stok berhasil dipotong.' });
                setTimeout(() => window.location.reload(), 500);
            })
            .catch((err) => {
                iziToast.error({ title: 'Gagal', message: err.message || 'Terjadi kesalahan saat konfirmasi retur.' });
            });
        };

        // Modal Edit Item
        window.openEditItemModal = function(it) {
            document.getElementById('edit_item_id').value = it.id;
            document.getElementById('edit_qty').value = it.qty;
            document.getElementById('edit_unit_price').value = it.unit_price;
            document.getElementById('edit_reason').value = it.reason || 'ED DEKAT';
            document.getElementById('edit_pickup_date').value = it.pickup_date ? it.pickup_date.split('T')[0] : '';
            document.getElementById('edit_deduction_note').value = it.deduction_note || '';

            const m = document.getElementById('editItemModal');
            m.classList.remove('hidden');
            m.classList.add('flex');
        };

        window.closeEditItemModal = function() {
            const m = document.getElementById('editItemModal');
            m.classList.add('hidden');
            m.classList.remove('flex');
        };

        window.submitEditItem = function(e) {
            e.preventDefault();
            const id = document.getElementById('edit_item_id').value;
            const payload = {
                qty: document.getElementById('edit_qty').value,
                unit_price: document.getElementById('edit_unit_price').value,
                reason: document.getElementById('edit_reason').value,
                pickup_date: document.getElementById('edit_pickup_date').value,
                deduction_note: document.getElementById('edit_deduction_note').value,
            };

            fetch(`{{ url('/finance/purchase-returns/items') }}/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Gagal memperbarui rincian retur.');
                }
                closeEditItemModal();
                iziToast.success({ title: 'Berhasil', message: data.message || 'Rincian retur berhasil diperbarui.' });
                setTimeout(() => window.location.reload(), 500);
            })
            .catch((err) => {
                iziToast.error({ title: 'Gagal', message: err.message || 'Terjadi kesalahan saat update.' });
            });
        };

        // Delete Item
        window.deleteItem = function(id) {
            if (!confirm('Yakin ingin menghapus item retur ini?')) return;

            fetch(`{{ url('/finance/purchase-returns/items') }}/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || 'Gagal menghapus item retur.');
                }
                iziToast.success({ title: 'Berhasil', message: data.message || 'Item retur berhasil dihapus.' });
                setTimeout(() => window.location.reload(), 500);
            })
            .catch((err) => {
                iziToast.error({ title: 'Gagal', message: err.message || 'Gagal menghapus item retur.' });
            });
        };

        function escapeHtml(str) {
            if (!str) return '';
            return String(str).replace(/[&<>"']/g, function(m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m];
            });
        }

        $(document).ready(function() {
            $('#monitoringCreditorCode').select2({
                placeholder: '— Semua PBF —',
                allowClear: true,
                width: '100%'
            }).on('change', function() {
                submitMonitoringFilter();
            });
        });
    </script>
@endsection
