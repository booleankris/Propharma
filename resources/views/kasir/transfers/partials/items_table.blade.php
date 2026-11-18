<div class="w-full overflow-x-auto">
    <table class="w-full text-left text-xs sm:text-sm text-slate-600 border-collapse">
        <thead class="bg-slate-100/80 text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
            <tr>
                <th class="py-3.5 px-6 font-bold">Obat</th>
                <th class="py-3.5 px-6 font-bold">Tgl Exp. / Etalase Tujuan</th>
                <th class="py-3.5 px-6 font-bold text-center">Jumlah</th>
                <th class="py-3.5 px-6 font-bold text-center">Status</th>
                <th class="py-3.5 px-6 font-bold text-center w-48">Aksi Konfirmasi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse($transfer->items as $item)
                @php
                    $currentPharmacyId = getActivePharmacyId();
                    $isDestinationPharmacy = ($item->batches?->pharmacy_id == $currentPharmacyId);
                @endphp
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="py-4 px-6">
                        <div class="font-bold text-slate-800 text-sm">
                            {{ $item->batches?->medicines?->name ?? '—' }}
                        </div>
                        <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                            {{ $item->batches?->medicines?->code ?? '—' }}
                        </div>
                    </td>
                    <td class="py-4 px-6">
                        <div class="flex flex-col gap-1.5">
                            <span class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                {{ $item->batches?->expired_date ? \Carbon\Carbon::parse($item->batches->expired_date)->format('d M Y') : '—' }}
                            </span>
                            @php
                                $auditUserName = $item->etalaseUpdater?->name
                                    ?? $item->etalaseUpdater?->username
                                    ?? ($item->etalases_id ? ($transfer->receiver_display_name ?? null) : null);
                                $auditTime = $item->etalase_updated_at?->format('d M Y H:i');
                            @endphp
                            @if ($isDestinationPharmacy && (int) $item->status !== 2)
                                @php
                                    $itemDefault = $itemDefaultEtalases[$item->id] ?? null;
                                    $defaultEtId = $itemDefault['id'] ?? '';
                                    $defaultEtName = $itemDefault['name'] ?? '';
                                    $defaultEtSource = $itemDefault['source'] ?? '';
                                @endphp
                                <div class="inline-flex flex-wrap items-center gap-1.5">
                                    <select class="item-etalase-select text-xs bg-white border border-slate-200 rounded-lg px-2 py-1 text-slate-700 outline-none focus:border-emerald-500 max-w-[180px] transition"
                                        data-transfer-id="{{ $transfer->id }}"
                                        data-item-id="{{ $item->id }}"
                                        data-item-status="{{ $item->status }}"
                                        data-current-etalase-id="{{ $item->etalases_id ?? '' }}"
                                        data-default-etalase-id="{{ $defaultEtId }}"
                                        data-default-etalase-source="{{ $defaultEtSource }}">
                                        <option value="">— Tanpa / Pilih Etalase —</option>
                                        @if (!empty($defaultEtId))
                                            <option value="default">Default ({{ $defaultEtSource === 'previous' ? 'Sebelumnya' : 'Acak' }}: {{ $defaultEtName }})</option>
                                        @endif
                                        @foreach ($etalases ?? [] as $et)
                                            <option value="{{ $et->id }}" @selected($item->etalases_id == $et->id)>{{ $et->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" onclick="window.openManageEtalaseModal(this)"
                                        title="Tambah / ubah / hapus etalase cabang"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-[11px] font-semibold bg-white border border-slate-200 hover:bg-indigo-50 hover:border-indigo-200 hover:text-indigo-700 text-slate-600 rounded-lg transition">
                                        + / Kelola
                                    </button>
                                </div>
                                <span class="item-etalase-audit text-[10px] text-slate-400 {{ $auditUserName ? '' : 'hidden' }}" data-item-id="{{ $item->id }}">
                                    @if ($auditUserName)
                                        Diubah oleh: <strong class="font-medium text-slate-600">{{ $auditUserName }}</strong>@if ($auditTime) ({{ $auditTime }})@endif
                                    @endif
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 text-xs font-medium text-slate-500">
                                    <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                    </svg>
                                    {{ $item->etalases?->name ?? 'Belum dipilih' }}
                                </span>
                                @if ($auditUserName)
                                    <span class="item-etalase-audit text-[10px] text-slate-400" data-item-id="{{ $item->id }}">
                                        Diubah oleh: <strong class="font-medium text-slate-600">{{ $auditUserName }}</strong>@if ($auditTime) ({{ $auditTime }})@endif
                                    </span>
                                @endif
                            @endif
                        </div>
                    </td>
                    <td class="py-4 px-6 text-center whitespace-nowrap">
                        <span class="inline-flex items-center px-3 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-mono text-xs font-bold border border-emerald-200 shadow-sm">
                            {{ rtrim(rtrim(number_format((float) ($item->original_qty ?? $item->qty), 2, ',', '.'), '0'), ',') }} {{ $item->batches?->medicines?->unit ?? '' }}
                        </span>
                    </td>
                    <td class="py-4 px-6 text-center whitespace-nowrap">
                        @php [$iLabel, $iClass] = $statusMap[$item->status] ?? ['—', '']; @endphp
                        <span class="text-[11px] font-semibold px-3 py-1 rounded-full border shadow-sm {{ $iClass }}">
                            {{ $iLabel }}
                        </span>
                    </td>
                    <td class="py-4 px-6 text-center whitespace-nowrap">
                        @if ($item->status === 0 && $isDestinationPharmacy)
                            <div class="flex items-center justify-center gap-2">
                                <form method="POST" action="{{ route('transfers.acceptItem', $item) }}" class="pin-required"
                                    data-accept-item-id="{{ $item->id }}"
                                    data-transfer-id="{{ $transfer->id }}"
                                    data-require-etalase="{{ isWarehousePharmacy() ? '0' : '1' }}"
                                    data-confirm="Terima obat {{ $item->batches?->medicines?->name }} (Qty: {{ rtrim(rtrim(number_format((float) ($item->original_qty ?? $item->qty), 2, ',', '.'), '0'), ',') }})?"
                                    data-pin-title="PIN Terima Mutasi"
                                    data-pin-subtitle="Masukkan PIN pribadi Anda untuk menerima item ini.">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-[11px] font-semibold rounded-lg transition shadow-sm">
                                        <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <polyline points="20 6 9 17 4 12" />
                                        </svg>
                                        Terima
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('transfers.denyItem', $item) }}"
                                    data-modern-confirm="Tolak mutasi {{ $item->batches?->medicines?->name }} (Qty: {{ rtrim(rtrim(number_format((float) ($item->original_qty ?? $item->qty), 2, ',', '.'), '0'), ',') }})?"
                                    data-confirm-title="Tolak Item Mutasi"
                                    data-confirm-ok="Ya, Tolak">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 text-slate-600 text-[11px] font-semibold rounded-lg transition shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Tolak
                                    </button>
                                </form>
                            </div>
                        @elseif ($item->status === 0 && !$isDestinationPharmacy)
                            <span class="text-[11px] text-amber-600 font-medium bg-amber-50 px-2 py-0.5 rounded border border-amber-200">Menunggu Diterima Tujuan</span>
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
