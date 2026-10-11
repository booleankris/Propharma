<?php

namespace App\Exports\Finance;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CashflowTradeExport implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    private array $report;
    private string $reportTitle;
    private string $sheetTitle;
    private string $periodTitle;
    private string $lastColumn = 'C';
    private int $lastDataRow = 5;

    public function __construct(array $report, string $reportTitle, string $sheetTitle, string $periodTitle)
    {
        $this->report = $report;
        $this->reportTitle = $reportTitle;
        $this->sheetTitle = $sheetTitle;
        $this->periodTitle = $periodTitle;
        $this->lastColumn = Coordinate::stringFromColumnIndex(count($report['dates'] ?? []) + 3);
        $this->lastDataRow = count($report['rows'] ?? []) + 5;
    }

    public function title(): string
    {
        return mb_substr($this->sheetTitle, 0, 31);
    }

    public function array(): array
    {
        $dates = $this->report['dates'] ?? [];
        $rows = [
            [$this->reportTitle],
            [$this->periodTitle],
            [''],
            array_merge(['NO', 'NAMA DEBITUR / SUPPLIER'], array_map(fn ($date) => date('d-M-y', strtotime($date)), $dates), ['TOTAL']),
        ];

        $no = 1;
        foreach ($this->report['rows'] ?? [] as $entity) {
            $values = array_map(fn ($date) => (float) ($entity['amounts'][$date] ?? 0), $dates);
            $rows[] = array_merge([$no++, $entity['name']], $values, [(float) ($entity['total'] ?? 0)]);
        }

        $grandTotals = array_map(fn ($date) => (float) ($this->report['grandTotalsByDate'][$date] ?? 0), $dates);
        $rows[] = array_merge(['GRAND TOTAL', ''], $grandTotals, [(float) ($this->report['grandTotal'] ?? 0)]);
        $this->lastDataRow = count($rows);
        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 7, 'B' => 36];
        foreach ($this->report['dates'] ?? [] as $index => $_date) {
            $widths[Coordinate::stringFromColumnIndex($index + 3)] = 14;
        }
        $widths[$this->lastColumn] = 18;
        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells("A1:{$this->lastColumn}1");
        $sheet->mergeCells("A2:{$this->lastColumn}2");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle("A4:{$this->lastColumn}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getStyle("A5:{$this->lastColumn}{$this->lastDataRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        if ($this->lastDataRow >= 5) {
            $sheet->getStyle("C5:{$this->lastColumn}{$this->lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00;[Red]-#,##0.00;-');
            $sheet->getStyle("C5:{$this->lastColumn}{$this->lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$this->lastDataRow}:{$this->lastColumn}{$this->lastDataRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF08A']],
                'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '334155']]],
            ]);
        }
        $sheet->freezePane('C5');
        $sheet->setAutoFilter("A4:{$this->lastColumn}" . max(4, $this->lastDataRow - 1));
        return [];
    }
}
