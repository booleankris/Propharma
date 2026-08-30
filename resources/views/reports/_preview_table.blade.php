{{-- Reusable table renderer for report preview (single sheet) --}}
<div class="overflow-x-auto w-full max-h-[70vh] border border-slate-300 bg-white shadow-sm rounded-lg" style="font-family: Arial, sans-serif;">
    <table class="w-full text-left text-[12px] text-slate-800 whitespace-nowrap border-collapse">
        <tbody class="bg-white">
            @php
                // Cari jumlah kolom terbanyak untuk colspan
                $maxCols = 1;
                foreach($rows as $r) {
                    if (is_array($r) && count($r) > $maxCols) {
                        $maxCols = count($r);
                    }
                }
            @endphp
            @foreach($rows as $rowIndex => $row)
                @php
                    $isHeader = false;
                    $isSubTotal = false;
                    $isGrandTotal = false;
                    $isGroupHeader = false;
                    $isTitle = $rowIndex === 0;

                    if (is_array($row) && count($row) > 1) {
                        $firstCell = trim((string)($row[0] ?? ''));
                        $secondCell = trim((string)($row[1] ?? ''));

                        if (in_array(strtolower($firstCell), ['no', 'no.', 'no '])) {
                            $isHeader = true;
                        }

                        if (stripos($secondCell, 'sub total') !== false || stripos($firstCell, 'sub total') !== false) {
                            $isSubTotal = true;
                        }
                        if (stripos($secondCell, 'total') !== false || stripos($secondCell, 'grand total') !== false || stripos($firstCell, 'total') !== false) {
                            $isGrandTotal = true;
                        }
                        if ($firstCell !== '' && !in_array(strtolower($firstCell), ['no', 'no.']) && $secondCell === '') {
                            $isGroupHeader = true;
                        }
                    }
                @endphp
                
                <tr class="{{ $isHeader ? 'bg-slate-100 font-bold text-slate-900 border-b border-slate-300 sticky top-0' : '' }} {{ $isSubTotal || $isGrandTotal ? 'bg-slate-50 font-bold text-slate-900 border-t-2 border-slate-400' : '' }} {{ $isGroupHeader ? 'bg-slate-50 font-bold text-slate-800' : '' }} {{ $isTitle ? 'font-bold text-[14px] text-slate-900' : '' }}">
                    @if(empty($row))
                        <td class="px-2 py-1 border border-slate-200" colspan="{{ $maxCols }}"></td>
                    @else
                        @if(!is_array($row) || count($row) === 1)
                            <td class="px-3 py-1.5 border border-slate-200" colspan="{{ $maxCols }}">
                                {{ is_array($row) ? ($row[0] ?? '') : $row }}
                            </td>
                        @else
                            @foreach($row as $colIndex => $col)
                                @php
                                    $colStr = trim((string)$col);
                                    $isNumeric = is_numeric($col) && $colStr !== '';
                                    $isNoCol = ($colIndex === 0 && !$isHeader && $colStr !== '');
                                @endphp
                                <td class="px-3 py-1.5 border border-slate-200 {{ $isHeader ? 'text-center' : ($isNoCol ? 'text-center' : ($isNumeric ? 'text-right' : 'text-left')) }}">
                                    @if($isHeader || $isNoCol)
                                        {{ $col }}
                                    @elseif($isNumeric)
                                        {{ number_format((float)$col, str_contains($colStr, '.') && fmod((float)$col, 1) !== 0.0 ? 2 : 0, ',', '.') }}
                                    @else
                                        {{ $col }}
                                    @endif
                                </td>
                            @endforeach
                        @endif
                    @endif
                </tr>
            @endforeach
            @if(count($rows) === 0)
                <tr>
                    <td class="px-4 py-8 text-center text-slate-500" colspan="100%">Tidak ada data.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
