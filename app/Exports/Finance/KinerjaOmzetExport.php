<?php

namespace App\Exports\Finance;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KinerjaOmzetExport implements FromArray, WithEvents, WithStyles, WithTitle
{
    private array $data;
    private int $month;
    private int $year;
    private int $debtorStart = 6;
    private int $debtorCount;
    private int $purchaseStart;
    private int $lastColumn;
    private int $lastRow;

    public function __construct(array $data, int $month, int $year)
    {
        $this->data = $data;
        $this->month = $month;
        $this->year = $year;
        $this->debtorCount = count($data['debtors']);
        $this->purchaseStart = $this->debtorStart + $this->debtorCount + 1;
        $this->lastColumn = $this->purchaseStart + 14;
        $this->lastRow = 6 + count($data['days']);
    }

    public function title(): string
    {
        return 'Kinerja Omzet';
    }

    private function blanks(): array
    {
        return array_fill(0, $this->lastColumn + 1, '');
    }

    private function number($value): float
    {
        return (float) ($value ?? 0);
    }

    public function array(): array
    {
        $title = $this->blanks();
        $title[0] = 'KINERJA OMZET';
        $period = $this->blanks();
        $period[0] = 'PERIODE ' . mb_strtoupper(Carbon::createFromDate($this->year, $this->month, 1)->locale('id')->translatedFormat('F Y'));

        $outer = $this->blanks();
        $outer[0] = 'TANGGAL';
        $outer[1] = 'PENJUALAN';
        $outer[$this->purchaseStart] = 'PEMBELIAN';

        $middle = $this->blanks();
        $middle[1] = 'CASH';
        $middle[3] = 'BANK';
        $middle[5] = 'TOTAL OMSET TUNAI';
        $middle[$this->debtorStart] = 'KREDIT';

        $leaf = $this->blanks();
        $leaf[1] = 'PAGI';
        $leaf[2] = 'MALAM';
        $leaf[3] = 'PAGI';
        $leaf[4] = 'MALAM';
        foreach ($this->data['debtors'] as $index => $debtor)
            $leaf[$this->debtorStart + $index] = $debtor['name'];
        $leaf[$this->debtorStart + $this->debtorCount] = 'TOTAL OMSET KREDIT';
        $purchaseLabels = ['PBF', 'KONSINYASI', 'RETUR PEMBELIAN', 'CASH', 'JUMLAH BELI', 'PPN FAKTUR', 'BELI EXC PPN', 'MARGIN EXC PPN', 'HPP %'];
        foreach ($purchaseLabels as $index => $label)
            $middle[$this->purchaseStart + $index] = $label;

        $extraLabels = [
            'PENDAPATAN LAIN-LAIN',
            'PENDAPATAN LAIN-LAIN S/D',
            'BIAYA',
            'SAVING',
            'BIAYA S/D',
            'R/L S/D',
        ];
        foreach ($extraLabels as $index => $label) {
            $outer[$this->purchaseStart + 9 + $index] = $label;
        }

        $rows = [$title, $period, $outer, $middle, $leaf];
        foreach ($this->data['days'] as $day) {
            $row = $this->blanks();
            $row[0] = Carbon::parse($day['date'])->format('d-M-y');
            $row[1] = $this->number($day['sales']['cash']['morning'] ?? 0);
            $row[2] = $this->number($day['sales']['cash']['evening'] ?? 0);
            $row[3] = $this->number($day['sales']['bank']['morning'] ?? 0);
            $row[4] = $this->number($day['sales']['bank']['evening'] ?? 0);
            $row[5] = $this->number($day['sales']['cashBankTotal'] ?? 0);
            foreach ($this->data['debtors'] as $index => $debtor) {
                $row[$this->debtorStart + $index] = $this->number($day['sales']['credit']['morning'][$debtor['id']] ?? 0) + $this->number($day['sales']['credit']['evening'][$debtor['id']] ?? 0);
            }
            $creditIndex = $this->debtorStart + $this->debtorCount;
            $row[$creditIndex] = $this->number($day['sales']['creditTotal'] ?? 0);

            $purchaseValues = [
                $day['purchases']['pbf'] ?? 0,
                $day['purchases']['consignment'] ?? 0,
                $day['purchases']['return'] ?? 0,
                $day['purchases']['cash'] ?? 0,
                $day['purchases']['total'] ?? 0,
                $day['purchases']['ppn'] ?? 0,
                $day['purchases']['exVat'] ?? 0,
                $this->number($day['sales']['exVat'] ?? 0) - $this->number($day['purchases']['exVat'] ?? 0),
                ($this->number($day['sales']['exVat'] ?? 0) > 0) ? $this->number($day['purchases']['exVat'] ?? 0) / $this->number($day['sales']['exVat']) * 100 : 0,
            ];
            foreach ($purchaseValues as $index => $value)
                $row[$this->purchaseStart + $index] = $this->number($value);

            $extraValues = [
                $day['other_income'] ?? 0,
                $day['other_income_sd'] ?? 0,
                $day['expense'] ?? 0,
                $day['saving'] ?? 0,
                $day['expense_sd'] ?? 0,
                $day['profit_loss_sd'] ?? 0,
            ];
            foreach ($extraValues as $index => $value) $row[$this->purchaseStart + 9 + $index] = $this->number($value);

            $rows[] = $row;
        }

        $total = $this->blanks();
        $total[0] = 'TOTAL PERIODE';
        $skipColumns = [
            $this->purchaseStart + 6,
            $this->purchaseStart + 7,
            $this->purchaseStart + 8,
            $this->purchaseStart + 10,
            $this->purchaseStart + 12,
            $this->purchaseStart + 13,
            $this->purchaseStart + 14,
        ];
        for ($column = 1; $column <= $this->lastColumn; $column++) {
            if (in_array($column, $skipColumns, true)) continue;
            $total[$column] = array_sum(array_map(fn ($row) => is_numeric($row[$column] ?? null) ? (float) $row[$column] : 0, array_slice($rows, 5)));
        }
        $salesExVatTotal = array_sum(array_map(fn($d) => $this->number($d['sales']['exVat'] ?? 0), $this->data['days']));
        $purchaseExVatTotal = $total[$this->purchaseStart + 6];
        $total[$this->purchaseStart + 7] = $salesExVatTotal - $purchaseExVatTotal;
        $total[$this->purchaseStart + 8] = $salesExVatTotal > 0 ? $purchaseExVatTotal / $salesExVatTotal * 100 : 0;

        $total[$this->purchaseStart + 9] = array_sum(array_map(fn ($d) => $this->number($d['other_income'] ?? 0), $this->data['days']));
        $total[$this->purchaseStart + 10] = $this->number($lastDay['other_income_sd'] ?? 0);
        $total[$this->purchaseStart + 11] = array_sum(array_map(fn ($d) => $this->number($d['expense'] ?? 0), $this->data['days']));
        $total[$this->purchaseStart + 12] = 0; // SAVING
        $total[$this->purchaseStart + 13] = $this->number($lastDay['expense_sd'] ?? 0);
        $total[$this->purchaseStart + 14] = $this->number($lastDay['profit_loss_sd'] ?? 0);
        $rows[] = $total;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getColumnDimension('A')->setWidth(16);
        for ($column = 2; $column <= $this->lastColumn + 1; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(16);
        }
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($this->debtorStart + $this->debtorCount + 1))->setWidth(18);
        for ($column = $this->purchaseStart + 10; $column <= $this->lastColumn + 1; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(18);
        }
        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $last = Coordinate::stringFromColumnIndex($this->lastColumn + 1);
            $salesEnd = Coordinate::stringFromColumnIndex($this->purchaseStart);
            $purchaseStart = Coordinate::stringFromColumnIndex($this->purchaseStart + 1);
            $purchaseEnd = Coordinate::stringFromColumnIndex($this->purchaseStart + 9);
            $creditCol = Coordinate::stringFromColumnIndex($this->debtorStart + $this->debtorCount + 1);
            $sheet->mergeCells("A1:{$last}1");
            $sheet->mergeCells("A2:{$last}2");
            $sheet->mergeCells('A3:A5');
            $sheet->mergeCells("B3:{$salesEnd}3");
            $sheet->mergeCells("{$purchaseStart}3:{$purchaseEnd}3");
            $sheet->mergeCells('B4:C4');
            $sheet->mergeCells('D4:E4');
            $sheet->mergeCells('F4:F5');
            $creditStart = Coordinate::stringFromColumnIndex($this->debtorStart + 1);
            $creditEnd = Coordinate::stringFromColumnIndex($this->purchaseStart);
            $sheet->mergeCells("{$creditStart}4:{$creditEnd}4");
            for ($column = $this->purchaseStart + 1; $column <= $this->purchaseStart + 9; $column++) {
                $letter = Coordinate::stringFromColumnIndex($column);
                $sheet->mergeCells("{$letter}4:{$letter}5");
            }
            for ($column = $this->purchaseStart + 10; $column <= $this->lastColumn + 1; $column++) {
                $letter = Coordinate::stringFromColumnIndex($column);
                $sheet->mergeCells("{$letter}3:{$letter}5");
            }

