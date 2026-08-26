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

        .near-expiry .select2-container--default .select2-selection--single {
            height: 38px;
            display: flex;
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
        }

        .near-expiry .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 12px;
            color: #334155;
        }

        .near-expiry .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
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


            <div
                class="flex flex-col my-3 gap-4 p-4 bg-white border border-slate-200/80 rounded-xl shadow-xs md:flex-row md:items-center md:justify-between">


                <div class="flex items-center gap-3">
                    <div
                        class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 shrink-0">
                        <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-slate-800 leading-tight">Barang Mendekati Kadaluarsa</h2>
                        <p class="text-xs text-slate-400">Daftar stok dengan expired date terdekat</p>
                    </div>
                </div>


                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">

                    <a href="{{ route('home') }}">


                        <button id="back" type="button"
                            class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-300 transition-all duration-150">
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"></path>
                            </svg>
                            <span>Kembali</span>
                        </button>
                    </a>

                  
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
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="flex flex-col gap-3 p-4 border-b border-slate-100 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:gap-3 sm:flex-1">
                        <label for="medicineSearch"
                            class="text-xs font-semibold text-slate-500 shrink-0">Cari Nama Obat</label>
                        <select id="medicineSearch" class="select2 w-full sm:max-w-sm">
                            @if ($selectedMedicine)
                                <option value="{{ $selectedMedicine->id }}" selected>{{ $selectedMedicine->name }}
                                </option>
                            @endif
                        </select>
                    </div>
                </div>
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
                                    <td class="px-5 py-3 font-semibold text-slate-700">{{ $item->medicines->name ?? '-' }}
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->medicines->unit ?? '-' }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->name }}</td>
                                    <td class="px-5 py-3 text-slate-600">{{ $item->expiry_formatted }}</td>
                                    <td class="px-5 py-3">
                                        @if ($item->expiry_status === 'expired')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-600">Expired
                                                ({{ abs($item->days_left) }} hari lalu)
                                            </span>
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

@section('scripts')
    <script>
        $(function() {
            const $sel = $('#medicineSearch');

            $sel.select2({
                placeholder: 'Ketik nama obat...',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: "{{ route('supplies.medicineSelect') }}",
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term }),
                    processResults: data => ({
                        results: data.map(item => ({
                            id: item.id,
                            text: item.name
                        }))
                    }),
                    cache: true,
                }
            });

            const applyFilter = (medicineId) => {
                const url = new URL(window.location.href);
                if (medicineId) {
                    url.searchParams.set('medicine_id', medicineId);
                } else {
                    url.searchParams.delete('medicine_id');
                }
                window.location.href = url.toString();
            };

            $sel.on('select2:select', e => applyFilter(e.params.data.id));
            $sel.on('select2:clear', () => applyFilter(null));
        });
    </script>
@endsection
