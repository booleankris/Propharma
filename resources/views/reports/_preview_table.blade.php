{{-- Reusable table renderer for report preview (single sheet) --}}
<div class="overflow-x-auto w-full max-h-[70vh] border border-slate-300 bg-white shadow-sm rounded-lg" style="font-family: Arial, sans-serif;">
    <table class="w-full text-left text-[12px] text-slate-800 whitespace-nowrap border-collapse">
        <tbody class="bg-white">
            @php
                // Cari jumlah kolom terbanyak untuk colspan (abaikan notice row agar tidak melebarkan kolom)
                $maxCols = 1;
                foreach($rows as $r) {
                    if (is_array($r)) {
                        $rowStr = implode(' ', array_filter($r, fn($v) => is_scalar($v)));
                        if (stripos($rowStr, 'Menampilkan ') !== false) {
                            continue;
                        }
                        if (count($r) > $maxCols) {
                            $maxCols = count($r);
                        }
                    }
                }
                $headerMap = [];
            @endphp
            @foreach($rows as $rowIndex => $row)
                @php
                    $isHeader = false;
                    $isSubTotal = false;
                    $isGrandTotal = false;
                    $isGroupHeader = false;
                    $isTitle = $rowIndex === 0;

                    $rowText = is_array($row) ? implode(' ', array_filter($row, fn($v) => is_scalar($v))) : (string)$row;
                    $isNoticeRow = stripos($rowText, 'Menampilkan ') !== false && (stripos($rowText, 'baris pertama') !== false || stripos($rowText, 'Unduh file Excel') !== false);

                    if (!$isNoticeRow && is_array($row) && count($row) > 1) {
                        $firstCell = trim((string)($row[0] ?? ''));
                        $secondCell = trim((string)($row[1] ?? ''));

                        if (in_array(strtolower($firstCell), ['no', 'no.', 'no '])) {
                            $isHeader = true;
                            $headerMap = array_map(fn($c) => strtolower(trim((string)$c)), $row);
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
                
                @if($isNoticeRow)
                    <tr class="bg-amber-50/80 border-y border-amber-200 text-amber-900">
                        <td class="px-4 py-3 text-center text-xs font-medium italic" colspan="{{ $maxCols }}">
                            <div class="flex items-center justify-center gap-2">
                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>{{ is_array($row) ? (collect($row)->first(fn($v) => stripos((string)$v, 'Menampilkan') !== false) ?? ($row[0] ?? '')) : $row }}</span>
                            </div>
                        </td>
                    </tr>
                @elseif(empty($row))
                    <tr class="{{ $isTitle ? 'font-bold text-[14px] text-slate-900' : '' }}">
                        <td class="px-2 py-1 border border-slate-200" colspan="{{ $maxCols }}"></td>
                    </tr>
                @else
                    <tr class="{{ $isHeader ? 'bg-slate-100 font-bold text-slate-900 border-b border-slate-300 sticky top-0' : '' }} {{ $isSubTotal || $isGrandTotal ? 'bg-slate-50 font-bold text-slate-900 border-t-2 border-slate-400' : '' }} {{ $isGroupHeader ? 'bg-slate-50 font-bold text-slate-800' : '' }} {{ $isTitle ? 'font-bold text-[14px] text-slate-900' : '' }}">
                        @if(!is_array($row) || count($row) === 1)
                            <td class="px-3 py-1.5 border border-slate-200" colspan="{{ $maxCols }}">
                                {{ is_array($row) ? ($row[0] ?? '') : $row }}
                            </td>
                        @else
                            @foreach($row as $colIndex => $col)
                                @php
                                    $colStr = trim((string)$col);
                                    $colHeader = $headerMap[$colIndex] ?? '';

                                    // Kolom yang merupakan kode/ID/teks tidak boleh diformat angka:
                                    $isCodeOrTextHeader = false;
                                    $codeKeywords = [
                                        'kode', 'code', 'struk', 'faktur', 'nomor', 'no.', 'no ', 'batch',
                                        'rekening', 'telepon', 'telp', 'hp', 'nik', 'transaksi', 'waktu',
                                        'tanggal', 'tgl', 'shift', 'tipe', 'jenis', 'nama', 'pasien', 'dokter',
                                        'kasir', 'user', 'resep', 'ed', 'barcode', 'satuan', 'kategori', 'pabrik', 'alamat'
                                    ];
                                    $numericKeywords = [
                                        'total', 'diskon', 'discount', 'potongan', 'harga', 'price', 'qty', 'jumlah',
                                        'nominal', 'subtotal', 'sub total', 'bayar', 'kembali', 'netto', 'bruto',
                                        'dpp', 'ppn', 'lembar', 'r/', 'jasa', 'embalase', 'nilai', 'omzet', 'saldo',
                                        'uang', 'kurang', 'masuk', 'keluar', 'awal', 'fisik', 'selisih', 'kredit', 'penjualan', 'pembelian'
                                    ];

                                    foreach ($codeKeywords as $ck) {
                                        if (stripos($colHeader, $ck) !== false) {
                                            $isNum = false;
                                            foreach ($numericKeywords as $nk) {
                                                if (stripos($colHeader, $nk) !== false) {
                                                    $isNum = true;
                                                    break;
                                                }
                                            }
                                            if (!$isNum) {
                                                $isCodeOrTextHeader = true;
                                                break;
                                            }
                                        }
                                    }

                                    // Cek apakah string memiliki leading zero (seperti "000100012") yang bukan angka desimal 0.x
                                    $hasLeadingZero = (strlen($colStr) > 1 && $colStr[0] === '0' && $colStr[1] !== '.');

                                    $shouldNotFormat = $isHeader || $isCodeOrTextHeader || $hasLeadingZero;
                                    $isNumeric = is_numeric($col) && $colStr !== '' && !$shouldNotFormat;
                                    $isNoCol = ($colIndex === 0 && !$isHeader && $colStr !== '');
                                    $isCenterCol = $isHeader || $isNoCol || stripos($colHeader, 'kode') !== false || stripos($colHeader, 'struk') !== false || stripos($colHeader, 'tanggal') !== false || stripos($colHeader, 'shift') !== false;
                                @endphp
                                <td class="px-3 py-1.5 border border-slate-200 {{ $isCenterCol ? 'text-center' : ($isNumeric ? 'text-right' : 'text-left') }}">
                                    @if($shouldNotFormat)
                                        {{ $col }}
                                    @elseif($isNumeric)
                                        {{ number_format((float)$col, str_contains($colStr, '.') && fmod((float)$col, 1) !== 0.0 ? 2 : 0, ',', '.') }}
                                    @else
                                        {{ $col }}
                                    @endif
                                </td>
                            @endforeach
                        @endif
                    </tr>
                @endif
            @endforeach
            @if(count($rows) === 0)
                <tr>
                    <td class="px-4 py-8 text-center text-slate-500" colspan="100%">Tidak ada data.</td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
