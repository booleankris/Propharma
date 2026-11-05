@extends('layouts.app')

@section('title', 'Tindak Lanjut Penolakan')

@section('style')
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <style>
        .status-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.45rem center;
            background-size: 0.8rem;
            padding-right: 1.5rem;
        }
        .followup-table-scroll::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        .followup-table-scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 9999px;
        }
        .followup-table-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 9999px;
        }
        .followup-table-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
@endsection

@section('content')
@php
    $stockStatusOptions = $optionsByCategory['stock_status'] ?? [['id' => null, 'label' => 'RETUR ED', 'is_default' => true]];
    $followUpOptions = $optionsByCategory['follow_up'] ?? [['id' => null, 'label' => 'SUDAH DIPESAN', 'is_default' => true]];
    $updateOptions = $optionsByCategory['follow_up_update'] ?? [['id' => null, 'label' => 'Sudah Datang', 'is_default' => true]];
    $exportUrl = route('sales.reject.follow-up.export', request()->query());
@endphp

<section class="section w-full max-w-full min-w-0 px-3 sm:px-5 py-4 space-y-4 overflow-hidden" style="width: 100%; max-width: 100%; min-width: 0;">

    {{-- ─── Header Section ─── --}}
    <div class="w-full max-w-full min-w-0 flex flex-wrap items-center justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shadow-sm shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">Operasional Apotek</span>
                </div>
                <h1 class="text-lg sm:text-xl font-bold text-slate-800 mt-0.5 truncate">Tindak Lanjut Penolakan</h1>
                <p class="text-xs text-slate-500">Kelola <span class="font-semibold text-slate-700">Lokasi</span>, <span class="font-semibold text-slate-700">Status Barang</span>, <span class="font-semibold text-slate-700">Tindak Lanjut</span>, <span class="font-semibold text-slate-700">Keterangan</span>, dan <span class="font-semibold text-slate-700">Update</span>.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="openManageOptionModal('stock_status', null)"
                class="inline-flex items-center gap-1 rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span>Opsi Status Barang</span>
            </button>
            <button type="button" onclick="openManageOptionModal('follow_up', null)"
                class="inline-flex items-center gap-1 rounded-xl border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-800 hover:bg-indigo-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span>Opsi Tindak Lanjut</span>
            </button>
            <button type="button" onclick="openManageOptionModal('follow_up_update', null)"
                class="inline-flex items-center gap-1 rounded-xl border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span>Opsi Update</span>
            </button>
            <a href="{{ $exportUrl }}" id="exportFollowUpBtn"
                class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700 shadow-xs transition-all">
                <svg class="w-4 h-4" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M14 3v4a1 1 0 0 0 1 1h4" />
                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" />
                    <path d="M8 11h8v7h-8z" />
                    <path d="M8 15h8" />
                    <path d="M11 11v7" />
                </svg>
                <span>Export Excel</span>
            </a>
            <a href="{{ route('sales.reject') }}"
                class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 shadow-xs transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                <span>Input Penolakan</span>
            </a>
        </div>
    </div>

    {{-- ─── Summary Cards ─── --}}
    <div class="w-full max-w-full min-w-0 grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-xs flex items-center justify-between min-w-0">
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-slate-500 truncate">Total Data Penolakan</div>
                <div class="text-xl font-black text-slate-800 mt-0.5" id="stat-total">{{ number_format($stats['total'] ?? 0) }}</div>
                <div class="text-[10px] text-slate-400">Seluruh item tercatat</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-2xl border border-amber-200/80 shadow-xs flex items-center justify-between min-w-0">
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-amber-700 truncate">Status Barang Terisi</div>
                <div class="text-xl font-black text-amber-600 mt-0.5" id="stat-stock-status">{{ number_format($stats['stock_status_filled'] ?? 0) }}</div>
                <div class="text-[10px] text-amber-500 truncate">Default: RETUR ED</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center border border-amber-200 shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-2xl border border-indigo-200/80 shadow-xs flex items-center justify-between min-w-0">
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-indigo-700 truncate">Sudah Ditindaklanjuti</div>
                <div class="text-xl font-black text-indigo-600 mt-0.5" id="stat-follow-up">{{ number_format($stats['follow_up_filled'] ?? 0) }}</div>
                <div class="text-[10px] text-indigo-500 truncate">Default: SUDAH DIPESAN</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-200 shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2m-4 0a2 2 0 002 2h2a2 2 0 002-2m-6 8h6m-6 4h6"/></svg>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-2xl border border-emerald-200/80 shadow-xs flex items-center justify-between min-w-0">
            <div class="min-w-0">
                <div class="text-[11px] font-medium text-emerald-700 truncate">Update Kondisi Terkini</div>
                <div class="text-xl font-black text-emerald-600 mt-0.5" id="stat-update">{{ number_format($stats['update_filled'] ?? 0) }}</div>
                <div class="text-[10px] text-emerald-500 truncate">Default: Sudah Datang</div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-200 shrink-0">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
        </div>
    </div>

    {{-- ─── Filter Bar ─── --}}
    <div class="w-full max-w-full min-w-0 bg-white p-3.5 rounded-2xl border border-slate-200/80 shadow-xs">
        <form method="GET" action="{{ route('sales.reject.follow-up') }}" id="filterForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-2.5 items-end">
            <div class="lg:col-span-3">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Cari Obat / Persamaan / Keterangan</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
                        placeholder="Ketik nama obat, komposisi, keterangan..."
                        class="w-full h-9 rounded-xl border border-slate-300 bg-slate-50/60 pl-8 pr-3 text-xs text-slate-700 focus:bg-white focus:border-blue-500 focus:outline-none">
                    <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Dari Tanggal</label>
                <input type="date" name="start_date" value="{{ $filters['start_date'] ?? '' }}"
                    class="w-full h-9 rounded-xl border border-slate-300 bg-slate-50/60 px-2.5 text-xs text-slate-700 focus:bg-white focus:border-blue-500 focus:outline-none">
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Sampai Tanggal</label>
                <input type="date" name="end_date" value="{{ $filters['end_date'] ?? '' }}"
                    class="w-full h-9 rounded-xl border border-slate-300 bg-slate-50/60 px-2.5 text-xs text-slate-700 focus:bg-white focus:border-blue-500 focus:outline-none">
            </div>

            <div class="lg:col-span-1">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Lokasi</label>
                <select name="location" onchange="this.form.submit()"
                    class="w-full h-9 rounded-xl border border-slate-300 bg-slate-50/60 px-2 text-xs text-slate-700 focus:bg-white focus:border-blue-500 focus:outline-none">
                    <option value="">Semua</option>
                    <option value="Offline" @selected(($filters['location'] ?? '') === 'Offline')>Offline</option>
                    <option value="Online" @selected(($filters['location'] ?? '') === 'Online')>Online</option>
                    <option value="__EMPTY__" @selected(($filters['location'] ?? '') === '__EMPTY__')>Kosong</option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Status Barang</label>
                <select name="stock_status" onchange="this.form.submit()"
                    class="w-full h-9 rounded-xl border border-slate-300 bg-slate-50/60 px-2 text-xs text-slate-700 focus:bg-white focus:border-blue-500 focus:outline-none">
                    <option value="">— Semua Status —</option>
                    <option value="__EMPTY__" @selected(($filters['stock_status'] ?? '') === '__EMPTY__')>Belum Diisi</option>
                    @foreach($stockStatusOptions as $opt)
                        <option value="{{ $opt['label'] }}" @selected(($filters['stock_status'] ?? '') === $opt['label'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-1">
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Tindak Lanjut</label>
                <select name="follow_up" onchange="this.form.submit()"
                    class="w-full h-9 rounded-xl border border-slate-300 bg-slate-50/60 px-2 text-xs text-slate-700 focus:bg-white focus:border-blue-500 focus:outline-none">
                    <option value="">Semua</option>
                    <option value="__EMPTY__" @selected(($filters['follow_up'] ?? '') === '__EMPTY__')>Kosong</option>
                    @foreach($followUpOptions as $opt)
                        <option value="{{ $opt['label'] }}" @selected(($filters['follow_up'] ?? '') === $opt['label'])>{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-1 flex items-center gap-1.5">
                <button type="submit"
                    class="h-9 flex-1 rounded-xl bg-blue-600 px-2.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors">
                    Cari
                </button>
                @if(!empty($filters['search']) || !empty($filters['start_date']) || !empty($filters['end_date']) || !empty($filters['location']) || !empty($filters['stock_status']) || !empty($filters['follow_up']) || !empty($filters['follow_up_update']))
                    <a href="{{ route('sales.reject.follow-up') }}"
                        class="h-9 px-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold flex items-center justify-center"
                        title="Reset Filter">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ─── Main Table Card ─── --}}
    <div class="w-full max-w-full min-w-0 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2 bg-slate-50/50">
            <div class="flex items-center gap-2 text-xs text-slate-600 min-w-0">
                <span class="inline-block w-2 h-2 rounded-full bg-blue-600 shrink-0"></span>
                <span class="truncate sm:whitespace-normal">Ubah langsung <strong>Lokasi</strong>, <strong>Status Barang</strong>, <strong>Tindak Lanjut</strong>, <strong>Keterangan</strong>, atau <strong>Update</strong> di tabel, atau klik baris untuk membuka form modal.</span>
            </div>
            <div class="text-xs text-slate-400 font-medium shrink-0">
                Menampilkan {{ $rejects->firstItem() ?? 0 }}–{{ $rejects->lastItem() ?? 0 }} dari {{ $rejects->total() }} data
            </div>
        </div>

        <div class="w-full max-w-full overflow-x-auto followup-table-scroll">
            <table class="w-full min-w-[1180px] border-collapse text-left text-xs">
                <thead>
                    <tr class="bg-slate-100/90 border-b border-slate-200 text-[11px] font-bold text-slate-600 uppercase tracking-wider">
                        <th class="py-3 px-2.5 w-10 text-center border-r border-slate-200/70">No</th>
                        <th class="py-3 px-3 min-w-[175px] border-r border-slate-200/70">Nama Obat</th>
                        <th class="py-3 px-2.5 w-20 text-center border-r border-slate-200/70">Satuan</th>
                        <th class="py-3 px-2.5 w-20 text-center border-r border-slate-200/70">Jml Tolak</th>
                        <th class="py-3 px-2.5 w-28 text-center border-r border-slate-200/70 bg-sky-50/60 text-sky-900">Lokasi</th>
                        <th class="py-3 px-2.5 w-32 text-center border-r border-slate-200/70">Alasan Penolakan</th>
                        <th class="py-3 px-2.5 min-w-[165px] border-r border-slate-200/70 bg-amber-50/50 text-amber-900">
                            <div class="flex items-center justify-between gap-1">
                                <span>Status Barang</span>
                                <button type="button" onclick="openManageOptionModal('stock_status', null)"
                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-amber-200/70 hover:bg-amber-300 text-amber-900 text-[10px] font-bold normal-case transition-colors"
                                    title="Tambah Opsi Status Barang">
                                    + Opsi
                                </button>
                            </div>
                        </th>
                        <th class="py-3 px-3 min-w-[140px] border-r border-slate-200/70">Persamaan</th>
                        <th class="py-3 px-2.5 min-w-[165px] border-r border-slate-200/70 bg-indigo-50/50 text-indigo-900">
                            <div class="flex items-center justify-between gap-1">
                                <span>Tindak Lanjut</span>
                                <button type="button" onclick="openManageOptionModal('follow_up', null)"
                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-indigo-200/70 hover:bg-indigo-300 text-indigo-900 text-[10px] font-bold normal-case transition-colors"
                                    title="Tambah Opsi Tindak Lanjut">
                                    + Opsi
                                </button>
                            </div>
                        </th>
                        <th class="py-3 px-2.5 min-w-[155px] border-r border-slate-200/70">Keterangan</th>
                        <th class="py-3 px-2.5 min-w-[165px] bg-emerald-50/50 text-emerald-900">
                            <div class="flex items-center justify-between gap-1">
                                <span>Update</span>
                                <button type="button" onclick="openManageOptionModal('follow_up_update', null)"
                                    class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-emerald-200/70 hover:bg-emerald-300 text-emerald-900 text-[10px] font-bold normal-case transition-colors"
                                    title="Tambah Opsi Update">
                                    + Opsi
                                </button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/80 text-slate-700">
                    @forelse($rejects as $idx => $reject)
                        @php
                            $medName = $reject->medicines?->name ?? $reject->medicine_name ?? '—';
                            $medCode = $reject->medicines?->code ?? null;
                            $unitName = trim((string) ($reject->unit ?? $reject->medicines?->unit ?? ''));
                            $compositionName = $reject->medicines?->composition?->name
                                ?? $reject->medicines?->component
                                ?? $reject->equivalent
                                ?? '—';
                            $reasonStr = trim((string) ($reject->reason ?? '—'));
                            $reasonUpper = mb_strtoupper($reasonStr);
                            $reasonBadgeClass = match(true) {
                                str_contains($reasonUpper, 'KOSONG DISTRIBUTOR') || str_contains($reasonUpper, 'KOSDIS') => 'bg-pink-100 text-pink-800 border-pink-300',
                                str_contains($reasonUpper, 'DEKAT ED') || str_contains($reasonUpper, 'ED') => 'bg-rose-100 text-rose-800 border-rose-300',
                                str_contains($reasonUpper, 'TIDAK ADA') || str_contains($reasonUpper, 'HABIS') || str_contains($reasonUpper, 'TIDAK STOK') || str_contains($reasonUpper, 'STOK KURANG') => 'bg-amber-50 text-amber-800 border-amber-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                            $formattedDate = $reject->date ? \Carbon\Carbon::parse($reject->date)->format('d/m/Y') : '—';
                            $locVal = $reject->location ?? '';
                        @endphp
                        <tr class="hover:bg-blue-50/30 transition-colors group cursor-pointer"
                            data-row-id="{{ $reject->id }}"
                            data-med-name="{{ $medName }}"
                            data-unit="{{ $unitName ?: '—' }}"
                            data-qty="{{ $reject->quantity }}"
                            data-location="{{ $locVal }}"
                            data-reason="{{ $reasonStr }}"
                            data-composition="{{ $compositionName }}"
                            data-note="{{ $reject->follow_up_note ?? '' }}"
                            data-date="{{ $formattedDate }}"
                            onclick="handleRowClick(event, {{ $reject->id }})">

                            {{-- 1. NO --}}
                            <td class="py-2.5 px-2.5 text-center font-semibold text-slate-500 border-r border-slate-100">
                                {{ $rejects->firstItem() + $idx }}
                            </td>

                            {{-- 2. NAMA OBAT --}}
                            <td class="py-2.5 px-3 border-r border-slate-100">
                                <div class="font-bold text-slate-800 leading-snug">{{ $medName }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-slate-400 font-medium">
                                    <span>{{ $formattedDate }}</span>
                                    @if($medCode)
                                        <span>•</span>
                                        <span class="font-mono">{{ $medCode }}</span>
                                    @endif
                                </div>
                            </td>

                            {{-- 3. SATUAN --}}
                            <td class="py-2.5 px-2.5 text-center border-r border-slate-100 whitespace-nowrap">
                                @if($unitName !== '')
                                    <span class="inline-block font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md text-[11px] border border-slate-200">
                                        {{ $unitName }}
                                    </span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            {{-- 4. JUMLAH PENOLAKAN --}}
                            <td class="py-2.5 px-2.5 text-center border-r border-slate-100 whitespace-nowrap">
                                <span class="inline-block font-bold text-slate-800 text-xs bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                                    {{ $reject->quantity }}
                                </span>
                            </td>

                            {{-- 5. LOKASI (Editable Online / Offline) --}}
                            <td class="py-2 px-2 border-r border-slate-100 bg-sky-50/15" onclick="event.stopPropagation()">
                                <select
                                    id="select-location-{{ $reject->id }}"
                                    data-reject-id="{{ $reject->id }}"
                                    data-prev-val="{{ $locVal }}"
                                    onchange="handleLocationChange(this)"
                                    class="status-select w-full rounded-xl border px-2 py-1.5 text-[11px] font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-sky-300 {{ $locVal === 'Online' ? 'bg-sky-100/90 border-sky-300 text-sky-900' : ($locVal === 'Offline' ? 'bg-slate-200/80 border-slate-300 text-slate-800' : 'bg-white border-slate-200 text-slate-500') }}">
                                    <option value="">— Lokasi —</option>
                                    <option value="Offline" @selected($locVal === 'Offline')>Offline</option>
                                    <option value="Online" @selected($locVal === 'Online')>Online</option>
                                </select>
                            </td>

                            {{-- 6. ALASAN PENOLAKAN --}}
                            <td class="py-2.5 px-2.5 text-center border-r border-slate-100">
                                <span class="inline-block px-2 py-1 rounded-lg text-[11px] font-bold border {{ $reasonBadgeClass }}">
                                    {{ $reasonStr }}
                                </span>
                            </td>

                            {{-- 7. STATUS BARANG (SELECT) --}}
                            <td class="py-2 px-2 border-r border-slate-100 bg-amber-50/15" onclick="event.stopPropagation()">
                                <div class="flex items-center gap-1">
                                    <select
                                        id="select-stock_status-{{ $reject->id }}"
                                        data-reject-id="{{ $reject->id }}"
                                        data-category="stock_status"
                                        data-prev-val="{{ $reject->stock_status ?? '' }}"
                                        onchange="handleSelectChange(this)"
                                        class="status-select w-full rounded-xl border px-2 py-1.5 text-[11px] font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-amber-300 {{ !empty($reject->stock_status) ? 'bg-amber-100/90 border-amber-300 text-amber-900' : 'bg-white border-slate-200 text-slate-500' }}">
                                        <option value="">— Pilih Status —</option>
                                        @foreach($stockStatusOptions as $opt)
                                            <option value="{{ $opt['label'] }}" @selected(($reject->stock_status ?? '') === $opt['label'])>{{ $opt['label'] }}</option>
                                        @endforeach
                                        <option value="__ADD_NEW__" class="font-bold text-blue-600 bg-blue-50">+ Tambah Status Barang...</option>
                                    </select>
                                    <button type="button"
                                        onclick="openManageOptionModal('stock_status', {{ $reject->id }})"
                                        class="p-1.5 rounded-lg border border-amber-200 bg-white hover:bg-amber-100 text-amber-700 shrink-0 transition-colors"
                                        title="Tambah Status Barang Baru">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </div>
                            </td>

                            {{-- 8. PERSAMAAN (medicines.composition) --}}
                            <td class="py-2.5 px-3 border-r border-slate-100">
                                @if($compositionName && $compositionName !== '—')
                                    <span class="font-semibold text-slate-700">{{ $compositionName }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            {{-- 9. TINDAK LANJUT (SELECT) --}}
                            <td class="py-2 px-2 border-r border-slate-100 bg-indigo-50/15" onclick="event.stopPropagation()">
                                <div class="flex items-center gap-1">
                                    <select
                                        id="select-follow_up-{{ $reject->id }}"
                                        data-reject-id="{{ $reject->id }}"
                                        data-category="follow_up"
                                        data-prev-val="{{ $reject->follow_up ?? '' }}"
                                        onchange="handleSelectChange(this)"
                                        class="status-select w-full rounded-xl border px-2 py-1.5 text-[11px] font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-indigo-300 {{ !empty($reject->follow_up) ? 'bg-indigo-100/90 border-indigo-300 text-indigo-900' : 'bg-white border-slate-200 text-slate-500' }}">
                                        <option value="">— Pilih Tindak Lanjut —</option>
                                        @foreach($followUpOptions as $opt)
                                            <option value="{{ $opt['label'] }}" @selected(($reject->follow_up ?? '') === $opt['label'])>{{ $opt['label'] }}</option>
                                        @endforeach
                                        <option value="__ADD_NEW__" class="font-bold text-blue-600 bg-blue-50">+ Tambah Tindak Lanjut...</option>
                                    </select>
                                    <button type="button"
                                        onclick="openManageOptionModal('follow_up', {{ $reject->id }})"
                                        class="p-1.5 rounded-lg border border-indigo-200 bg-white hover:bg-indigo-100 text-indigo-700 shrink-0 transition-colors"
                                        title="Tambah Tindak Lanjut Baru">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </div>
                            </td>

                            {{-- 10. KETERANGAN (Editable Inline) --}}
                            <td class="py-2 px-2 border-r border-slate-100" onclick="event.stopPropagation()">
                                <input
                                    type="text"
                                    id="input-note-{{ $reject->id }}"
                                    data-reject-id="{{ $reject->id }}"
                                    data-prev-val="{{ $reject->follow_up_note ?? '' }}"
                                    value="{{ $reject->follow_up_note ?? '' }}"
                                    placeholder="Ketik keterangan..."
                                    onblur="handleNoteChange(this)"
                                    onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
                                    class="w-full rounded-xl border px-2.5 py-1.5 text-[11px] font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-blue-200 focus:bg-white {{ !empty($reject->follow_up_note) ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-slate-200 text-slate-500' }}"
                                />
                            </td>

                            {{-- 11. UPDATE (SELECT - Kondisi terkini barang tersebut) --}}
                            <td class="py-2 px-2 bg-emerald-50/15" onclick="event.stopPropagation()">
                                <div class="flex items-center gap-1">
                                    <select
                                        id="select-follow_up_update-{{ $reject->id }}"
                                        data-reject-id="{{ $reject->id }}"
                                        data-category="follow_up_update"
                                        data-prev-val="{{ $reject->follow_up_update ?? '' }}"
                                        onchange="handleSelectChange(this)"
                                        class="status-select w-full rounded-xl border px-2 py-1.5 text-[11px] font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-emerald-300 {{ !empty($reject->follow_up_update) ? 'bg-emerald-100/90 border-emerald-300 text-emerald-900' : 'bg-white border-slate-200 text-slate-500' }}">
                                        <option value="">— Pilih Update —</option>
                                        @foreach($updateOptions as $opt)
                                            <option value="{{ $opt['label'] }}" @selected(($reject->follow_up_update ?? '') === $opt['label'])>{{ $opt['label'] }}</option>
                                        @endforeach
                                        <option value="__ADD_NEW__" class="font-bold text-blue-600 bg-blue-50">+ Tambah Update...</option>
                                    </select>
                                    <button type="button"
                                        onclick="openManageOptionModal('follow_up_update', {{ $reject->id }})"
                                        class="p-1.5 rounded-lg border border-emerald-200 bg-white hover:bg-emerald-100 text-emerald-700 shrink-0 transition-colors"
                                        title="Tambah Update Baru">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-14 text-center text-slate-400">
                                <div class="w-12 h-12 mx-auto mb-2.5 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="font-semibold text-slate-600 text-sm">Belum ada data penolakan untuk filter ini.</p>
                                <p class="text-xs text-slate-400 mt-1">Data penolakan yang dicatat di halaman Penolakan Barang akan otomatis tampil di sini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rejects->hasPages())
            <div class="px-4 py-3.5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs text-slate-500">
                    Menampilkan {{ $rejects->firstItem() }} – {{ $rejects->lastItem() }} dari {{ $rejects->total() }} item
                </div>
                <div>
                    {{ $rejects->links() }}
                </div>
            </div>
        @endif
    </div>
</section>

{{-- ═══════════════════════════════════════════════════════════════════════════
     MODAL 1: UBAH STATUS BARIS (Saat klik baris seperti Retur PBF)
═══════════════════════════════════════════════════════════════════════════ --}}
<div id="rowStatusModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-lg overflow-hidden">
        <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600">Ubah Status & Detail Penolakan</span>
                <h3 class="text-base font-bold text-slate-800 mt-0.5" id="rowModalMedName">Nama Obat</h3>
            </div>
            <button type="button" onclick="closeRowStatusModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 flex items-center justify-center">
                ✕
            </button>
        </div>

        <div class="p-5 space-y-3.5 max-h-[80vh] overflow-y-auto">
            {{-- Read-only Info Summary --}}
            <div class="grid grid-cols-3 gap-2.5 bg-slate-50 p-3 rounded-xl border border-slate-200/80 text-xs">
                <div>
                    <span class="text-slate-400 block text-[10px]">Tanggal & Jumlah</span>
                    <span class="font-bold text-slate-700" id="rowModalDateQty">—</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px]">Satuan</span>
                    <span class="font-bold text-slate-700" id="rowModalUnit">—</span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[10px]">Alasan Penolakan</span>
                    <span class="font-bold text-slate-700" id="rowModalReason">—</span>
                </div>
                <div class="col-span-3 pt-1 border-t border-slate-200/60">
                    <span class="text-slate-400 block text-[10px]">Persamaan (Komposisi)</span>
                    <span class="font-semibold text-slate-700" id="rowModalComposition">—</span>
                </div>
            </div>

            <input type="hidden" id="rowModalRejectId">

            {{-- Lokasi & Keterangan --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Lokasi</label>
                    <select id="modal-select-location" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none">
                        <option value="">— Pilih Lokasi —</option>
                        <option value="Offline">Offline</option>
                        <option value="Online">Online</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">Keterangan</label>
                    <input type="text" id="modal-input-note" maxlength="2000" placeholder="Contoh: UPDS / R/ Dr. ..."
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            {{-- 1. Status Barang --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-slate-700">Status Barang <span class="text-[11px] font-normal text-slate-400">(Default: RETUR ED)</span></label>
                    <button type="button" onclick="openManageOptionModal('stock_status', document.getElementById('rowModalRejectId').value)"
                        class="text-[11px] font-bold text-amber-700 hover:text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-2 py-0.5 rounded-lg transition-colors">
                        + Tambah Status Barang
                    </button>
                </div>
                <select id="modal-select-stock_status" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="">— Pilih Status Barang —</option>
                    @foreach($stockStatusOptions as $opt)
                        <option value="{{ $opt['label'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 2. Tindak Lanjut --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-slate-700">Tindak Lanjut <span class="text-[11px] font-normal text-slate-400">(Default: SUDAH DIPESAN)</span></label>
                    <button type="button" onclick="openManageOptionModal('follow_up', document.getElementById('rowModalRejectId').value)"
                        class="text-[11px] font-bold text-indigo-700 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 px-2 py-0.5 rounded-lg transition-colors">
                        + Tambah Tindak Lanjut
                    </button>
                </div>
                <select id="modal-select-follow_up" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="">— Pilih Tindak Lanjut —</option>
                    @foreach($followUpOptions as $opt)
                        <option value="{{ $opt['label'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- 3. Update --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-bold text-slate-700">Update Kondisi Terkini <span class="text-[11px] font-normal text-slate-400">(Default: Sudah Datang)</span></label>
                    <button type="button" onclick="openManageOptionModal('follow_up_update', document.getElementById('rowModalRejectId').value)"
                        class="text-[11px] font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-2 py-0.5 rounded-lg transition-colors">
                        + Tambah Update
                    </button>
                </div>
                <select id="modal-select-follow_up_update" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="">— Pilih Update —</option>
                    @foreach($updateOptions as $opt)
                        <option value="{{ $opt['label'] }}">{{ $opt['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
            <button type="button" onclick="closeRowStatusModal()" class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100">
                Batal
            </button>
            <button type="button" onclick="saveRowStatusModal()" id="rowModalSaveBtn" class="px-5 py-2 rounded-xl bg-blue-600 text-xs font-semibold text-white hover:bg-blue-700 shadow-xs">
                Simpan Perubahan
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════════
     MODAL 2: TAMBAH & KELOLA OPSI CUSTOM (Status Barang / Tindak Lanjut / Update)
═══════════════════════════════════════════════════════════════════════════ --}}
<div id="manageOptionModal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600" id="optionModalSubtitle">Custom Database Option</span>
                <h3 class="text-base font-bold text-slate-800 mt-0.5" id="optionModalTitle">Tambah Opsi Baru</h3>
            </div>
            <button type="button" onclick="closeManageOptionModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 flex items-center justify-center">
                ✕
            </button>
        </div>

        <div class="p-5 space-y-4">
            <input type="hidden" id="optionModalCategory">
            <input type="hidden" id="optionModalRejectId">

            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1.5" id="optionModalInputLabel">Nama Opsi Baru</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="optionModalInput" maxlength="255"
                        placeholder="Ketik opsi baru..."
                        class="flex-1 rounded-xl border border-slate-300 px-3.5 py-2 text-xs font-medium text-slate-800 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <button type="button" onclick="submitNewOption()" id="optionModalSubmitBtn"
                        class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-xs font-semibold text-white shrink-0 shadow-xs transition-colors">
                        + Tambah
                    </button>
                </div>
                <p class="text-[11px] text-slate-400 mt-1" id="optionModalHelper">Opsi yang ditambahkan akan tersimpan di database dan dapat dipilih untuk semua item.</p>
            </div>

            <div>
                <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Daftar Pilihan Tersedia</div>
                <div id="optionModalList" class="max-h-52 overflow-y-auto space-y-1.5 pr-1"></div>
            </div>
        </div>

        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
            <button type="button" onclick="closeManageOptionModal()" class="px-4 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-600 hover:bg-slate-100">
                Selesai
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
<script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    // Store options state per category on client side so newly added options appear immediately
    const optionsState = {
        stock_status: @json($stockStatusOptions),
        follow_up: @json($followUpOptions),
        follow_up_update: @json($updateOptions),
    };

    const categoryMeta = {
        stock_status: {
            title: 'Tambah Status Barang',
            subtitle: 'Kategori: Status Barang (Default: RETUR ED)',
            inputLabel: 'Nama Status Barang Baru',
            placeholder: 'Contoh: DISCONTINUE / PRODUK RECALL / RELASI ED...',
            emptyLabel: '— Pilih Status —',
            addLabel: '+ Tambah Status Barang...',
            activeClasses: 'bg-amber-100/90 border-amber-300 text-amber-900',
            emptyClasses: 'bg-white border-slate-200 text-slate-500',
        },
        follow_up: {
            title: 'Tambah Tindak Lanjut',
            subtitle: 'Kategori: Tindak Lanjut (Default: SUDAH DIPESAN)',
            inputLabel: 'Nama Tindak Lanjut Baru',
            placeholder: 'Contoh: PERMINTAAN DIREALISASIKAN / RESTOCK ED JAUH...',
            emptyLabel: '— Pilih Tindak Lanjut —',
            addLabel: '+ Tambah Tindak Lanjut...',
            activeClasses: 'bg-indigo-100/90 border-indigo-300 text-indigo-900',
            emptyClasses: 'bg-white border-slate-200 text-slate-500',
        },
        follow_up_update: {
            title: 'Tambah Update Kondisi Terkini',
            subtitle: 'Kategori: Update (Default: Sudah Datang)',
            inputLabel: 'Nama Update Baru',
            placeholder: 'Contoh: Sudah Datang / Belum Datang / Kosong PBF...',
            emptyLabel: '— Pilih Update —',
            addLabel: '+ Tambah Update...',
            activeClasses: 'bg-emerald-100/90 border-emerald-300 text-emerald-900',
            emptyClasses: 'bg-white border-slate-200 text-slate-500',
        }
    };

    function applySelectVisualStyle(selectEl) {
        const category = selectEl.dataset.category;
        const meta = categoryMeta[category];
        if (!meta) return;

        const allActive = ['bg-amber-100/90', 'border-amber-300', 'text-amber-900', 'bg-indigo-100/90', 'border-indigo-300', 'text-indigo-900', 'bg-emerald-100/90', 'border-emerald-300', 'text-emerald-900'];
        const allEmpty = ['bg-white', 'border-slate-200', 'text-slate-500'];

        selectEl.classList.remove(...allActive, ...allEmpty);
        if (selectEl.value && selectEl.value !== '__ADD_NEW__') {
            selectEl.classList.add(...meta.activeClasses.split(' '));
        } else {
            selectEl.classList.add(...meta.emptyClasses.split(' '));
        }
    }

    function applyLocationVisualStyle(selectEl) {
        const allClasses = [
            'bg-sky-100/90', 'border-sky-300', 'text-sky-900',
            'bg-slate-200/80', 'border-slate-300', 'text-slate-800',
            'bg-white', 'border-slate-200', 'text-slate-500'
        ];
        selectEl.classList.remove(...allClasses);
        if (selectEl.value === 'Online') {
            selectEl.classList.add('bg-sky-100/90', 'border-sky-300', 'text-sky-900');
        } else if (selectEl.value === 'Offline') {
            selectEl.classList.add('bg-slate-200/80', 'border-slate-300', 'text-slate-800');
        } else {
            selectEl.classList.add('bg-white', 'border-slate-200', 'text-slate-500');
        }
    }

    function applyNoteVisualStyle(inputEl) {
        const hasVal = inputEl.value.trim() !== '';
        inputEl.classList.remove('bg-slate-100', 'border-slate-300', 'text-slate-800', 'bg-white', 'border-slate-200', 'text-slate-500');
        if (hasVal) {
            inputEl.classList.add('bg-slate-100', 'border-slate-300', 'text-slate-800');
        } else {
            inputEl.classList.add('bg-white', 'border-slate-200', 'text-slate-500');
        }
    }

    function handleLocationChange(selectEl) {
        const rejectId = selectEl.dataset.rejectId;
        const prevVal = selectEl.dataset.prevVal || '';
        const newVal = selectEl.value;

        selectEl.disabled = true;
        axios.put(`{{ url('/reject') }}/${rejectId}/follow-up`, { location: newVal }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (res.data.success) {
                selectEl.dataset.prevVal = newVal;
                applyLocationVisualStyle(selectEl);
                const row = document.querySelector(`tr[data-row-id="${rejectId}"]`);
                if (row) row.dataset.location = newVal;
                iziToast.success({
                    title: 'Tersimpan',
                    message: 'Lokasi berhasil diperbarui.',
                    position: 'topRight',
                    timeout: 2000
                });
            }
        })
        .catch(err => {
            selectEl.value = prevVal;
            applyLocationVisualStyle(selectEl);
            iziToast.error({
                title: 'Gagal',
                message: err.response?.data?.message || 'Gagal memperbarui lokasi.',
                position: 'topRight'
            });
        })
        .finally(() => {
            selectEl.disabled = false;
        });
    }

    function handleNoteChange(inputEl) {
        const rejectId = inputEl.dataset.rejectId;
        const prevVal = inputEl.dataset.prevVal ?? '';
        const newVal = inputEl.value.trim();

        if (newVal === prevVal) {
            applyNoteVisualStyle(inputEl);
            return;
        }

        inputEl.disabled = true;
        axios.put(`{{ url('/reject') }}/${rejectId}/follow-up`, { follow_up_note: newVal }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (res.data.success) {
                inputEl.value = newVal;
                inputEl.dataset.prevVal = newVal;
                applyNoteVisualStyle(inputEl);
                const row = document.querySelector(`tr[data-row-id="${rejectId}"]`);
                if (row) row.dataset.note = newVal;
                iziToast.success({
                    title: 'Tersimpan',
                    message: 'Keterangan berhasil diperbarui.',
                    position: 'topRight',
                    timeout: 2000
                });
            }
        })
        .catch(err => {
            inputEl.value = prevVal;
            applyNoteVisualStyle(inputEl);
            iziToast.error({
                title: 'Gagal',
                message: err.response?.data?.message || 'Gagal memperbarui keterangan.',
                position: 'topRight'
            });
        })
        .finally(() => {
            inputEl.disabled = false;
        });
    }

    function handleSelectChange(selectEl) {
        const rejectId = selectEl.dataset.rejectId;
        const category = selectEl.dataset.category;
        const prevVal = selectEl.dataset.prevVal || '';
        const newVal = selectEl.value;

        if (newVal === '__ADD_NEW__') {
            selectEl.value = prevVal;
            applySelectVisualStyle(selectEl);
            openManageOptionModal(category, rejectId);
            return;
        }

        saveFieldUpdate(rejectId, { [category]: newVal }, selectEl, prevVal);
    }

    function saveFieldUpdate(rejectId, payload, selectEl = null, prevVal = '') {
        if (selectEl) {
            selectEl.disabled = true;
        }

        axios.put(`{{ url('/reject') }}/${rejectId}/follow-up`, payload, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (res.data.success) {
                if (selectEl) {
                    selectEl.dataset.prevVal = selectEl.value;
                    applySelectVisualStyle(selectEl);
                }
                iziToast.success({
                    title: 'Tersimpan',
                    message: res.data.message || 'Status berhasil diperbarui.',
                    position: 'topRight',
                    timeout: 2200
                });
            }
        })
        .catch(err => {
            console.error(err);
            if (selectEl) {
                selectEl.value = prevVal;
                applySelectVisualStyle(selectEl);
            }
            iziToast.error({
                title: 'Gagal',
                message: err.response?.data?.message || 'Gagal memperbarui status.',
                position: 'topRight'
            });
        })
        .finally(() => {
            if (selectEl) {
                selectEl.disabled = false;
            }
        });
    }

    function handleRowClick(event, rejectId) {
        const tag = event.target.tagName.toLowerCase();
        if (['select', 'button', 'option', 'svg', 'path', 'input', 'a'].includes(tag)) {
            return;
        }
        openRowStatusModal(rejectId);
    }

    function openRowStatusModal(rejectId) {
        const row = document.querySelector(`tr[data-row-id="${rejectId}"]`);
        if (!row) return;

        document.getElementById('rowModalRejectId').value = rejectId;
        document.getElementById('rowModalMedName').textContent = row.dataset.medName || '—';
        document.getElementById('rowModalDateQty').textContent = `${row.dataset.date || '—'} • ${row.dataset.qty || '0'}`;
        document.getElementById('rowModalUnit').textContent = row.dataset.unit || '—';
        document.getElementById('rowModalReason').textContent = row.dataset.reason || '—';
        document.getElementById('rowModalComposition').textContent = row.dataset.composition || '—';

        const rowLocSelect = document.getElementById(`select-location-${rejectId}`);
        const modalLocSelect = document.getElementById('modal-select-location');
        if (modalLocSelect) {
            modalLocSelect.value = rowLocSelect ? rowLocSelect.value : (row.dataset.location || '');
        }

        const rowNoteInput = document.getElementById(`input-note-${rejectId}`);
        const modalNoteInput = document.getElementById('modal-input-note');
        if (modalNoteInput) {
            modalNoteInput.value = rowNoteInput ? rowNoteInput.value : (row.dataset.note || '');
        }

        ['stock_status', 'follow_up', 'follow_up_update'].forEach(cat => {
            const rowSelect = document.getElementById(`select-${cat}-${rejectId}`);
            const modalSelect = document.getElementById(`modal-select-${cat}`);
            if (rowSelect && modalSelect) {
                modalSelect.value = rowSelect.value;
            }
        });

        const modal = document.getElementById('rowStatusModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeRowStatusModal() {
        const modal = document.getElementById('rowStatusModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function saveRowStatusModal() {
        const rejectId = document.getElementById('rowModalRejectId').value;
        if (!rejectId) return;

        const payload = {
            location: document.getElementById('modal-select-location').value,
            follow_up_note: document.getElementById('modal-input-note').value.trim(),
            stock_status: document.getElementById('modal-select-stock_status').value,
            follow_up: document.getElementById('modal-select-follow_up').value,
            follow_up_update: document.getElementById('modal-select-follow_up_update').value,
        };

        const btn = document.getElementById('rowModalSaveBtn');
        btn.disabled = true;
        btn.textContent = 'Menyimpan...';

        axios.put(`{{ url('/reject') }}/${rejectId}/follow-up`, payload, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (res.data.success) {
                const row = document.querySelector(`tr[data-row-id="${rejectId}"]`);
                if (row) {
                    row.dataset.location = payload.location || '';
                    row.dataset.note = payload.follow_up_note || '';
                }

                const rowLocSelect = document.getElementById(`select-location-${rejectId}`);
                if (rowLocSelect) {
                    rowLocSelect.value = payload.location || '';
                    rowLocSelect.dataset.prevVal = rowLocSelect.value;
                    applyLocationVisualStyle(rowLocSelect);
                }

                const rowNoteInput = document.getElementById(`input-note-${rejectId}`);
                if (rowNoteInput) {
                    rowNoteInput.value = payload.follow_up_note || '';
                    rowNoteInput.dataset.prevVal = rowNoteInput.value;
                    applyNoteVisualStyle(rowNoteInput);
                }

                ['stock_status', 'follow_up', 'follow_up_update'].forEach(cat => {
                    const rowSelect = document.getElementById(`select-${cat}-${rejectId}`);
                    if (rowSelect) {
                        rowSelect.value = payload[cat] || '';
                        rowSelect.dataset.prevVal = rowSelect.value;
                        applySelectVisualStyle(rowSelect);
                    }
                });
                closeRowStatusModal();
                iziToast.success({
                    title: 'Berhasil',
                    message: 'Tindak lanjut penolakan berhasil diperbarui.',
                    position: 'topRight',
                    timeout: 2200
                });
            }
        })
        .catch(err => {
            iziToast.error({
                title: 'Gagal',
                message: err.response?.data?.message || 'Gagal menyimpan perubahan.',
                position: 'topRight'
            });
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Simpan Perubahan';
        });
    }

    function openManageOptionModal(category, rejectId = null) {
        const meta = categoryMeta[category];
        if (!meta) return;

        document.getElementById('optionModalCategory').value = category;
        document.getElementById('optionModalRejectId').value = rejectId || '';
        document.getElementById('optionModalTitle').textContent = meta.title;
        document.getElementById('optionModalSubtitle').textContent = meta.subtitle;
        document.getElementById('optionModalInputLabel').textContent = meta.inputLabel;

        const input = document.getElementById('optionModalInput');
        input.value = '';
        input.placeholder = meta.placeholder;

        renderOptionModalList(category, rejectId);

        const modal = document.getElementById('manageOptionModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => input.focus(), 50);
    }

    function closeManageOptionModal() {
        const modal = document.getElementById('manageOptionModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function renderOptionModalList(category, rejectId = null) {
        const listEl = document.getElementById('optionModalList');
        const items = optionsState[category] || [];

        if (!items.length) {
            listEl.innerHTML = `<div class="text-xs text-slate-400 italic py-2">Belum ada opsi.</div>`;
            return;
        }

        listEl.innerHTML = items.map(item => {
            const safeLabel = String(item.label).replace(/"/g, '&quot;');
            const badge = item.is_default
                ? `<span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-200">Default</span>`
                : (item.id
                    ? `<button type="button" onclick="deleteCustomOption(${item.id}, '${category}')" class="text-[11px] text-rose-500 hover:text-rose-700 px-1.5 py-0.5 rounded hover:bg-rose-50 font-semibold" title="Hapus opsi ini">Hapus</button>`
                    : `<span class="text-[10px] text-slate-400">Tersimpan</span>`);

            const pickBtn = rejectId
                ? `<button type="button" onclick="selectOptionFromModal('${category}', '${safeLabel.replace(/'/g, "\\'")}', ${rejectId})" class="text-[11px] font-semibold text-blue-600 hover:underline mr-2">Pilih</button>`
                : '';

            return `
                <div class="flex items-center justify-between px-3 py-2 rounded-xl border border-slate-200 bg-slate-50/70 hover:bg-white transition-colors">
                    <span class="text-xs font-semibold text-slate-700">${safeLabel}</span>
                    <div class="flex items-center gap-1">
                        ${pickBtn}
                        ${badge}
                    </div>
                </div>
            `;
        }).join('');
    }

    function selectOptionFromModal(category, label, rejectId) {
        const rowSelect = document.getElementById(`select-${category}-${rejectId}`);
        if (rowSelect) {
            rowSelect.value = label;
            handleSelectChange(rowSelect);
        }
        const modalSelect = document.getElementById(`modal-select-${category}`);
        if (modalSelect) {
            modalSelect.value = label;
        }
        closeManageOptionModal();
    }

    function rebuildAllSelectsForCategory(category) {
        const meta = categoryMeta[category];
        const items = optionsState[category] || [];

        // Update all row selects
        document.querySelectorAll(`select[data-category="${category}"]`).forEach(selectEl => {
            const currentVal = selectEl.dataset.prevVal ?? selectEl.value;
            let html = `<option value="">${meta.emptyLabel}</option>`;
            items.forEach(opt => {
                const escaped = String(opt.label).replace(/"/g, '&quot;');
                const selected = currentVal === opt.label ? 'selected' : '';
                html += `<option value="${escaped}" ${selected}>${escaped}</option>`;
            });
            html += `<option value="__ADD_NEW__" class="font-bold text-blue-600 bg-blue-50">${meta.addLabel}</option>`;
            selectEl.innerHTML = html;
            selectEl.value = currentVal || '';
            applySelectVisualStyle(selectEl);
        });

        // Update modal select
        const modalSelect = document.getElementById(`modal-select-${category}`);
        if (modalSelect) {
            const currentModalVal = modalSelect.value;
            let mHtml = `<option value="">${meta.emptyLabel}</option>`;
            items.forEach(opt => {
                const escaped = String(opt.label).replace(/"/g, '&quot;');
                const selected = currentModalVal === opt.label ? 'selected' : '';
                mHtml += `<option value="${escaped}" ${selected}>${escaped}</option>`;
            });
            modalSelect.innerHTML = mHtml;
            modalSelect.value = currentModalVal || '';
        }
    }

    function submitNewOption() {
        const category = document.getElementById('optionModalCategory').value;
        const rejectId = document.getElementById('optionModalRejectId').value;
        const input = document.getElementById('optionModalInput');
        const label = input.value.trim();

        if (!label) {
            iziToast.warning({
                title: 'Perhatian',
                message: 'Silakan ketik nama opsi terlebih dahulu.',
                position: 'topRight'
            });
            input.focus();
            return;
        }

        const btn = document.getElementById('optionModalSubmitBtn');
        btn.disabled = true;

        axios.post(`{{ route('sales.reject.follow-up.options.store') }}`, {
            category: category,
            label: label,
            reject_id: rejectId ? Number(rejectId) : null,
        }, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (res.data.success && res.data.option) {
                const newOpt = res.data.option;
                const exists = (optionsState[category] || []).some(
                    o => o.label.toUpperCase() === newOpt.label.toUpperCase()
                );
                if (!exists) {
                    optionsState[category].push(newOpt);
                }

                if (rejectId) {
                    const rowSelect = document.getElementById(`select-${category}-${rejectId}`);
                    if (rowSelect) {
                        rowSelect.dataset.prevVal = newOpt.label;
                    }
                }

                rebuildAllSelectsForCategory(category);

                if (rejectId) {
                    const rowSelect = document.getElementById(`select-${category}-${rejectId}`);
                    if (rowSelect) {
                        rowSelect.value = newOpt.label;
                        rowSelect.dataset.prevVal = newOpt.label;
                        applySelectVisualStyle(rowSelect);
                    }
                    const modalSelect = document.getElementById(`modal-select-${category}`);
                    if (modalSelect) {
                        modalSelect.value = newOpt.label;
                    }
                }

                input.value = '';
                renderOptionModalList(category, rejectId);

                iziToast.success({
                    title: 'Berhasil',
                    message: rejectId
                        ? `Opsi "${newOpt.label}" ditambahkan & dipilih.`
                        : `Opsi "${newOpt.label}" berhasil disimpan ke database.`,
                    position: 'topRight'
                });

                if (rejectId) {
                    closeManageOptionModal();
                }
            }
        })
        .catch(err => {
            iziToast.error({
                title: 'Gagal',
                message: err.response?.data?.message || 'Gagal menambahkan opsi baru.',
                position: 'topRight'
            });
        })
        .finally(() => {
            btn.disabled = false;
        });
    }

    function deleteCustomOption(optionId, category) {
        axios.delete(`{{ url('/reject/follow-up/options') }}/${optionId}`, {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (res.data.success) {
                optionsState[category] = (optionsState[category] || []).filter(o => o.id !== optionId);
                rebuildAllSelectsForCategory(category);
                const rejectId = document.getElementById('optionModalRejectId').value;
                renderOptionModalList(category, rejectId);
                iziToast.success({
                    title: 'Dihapus',
                    message: 'Opsi custom berhasil dihapus.',
                    position: 'topRight'
                });
            }
        })
        .catch(err => {
            iziToast.error({
                title: 'Gagal',
                message: 'Gagal menghapus opsi.',
                position: 'topRight'
            });
        });
    }

    document.getElementById('optionModalInput')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitNewOption();
        }
    });
</script>
@endsection
