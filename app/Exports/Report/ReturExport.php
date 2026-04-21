<?php

namespace App\Exports\Report;

use App\Models\ItemsLog;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReturExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;

    public function __construct($pharmacyId, $startDate, $endDate)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate  = Carbon::parse($startDate)->startOfDay();
        $this->endDate    = Carbon::parse($endDate)->endOfDay();
    }

    public function array(): array
    {
        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Retur Penjualan'],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        return array_merge($header, $this->buildBody());
    }

    private function buildBody(): array
    {
        $items = ItemsLog::with('medicines')
            ->where('status', 3)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->orderBy('created_at', 'asc')
            ->get();

        $rows   = [];
        $rows[] = ['NO', 'TANGGAL', 'NO RETUR', 'NO RESEP', 'NAMA OBAT', 'QTY RETUR', 'JUMLAH'];

        $no         = 1;
        $grandQty   = 0;
        $grandTotal = 0;

        foreach ($items as $item) {
            $qty   = (int) ($item->qty ?? 0);
            $total = (int) ($item->total ?? 0);

            $rows[] = [
                $no++,
                Carbon::parse($item->created_at)->format('d/m/Y'),
                $item->code ?? '-',
                $item->transaction_code ?? '-',
                $item->medicines?->name ?? '-',
                '-' . $qty,
                '-' . number_format($total, 0, ',', '.'),
            ];

            $grandQty   += $qty;
            $grandTotal += $total;
        }

        $rows[] = ['', '', '', '', 'TOTAL RETUR', '', '-' . number_format($grandTotal, 0, ',', '.')];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = $sheet->getHighestColumn();
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7;

        // Merge header rows
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        // Header styles
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Table header bold
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        // Borders on data area
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Row height
        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }

        // Left-align all data
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Right-align Qty Retur (col F) and Jumlah (col G)
        $sheet->getStyle("F{$dataStartRow}:G{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 15,
            'C' => 15,
            'D' => 15,
            'E' => 40,
            'F' => 12,
            'G' => 20,
        ];
    }

    public function title(): string
    {
        return 'ReturPenjualanExport';
    }
}
