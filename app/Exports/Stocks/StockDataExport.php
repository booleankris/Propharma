<?php

namespace App\Exports\Stocks;

use App\Models\Medicines;
use App\Models\ItemsLog;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockDataExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function collection()
    {
        $medicines = Medicines::query()
            ->withSum(['items_log as qty_orders' => function ($q) {
                $q->where('status', 2);
            }], 'qty')
            ->withSum(['items_log as qty_sales' => function ($q) {
                $q->where('status', 1);
            }], 'qty')
            ->addSelect([
                'id',
                'name',
                'qty_start' => ItemsLog::select('qty_before')
                    ->whereColumn('medicine_id', 'medicines.id')
                    ->orderByDesc('id')
                    ->limit(1),

                'qty_now' => ItemsLog::select('qty_after')
                    ->whereColumn('medicine_id', 'medicines.id')
                    ->orderByDesc('id')
                    ->limit(1),
            ])
            ->get();

        return $medicines->map(function ($m) {
            return [
                'ID' => $m->id,
                'Medicine Name' => $m->name,
                'Qty Start' => $m->qty_start ?? 0,
                'Qty Now' => $m->qty_now ?? 0,
                'Qty Orders' => $m->qty_orders ?? 0,
                'Qty Sales' => $m->qty_sales ?? 0,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Medicine Name',
            'Qty Start',
            'Qty Now',
            'Qty Orders',
            'Qty Sales',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
        $sheet->getStyle('A')->getAlignment()->setHorizontal('center');
        $sheet->getStyle('C:F')->getAlignment()->setHorizontal('right');

        return [];
    }
}
