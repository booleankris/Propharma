<div class="overflow-x-auto w-full max-h-[60vh] rounded-xl border border-slate-200">
    <table class="w-full text-left text-sm text-slate-600 whitespace-nowrap">
        <thead class="bg-slate-50 sticky top-0 shadow-sm z-10">
            @if(count($rows) > 0)
                <tr>
                    @foreach($rows[0] as $col)
                        <th class="px-4 py-3 font-semibold text-slate-700 border-b border-slate-200">{{ $col }}</th>
                    @endforeach
                </tr>
            @endif
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @foreach(array_slice($rows, 1) as $row)
                <tr class="hover:bg-slate-50 transition-colors">
                    @foreach($row as $col)
                        <td class="px-4 py-3 {{ is_numeric($col) && strlen((string)$col) < 15 ? 'text-right' : '' }}">
                            {{ is_numeric($col) && strlen((string)$col) < 15 ? number_format((float)$col, 0, ',', '.') : $col }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
            @if(count($rows) === 0)
                <tr>
                    <td class="px-4 py-8 text-center text-slate-500">Tidak ada data.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
