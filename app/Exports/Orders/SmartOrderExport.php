<?php

namespace App\Exports\Orders;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SmartOrderExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle, WithColumnFormatting
{
    protected array $items;
    protected array $filters;

    public function __construct(array $items, array $filters = [])
    {
        $this->items = $items;
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'Smart Order';
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'I' => '#,##0',
            'J' => '#,##0',
            'K' => '#,##0',
        ];
    }

    public function array(): array
    {
        $now = Carbon::now()->translatedFormat('d F Y H:i');

        $dateFromFmt = !empty($this->filters['date_from'])
            ? Carbon::parse($this->filters['date_from'])->format('d/m/Y')
            : '-';
        $dateToFmt = !empty($this->filters['date_to'])
            ? Carbon::parse($this->filters['date_to'])->format('d/m/Y')
            : '-';

        $sortLabels = [
            'name_asc' => 'Abjad (A - Z)',
            'sold_desc' => 'Qty Terjual Terbanyak',
            'stock_asc' => 'Sisa Stok Paling Sedikit',
            'sold_desc_stock_asc' => 'Terjual Terbanyak & Stok Sedikit',
        ];
        $sortLabel = $sortLabels[$this->filters['sort'] ?? ''] ?? ($this->filters['sort'] ?? 'Abjad (A - Z)');
        $searchLabel = !empty($this->filters['search']) ? $this->filters['search'] : 'Semua';
        $pharmacyName = $this->filters['pharmacy_name'] ?? 'Apotek';
        $orderCode = $this->filters['order_code'] ?? '-';

        $rows = [
            ['SMART ORDER - REKOMENDASI PESANAN OBAT'],
            ["Apotek: {$pharmacyName} | No. Pesanan / SP: {$orderCode}"],
            ["Rentang Filter Transaksi: {$dateFromFmt} s/d {$dateToFmt} | Urutan: {$sortLabel} | Pencarian: {$searchLabel}"],
            ["Waktu Unduh: {$now} | Total: " . count($this->items) . ' obat'],
            [''], // Baris kosong pemisah
            [
                'No.',
                'Kode Obat',
                'Nama Obat',
                'Kemasan',
                'Harga Beli (HNA)',
                'Sisa Stok',
                'Min. Stok',
                'Status Stok',
                'Jual (Rentang)',
                'Jual 1 Bln (30 Hari)',
                'Jual 3 Bln (90 Hari)',
            ],
        ];

        if (empty($this->items)) {
            $rows[] = ['Tidak ada data rekomendasi smart order untuk filter yang dipilih.', '', '', '', '', '', '', '', '', '', ''];
            return $rows;
        }

        $no = 1;
        $totalSoldRange = 0;
        $totalSold1Month = 0;
        $totalSold3Months = 0;

        foreach ($this->items as $item) {
            $stocks = (int) ($item['stocks'] ?? 0);
            $minStock = (int) ($item['min_stock'] ?? 0);
            $isLowStock = $stocks <= $minStock;
            $statusStock = $isLowStock ? 'DI BAWAH MIN' : 'AMAN';

            $totalSold = (int) ($item['total_sold'] ?? 0);
            $sold1M = (int) ($item['sold_1_month'] ?? 0);
            $sold3M = (int) ($item['sold_3_months'] ?? 0);

            $totalSoldRange += $totalSold;
            $totalSold1Month += $sold1M;
            $totalSold3Months += $sold3M;

            $rows[] = [
                $no++,
                (string) ($item['code'] ?? '-'),
                (string) ($item['name'] ?? '-'),
                (string) ($item['packaging'] ?? '-'),
                (float) ($item['raw_price'] ?? 0),
                $stocks,
                $minStock,
                $statusStock,
                $totalSold,
                $sold1M,
                $sold3M,
            ];
        }

        // Summary row
        $rows[] = [
            '',
            '',
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            $totalSoldRange,
            $totalSold1Month,
            $totalSold3Months,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $headerRow = 6;

        // Title styling
        $sheet->mergeCells("A1:K1");
        $sheet->mergeCells("A2:K2");
        $sheet->mergeCells("A3:K3");
        $sheet->mergeCells("A4:K4");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B21A8'));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3:A4')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF4B5563'));

        // Table Header Styling
        $sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '7E22CE'], // Purple 700
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
            $sheet->getStyle("A{$headerRow}:K{$lastRow}")->getBorders()->getAllBorders()->applyFromArray([
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E5E7EB'],
            ]);

            // Alignment
            $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B7:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C7:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D7:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E7:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H7:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I7:K{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Total row styling
            $sheet->getStyle("A{$lastRow}:K{$lastRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F3E8FF'], // Purple 100
                ],
            ]);
            $sheet->getStyle("C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return [];
    }
}
