<?php

namespace App\Exports\Stocks;

use App\Models\ItemsLog;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PrintStockOpnameExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function collection()
    {
        $items = ItemsLog::with('medicines')
            ->where('status', 5) // Stok Opname Minus
            ->orWhere('status', 6); // Stok Opname Plus


        // Filter by medicine name or code
        if ($this->request->filled('searchMedicine')) {
            $items->whereHas('medicines', function ($q) {
                $q->where('name', 'like', "%{$this->request->searchMedicine}%")
                    ->orWhere('code', 'like', "%{$this->request->searchMedicine}%");
            });
        }

        // Filter by date
        if ($this->request->filled('start_date')) {
            $items->whereDate('date', '>=', $this->request->start_date);
        }

        if ($this->request->filled('end_date')) {
            $items->whereDate('date', '<=', $this->request->end_date);
        }

        $items = $items->orderBy('date')->get();

        return $items->map(function ($item) {
            return [
                'Tanggal'          => $item->date ? Carbon::parse($item->date)->format('d/m/Y') : '-',
                'Kode' => $item->medicines->code ?? '-',
                'Nama Obat' => $item->medicines->name ?? '-',
                'Saldo Awal'     => $item->qty_before ?? 0,
                'QTY'    => $item->qty,
                'Jumlah'     => $item->qty_after,
                'Saldo Akhir'     => $item->medicines->stock ?? 0,
                'Keterangan' => $item->status == 1 ? 'Penjualan'
                    : ($item->status == 2 ? 'Pembelian'
                        : ($item->status == 3 ? 'Retur Penjualan'
                            : ($item->status == 4 ? 'Retur Pembelian'
                                : 'Stock Opname'))),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Kode',
            'Nama',
            'Saldo Awal',
            'Qty',
            'Jumlah',
            'Saldo Akhir',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Bold headers
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        // Freeze header row
        $sheet->freezePane('A2');

        // Align numeric columns right
        $sheet->getStyle('D:H')->getAlignment()->setHorizontal('right');

        // Center align status
        $sheet->getStyle('H')->getAlignment()->setHorizontal('center');

        // Optional: header background
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9EAD3'); // light green

        return [];
    }
}
