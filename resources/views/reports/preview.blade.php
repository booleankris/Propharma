<div class="overflow-x-auto w-full max-h-[70vh] border border-gray-400 bg-white shadow-sm" style="font-family: Arial, sans-serif;">
    <table class="w-full text-left text-[12px] text-black whitespace-nowrap border-collapse">
        <tbody class="bg-white">
            @php
                // Cari jumlah kolom terbanyak untuk colspan
                $maxCols = 1;
                foreach($rows as $r) {
                    if (count($r) > $maxCols) {
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

                    if (count($row) > 1) {
                        if (isset($row[0]) && $row[0] === 'No.') {
                            $isHeader = true;
                        }
                        if (isset($row[1]) && str_contains((string)$row[1], 'Sub Total')) {
                            $isSubTotal = true;
                        }
                        if (isset($row[1]) && str_contains((string)$row[1], 'Grand Total')) {
                            $isGrandTotal = true;
                        }
                    }
                    if (count($row) > 1 && $row[0] !== '' && $row[0] !== 'No.' && $row[1] === '') {
                        $isGroupHeader = true;
                    }
                @endphp
                
                <tr class="{{ $isHeader ? 'bg-gray-50 font-bold text-center' : '' }} {{ $isSubTotal || $isGrandTotal ? 'font-bold' : '' }} {{ $isGroupHeader ? 'font-bold' : '' }} {{ $isTitle ? 'font-bold text-[14px]' : '' }}">
                    @if(empty($row))
                        <td class="px-2 py-1 border border-gray-300" colspan="{{ $maxCols }}"></td>
                    @else
                        @if(count($row) === 1)
                            <td class="px-2 py-1.5 border border-gray-300" colspan="{{ $maxCols }}">
                                {{ $row[0] }}
                            </td>
                        @else
                            @foreach($row as $colIndex => $col)
                                @php
                                    $isNumeric = is_numeric($col) && strlen((string)$col) < 15;
                                @endphp
                                <td class="px-2 py-1.5 border border-gray-300 {{ $isNumeric ? 'text-right' : '' }} {{ $isHeader ? 'text-center' : '' }}">
                                    {{ $isNumeric ? number_format((float)$col, 0, ',', '.') : $col }}
                                </td>
                            @endforeach
                        @endif
                    @endif
                </tr>
            @endforeach
            @if(count($rows) === 0)
                <tr>
                    <td class="px-4 py-8 text-center text-gray-500" colspan="100%">Tidak ada data.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