            $sheet->getStyle("A3:{$last}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFB7C9C0');
            $sheet->getStyle("A3:{$last}5")->getFont()->setBold(true);
            $sheet->getStyle("A3:{$last}5")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle("A1:{$last}2")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("A1:{$last}2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A6:{$last}{$this->lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF708078');
            $sheet->getStyle("B6:{$last}{$this->lastRow}")->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);-');
            $sheet->getStyle("A{$this->lastRow}:{$last}{$this->lastRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$this->lastRow}:{$last}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');

            // Data rows for Cash & Bank & Total Omzet Tunai (bright green as in template)
            $sheet->getStyle('B6:F' . ($this->lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00E000');
            // Credit total column data rows highlight
            $sheet->getStyle("{$creditCol}6:{$creditCol}" . ($this->lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE2EFDA');

            // Data rows for extra columns: Green for Income, Expense, Saving; Cyan for S/D columns
            $colIncome = Coordinate::stringFromColumnIndex($this->purchaseStart + 10);
            $colIncomeSd = Coordinate::stringFromColumnIndex($this->purchaseStart + 11);
            $colExpense = Coordinate::stringFromColumnIndex($this->purchaseStart + 12);
            $colSaving = Coordinate::stringFromColumnIndex($this->purchaseStart + 13);
            $colExpenseSd = Coordinate::stringFromColumnIndex($this->purchaseStart + 14);
            $colRlSd = Coordinate::stringFromColumnIndex($this->purchaseStart + 15);
            $dataEnd = $this->lastRow - 1;

            $sheet->getStyle("{$colIncome}6:{$colIncome}{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF92D050');
            $sheet->getStyle("{$colIncomeSd}6:{$colIncomeSd}{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');
            $sheet->getStyle("{$colExpense}6:{$colExpense}{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF92D050');
            $sheet->getStyle("{$colSaving}6:{$colSaving}{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF92D050');
            $sheet->getStyle("{$colExpenseSd}6:{$colExpenseSd}{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');
            $sheet->getStyle("{$colRlSd}6:{$colRlSd}{$dataEnd}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');

            // Total row colors (B: Yellow, C: Cyan, D: Yellow, E: Cyan, F: Green, Credit Total: Soft Green)
            $sheet->getStyle("B{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            $sheet->getStyle("C{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');
            $sheet->getStyle("D{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFF00');
            $sheet->getStyle("E{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');
            $sheet->getStyle("F{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00E000');
            $sheet->getStyle("{$creditCol}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFC6EFCE');

            $sheet->getStyle("{$colIncome}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF92D050');
            $sheet->getStyle("{$colIncomeSd}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');
            $sheet->getStyle("{$colExpense}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00B050');
            $sheet->getStyle("{$colSaving}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');
            $sheet->getStyle("{$colExpenseSd}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');
            $sheet->getStyle("{$colRlSd}{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF00FFFF');

            $sheet->freezePane('B6');
            $sheet->getRowDimension(3)->setRowHeight(28);
            $sheet->getRowDimension(4)->setRowHeight(27);
            $sheet->getRowDimension(5)->setRowHeight(38);
        }];
    }
}
