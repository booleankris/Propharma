<?php

namespace App\Exports\Finance;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CashflowExport implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const BANKS = ['BNI', 'BRI', 'MANDIRI', 'BCA', 'BPD', 'BTN', 'OTHER'];
    private array $days;

    public function __construct(array $days)
    {
        $this->days = $days;
    }

    public function title(): string
    {
        return 'Cashflow Omzet';
    }

    public function array(): array
    {
        $rows = [$this->headerRowOne(), $this->headerRowTwo(), $this->headerRowThree()];

        foreach ($this->days as $day) {
            $rows[] = array_merge(
                [ExcelDate::dateTimeToExcel(Carbon::parse($day['date']))],
                $this->shiftCells($day['morning'] ?? []),
                $this->shiftCells($day['evening'] ?? []),
                [
                    (float) ($day['morning']['directDeposit'] ?? 0),
                    (float) ($day['evening']['directDeposit'] ?? 0),
                    (float) ($day['deposit'] ?? 0),
                ]
            );
        }

        $totalRow = ['TOTAL PERIODE'];
        $firstDataRow = 4;
        $lastDataRow = count($this->days) + 3;
        for ($column = 2; $column <= 38; $column++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
            $totalRow[] = "=SUM({$letter}{$firstDataRow}:{$letter}{$lastDataRow})";
        }
        $rows[] = $totalRow;

        return $rows;
    }

    /** Return the same sheet data with concrete totals for the HTML preview. */
    public function previewArray(): array
    {
        $rows = $this->array();
        $totalIndex = count($rows) - 1;
        for ($column = 1; $column < count($rows[$totalIndex]); $column++) {
            $total = 0;
            foreach (array_slice($rows, 3, count($this->days)) as $row) {
                $value = $row[$column] ?? null;
                if (is_numeric($value)) $total += (float) $value;
            }
            $rows[$totalIndex][$column] = $total;
        }
        return $rows;
    }

    private function headerRowOne(): array
    {
        return array_merge(['TANGGAL', 'PAGI'], array_fill(0, 16, ''), ['MALAM'], array_fill(0, 16, ''), ['TOTAL SETORAN', '', '']);
    }

    private function headerRowTwo(): array
    {
        $headers = [''];
        foreach (range(0, 1) as $unused) {
            $headers[] = 'CASH';
            foreach (self::BANKS as $bank) {
                $headers[] = 'BANK '.($bank === 'OTHER' ? 'LAIN' : $bank);
                $headers[] = '';
            }
            $headers[] = 'DISC';
            $headers[] = 'JUMLAH';
        }
        return array_merge($headers, ['PAGI', 'MALAM', 'JUMLAH']);
    }

    private function headerRowThree(): array
    {
        $headers = [''];
        foreach (range(0, 1) as $unused) {
            $headers[] = '';
            foreach (self::BANKS as $bank) {
                $headers[] = 'EDC';
                $headers[] = 'TRANSFER / QRIS';
            }
            $headers[] = '';
            $headers[] = '';
        }
        return array_merge($headers, ['', '', '']);
    }

    private function shiftCells(array $shift): array
    {
        $cells = [(float) ($shift['cash'] ?? 0)];
        foreach (self::BANKS as $bank) {
            $cells[] = (float) ($shift['banks'][$bank]['edc'] ?? 0);
            $cells[] = (float) ($shift['banks'][$bank]['transfer'] ?? 0);
        }
        $cells[] = (float) ($shift['discount'] ?? 0) + (float) ($shift['itemDiscount'] ?? 0);
        $cells[] = (float) ($shift['total'] ?? 0);

        return $cells;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 15];
        for ($column = 2; $column <= 38; $column++) {
            $widths[\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column)] = 16;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = count($this->days) + 4;
        $sheet->getStyle("A1:AL{$lastRow}")->getFont()->setName('Arial')->setSize(9);
        $sheet->getStyle('A1:AL3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '475569']]],
        ]);
        $sheet->getStyle('A1:AL1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('AEB7C2');
        $sheet->getStyle('A2:AL3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D5DCE5');
        $sheet->getStyle("A4:AL{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        if ($lastRow > 4) {
            $sheet->getStyle("B4:R".($lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAFBF0');
            $sheet->getStyle("S4:AI".($lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAFBF0');
            $sheet->getStyle("C4:P".($lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9F0F9');
            $sheet->getStyle("T4:AG".($lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9F0F9');
            $sheet->getStyle("R4:R".($lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
            $sheet->getStyle("AI4:AI".($lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');
            $sheet->getStyle("AJ4:AL".($lastRow - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF5');
        }

        $sheet->getStyle("B4:AL{$lastRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0;[Red]("Rp" #,##0);-');
        $sheet->getStyle("A4:A".($lastRow - 1))->getNumberFormat()->setFormatCode('dd-mmm-yy');
        $sheet->getStyle("B4:AL{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("A{$lastRow}:AL{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDE4EC']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(26);
        $sheet->getRowDimension(3)->setRowHeight(24);

        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $sheet->mergeCells('A1:A3');
            $sheet->mergeCells('B1:R1');
            $sheet->mergeCells('S1:AI1');
            $sheet->mergeCells('AJ1:AL1');

            foreach (['B', 'Q', 'R', 'S', 'AH', 'AI'] as $column) {
                $sheet->mergeCells("{$column}2:{$column}3");
            }
            foreach (['AJ', 'AK', 'AL'] as $column) {
                $sheet->mergeCells("{$column}2:{$column}3");
            }
            foreach ([['C', 'D'], ['E', 'F'], ['G', 'H'], ['I', 'J'], ['K', 'L'], ['M', 'N'], ['O', 'P'], ['T', 'U'], ['V', 'W'], ['X', 'Y'], ['Z', 'AA'], ['AB', 'AC'], ['AD', 'AE'], ['AF', 'AG']] as [$start, $end]) {
                $sheet->mergeCells("{$start}2:{$end}2");
            }

            $sheet->freezePane('B4');
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0)->setFitToPage(true);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 3);
        }];
    }
}
