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

class SmartOrderFactorySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle, WithColumnFormatting
{
    public function __construct(
        private string $sheetTitle,
        private array $items,
        private array $filters = []
    ) {}

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
            'E' => '#,##0',
            'F' => '#,##0',
            'G' => '#,##0',
            'I' => '#,##0',
        ];
    }

    public function array(): array
    {
        $periodFrom = Carbon::parse($this->filters['period_from'])->format('d/m/Y');
        $periodTo = Carbon::parse($this->filters['period_to'])->format('d/m/Y');
        $search = trim((string) ($this->filters['search'] ?? '')) ?: 'Semua';
        $rows = [
            ['SMART ORDER - REKOMENDASI PESANAN OBAT'],
            ['Apotek: ' . ($this->filters['pharmacy_name'] ?? 'Apotek') . ' | No. Pesanan / SP: ' . ($this->filters['order_code'] ?? '-')],
            ["Periode Penjualan: {$periodFrom} s/d {$periodTo} | Pencarian: {$search}"],
            ['Waktu Unduh: ' . Carbon::now()->translatedFormat('d F Y H:i') . ' | Total: ' . count($this->items) . ' obat'],
            [''],
            ['No.', 'Kode Obat', 'Nama Obat', 'Kemasan', 'Harga Beli (HNA)', 'Sisa Stok', 'Min. Stok', 'Status Stok', 'Jual 6 Bulan'],
        ];

        if (!$this->items) {
            $rows[] = ['Tidak ada obat dari pabrik ini yang terjual dalam 6 bulan terakhir.'];
            return $rows;
        }

        $totalSold = 0;
        foreach ($this->items as $index => $item) {
            $stock = (int) ($item['stocks'] ?? 0);
            $minStock = (int) ($item['min_stock'] ?? 0);
            $sold = (int) ($item['sold_6_months'] ?? 0);
            $totalSold += $sold;

            $rows[] = [
                $index + 1,
                (string) ($item['code'] ?? '-'),
                (string) ($item['name'] ?? '-'),
                (string) ($item['packaging'] ?? '-'),
                (float) ($item['raw_price'] ?? 0),
                $stock,
                $minStock,
                $stock <= $minStock ? 'DI BAWAH MIN' : 'AMAN',
                $sold,
            ];
        }

        $rows[] = ['', '', 'TOTAL', '', '', '', '', '', $totalSold];
        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        $headerRow = 6;

        foreach (range(1, 4) as $row) {
            $sheet->mergeCells("A{$row}:I{$row}");
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF6B21A8'));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A3:A4')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF4B5563'));
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7E22CE']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        if ($lastRow > $headerRow) {
            $sheet->getStyle("A{$headerRow}:I{$lastRow}")->getBorders()->getAllBorders()->applyFromArray([
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'E5E7EB'],
            ]);
            $sheet->getStyle("A7:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B7:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C7:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D7:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E7:G{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('H7:H' . ($lastRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I7:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$lastRow}:I{$lastRow}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F3E8FF']],
            ]);
            $sheet->getStyle("C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return [];
    }
}
