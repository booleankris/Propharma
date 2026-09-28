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
    protected int $dataStartRow = 7;
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
            $acc = FinanceAccount::find($this->filters['expense_account_id']);
            if ($acc) $filterExpenseName = "{$acc->code} - {$acc->name}";
        }

        $filterKasName = 'Semua Akun Kas/Bank';
        if (!empty($this->filters['account_id'])) {
            $kas = FinanceAccount::find($this->filters['account_id']);
            if ($kas) $filterKasName = "{$kas->code} - {$kas->name}";
        }

        $filterDateStr = 'Semua Tanggal';
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $filterDateStr = Carbon::parse($this->filters['start_date'])->format('d/m/Y') . ' s/d ' . Carbon::parse($this->filters['end_date'])->format('d/m/Y');
        } elseif (!empty($this->filters['start_date'])) {
            $filterDateStr = 'Mulai ' . Carbon::parse($this->filters['start_date'])->format('d/m/Y');
        }

        // Header Title Block
        $rows = [
            ['APOTEK SAHABAT - LAPORAN BIAYA OPERASIONAL & PENGELUARAN'],
            ["Unit / Cabang: {$branchName} | Kategori Beban: {$filterExpenseName} | Sumber Dana: {$filterKasName}"],
            ["Periode Transaksi: {$filterDateStr} | Dicetak Pada: " . Carbon::now()->format('d/m/Y H:i:s')],
            [],
            [
                'NO',
                'NO. BUKTI / REF',
                'TANGGAL',
                'KODE AKUN',
                'NAMA AKUN BEBAN',
                'KATEGORI BEBAN',
                'SUMBER DANA (KAS/BANK)',
                'PENERIMA / VENDOR',
                'KETERANGAN / CATATAN',
                'JUMLAH (RP)',
                'PETUGAS',
            ]
        ];

        $this->dataStartRow = 6;

        // Query Data
        $query = FinancePayment::with(['account', 'expenseAccount', 'creator', 'pharmacy'])
            ->where('payment_type', 'BIAYA')
            ->where(function ($q) {
                $q->whereIn('pharmacy_id', $this->targetPharmacyIds)
                  ->orWhereNull('pharmacy_id');
            });

        if (!empty($this->filters['search'])) {
            $s = $this->filters['search'];
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
                    });
            });
        }

        if (!empty($this->filters['expense_account_id'])) {
            $query->where('expense_account_id', $this->filters['expense_account_id']);
        }

        if (!empty($this->filters['account_id'])) {
            $query->where('account_id', $this->filters['account_id']);
        }

        if (!empty($this->filters['start_date'])) {
            $query->whereDate('payment_date', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->whereDate('payment_date', '<=', $this->filters['end_date']);
        }

        $sortOrder = $this->filters['sort_order'] ?? 'desc';
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
                Carbon::parse($item->payment_date)->format('d/m/Y'),
                $item->expenseAccount->code ?? '-',
                $item->expenseAccount->name ?? 'Biaya Operasional',
                $item->expenseAccount->category ?? 'Beban Operasional',
                $item->account->name ?? 'Kas / Bank',
                $item->recipient ?: '-',
                $item->notes ?: '-',
                $amount,
                $item->creator->name ?? 'Petugas',
            ];
        }

        // Summary Row
        $summaryRowIndex = count($rows) + 1;
        $rows[] = [
            'TOTAL PENGELUARAN BIAYA',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $totalNominal,
            '',
        ];

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // NO
            'B' => 18,  // NO BUKTI
            'C' => 14,  // TANGGAL
            'D' => 14,  // KODE AKUN
            'E' => 30,  // NAMA AKUN BEBAN
            'F' => 22,  // KATEGORI BEBAN
            'G' => 26,  // SUMBER DANA
            'H' => 24,  // PENERIMA
            'I' => 35,  // KETERANGAN
            'J' => 20,  // JUMLAH RP
            'K' => 18,  // PETUGAS
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Title banner
        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');
        $sheet->mergeCells('A3:K3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF047857']], // Emerald 700
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle('A2:A3')->applyFromArray([
            'font' => ['size' => 10, 'italic' => true, 'color' => ['argb' => 'FF334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Table Header
        $sheet->getStyle('A5:K5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FFFFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF065F46']], // Emerald 800
        ]);
        $sheet->getRowDimension(5)->setRowHeight(26);

        // Body rows border & alignment
        if ($this->dataRowCount > 0) {
            $endDataRow = $this->dataStartRow + $this->dataRowCount - 1;

            $sheet->getStyle("A{$this->dataStartRow}:K{$endDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Number formatting for nominal
            $sheet->getStyle("J{$this->dataStartRow}:J{$endDataRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("A{$this->dataStartRow}:A{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$this->dataStartRow}:B{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$this->dataStartRow}:C{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$this->dataStartRow}:D{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$this->dataStartRow}:J{$endDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Summary row style
        $summaryRow = $this->lastRow;
        $sheet->mergeCells("A{$summaryRow}:I{$summaryRow}");
        $sheet->getStyle("A{$summaryRow}:K{$summaryRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => 'FF065F46']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD1FAE5']], // Emerald 100
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF047857']],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => 'FF047857']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("J{$summaryRow}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("J{$summaryRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($summaryRow)->setRowHeight(24);

        return [];
    }
}
