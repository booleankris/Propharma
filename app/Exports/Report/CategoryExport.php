<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class CategoryExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;

    public function __construct($pharmacyId, $startDate, $endDate, $shift, $shiftType)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate  = Carbon::parse($startDate)->startOfDay();
        $this->endDate    = Carbon::parse($endDate)->endOfDay();
        $this->shift      = $shift;
        $this->shiftType  = $shiftType;
    }

    public function array(): array
    {
        $transactions = MedicineTransactions::with(['transactions.medicine.category'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->where('status', 1)
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->get();

        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan (Golongan Obat)'],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        return array_merge($header, $this->buildGolongan($transactions));
    }

    private function buildGolongan($transactions): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {

                $medicine = $item->medicine;
                if (!$medicine) continue;

                $category = $medicine->category->name ?? 'LAINNYA';

                if (!isset($grouped[$category])) {
                    $grouped[$category] = [
                        'name'  => $category,
                        'qty'   => 0,
                        'total' => 0,
                    ];
                }

                $grouped[$category]['qty'] += (int) ($item->quantity ?? 0);
                $grouped[$category]['total'] += (int) ($item->final_price ?? 0);
            }
        }

        ksort($grouped);

        $rows   = [];
        $rows[] = ['No', 'Golongan', 'Qty', 'Nilai'];

        $no = 1;
        $grandQty = 0;
        $grandTotal = 0;

        foreach ($grouped as $data) {
            $rows[] = [
                $no++,
                $data['name'],
                $data['qty'],
                $data['total'],
            ];

            $grandQty += $data['qty'];
            $grandTotal += $data['total'];
        }

        // TOTAL row
        $rows[] = ['', 'TOTAL', $grandQty, $grandTotal];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $dataStartRow = 7;

        // Merge header
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        // Header style
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Table header bold
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        // Borders
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Row height
        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }

        // Align numbers right (Qty & Nilai)
        $sheet->getStyle("C{$dataStartRow}:D{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Format number
        $sheet->getStyle("C{$dataStartRow}:D{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 40,
            'C' => 15,
            'D' => 20,
        ];
    }

    public function title(): string
    {
        return 'CategoryExport';
    }
}
