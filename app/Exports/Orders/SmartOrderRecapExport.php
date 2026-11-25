<?php

namespace App\Exports\Orders;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SmartOrderRecapExport extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithStyles, WithTitle, WithColumnFormatting, WithCustomValueBinder
{
    protected array $items;
    protected array $filters;
    protected int $headerRow = 6;

    public function __construct(array $items, array $filters = [])
    {
        $this->items = $items;
        $this->filters = $filters;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Preserve string format for Kode Obat (Column C)
        if ($cell->getColumn() === 'C' && $cell->getRow() >= $this->headerRow + 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'Rekap Smart Order';
    }

    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'H' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
            'K' => '#,##0',
        ];
    }

    public function array(): array
    {
        $now = Carbon::now()->translatedFormat('d F Y H:i');
        $pharmacyName = $this->filters['pharmacy_name'] ?? 'Apotek';
        $orderCode = $this->filters['order_code'] ?? '-';
        $stockScopeLabel = $this->filters['stock_scope_label'] ?? 'Sisa Stok';
        $dateFromFmt = !empty($this->filters['date_from'])
            ? Carbon::parse($this->filters['date_from'])->format('d/m/Y')
            : '-';
        $dateToFmt = !empty($this->filters['date_to'])
            ? Carbon::parse($this->filters['date_to'])->format('d/m/Y')
            : '-';

        $rows = [
            ['SMART ORDER - REKAPITULASI PENJUALAN & STOK PER PABRIK'],
            ["Apotek: {$pharmacyName} | No. Pesanan / SP: {$orderCode} | Cakupan Stok: {$stockScopeLabel}"],
            ["Rentang Tanggal: {$dateFromFmt} s/d {$dateToFmt} | Urutan: Nama Pabrik (A - Z) & Penjualan 3 Bulan Terbanyak"],
            ["Waktu Unduh: {$now} | Total: " . count($this->items) . ' obat'],
            [''], // Baris kosong pemisah
            [
                'No.',
                'Nama Obat',
                'Kode Obat',
                'Nama Pabrik',
                'Isi',
                'HNA',
                'Sisa Stok',
                'Min. Stok',
                'Jual (Rentang)',
                'Terjual 1 Bulan',
                'Terjual 3 Bulan',
            ],
        ];

        if (empty($this->items)) {
            $rows[] = ['Tidak ada data barang dengan histori penjualan.', '', '', '', '', '', '', '', '', '', ''];
            return $rows;
        }

        $no = 1;
        $totalCurrentStock = 0;
        $totalMinStock = 0;
        $totalSoldRange = 0;
        $totalSold1Month = 0;
        $totalSold3Months = 0;

        foreach ($this->items as $item) {
            $currentStock = (int) ($item['stocks'] ?? 0);
            $minStock = (int) ($item['min_stock'] ?? 0);
            $soldRange = (int) ($item['total_sold'] ?? 0);
            $sold1M = (int) ($item['sold_1_month'] ?? 0);
            $sold3M = (int) ($item['sold_3_months'] ?? 0);

            $totalCurrentStock += $currentStock;
            $totalMinStock += $minStock;
            $totalSoldRange += $soldRange;
            $totalSold1Month += $sold1M;
            $totalSold3Months += $sold3M;

            $rows[] = [
                $no++,
                (string) ($item['name'] ?? '-'),
                (string) ($item['code'] ?? '-'),
                (string) ($item['factory_name'] ?? 'Tanpa Pabrik'),
                (float) ($item['content'] ?? 1),
                (float) ($item['raw_price'] ?? 0),
                $currentStock,
                $minStock,
                $soldRange,
                $sold1M,
                $sold3M,
            ];
        }

        // Summary row
        $rows[] = [
            '',
            'TOTAL',
            '',
            '',
            '',
            '',
            $totalCurrentStock,
            $totalMinStock,
            $totalSoldRange,
            $totalSold1Month,
            $totalSold3Months,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $headerRow = $this->headerRow;
        $lastCol = 'K';

        // Title styling
        foreach (range(1, 4) as $r) {
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        }

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E40AF'));
        $sheet->getStyle('A3:A4')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF475569'));

        // Table Header Styling
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'], // Royal Blue 800
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        // Data Rows Borders & Alignment
        if ($lastRow > $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->getBorders()->getAllBorders()->applyFromArray([
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E2E8F0'],
            ]);

            // Alignment
            $startDataRow = $headerRow + 1;
            $sheet->getStyle("A{$startDataRow}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startDataRow}:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$startDataRow}:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$startDataRow}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("E{$startDataRow}:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$startDataRow}:{$lastCol}{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Total row styling
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DBEAFE'], // Blue 100
                ],
            ]);
            $sheet->getStyle("B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return [];
    }
}

