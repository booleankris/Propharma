<div class="overflow-x-auto">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-100/50 text-[11px] uppercase tracking-wider text-slate-500">
            <tr>
                <th class="py-3 px-4 font-semibold rounded-tl-xl">Obat</th>
                <th class="py-3 px-4 font-semibold">Tgl Exp. / Etalase Tujuan</th>
                <th class="py-3 px-4 font-semibold text-center">Jumlah</th>
                <th class="py-3 px-4 font-semibold text-center">Status</th>
                <th class="py-3 px-4 font-semibold text-center rounded-tr-xl w-32">Aksi Konfirmasi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($transfer->items as $item)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="py-3 px-5">
                        <div class="font-semibold text-slate-800">
                            {{ $item->batches?->medicines?->name ?? '—' }}
                        </div>
                        <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                            {{ $item->batches?->medicines?->code ?? '—' }}
                        </div>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex flex-col gap-1">
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-600">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                {{ $item->batches?->expired_date ? \Carbon\Carbon::parse($item->batches->expired_date)->format('d M Y') : '—' }}
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-xs text-slate-500">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                {{ $item->etalases?->name ?? '—' }}
                            </span>
                        </div>
                    </td>
                    <td class="py-3 px-4 text-center">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-mono text-xs font-bold border border-emerald-200">
                            {{ $item->qty }} {{ $item->batches?->medicines?->unit ?? '' }}
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
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 text-slate-600 text-[11px] font-semibold rounded-lg transition shadow-sm"
                                        onclick="return confirm('Tolak mutasi {{ $item->batches?->medicines?->name }} (Qty: {{ $item->qty }})?')">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
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
                    <td colspan="5" class="py-8 text-center text-slate-400 text-sm">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 12H4M8 16l-4-4 4-4" />
                            </svg>
                            Tidak ada item mutasi.
                        </div>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
