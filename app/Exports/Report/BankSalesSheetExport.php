<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BankSalesSheetExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $pharmacyAddress;
    protected $shift;
    protected $shiftType;
    protected $bankName;
    protected $isRecap;
    protected $allBanksData;
    protected $transactions;
    protected $hasTrxDiscount = false;

    public function __construct(
        $pharmacyId,
        $startDate,
        $endDate,
        $pharmacyName = '',
        $pharmacyAddress = '',
        $shift = null,
        $shiftType = 'semua',
        $bankName = null,
        $isRecap = false,
        $allBanksData = [],
        $transactions = null
    ) {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = Carbon::parse($startDate)->startOfDay();
        $this->endDate         = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName    = $pharmacyName ?: 'APOTEK SAHABAT';
        $this->pharmacyAddress = $pharmacyAddress ?: '';
        $this->shift           = $shift;
        $this->shiftType       = $shiftType;
        $this->bankName        = $bankName;
        $this->isRecap         = $isRecap;
        $this->allBanksData    = $allBanksData;
        $this->transactions    = $transactions;
    }

    public function title(): string
    {
        if ($this->isRecap) {
            return 'Rekap Pembayaran';
        }

        $title = $this->bankName ?: 'Lainnya';
        // Clean Excel sheet title (max 31 chars, no invalid chars)
        $cleanTitle = preg_replace('/[\\\\\\/?*\\[\\]:]/', '', $title);
        return mb_substr($cleanTitle, 0, 31);
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);
        $pharmacyName = $pharmacy->name ?? $this->pharmacyName;
        $pharmacyAddress = $pharmacy->address ?? $this->pharmacyAddress;

        $header = [
            [$pharmacyName],
            [$pharmacyAddress],
            [''],
            [$this->isRecap ? 'REKAPITULASI PENJUALAN PER PEMBAYARAN' : 'LAPORAN PENJUALAN : ' . strtoupper($this->bankName ?: 'METODE BAYAR')],
            ['Periode : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y') . ($this->shiftType === 'shift' && $this->shift ? ' (Shift ' . $this->shift . ')' : '')],
            [''],
        ];

        $body = $this->isRecap ? $this->buildRecapRows() : $this->buildDetailRows();

        return array_merge($header, $body);
    }

    /**
     * Build rows for the Recap Summary Sheet
     */
    private function buildRecapRows(): array
    {
        $tableHeaders = [
            'No',
            'Pembayaran',
            'Jumlah Struk',
            'Total Qty Obat',
            'Subtotal Kotor',
            'Diskon Transaksi',
            'Total Netto',
        ];

        $rows = [];
        $rows[] = $tableHeaders;

        $no = 1;
        $grandTotalStruk = 0;
        $grandTotalQty = 0;
        $grandTotalGross = 0;
        $grandTotalDiscount = 0;
        $grandTotalNet = 0;

        foreach ($this->allBanksData as $paymentMethod => $data) {
            $strukCount = $data['struk_count'] ?? 0;
            $qtySum     = $data['qty_sum'] ?? 0;
            $grossSum   = $data['gross_sum'] ?? 0;
            $discSum    = $data['discount_sum'] ?? 0;
            $netSum     = $data['net_sum'] ?? 0;

            $rows[] = [
                $no++,
                $paymentMethod,
                $strukCount,
                $qtySum,
                $grossSum,
                $discSum,
                $netSum,
            ];

            $grandTotalStruk    += $strukCount;
            $grandTotalQty      += $qtySum;
            $grandTotalGross    += $grossSum;
            $grandTotalDiscount += $discSum;
            $grandTotalNet      += $netSum;
        }

        if (count($this->allBanksData) === 0) {
            $rows[] = ['-', 'Tidak ada transaksi pembayaran pada periode ini', 0, 0, 0, 0, 0];
        }

        // Grand Total Row
        $rows[] = [
            '',
            'TOTAL KESELURUHAN',
            $grandTotalStruk,
            $grandTotalQty,
            $grandTotalGross,
            $grandTotalDiscount,
            $grandTotalNet,
        ];

        return $rows;
    }

    /**
     * Build item-level detail rows for a specific Bank
     */
    private function buildDetailRows(): array
    {
        $tableHeaders = [
            'No',
            'Tanggal & Waktu',
            'No. Struk',
            'Jenis Transaksi',
            'Kode Obat',
            'Nama Obat',
            'Qty',
            'Harga Satuan',
            'Diskon Item',
            'Total Item',
            'Pasien / Pelanggan',
            'Dokter',
            'Kasir',
            'Shift',
        ];

        if ($this->transactions !== null) {
            $transactions = is_array($this->transactions) ? collect($this->transactions) : $this->transactions;
        } else {
            $query = MedicineTransactions::with([
                'transactions.medicine',
                'patients',
                'doctors',
                'user',
                'shift_logs',
            ])
                ->where('pharmacy_id', $this->pharmacyId);

            \App\Support\LiphTransactionScope::applySalesStatus($query);
            \App\Support\LiphTransactionScope::applyPeriod(
                $query,
                $this->startDate,
                $this->endDate,
                $this->shiftType,
                $this->shift
            );

            $transactions = $query->orderByRaw('COALESCE(created_at, updated_at) ASC')->get()
                ->filter(fn ($trx) => BankSalesExport::resolveCategory($trx) === $this->bankName);
        }

        $rows = [];
        $rows[] = $tableHeaders;

        $no = 1;
        $totalQty = 0;
        $totalItemDiscount = 0;
        $totalItemAmount = 0;
        $totalTrxDiscount = 0;

        foreach ($transactions as $trx) {
            $dateCol = $trx->created_at ?? $trx->updated_at;
            $tgl = $dateCol ? $dateCol->format('d/m/Y H:i') : '-';
            $code = $trx->transaction_code ?? '-';
            $type = $trx->transaction_type ?? '-';
            $pasien = $trx->patients->name ?? '-';
            $dokter = $trx->doctors->name ?? '-';
            $kasir = $trx->user->name ?? '-';
            $shift = $trx->shift_logs->shift_name ?? ($trx->shift_logs_id ? 'Shift ' . $trx->shift_logs_id : '-');

            $originalMap = LiphExport::TYPE_MAP[$trx->transaction_type] ?? null;
            $originalLabel = $originalMap[1] ?? '';
            $isReturnTrx = in_array($originalLabel, ['Retur Tunai', 'Retur Kredit'], true)
                || in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || str_starts_with(strtoupper(trim((string) ($trx->transaction_code ?? ''))), 'RT');

            $trxDiscount = (!$isReturnTrx && is_numeric($trx->discount)) ? (int) round((float) $trx->discount) : 0;
            $items = $trx->transactions;

            if ($items->isEmpty()) {
                // If no cart items found, still show transaction total
                $subtotal = is_numeric($trx->subtotal) ? (int) round((float) $trx->subtotal) : 0;
                $signedSubtotal = $isReturnTrx ? -abs($subtotal) : $subtotal;
                $qty = $isReturnTrx ? -1 : 1;

                $totalQty += $qty;
                $totalItemAmount += $signedSubtotal;

                $rows[] = [
                    $no++,
                    $tgl,
                    $code,
                    $type,
                    '-',
                    $isReturnTrx ? '[RETUR PENJUALAN]' : 'Transaksi Tanpa Detail Item',
                    $qty,
                    $signedSubtotal,
                    0,
                    $signedSubtotal,
                    $pasien,
                    $dokter,
                    $kasir,
                    $shift,
                ];

                if ($trxDiscount > 0 && !$isReturnTrx) {
                    $totalTrxDiscount += $trxDiscount;
                    $rows[] = [
                        $no++,
                        $tgl,
                        $code,
                        $type,
                        '-',
                        '[DISKON TRANSAKSI]',
                        0,
                        0,
                        0,
                        -$trxDiscount,
                        $pasien,
                        $dokter,
                        $kasir,
                        $shift,
                    ];
                }
                continue;
            }

            foreach ($items as $item) {
                $medicine = $item->medicine;
                $medCode  = $medicine->code ?? '-';
                $medName  = $medicine->name ?? 'Obat Tidak Ditemukan';
                $qty      = (float) ($item->quantity ?? 0);
                $price    = (int) round((float) ($item->item_price ?? 0));
                $itemDiscount = (int) round((float) ($item->discount ?? 0));
                $final    = (int) round((float) ($item->final_price ?? ($item->total_price ?? ($qty * $price - $itemDiscount))));

                $effectiveType = $item->cart_type
                    ? (LiphExport::CART_TYPE_ABBR[$item->cart_type] ?? $item->cart_type)
                    : $trx->transaction_type;
                $effectiveMap = LiphExport::TYPE_MAP[$effectiveType] ?? $originalMap;
                $isReturn = $isReturnTrx || in_array($effectiveMap[1] ?? $originalLabel, ['Retur Tunai', 'Retur Kredit'], true);

                $unitPrice = ($qty > 0)
                    ? (int) round(($item->total_price ?? ($final + $itemDiscount)) / $qty)
                    : $price;

                if ($isReturn) {
                    $qty = -abs($qty);
                    $unitPrice = -abs($unitPrice);
                    $itemDiscount = 0;
                    $final = -abs($final);
                }

                $totalQty += $qty;
                $totalItemDiscount += $itemDiscount;
                $totalItemAmount += $final;

                $rows[] = [
                    $no++,
                    $tgl,
                    $code,
                    $type,
                    $medCode,
                    $medName,
                    $qty,
                    $unitPrice,
                    $itemDiscount,
                    $final,
                    $pasien,
                    $dokter,
                    $kasir,
                    $shift,
                ];
            }

            if ($trxDiscount > 0 && !$isReturnTrx) {
                $totalTrxDiscount += $trxDiscount;
                $rows[] = [
                    $no++,
                    $tgl,
                    $code,
                    $type,
                    '-',
                    '[DISKON TRANSAKSI]',
                    0,
                    0,
                    0,
                    -$trxDiscount,
                    $pasien,
                    $dokter,
                    $kasir,
                    $shift,
                ];
            }
        }

        if ($transactions->isEmpty()) {
            $rows[] = ['-', '-', '-', '-', '-', 'Tidak ada transaksi untuk pembayaran ini', 0, 0, 0, 0, '-', '-', '-', '-'];
        }

        $this->hasTrxDiscount = ($totalTrxDiscount > 0);
        $bankTitle = strtoupper($this->bankName ?: 'PEMBAYARAN');

        if ($totalTrxDiscount > 0) {
            $rows[] = [
                '',
                'SUBTOTAL ITEM ' . $bankTitle,
                '',
                '',
                '',
                '',
                $totalQty,
                '',
                (int) $totalItemDiscount,
                (int) $totalItemAmount,
                '',
                '',
                '',
                '',
            ];
            $rows[] = [
                '',
                'TOTAL DISKON TRANSAKSI',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                (int) (-$totalTrxDiscount),
                '',
                '',
                '',
                '',
            ];
            $rows[] = [
                '',
                'TOTAL NETTO ' . $bankTitle,
                '',
                '',
                '',
                '',
                $totalQty,
                '',
                (int) ($totalItemDiscount + $totalTrxDiscount),
                (int) ($totalItemAmount - $totalTrxDiscount),
                '',
                '',
                '',
                '',
            ];
        } else {
            $rows[] = [
                '',
                'TOTAL ' . $bankTitle,
                '',
                '',
                '',
                '',
                $totalQty,
                '',
                (int) $totalItemDiscount,
                (int) $totalItemAmount,
                '',
                '',
                '',
                '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // 1. Pharmacy Title & Header Styling
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A5')->getFont()->setItalic(true)->setSize(10);

        // 2. Table Header Row (Row 7)
        $headerRow = 7;
        $sheet->getStyle("A{$headerRow}:{$highestColumn}{$headerRow}")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        // 3. Table Data Borders & Alignment
        if ($highestRow > $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$highestColumn}{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

            // Number Formats
            if ($this->isRecap) {
                // Column C..G numeric
                $sheet->getStyle("A8:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C8:C{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("D8:D{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E8:G{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');

                for ($row = 8; $row <= $highestRow; $row++) {
                    $label = strtoupper((string) $sheet->getCell("B{$row}")->getValue());

                    if ($label === 'KREDIT' || $label === 'CASH') {
                        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
                    }
                }
            } else {
                // Detail numeric and text format
                $sheet->getStyle("A8:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B8:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C8:C{$highestRow}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                $sheet->getStyle("C8:C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D8:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E8:E{$highestRow}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                $sheet->getStyle("E8:E{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("G8:G{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("H8:J{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("N8:N{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Total Bottom Row Highlight
            if (! $this->isRecap && $this->hasTrxDiscount && $highestRow >= 10) {
                $subtotalRow = $highestRow - 2;
                $discRow     = $highestRow - 1;
                $netRow      = $highestRow;

                // Subtotal Item row
                $sheet->getStyle("A{$subtotalRow}:{$highestColumn}{$subtotalRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                    ],
                ]);

                // Total Diskon Transaksi row
                $sheet->getStyle("A{$discRow}:{$highestColumn}{$discRow}")->applyFromArray([
                    'font' => ['bold' => true, 'italic' => true],
                ]);

                // Total Netto row
                $sheet->getStyle("A{$netRow}:{$highestColumn}{$netRow}")->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '94A3B8'],
                        ],
                        'bottom' => [
                            'borderStyle' => Border::BORDER_DOUBLE,
                            'color'       => ['rgb' => '64748B'],
                        ],
                    ],
                ]);
            } else {
                $sheet->getStyle("A{$highestRow}:{$highestColumn}{$highestRow}")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ],
                    'borders' => [
                        'top' => [
                            'borderStyle' => Border::BORDER_MEDIUM,
                            'color'       => ['rgb' => '94A3B8'],
                        ],
                        'bottom' => [
                            'borderStyle' => Border::BORDER_DOUBLE,
                            'color'       => ['rgb' => '64748B'],
                        ],
                    ],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        if ($this->isRecap) {
            return [
                'A' => 6,
                'B' => 28,
                'C' => 16,
                'D' => 16,
                'E' => 20,
                'F' => 20,
                'G' => 22,
            ];
        }

        return [
            'A' => 6,
            'B' => 18,
            'C' => 18,
            'D' => 15,
            'E' => 16,
            'F' => 32,
            'G' => 10,
            'H' => 16,
            'I' => 14,
            'J' => 18,
            'K' => 22,
            'L' => 20,
            'M' => 18,
            'N' => 12,
        ];
    }
}
