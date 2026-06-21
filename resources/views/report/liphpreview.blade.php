@extends('layouts.app')

@section('content')
    {{--
    Fragment view — no @extends/@layout.
    Intended to be fetched via AJAX (axios/jQuery) and injected into a modal
    or a panel container (e.g. #report-preview-modal .modal-body).

    Controller should return this view directly:
        return view('reports.liph-preview', [
            'rows'        => $export->array(),
            'pharmacyName'=> $pharmacy->name,
            'queryParams' => $request->except('mode'),
        ]);
--}}

    <div id="liph-preview" class="space-y-4">

        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold tracking-widest uppercase text-slate-400">Pratinjau Laporan</p>
                <h2 class="text-base font-bold text-slate-800">LIPH — {{ $pharmacyName ?? '' }}</h2>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" onclick="downloadLiphReport()"
                    class="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold transition-all">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                    </svg>
                    Download Excel
                </button>

                <button type="button" onclick="closeReportPreview()"
                    class="flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-500 transition-all">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18" />
                        <line x1="6" y1="6" x2="18" y2="18" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-100 bg-white overflow-hidden">
            <div class="overflow-x-auto max-h-[60vh] overflow-y-auto">
                <table class="min-w-full text-xs border-collapse">
                    <tbody>
                        @foreach ($rows as $index => $row)
                            @php
                                $colA = $row[0] ?? '';
                                $colB = $row[1] ?? '';
                                $isTopHeader = $index <= 4;
                                $isBlankRow = collect($row)->filter(fn($v) => $v !== '' && $v !== null)->isEmpty();
                                $isTableHeader = $index === 6;
                                $isSectionRow = in_array($colA, ['Penjualan Kredit', 'Penjualan Tunai']);
                                $isSubTotal = $colB === 'Sub Total';
                                $isGrandTotal = $colB === 'Grand Total';
                            @endphp

                            @if ($isBlankRow)
                                <tr>
                                    <td colspan="8" class="py-1"></td>
                                </tr>
                            @elseif ($isTopHeader)
                                <tr>
                                    <td colspan="8"
                                        class="px-3 py-1.5 text-slate-700 {{ $index === 0 ? 'text-sm font-bold' : ($index === 3 ? 'font-semibold' : 'text-slate-400') }}">
                                        {{ $colA }}
                                    </td>
                                </tr>
                            @elseif ($isTableHeader)
                                <tr
                                    class="bg-slate-50 text-slate-500 font-semibold text-center uppercase tracking-wide text-[10px]">
                                    @foreach ($row as $cell)
                                        <td class="px-3 py-2.5 border-y border-slate-100">{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @elseif ($isSectionRow)
                                <tr>
                                    <td colspan="8" class="px-3 py-2 font-bold text-violet-700 bg-violet-50/50">
                                        {{ $colA }}
                                    </td>
                                </tr>
                            @elseif ($isSubTotal || $isGrandTotal)
                                <tr
                                    class="font-bold {{ $isGrandTotal ? 'bg-violet-50 border-y-2 border-violet-200 text-violet-800' : 'bg-slate-50 border-y border-slate-200 text-slate-700' }}">
                                    <td class="px-3 py-2 text-center" colspan="2">{{ $colB }}</td>
                                    @foreach (array_slice($row, 2) as $cell)
                                        <td class="px-3 py-2 text-right">
                                            {{ is_numeric($cell) ? number_format((float) $cell, 0, ',', '.') : $cell }}
                                        </td>
                                    @endforeach
                                </tr>
                            @else
                                <tr class="hover:bg-slate-50 text-slate-600">
                                    <td class="px-3 py-1.5 text-center border-b border-slate-50">{{ $colA }}</td>
                                    <td class="px-3 py-1.5 border-b border-slate-50">{{ $colB }}</td>
                                    @foreach (array_slice($row, 2) as $cell)
                                        <td class="px-3 py-1.5 text-right border-b border-slate-50">
                                            {{ is_numeric($cell) ? number_format((float) $cell, 0, ',', '.') : $cell }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-[11px] text-slate-400">
            Pratinjau data, tampilan akhir pada file Excel (merge cell &amp; border) bisa sedikit berbeda.
        </p>

    </div>

    <script>
        // Keep the exact params used to generate this preview so "Download Excel"
        // re-submits the same filters (start_date, end_date, selectedReport,
        // shiftType, shift, selectedType, factory, doctor) without re-reading the form.
        window.liphPreviewParams = @json($queryParams ?? []);

        function downloadLiphReport() {
            const downloadBtn = document.querySelector('#liph-preview button[onclick="downloadLiphReport()"]');
            if (downloadBtn) downloadBtn.disabled = true;

            axios.post('{{ route('reports') }}', {
                ...window.liphPreviewParams,
                mode: 'download'
            }, {
                responseType: 'blob',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).then(res => {
                const blob = new Blob([res.data], {
                    type: res.headers['content-type']
                });
                const url = URL.createObjectURL(blob);

                const match = res.headers['content-disposition']?.match(/filename="?([^"]+)"?/);
                const filename = match ? match[1] : 'LIPH.xlsx';

                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            }).catch(() => {
                // adjust to your own toast lib, e.g. iziToast.error({ title: 'Gagal', message: 'Gagal mengunduh laporan' });
                alert('Gagal mengunduh laporan.');
            }).finally(() => {
                if (downloadBtn) downloadBtn.disabled = false;
            });
        }

        function closeReportPreview() {
            // adjust to however your modal is closed, e.g.:
            // $('#report-preview-modal').modal('hide');
            document.getElementById('report-preview-modal')?.classList.add('hidden');
        }
    </script>
@endsection
