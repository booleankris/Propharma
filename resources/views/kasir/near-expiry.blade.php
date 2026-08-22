@extends('layouts.app')

@section('title', 'Barang Mendekati Kadaluarsa')

@section('style')
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        .near-expiry,
        .near-expiry * {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
    </style>
@endsection

@section('content')
    <div class="near-expiry mx-4 max-w-full">
        <section>
            {{-- Breadcrumb --}}
            <nav class="flex items-center gap-1.5 mb-3 text-xs text-slate-400">
                <a href="{{ route('home') }}" class="hover:text-blue-600 transition-colors font-medium">Dashboard</a>
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M9 6l6 6l-6 6" />
                </svg>
                <span class="text-slate-500 font-semibold">Barang Mendekati Kadaluarsa</span>
            </nav>

            <div class="flex items-center justify-between gap-3 mb-4">
                <div class="flex items-center gap-3">
                    <a href="{{ route('home') }}"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white text-slate-500 border border-slate-200 hover:bg-slate-50 hover:text-blue-600 hover:border-blue-200 transition-colors">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M5 12l14 0" />
                            <path d="M13 6l6 6l-6 6" />
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-xl font-bold text-slate-800 leading-tight">Barang Mendekati Kadaluarsa</h1>
                        <p class="text-xs text-slate-400">Daftar stok dengan expired date terdekat</p>
                    </div>
                </div>

                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 8v4l2 2" />
                        <path d="M3.05 11a9 9 0 1 1 .5 4m-.5-5v-5h5" />
                    </svg>
                    {{ $items->count() }} item
                </span>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr
                                class="text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100 bg-slate-50/50">
                                <th class="px-5 py-3 font-semibold">#</th>
                                <th class="px-5 py-3 font-semibold">Kode</th>
                                <th class="px-5 py-3 font-semibold">Nama Obat</th>
                                <th class="px-5 py-3 font-semibold">Satuan</th>
                                <th class="px-5 py-3 font-semibold">Batch</th>
                                <th class="px-5 py-3 font-semibold">Expired Date</th>
                                <th class="px-5 py-3 font-semibold">Sisa Hari</th>
                                <th class="px-5 py-3 font-semibold text-right">Stok</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @forelse ($items as $i => $item)
                                <tr class="hover:bg-slate-50/60 transition-colors">
                                    <td class="px-5 py-3 text-slate-400">{{ $items->firstItem() + $i }}</td>
                                    <td class="px-5 py-3">
                                        <span
                                            class="text-[11px] font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">{{ $item->medicines->code ?? '-' }}</span>
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-slate-700">{{ $item->medicines->name ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->medicines->unit ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->expiry_formatted }}</td>
                                    <td class="px-5 py-3">
                                        @if ($item->expiry_status === 'expired')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-600">Expired
                                                ({{ abs($item->days_left) }} hari lalu)</span>
                                        @elseif ($item->expiry_status === 'near')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-600">{{ $item->days_left }}
                                                hari lagi</span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-600">{{ $item->days_left }}
                                                hari lagi</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-slate-700">{{ $item->stock }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-12 text-center text-sm text-slate-400">Tidak ada
                                        barang yang mendekati kadaluarsa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($items->hasPages())
                <div class="mt-4">
                    {{ $items->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
