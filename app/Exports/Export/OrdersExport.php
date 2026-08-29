<?php

namespace App\Exports\Export;

use App\Models\OrderItems;
use Illuminate\Contracts\Queue\ShouldQueue;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Maatwebsite\Excel\Concerns\{
    FromQuery,
    WithHeadings,
    WithMapping,
    WithChunkReading,
    WithStyles,
    ShouldAutoSize
};

class OrdersExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    ShouldQueue,
    WithStyles,
    WithChunkReading,
    ShouldAutoSize
{
    protected $id;

    public function __construct($id)
    {
        $this->id = $id;
    }

    public function query()
    {
        return OrderItems::query()
            ->select('order_items.*')
            ->leftJoin('creditors', 'creditors.code', '=', 'order_items.creditor_code')
            ->with([
                'medicines.creditors',
                'medicines',
                'creditors'
            ])
            ->where('order_id', $this->id)
            ->orderBy('creditors.name', 'asc');
    }

    public function headings(): array
    {
        return [
            'NAMA',
            'QTY',
            'KEMASAN',
            'HRG_HNA',
            'JUMLAH',
            'KREDITUR',
            'DISKON',
            'SISA'
        ];
    }

    public function map($order): array
    {
        $credCode = $order->creditor_code ?? optional($order->creditors)->code;
        $medCred = $order->medicines?->creditors?->firstWhere('code', $credCode)
            ?? $order->medicines?->creditors?->first();
        $disc = $medCred?->pivot?->discount ?? 0;
        $discFormatted = $disc ? ($disc == (int)$disc ? (int)$disc : $disc) . '%' : '0%';

        return [
            $order->medicines?->name,
            $order->quantity,
            $order->medicines?->packaging,
            $order->medicines?->pharmacy_net_price,
            $order->total,
            $order->creditors->name ?? '-',
            $discFormatted,
            $order->medicines?->stock !== null ? (string)$order->medicines->stock : '0',
        ];
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'], 
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(25);

        $sheet->getStyle("A2:H{$highestRow}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6'], 
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        $sheet->getStyle("B2:B{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("C2:C{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("D2:E{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("G2:G{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getStyle("H2:H{$highestRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        return [];
    }
}