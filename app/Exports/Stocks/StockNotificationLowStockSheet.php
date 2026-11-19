<?php

namespace App\Exports\Stocks;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockNotificationLowStockSheet extends DefaultValueBinder implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
{
    /** @var array<int, array<string, mixed>> */
    protected array $items;
    protected string $branchLabel;
    protected int $lastRow = 5;

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(array $items, string $branchLabel)
    {
        $this->items = $items;
        $this->branchLabel = $branchLabel;
    }

    public function title(): string
    {
        return 'Stok Rendah & Habis';
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'C' && $cell->getRow() >= 6) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function array(): array
    {
        $total = count($this->items);
        $outCount = 0;
        foreach ($this->items as $item) {
            if (($item['status'] ?? '') === 'out_of_stock') {
                $outCount++;
            }
        }

        $rows = [
            ["APOTEK SAHABAT - {$this->branchLabel}"],
            ['LAPORAN NOTIFIKASI OBAT STOK RENDAH & HABIS'],
            ["Dicetak: " . date('d/m/Y H:i') . " | Total: {$total} item ({$outCount} habis) | Hanya obat yang pernah berstok"],
            [''],
            [
                'No',
                'Cabang',
                'Kode Obat',
                'Nama Obat',
                'Satuan',
                'Stok Saat Ini',
                'Stok Minimal',
                'Kekurangan',
                'Status',
            ],
        ];

        $no = 1;
        foreach ($this->items as $item) {
            $current = (float) ($item['current_stock'] ?? 0);
            $minimal = (int) ($item['minimal_stock'] ?? 0);
            $shortage = max(0, $minimal - $current);

            $rows[] = [
                $no++,
                $item['pharmacy_short'] ?? ($item['pharmacy_name'] ?? '-'),
                $item['code'] ?? '-',
                $item['name'] ?? '-',
                $item['unit'] ?: '-',
                $current,
                $minimal,
                floor($shortage) == $shortage ? (int) $shortage : round($shortage, 2),
                strtoupper((string) ($item['label'] ?? 'Stok Rendah')),
            ];
        }

        if ($this->items === []) {
            $rows[] = ['', '', '', 'Tidak ada obat dengan stok di bawah batas minimal.', '', '', '', '', ''];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 16,
            'C' => 15,
            'D' => 42,
            'E' => 10,
            'F' => 14,
            'G' => 14,
            'H' => 14,
            'I' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0F172A']],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'B91C1C']],
        ]);
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 9.5, 'color' => ['rgb' => '475569']],
        ]);

        $sheet->getStyle('A5:I5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(24);

        if ($this->lastRow >= 6) {
            $sheet->getStyle("A5:I{$this->lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("A6:A{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C6:C{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E6:E{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F6:H{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("F6:H{$this->lastRow}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sheet->getStyle("I6:I{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            if ($this->items !== []) {
                $rowIdx = 6;
                foreach ($this->items as $item) {
                    $isCritical = ($item['severity'] ?? '') === 'critical';
                    $sheet->getStyle("I{$rowIdx}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $isCritical ? 'B91C1C' : 'B45309']],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $isCritical ? 'FEE2E2' : 'FEF3C7'],
                        ],
                    ]);
                    $rowIdx++;
                }
            }
        }

        $sheet->freezePane('A6');
        $sheet->setAutoFilter('A5:I5');

        return [];
    }
}
