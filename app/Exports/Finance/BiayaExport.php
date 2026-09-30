<?php

namespace App\Exports\Finance;

use App\Models\FinancePayment;
use App\Models\FinanceAccount;
use App\Models\Pharmacies;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BiayaExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected array $targetPharmacyIds;
    protected array $filters;
    protected ?Pharmacies $activePharmacy;
    protected int $lastRow = 5;
    protected int $dataStartRow = 6;
    protected int $dataRowCount = 0;

    public function __construct(array $targetPharmacyIds, array $filters = [], ?Pharmacies $activePharmacy = null)
    {
        $this->targetPharmacyIds = $targetPharmacyIds;
        $this->filters = $filters;
        $this->activePharmacy = $activePharmacy;
    }

    public function title(): string
    {
        return 'Biaya Operasional';
    }

    public function array(): array
    {
        $branchName = $this->activePharmacy ? $this->activePharmacy->name : 'Semua Cabang';

        $filterExpenseName = 'Semua Kategori Beban';
        if (!empty($this->filters['expense_account_id'])) {
            $val = $this->filters['expense_account_id'];
            $acc = is_numeric($val)
                ? FinanceAccount::find($val)
                : FinanceAccount::where('name', 'LIKE', "%{$val}%")->orWhere('code', $val)->first();
            if ($acc) {
                $filterExpenseName = ($acc->code ? "{$acc->code} - " : '') . $acc->name;
            } else {
                $filterExpenseName = (string) $val;
            }
        }

        $filterKasName = 'Semua Akun Kas/Bank';
        if (!empty($this->filters['account_id'])) {
            $val = $this->filters['account_id'];
            $kas = is_numeric($val)
                ? FinanceAccount::find($val)
                : FinanceAccount::where('name', 'LIKE', "%{$val}%")->orWhere('code', $val)->first();
            if ($kas) {
                $filterKasName = ($kas->code ? "{$kas->code} - " : '') . $kas->name;
            } else {
                $filterKasName = (string) $val;
            }
        }

        $filterDateStr = 'Semua Periode';
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $filterDateStr = Carbon::parse($this->filters['start_date'])->format('d/m/Y') . ' s/d ' . Carbon::parse($this->filters['end_date'])->format('d/m/Y');
        } elseif (!empty($this->filters['start_date'])) {
            $filterDateStr = 'Mulai ' . Carbon::parse($this->filters['start_date'])->format('d/m/Y');
        } elseif (!empty($this->filters['end_date'])) {
            $filterDateStr = 'Sampai ' . Carbon::parse($this->filters['end_date'])->format('d/m/Y');
        }

        // Header Title Block
        $rows = [
            ["APOTEK PROPHARMA - {$branchName}"],
            ['LAPORAN BIAYA OPERASIONAL & PENGELUARAN'],
            ["Periode: {$filterDateStr} | Kategori Beban: {$filterExpenseName} | Sumber Dana: {$filterKasName} | Dicetak: " . Carbon::now()->format('d/m/Y H:i')],
            [], // Row 4 Blank
            [   // Row 5 Table Headers
                'No',
                'No. Bukti / Ref',
                'Tanggal',
                'Apotek Cabang',
                'Kode Akun',
                'Nama Akun Beban',
                'Kategori Beban',
                'Sumber Kas / Bank',
                'Penerima / Vendor',
                'Keterangan / Catatan',
                'Petugas',
                'Jumlah (Rp)',
            ],
        ];

        // Query Data
        $query = FinancePayment::with(['account', 'expenseAccount', 'creator', 'pharmacy'])
            ->where('payment_type', 'BIAYA');

        if (!empty($this->filters['pharmacy_id'])) {
            $query->where('pharmacy_id', (int) $this->filters['pharmacy_id']);
        } else {
            $query->where(function ($q) {
                $q->whereIn('pharmacy_id', $this->targetPharmacyIds)
                  ->orWhereNull('pharmacy_id');
            });
        }

        if (!empty($this->filters['search'])) {
            $s = trim($this->filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('reference_number', 'LIKE', "%{$s}%")
                    ->orWhere('recipient', 'LIKE', "%{$s}%")
                    ->orWhere('notes', 'LIKE', "%{$s}%")
                    ->orWhereHas('expenseAccount', function ($sub) use ($s) {
                        $sub->where('name', 'LIKE', "%{$s}%")
                            ->orWhere('code', 'LIKE', "%{$s}%");
                    })
                    ->orWhereHas('account', function ($sub) use ($s) {
                        $sub->where('name', 'LIKE', "%{$s}%")
                            ->orWhere('code', 'LIKE', "%{$s}%");
                    })
                    ->orWhereHas('creator', function ($sub) use ($s) {
                        $sub->where('name', 'LIKE', "%{$s}%");
                    });
            });
        }

        if (!empty($this->filters['expense_account_id'])) {
            $val = $this->filters['expense_account_id'];
            if (is_numeric($val)) {
                $query->where('expense_account_id', $val);
            } else {
                $query->whereHas('expenseAccount', function ($sub) use ($val) {
                    $sub->where('name', 'LIKE', "%{$val}%")
                        ->orWhere('code', 'LIKE', "%{$val}%");
                });
            }
        }

        if (!empty($this->filters['account_id'])) {
            $val = $this->filters['account_id'];
            if (is_numeric($val)) {
                $query->where('account_id', $val);
            } else {
                $query->whereHas('account', function ($sub) use ($val) {
                    $sub->where('name', 'LIKE', "%{$val}%")
                        ->orWhere('code', 'LIKE', "%{$val}%");
                });
            }
        }

        if (!empty($this->filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $this->filters['end_date']);
        }

        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('payment_date', $sortOrder)->orderBy('id', $sortOrder);

        $no = 1;
        $totalNominal = 0;
        $items = $query->get();
        $this->dataRowCount = $items->count();

        foreach ($items as $item) {
            $amount = (float) $item->amount;
            $totalNominal += $amount;

            $rows[] = [
                $no++,
                $item->reference_number ?: ('BY-' . str_pad($item->id, 5, '0', STR_PAD_LEFT)),
                $this->safeFormatDate($item->payment_date),
                $item->pharmacy->name ?? ($branchName ?: 'Pusat'),
                $item->expenseAccount->code ?? '-',
                $item->expenseAccount->name ?? 'Biaya Operasional',
                $item->expenseAccount->category ?? 'Beban Operasional',
                $item->account->name ?? 'Kas / Bank',
                $item->recipient ?: '-',
                $item->notes ?: '-',
                $item->creator->name ?? 'Petugas',
                $amount,
            ];
        }

        // Summary or Empty State Row
        if ($this->dataRowCount > 0) {
            $rows[] = [
                'TOTAL PENGELUARAN BIAYA',
                '', '', '', '', '', '', '', '', '', '',
                $totalNominal,
            ];
            $this->lastRow = count($rows);
        } else {
            $rows[] = [
                'Tidak ada data pengeluaran biaya operasional yang sesuai dengan filter yang dipilih.',
                '', '', '', '', '', '', '', '', '', '', ''
            ];
            $this->lastRow = count($rows);
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 18,  // No Bukti / Ref
            'C' => 14,  // Tanggal
            'D' => 24,  // Apotek Cabang
            'E' => 14,  // Kode Akun
            'F' => 30,  // Nama Akun Beban
            'G' => 22,  // Kategori Beban
            'H' => 24,  // Sumber Kas / Bank
            'I' => 24,  // Penerima / Vendor
            'J' => 32,  // Keterangan / Catatan
            'K' => 18,  // Petugas
            'L' => 22,  // Jumlah (Rp)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Title Styling
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        $sheet->mergeCells('A3:L3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '047857']], // Emerald 700
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']], // Slate 500
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // 2. Table Headers (Row 5)
        $sheet->getStyle('A5:L5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Dark Corporate Slate
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F172A']],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(28);

        // 3. Data Rows Styling
        if ($this->dataRowCount > 0) {
            $start = $this->dataStartRow;
            $end = $start + $this->dataRowCount - 1;

            for ($row = $start; $row <= $end; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(20);

                // Zebra striping for readability
                if ($row % 2 == 1) {
                    $sheet->getStyle("A{$row}:L{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }
            }

            // General Grid Borders & Alignments
            $sheet->getStyle("A{$start}:L{$end}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$start}:B{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$start}:B{$end}")->getFont()->setBold(true);
            $sheet->getStyle("C{$start}:C{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$start}:D{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("E{$start}:E{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("G{$start}:G{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("H{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("I{$start}:I{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("J{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("K{$start}:K{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("L{$start}:L{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("L{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("L{$start}:L{$end}")->getFont()->setBold(true);

            // Summary Row Style
            $totalRow = $this->lastRow;
            $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
            $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'ECFDF5'], // Emerald-50 highlight
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '10B981']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '047857']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'A7F3D0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("A{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '047857']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);

            $sheet->getStyle("L{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '047857']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);
        } else {
            // Empty State Row
            $emptyRow = $this->dataStartRow;
            $sheet->mergeCells("A{$emptyRow}:L{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:L{$emptyRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
            ]);
            $sheet->getRowDimension($emptyRow)->setRowHeight(30);
        }
    }

    protected function safeFormatDate($date): string
    {
        if (empty($date)) return '-';
        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y');
        }
        $str = trim((string) $date);
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $str)) {
            return substr($str, 0, 10);
        }
        try {
            return Carbon::parse($str)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $str;
        }
    }
}
