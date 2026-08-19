@php
    $hasSheets = !empty($sheets ?? []);
@endphp

@if($hasSheets)
    {{-- ═══════════════════════════════════════════════════════════════════
         MULTI-SHEET TABBED PREVIEW (e.g. LIPH Online WA / Grab / Shopee)
         ═══════════════════════════════════════════════════════════════════ --}}
    <div>
        {{-- Tab buttons --}}
        <div class="flex gap-1 mb-3 flex-wrap" id="sheet-tabs">
            @foreach(array_keys($sheets) as $idx => $tabTitle)
                <button type="button"
                    onclick="switchSheetTab({{ $idx }})"
                    data-tab-index="{{ $idx }}"
                    class="sheet-tab-btn px-4 py-2 text-sm font-semibold rounded-xl border transition-all
                        {{ $idx === 0
                            ? 'border-slate-800 bg-slate-800 text-white'
                            : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                    {{ $tabTitle }}
                </button>
            @endforeach
        </div>

        {{-- Sheet panels --}}
        @foreach($sheets as $tabTitle => $sheetRows)
            <div class="sheet-panel {{ $loop->first ? '' : 'hidden' }}" data-panel-index="{{ $loop->index }}">
                @include('reports._preview_table', ['rows' => $sheetRows])
            </div>
        @endforeach
    </div>

@else
    {{-- ════════════════════════════════
         SINGLE-SHEET PREVIEW (default)
         ════════════════════════════════ --}}
    @include('reports._preview_table', ['rows' => $rows])
@endif
