@extends('layouts.app')

@section('title', 'Master Ketentuan Retur')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/select2/dist/css/select2.min.css') }}" />
    <style>
        /* ─── Creative Custom Select2 ─── */
        .select2-container--default .select2-selection--single {
            height: 44px !important;
            border-radius: 0.85rem !important;
            border: 1px solid #cbd5e1 !important;
            background-color: #f8fafc !important;
            display: flex !important;
            align-items: center !important;
            transition: all 0.2s ease !important;
        }
        .select2-container--default .select2-selection--single:hover {
            border-color: #94a3b8 !important;
            background-color: #ffffff !important;
        }
        .select2-container--default.select2-container--focus .select2-selection--single,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #2563eb !important;
            background-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12) !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
            font-size: 0.8125rem !important;
            font-weight: 500 !important;
            padding-left: 0.875rem !important;
            padding-right: 2rem !important;
            line-height: 42px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
            right: 10px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear {
            height: 42px !important;
            line-height: 42px !important;
            right: 26px !important;
            font-size: 14px !important;
            color: #94a3b8 !important;
        }
        .select2-dropdown {
            border-radius: 0.85rem !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 14px 30px -4px rgba(0, 0, 0, 0.12) !important;
            overflow: hidden !important;
            z-index: 10020 !important;
        }
        .select2-search--dropdown {
            padding: 8px !important;
            background: #f8fafc !important;
        }
        .select2-search--dropdown .select2-search__field {
            border-radius: 0.6rem !important;
            border: 1px solid #cbd5e1 !important;
            padding: 7px 10px !important;
            font-size: 0.8125rem !important;
            outline: none !important;
        }
        .select2-results__option--highlighted[aria-selected] {
            background-color: #2563eb !important;
            color: white !important;
        }

        /* ─── Modern Card & Pill Hover Micro-Interactions ─── */
        .interactive-card {
            transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.18s ease;
        }
        .interactive-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px -5px rgba(0, 0, 0, 0.08);
        }
        .status-pill {
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .status-pill:hover {
            transform: translateY(-1px);
        }
    </style>
@endsection

@section('content')
<section class="section px-4 pb-12">
    <div class="section-body space-y-4">

        {{-- ─── Header Panel ─── --}}
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-500 text-white flex items-center justify-center flex-shrink-0 shadow-md shadow-blue-500/20">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg font-bold text-slate-800 tracking-tight">Master Ketentuan Retur Pabrik & PBF</h1>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-600 border border-blue-200">
                            Pembelian
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Pedoman batas waktu (H- bulan sebelum ED) untuk eligibilitas retur obat ke distributor/PBF
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('finance.returnPolicies.export') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all">
                    <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export Excel
                </a>
                <button type="button" onclick="openAddPolicyModal()"
                        class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 rounded-xl shadow-md shadow-blue-500/25 transition-all">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Aturan Baru
                </button>
            </div>
        </div>

        {{-- ─── Interactive Summary Cards (Click to filter) ─── --}}
        <div id="summaryCardsContainer" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Card Total --}}
            <div onclick="filterByStatus('')"
                 class="interactive-card cursor-pointer p-4 rounded-3xl border bg-white shadow-xs flex items-center justify-between {{ $returnableFilter === '' ? 'border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200/80 hover:border-slate-300' }}"
                 title="Klik untuk tampilkan semua aturan">
                <div>
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                        <span>Total Kebijakan Terdaftar</span>
                        @if($returnableFilter === '')
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                        @endif
                    </div>
                    <div class="text-2xl font-black text-slate-800 mt-1.5 tracking-tight">
                        {{ number_format($totalCount, 0, ',', '.') }}
                        <span class="text-xs font-semibold text-slate-400 font-normal">Aturan</span>
                    </div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
            </div>

            {{-- Card Bisa Retur --}}
            <div onclick="filterByStatus('1')"
                 class="interactive-card cursor-pointer p-4 rounded-3xl border bg-white shadow-xs flex items-center justify-between {{ $returnableFilter === '1' ? 'border-emerald-500 ring-2 ring-emerald-500/20 bg-emerald-50/20' : 'border-slate-200/80 hover:border-emerald-300' }}"
                 title="Klik untuk memfilter aturan yang Bisa Diretur">
                <div>
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-700">
                        <span>Bisa Diretur</span>
                        @if($returnableFilter === '1')
                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-100 text-emerald-700">Aktif</span>
                        @endif
                    </div>
                    <div class="text-2xl font-black text-emerald-600 mt-1.5 tracking-tight">
                        {{ number_format($returnableCount, 0, ',', '.') }}
                        <span class="text-xs font-semibold text-emerald-400 font-normal">Aturan</span>
                    </div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            {{-- Card Tidak Bisa Retur --}}
            <div onclick="filterByStatus('0')"
                 class="interactive-card cursor-pointer p-4 rounded-3xl border bg-white shadow-xs flex items-center justify-between {{ $returnableFilter === '0' ? 'border-rose-500 ring-2 ring-rose-500/20 bg-rose-50/20' : 'border-slate-200/80 hover:border-rose-300' }}"
                 title="Klik untuk memfilter aturan yang Tidak Bisa Diretur">
                <div>
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-rose-700">
                        <span>Tidak Bisa Diretur</span>
                        @if($returnableFilter === '0')
                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-100 text-rose-700">Aktif</span>
                        @endif
                    </div>
                    <div class="text-2xl font-black text-rose-600 mt-1.5 tracking-tight">
                        {{ number_format($nonReturnableCount, 0, ',', '.') }}
                        <span class="text-xs font-semibold text-rose-400 font-normal">Aturan</span>
                    </div>
                </div>
                <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        {{-- ─── Creative & Interactive Search Command Bar ─── --}}
        <div class="bg-white rounded-3xl p-5 border border-slate-200/80 shadow-xs space-y-4">
            <form id="filterForm" method="GET" action="{{ route('finance.returnPolicies.index') }}">
                {{-- Hidden input for status pill filter --}}
                <input type="hidden" name="is_returnable" id="hidden_is_returnable" value="{{ $returnableFilter }}">

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-center">
                    {{-- 1. Input Pencarian Teks Bebas --}}
                    <div class="lg:col-span-5 relative">
                        <svg class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text"
                               name="search"
                               id="searchInput"
                               value="{{ $search }}"
                               placeholder="Ketik nama pabrik atau catatan ketentuan..."
                               autocomplete="off"
                               onkeyup="onSearchKeyUp(this.value)"
                               class="w-full pl-11 pr-16 h-11 text-xs font-medium rounded-2xl border border-slate-300 bg-slate-50/60 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500 transition-all">
                        
                        {{-- Clear Button if has text --}}
                        @if(!empty($search))
                            <button type="button"
                                    onclick="clearSearchInput()"
                                    class="absolute right-10 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs px-1"
                                    title="Hapus kata kunci">
                                ✕
                            </button>
                        @endif
                        
                        {{-- Keyboard badge hint --}}
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-mono text-slate-400 bg-slate-200/60 px-1.5 py-0.5 rounded">
                            ↵
                        </span>
                    </div>

                    {{-- 2. Typeable PBF Selector (Select2 with Search) --}}
                    <div class="lg:col-span-5">
                        <select name="creditor_code" id="filterCreditorCode" class="w-full">
                            <option value="">— Semua Distributor (PBF) —</option>
                            @foreach($filterCreditors as $c)
                                <option value="{{ $c->code }}" {{ (string)$creditorFilter === (string)$c->code ? 'selected' : '' }}>
                                    {{ $c->name }} ({{ $c->code }}) — {{ $c->policy_count }} aturan
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 3. Action Buttons --}}
                    <div class="lg:col-span-2 flex items-center gap-2">
                        <button type="submit"
                                class="flex-1 h-11 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold transition-all shadow-sm flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <span>Cari</span>
                        </button>
                        
                        @if(!empty($search) || !empty($creditorFilter) || ($returnableFilter !== null && $returnableFilter !== ''))
                            <a href="{{ route('finance.returnPolicies.index') }}"
                               class="h-11 px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-2xl text-xs font-semibold transition-all flex items-center justify-center"
                               title="Reset semua filter">
                                ✕
                            </a>
                        @endif
                    </div>
                </div>

                {{-- ─── Quick Status Segmented Filter Pills ─── --}}
                <div id="filterPillsContainer" class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-slate-400 font-semibold text-[11px] uppercase tracking-wider mr-1">Status:</span>

                        {{-- Pill: Semua --}}
                        <button type="button"
                                onclick="filterByStatus('')"
                                class="status-pill px-3 py-1.5 rounded-xl font-semibold border text-xs flex items-center gap-1.5 {{ $returnableFilter === '' ? 'bg-slate-800 text-white border-slate-800 shadow-xs' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200' }}">
                            Semua
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $returnableFilter === '' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-700' }}">
                                {{ $totalCount }}
                            </span>
                        </button>

                        {{-- Pill: Bisa Retur --}}
                        <button type="button"
                                onclick="filterByStatus('1')"
                                class="status-pill px-3 py-1.5 rounded-xl font-semibold border text-xs flex items-center gap-1.5 {{ $returnableFilter === '1' ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Bisa Diretur
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $returnableFilter === '1' ? 'bg-white/25 text-white' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ $returnableCount }}
                            </span>
                        </button>

                        {{-- Pill: Tidak Bisa Retur --}}
                        <button type="button"
                                onclick="filterByStatus('0')"
                                class="status-pill px-3 py-1.5 rounded-xl font-semibold border text-xs flex items-center gap-1.5 {{ $returnableFilter === '0' ? 'bg-rose-600 text-white border-rose-600 shadow-xs' : 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100' }}">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            Tidak Bisa Retur
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] {{ $returnableFilter === '0' ? 'bg-white/25 text-white' : 'bg-rose-100 text-rose-800' }}">
                                {{ $nonReturnableCount }}
                            </span>
                        </button>
                    </div>

                    {{-- Active filter indicator summary --}}
                    @if(!empty($search) || !empty($creditorFilter) || ($returnableFilter !== null && $returnableFilter !== ''))
                        <div class="flex items-center gap-2 text-slate-500">
                            <span class="text-xs">Filter aktif:</span>
                            @if(!empty($search))
                                <span class="px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 text-[11px] font-medium">
                                    "{{ $search }}"
                                </span>
                            @endif
                            @if(!empty($creditorFilter))
                                <span class="px-2 py-0.5 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-[11px] font-medium">
                                    PBF: {{ $creditorFilter }}
                                </span>
                            @endif
                            @if($returnableFilter === '1')
                                <span class="px-2 py-0.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-medium">
                                    Bisa Retur
                                </span>
                            @elseif($returnableFilter === '0')
                                <span class="px-2 py-0.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-medium">
                                    Tidak Bisa Retur
                                </span>
                            @endif
                            <a href="{{ route('finance.returnPolicies.index') }}" class="text-blue-600 hover:underline text-xs font-semibold ml-1">
                                Reset
                            </a>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        {{-- ─── Table Content ─── --}}
        <div id="policiesTableContainer" class="bg-white rounded-3xl border border-slate-200/80 shadow-xs overflow-hidden transition-opacity duration-150">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50/90 text-slate-500 uppercase tracking-wider text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="py-4 px-4 w-12 text-center font-bold">#</th>
                            <th class="py-4 px-4 font-bold">Pabrik / Prinsipal</th>
                            <th class="py-4 px-4 font-bold">Distributor (PBF)</th>
                            <th class="py-4 px-4 font-bold text-center">Ketentuan Retur</th>
                            <th class="py-4 px-4 font-bold">Catatan Khusus</th>
                            <th class="py-4 px-4 w-28 text-center font-bold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($policies as $index => $p)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="py-3.5 px-4 text-center text-slate-400 font-medium">
                                    {{ $policies->firstItem() + $index }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-slate-800">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-[11px] flex-shrink-0">
                                            {{ substr($p->factory?->name ?? $p->factory_name ?? 'P', 0, 1) }}
                                        </div>
                                        <span>{{ $p->factory?->name ?? $p->factory_name ?? '-' }}</span>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="font-semibold text-slate-800 block">{{ $p->creditor?->name ?? '-' }}</span>
                                    <span class="inline-block text-[10px] text-blue-600 bg-blue-50 px-2 py-0.5 rounded font-mono font-bold border border-blue-100 mt-0.5">
                                        {{ $p->creditor_code }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    @if($p->is_returnable)
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-2xs">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            H-{{ (int) $p->months_before_ed }} Bulan sebelum ED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200 shadow-2xs">
                                            <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Tidak Bisa Retur
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate" title="{{ $p->notes }}">
                                    {{ $p->notes ?: '-' }}
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button" onclick='openEditPolicyModal(@json($p))'
                                                class="p-2 text-blue-600 hover:bg-blue-50 rounded-xl transition-colors border border-transparent hover:border-blue-100" title="Edit aturan">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <form method="POST" action="{{ route('finance.returnPolicies.destroy', $p->id) }}"
                                              onsubmit="return confirm('Yakin ingin menghapus ketentuan retur untuk {{ addslashes($p->factory?->name ?? $p->factory_name) }} di {{ addslashes($p->creditor?->name ?? $p->creditor_code) }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 text-rose-600 hover:bg-rose-50 rounded-xl transition-colors border border-transparent hover:border-rose-100" title="Hapus aturan">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-14 text-center text-slate-400">
                                    <div class="w-16 h-16 mx-auto mb-3.5 rounded-3xl bg-slate-100 flex items-center justify-center text-slate-400">
                                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </div>
                                    <div class="font-bold text-slate-700 text-sm">Tidak ada data ketentuan retur yang cocok</div>
                                    <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau bersihkan filter yang aktif</p>
                                    <div class="mt-4">
                                        <a href="{{ route('finance.returnPolicies.index') }}"
                                           class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-blue-600 bg-blue-50 hover:bg-blue-100 transition-colors">
                                            Reset Filter
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($policies->hasPages())
                <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <span class="text-xs text-slate-500">
                        Menampilkan <span class="font-bold text-slate-700">{{ $policies->firstItem() }}</span> - <span class="font-bold text-slate-700">{{ $policies->lastItem() }}</span> dari <span class="font-bold text-slate-700">{{ $policies->total() }}</span> data
                    </span>
                    <div>
                        {{ $policies->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

{{-- ─── Modal Tambah / Edit Ketentuan Retur ─── --}}
<div id="policyModal" class="hidden fixed inset-0 z-[10050] flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4 overflow-y-auto">
    <div class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200 overflow-hidden my-8">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between text-white">
            <h3 id="policyModalTitle" class="font-bold text-base">Tambah Ketentuan Retur Baru</h3>
            <button type="button" onclick="closePolicyModal()" class="text-white/80 hover:text-white text-xl font-bold leading-none">&times;</button>
        </div>

        <form id="policyForm" method="POST" action="{{ route('finance.returnPolicies.store') }}" class="p-6 space-y-4">
            @csrf
            <div id="policyMethodPut"></div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Pilih Distributor / PBF <span class="text-rose-500">*</span>
                </label>
                <select name="creditor_code" id="modal_creditor_code" required class="w-full">
                    <option value="">— Ketik & Pilih PBF —</option>
                    @foreach($creditors as $c)
                        <option value="{{ $c->code }}">{{ $c->name }} ({{ $c->code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                    Pabrik (Pilih Master atau Ketik Nama) <span class="text-rose-500">*</span>
                </label>
                <select name="factory_id" id="modal_factory_id" onchange="syncFactoryName(this)" class="w-full mb-2">
                    <option value="">— Pilih Dari Master Pabrik —</option>
                    @foreach($factories as $f)
                        <option value="{{ $f->id }}" data-name="{{ $f->name }}">{{ $f->name }}</option>
                    @endforeach
                </select>
                <input type="text" name="factory_name" id="modal_factory_name" required placeholder="Atau ketik nama pabrik jika belum ada di master..."
                       class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">Status Kebijakan Retur <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="flex items-center gap-2 p-3 border rounded-2xl cursor-pointer hover:bg-slate-50 text-xs font-semibold">
                        <input type="radio" name="is_returnable" value="1" id="radio_returnable_1" checked onchange="toggleReturnableFields(true)">
                        <span class="text-emerald-700">✓ Bisa Diretur</span>
                    </label>
                    <label class="flex items-center gap-2 p-3 border rounded-2xl cursor-pointer hover:bg-slate-50 text-xs font-semibold">
                        <input type="radio" name="is_returnable" value="0" id="radio_returnable_0" onchange="toggleReturnableFields(false)">
                        <span class="text-rose-700">✕ Tidak Bisa Retur</span>
                    </label>
                </div>
            </div>

            <div id="returnableFields" class="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">
                        Batas Waktu Pengajuan (H- Bulan sebelum ED)
                    </label>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500">H -</span>
                        <input type="number" name="months_before_ed" id="modal_months_before_ed" min="0" max="24" value="3"
                               class="w-24 px-3 py-1.5 text-xs rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500">
                        <span class="text-xs text-slate-500">Bulan sebelum expired</span>
                    </div>
                </div>

                <div class="flex items-center gap-2 pt-1">
                    <input type="hidden" name="allow_on_ed_month" value="0">
                    <input type="checkbox" name="allow_on_ed_month" id="modal_allow_on_ed_month" value="1" class="rounded text-blue-600">
                    <label for="modal_allow_on_ed_month" class="text-xs text-slate-600">Boleh diajukan di bulan ED berjalan</label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Catatan Tambahan / Keterangan</label>
                <textarea name="notes" id="modal_notes" rows="2" placeholder="Contoh: Harus ada faktur asli, potongan retur 10%, dll..."
                          class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-500"></textarea>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                <button type="button" onclick="closePolicyModal()"
                        class="px-4 py-2 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                    Batal
                </button>
                <button type="submit"
                        class="px-5 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xs transition-colors">
                    Simpan Ketentuan
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        // ─── Global Modal Controls ───
        window.openAddPolicyModal = function() {
            document.getElementById('policyModalTitle').textContent = 'Tambah Ketentuan Retur Baru';
            document.getElementById('policyForm').action = "{{ route('finance.returnPolicies.store') }}";
            document.getElementById('policyMethodPut').innerHTML = '';
            
            if ($('#modal_creditor_code').data('select2')) {
                $('#modal_creditor_code').val('').trigger('change');
            } else {
                document.getElementById('modal_creditor_code').value = '';
            }

            if ($('#modal_factory_id').data('select2')) {
                $('#modal_factory_id').val('').trigger('change');
            } else {
                document.getElementById('modal_factory_id').value = '';
            }

            document.getElementById('modal_factory_name').value = '';
            document.getElementById('modal_months_before_ed').value = '3';
            document.getElementById('modal_allow_on_ed_month').checked = false;
            document.getElementById('modal_notes').value = '';
            document.getElementById('radio_returnable_1').checked = true;
            toggleReturnableFields(true);
            document.getElementById('policyModal').classList.remove('hidden');
        };

        window.openEditPolicyModal = function(policy) {
            document.getElementById('policyModalTitle').textContent = 'Edit Ketentuan Retur';
            document.getElementById('policyForm').action = "/finance/return-policies/" + policy.id;
            document.getElementById('policyMethodPut').innerHTML = '@method("PUT")';
            
            if ($('#modal_creditor_code').data('select2')) {
                $('#modal_creditor_code').val(policy.creditor_code || '').trigger('change');
            } else {
                document.getElementById('modal_creditor_code').value = policy.creditor_code || '';
            }

            if ($('#modal_factory_id').data('select2')) {
                $('#modal_factory_id').val(policy.factory_id || '').trigger('change');
            } else {
                document.getElementById('modal_factory_id').value = policy.factory_id || '';
            }

            document.getElementById('modal_factory_name').value = policy.factory_name || (policy.factory ? policy.factory.name : '');
            document.getElementById('modal_months_before_ed').value = policy.months_before_ed ?? 0;
            document.getElementById('modal_allow_on_ed_month').checked = !!policy.allow_on_ed_month;
            document.getElementById('modal_notes').value = policy.notes || '';
            
            if (policy.is_returnable) {
                document.getElementById('radio_returnable_1').checked = true;
                toggleReturnableFields(true);
            } else {
                document.getElementById('radio_returnable_0').checked = true;
                toggleReturnableFields(false);
            }
            document.getElementById('policyModal').classList.remove('hidden');
        };

        window.closePolicyModal = function() {
            document.getElementById('policyModal').classList.add('hidden');
        };

        window.syncFactoryName = function(select) {
            const opt = select.options[select.selectedIndex];
            if (opt && opt.dataset.name) {
                document.getElementById('modal_factory_name').value = opt.dataset.name;
            }
        };

        window.toggleReturnableFields = function(isReturnable) {
            const box = document.getElementById('returnableFields');
            if (isReturnable) {
                box.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
            }
        };

        let searchTimer = null;

        window.onSearchKeyUp = function(val) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                performLiveSearch();
            }, 300);
        };

        window.performLiveSearch = function() {
            const form = document.getElementById('filterForm');
            if (!form) return;
            const params = new URLSearchParams(new FormData(form)).toString();
            const url = "{{ route('finance.returnPolicies.index') }}?" + params;

            const container = document.getElementById('policiesTableContainer');
            if (container) container.style.opacity = '0.4';

            fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.text())
            .then(html => {
                const doc = new DOMParser().parseFromString(html, 'text/html');
                
                // 1. Update Table
                const newTable = doc.getElementById('policiesTableContainer');
                if (newTable && container) {
                    container.innerHTML = newTable.innerHTML;
                }

                // 2. Update Summary Cards
                const newCards = doc.getElementById('summaryCardsContainer');
                const oldCards = document.getElementById('summaryCardsContainer');
                if (newCards && oldCards) {
                    oldCards.innerHTML = newCards.innerHTML;
                }

                // 3. Update Status Filter Pills
                const newPills = doc.getElementById('filterPillsContainer');
                const oldPills = document.getElementById('filterPillsContainer');
                if (newPills && oldPills) {
                    oldPills.innerHTML = newPills.innerHTML;
                }

                window.history.replaceState({}, '', url);
                if (container) container.style.opacity = '1';
            })
            .catch(err => {
                console.error('Live search error:', err);
                if (container) container.style.opacity = '1';
            });
        };

        window.filterByStatus = function(statusValue) {
            document.getElementById('hidden_is_returnable').value = statusValue;
            performLiveSearch();
        };

        window.clearSearchInput = function() {
            document.getElementById('searchInput').value = '';
            performLiveSearch();
        };

        // Close modal on click backdrop
        document.getElementById('policyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePolicyModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePolicyModal();
            }
        });

        // ─── Initialize Select2 and Notifications on DOM Ready ───
        $(document).ready(function() {
            // Select2 Filter PBF
            $('#filterCreditorCode').select2({
                placeholder: '— Ketik untuk cari PBF... —',
                allowClear: true,
                width: '100%'
            }).on('change', function() {
                performLiveSearch();
            });

            // Prevent standard form submit on Enter, trigger live search instead
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                performLiveSearch();
            });

            // Intercept pagination clicks for instant live loading
            $(document).on('click', '#policiesTableContainer a', function(e) {
                const href = $(this).attr('href');
                if (!href || href === '#' || $(this).closest('form').length) return;
                
                // Only intercept pagination or query links
                if (href.includes('page=') || href.includes('finance/return-policies')) {
                    e.preventDefault();
                    const container = document.getElementById('policiesTableContainer');
                    if (container) container.style.opacity = '0.4';

                    fetch(href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    })
                    .then(res => res.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const newTable = doc.getElementById('policiesTableContainer');
                        if (newTable && container) {
                            container.innerHTML = newTable.innerHTML;
                        }
                        window.history.replaceState({}, '', href);
                        if (container) container.style.opacity = '1';
                    })
                    .catch(err => {
                        console.error('Pagination click error:', err);
                        if (container) container.style.opacity = '1';
                    });
                }
            });

            // Select2 inside Modal
            $('#modal_creditor_code').select2({
                dropdownParent: $('#policyModal'),
                placeholder: '— Ketik nama / kode PBF —',
                allowClear: true,
                width: '100%'
            });

            $('#modal_factory_id').select2({
                dropdownParent: $('#policyModal'),
                placeholder: '— Ketik nama pabrik —',
                allowClear: true,
                width: '100%'
            }).on('change', function() {
                syncFactoryName(this);
            });

            // Toast Alerts
            @if(session('success'))
                iziToast.success({
                    title: 'Berhasil',
                    message: "{{ session('success') }}",
                    position: 'topRight'
                });
            @endif

            @if(session('error'))
                iziToast.error({
                    title: 'Gagal',
                    message: "{{ session('error') }}",
                    position: 'topRight'
                });
            @endif

            @if(isset($errors) && $errors->any())
                iziToast.error({
                    title: 'Error Validasi',
                    message: "{{ $errors->first() }}",
                    position: 'topRight'
                });
            @endif
        });
    </script>
@endsection
