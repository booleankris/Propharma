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
    </style>
@endsection

@section('content')
    <div class="p-6 md:p-8 text-slate-800">

        {{-- Page Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 mb-6 border-b border-slate-200">
            <div>
                <div class="flex items-center gap-2 text-xs font-medium text-slate-400 mb-1">
                    <span>Mutasi</span>
                    <svg class="w-3 h-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-sky-600 font-semibold">Daftar Transfer</span>
                </div>
                <h1 class="text-xl font-bold tracking-tight">Transfer Stok Obat</h1>
            </div>
            <a href="{{ route('transfers.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"
                    stroke-linecap="round">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                Buat Transfer Baru
            </a>
        </div>

        {{-- Flash --}}
        @if (session('success'))
            <div
                class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif
        @if (session('message'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium">
                {{ session('message') }}
            </div>
        @endif

        {{-- Filter & Export --}}
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <form action="{{ route('transfers.incoming') }}" method="GET" class="flex flex-wrap items-end gap-3 flex-1">
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Mulai Tanggal</label>
                    <input type="date" name="start_date" value="{{ request('start_date') }}" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Sampai Tanggal</label>
                    <input type="date" name="end_date" value="{{ request('end_date') }}" class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 outline-none focus:border-indigo-500 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">Cari Obat</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Ketik nama obat..." class="text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-1.5 outline-none focus:border-indigo-500 transition w-48">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-lg text-xs font-semibold transition">
                        Terapkan
                    </button>
                    @if(request()->anyFilled(['start_date', 'end_date', 'search']))
                        <a href="{{ route('transfers.incoming') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-1.5 rounded-lg text-xs font-semibold transition flex items-center">
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            <div>
                <a href="{{ route('transfers.export', request()->all()) }}" id="export-excel-btn" class="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-xs font-semibold transition shadow-sm">
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

        {{-- Tabs --}}
        <div class="flex gap-1 mb-6 bg-slate-100 p-1 rounded-xl w-fit">
            @foreach ([['pending', 'Mutasi Keluar', count($pending)], ['accepted', 'Mutasi Masuk', count($accepted)], ['denied', 'Ditolak', count($denied)]] as [$key, $label, $count])
                <button onclick="switchTab('{{ $key }}')" id="tab-btn-{{ $key }}"
                    class="tab-btn px-4 py-2 rounded-lg text-xs font-semibold transition flex items-center gap-2
                {{ $key === 'pending' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    {{ $label }}
                    <span
                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold
                {{ $key === 'pending' ? 'bg-sky-100 text-sky-700' : 'bg-slate-200 text-slate-500' }}">
                        {{ $count }}
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

        {{-- TAB: Mutasi Keluar (Pending) --}}
        <div id="tab-pending" class="tab-panel space-y-4">
            @forelse($pending as $transfer)
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">

                    {{-- Transfer Header --}}
                    <div class="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <span
                                class="font-mono text-xs px-2.5 py-1 bg-white border border-slate-200 rounded-md text-slate-700 font-semibold">
                                {{ $transfer->code }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $transfer->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Transfer Location -->
                            <div class="flex items-center gap-1.5 px-3 py-1 bg-white border border-slate-200 rounded-lg shadow-sm text-xs hidden sm:flex">
                                <span class="font-semibold text-slate-700">{{ $transfer->users?->pharmacy?->name ?? '—' }}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M5 12l14 0" />
                                    <path d="M13 18l6 -6" />
                                    <path d="M13 6l6 6" />
                                </svg>
                                <span class="font-semibold text-indigo-600">{{ $transfer->items->first()?->batches?->pharmacy?->name ?? '—' }}</span>
                            </div>
                            <div class="hidden sm:block h-4 w-px bg-slate-300"></div>
                            
                            <span class="text-xs text-slate-500">Oleh: <span
                                    class="font-semibold text-slate-700">{{ $transfer->users?->name ?? '—' }}</span></span>
                            @php
                                [$statusLabel, $statusClass] = $statusMap[$transfer->status] ?? ['—', ''];
                            @endphp
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </div>

                    {{-- Items Table --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead
                                class="bg-slate-50/50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-5 text-left">Obat</th>
                                    <th class="py-2.5 px-4 text-left">Batch</th>
                                    <th class="py-2.5 px-4 text-left">Etalase</th>
                                    <th class="py-2.5 px-4 text-center">Qty</th>
                                    <th class="py-2.5 px-4 text-center">Status</th>
                                    <th class="py-2.5 px-4 text-center w-40">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse($transfer->items as $item)
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3 px-5">
                                            <div class="font-semibold text-slate-800">
                                                {{ $item->batches?->medicines?->name ?? '—' }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                {{ $item->batches?->medicines?->code ?? '—' }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600">{{ $item->batches?->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-slate-600">{{ $item->etalases?->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-[11px] font-semibold">
                                                {{ $item->qty }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            @php
                                                [$iLabel, $iClass] = $statusMap[$item->status] ?? ['—', ''];
                                            @endphp
                                            <span
                                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $iClass }}">
                                                {{ $iLabel }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            {{-- No actions for outgoing --}}
                                            <span class="text-[10px] text-slate-300">—</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-slate-400 text-xs">Tidak ada item
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination placeholder --}}
                    <div class="px-5 py-3 border-t border-slate-100 text-xs text-slate-400">
                        {{ $pending->links() }}
                    </div>

                </div>
            @empty
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm py-16 text-center">
                    <div class="text-slate-300 mb-3">
                        <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                    <p class="text-sm text-slate-400">Tidak ada transfer keluar</p>
                </div>
            @endforelse
        </div>

        {{-- TAB: Mutasi Masuk (Accepted) --}}
        <div id="tab-accepted" class="tab-panel hidden space-y-4">
            @forelse($accepted as $transfer)
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">

                    <div class="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <span
                                class="font-mono text-xs px-2.5 py-1 bg-white border border-slate-200 rounded-md text-slate-700 font-semibold">
                                {{ $transfer->code }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $transfer->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-slate-500">Dari: <span
                                    class="font-semibold text-slate-700">{{ $transfer->users?->pharmacy?->name ?? '—' }}</span></span>
                            @php [$statusLabel, $statusClass] = $statusMap[$transfer->status] ?? ['—', '']; @endphp
                            <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full border {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                            @if ($transfer->items->contains('status', 0))
                                <form method="POST" action="{{ route('transfers.accept', $transfer) }}" class="ml-2">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-semibold rounded-lg transition shadow-sm" onclick="return confirm('Apakah Anda yakin ingin menerima semua obat yang masih pending pada mutasi ini?')">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Terima Semua Obat
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead
                                class="bg-slate-50/50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-5 text-left">Obat</th>
                                    <th class="py-2.5 px-4 text-left">Batch</th>
                                    <th class="py-2.5 px-4 text-left">Etalase</th>
                                    <th class="py-2.5 px-4 text-center">Qty</th>
                                    <th class="py-2.5 px-4 text-center">Status</th>
                                    <th class="py-2.5 px-4 text-center w-44">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse($transfer->items as $item)
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3 px-5">
                                            <div class="font-semibold text-slate-800">
                                                {{ $item->batches?->medicines?->name ?? '—' }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                {{ $item->batches?->medicines?->code ?? '—' }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600">{{ $item->batches?->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-slate-600">{{ $item->etalases?->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-[11px] font-semibold">
                                                {{ $item->qty }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            @php [$iLabel, $iClass] = $statusMap[$item->status] ?? ['—', '']; @endphp
                                            <span
                                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $iClass }}">
                                                {{ $iLabel }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4">
                                            @if ($item->status === 0)
                                                <div class="flex items-center justify-center gap-2">
                                                    <form method="POST"
                                                        action="{{ route('transfers.acceptItem', $item) }}">
                                                        @csrf
                                                        <button type="submit"
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-semibold rounded-lg transition"
                                                            onclick="return confirm('Terima item ini?')">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2.5"
                                                                stroke-linecap="round">
                                                                <polyline points="20 6 9 17 4 12" />
                                                            </svg>
                                                            Terima
                                                        </button>
                                                    </form>
                                                    <form method="POST"
                                                        action="{{ route('transfers.denyItem', $item) }}">
                                                        @csrf
                                                        <button type="submit"
                                                            class="inline-flex items-center gap-1 px-3 py-1.5 bg-white hover:bg-rose-50 text-rose-500 border border-rose-200 text-[11px] font-semibold rounded-lg transition"
                                                            onclick="return confirm('Tolak item ini?')">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24" stroke-width="2.5"
                                                                stroke-linecap="round">
                                                                <line x1="18" y1="6" x2="6"
                                                                    y2="18" />
                                                                <line x1="6" y1="6" x2="18"
                                                                    y2="18" />
                                                            </svg>
                                                            Tolak
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <div class="text-center text-[10px] text-slate-300">—</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-6 text-center text-slate-400 text-xs">Tidak ada item
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-3 border-t border-slate-100 text-xs text-slate-400">
                        {{ $accepted->links() }}
                    </div>

                </div>
            @empty
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm py-16 text-center">
                    <div class="text-slate-300 mb-3">
                        <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                    <p class="text-sm text-slate-400">Tidak ada transfer masuk</p>
                </div>
            @endforelse
        </div>

        {{-- TAB: Ditolak --}}
        <div id="tab-denied" class="tab-panel hidden space-y-4">
            @forelse($denied as $transfer)
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm overflow-hidden">

                    <div class="flex items-center justify-between px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <div class="flex items-center gap-3">
                            <span
                                class="font-mono text-xs px-2.5 py-1 bg-white border border-slate-200 rounded-md text-slate-700 font-semibold">
                                {{ $transfer->code }}
                            </span>
                            <span class="text-xs text-slate-400">{{ $transfer->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($transfer->users && $transfer->users->pharmacy_id == getActivePharmacyId())
                                <span class="text-xs text-slate-500">Dikirim oleh: <span
                                        class="font-semibold text-slate-700">{{ $transfer->users->name ?? '—' }}</span></span>
                            @else
                                <span class="text-xs text-slate-500">Dari: <span
                                        class="font-semibold text-slate-700">{{ $transfer->users?->pharmacy?->name ?? '—' }}</span></span>
                            @endif
                            <span
                                class="text-[11px] font-semibold px-2.5 py-1 rounded-full border bg-rose-50 text-rose-700 border-rose-200">
                                Ditolak
                            </span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead
                                class="bg-slate-50/50 border-b border-slate-100 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <tr>
                                    <th class="py-2.5 px-5 text-left">Obat</th>
                                    <th class="py-2.5 px-4 text-left">Batch</th>
                                    <th class="py-2.5 px-4 text-left">Etalase</th>
                                    <th class="py-2.5 px-4 text-center">Qty</th>
                                    <th class="py-2.5 px-4 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @forelse($transfer->items->where('status', 2) as $item)
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="py-3 px-5">
                                            <div class="font-semibold text-slate-800">
                                                {{ $item->batches?->medicines?->name ?? '—' }}</div>
                                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                                {{ $item->batches?->medicines?->code ?? '—' }}</div>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600">{{ $item->batches?->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-slate-600">{{ $item->etalases?->name ?? '—' }}</td>
                                        <td class="py-3 px-4 text-center">
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-mono text-[11px] font-semibold">
                                                {{ $item->qty }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            @php [$iLabel, $iClass] = $statusMap[$item->status] ?? ['—', '']; @endphp
                                            <span
                                                class="text-[10px] font-semibold px-2 py-0.5 rounded-full border {{ $iClass }}">
                                                {{ $iLabel }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-slate-400 text-xs">Tidak ada item
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="px-5 py-3 border-t border-slate-100 text-xs text-slate-400">
                        {{ $denied->links() }}
                    </div>

                </div>
            @empty
                <div class="bg-white rounded-xl border border-slate-200/80 shadow-sm py-16 text-center">
                    <div class="text-slate-300 mb-3">
                        <svg class="w-10 h-10 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                    <p class="text-sm text-slate-400">Tidak ada transfer ditolak</p>
                </div>
            @endforelse
        </div>

    </div>
@endsection

@section('scripts')
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script>
        function switchTab(key) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab-btn').forEach(b => {
                b.classList.remove('bg-white', 'text-slate-800', 'shadow-sm');
                b.classList.add('text-slate-500');
            });

            document.getElementById('tab-' + key).classList.remove('hidden');
            const activeBtn = document.getElementById('tab-btn-' + key);
            activeBtn.classList.add('bg-white', 'text-slate-800', 'shadow-sm');
            activeBtn.classList.remove('text-slate-500');

            // Save active tab to URL hash
            window.location.hash = key;
        }

        // Restore active tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.replace('#', '');
            const validTabs = ['pending', 'accepted', 'denied'];
            switchTab(validTabs.includes(hash) ? hash : 'pending');

            // Export Loading State
            const exportBtn = document.getElementById('export-excel-btn');
            if (exportBtn) {
                exportBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    
                    const originalContent = exportBtn.innerHTML;
                    exportBtn.innerHTML = `
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Menyiapkan Export...</span>
                    `;
                    exportBtn.classList.add('opacity-75', 'cursor-not-allowed', 'pointer-events-none');

                    try {
                        const response = await fetch(exportBtn.href);
                        if (!response.ok) throw new Error('Gagal mengunduh');
                        
                        const blob = await response.blob();
                        const url = window.URL.createObjectURL(blob);
                        
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        
                        // Try to parse filename from header
                        let filename = 'Mutasi_Apotek.xlsx';
                        const disposition = response.headers.get('content-disposition');
                        if (disposition && disposition.indexOf('attachment') !== -1) {
                            const filenameRegex = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/;
                            const matches = filenameRegex.exec(disposition);
                            if (matches != null && matches[1]) { 
                                filename = matches[1].replace(/['"]/g, '');
                            }
                        }
                        
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        a.remove();
                        
                        iziToast.success({
                            title: 'Berhasil',
                            message: 'File berhasil diunduh.',
                            position: 'topRight'
                        });
                    } catch (error) {
                        iziToast.error({
                            title: 'Gagal',
                            message: 'Terjadi kesalahan saat mengekspor data.',
                            position: 'topRight'
                        });
                    } finally {
                        exportBtn.innerHTML = originalContent;
                        exportBtn.classList.remove('opacity-75', 'cursor-not-allowed', 'pointer-events-none');
                    }
                });
            }
        });
    </script>
@endsection
