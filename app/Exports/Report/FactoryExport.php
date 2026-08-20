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

class FactoryExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;
    protected $selectedType;

    public function __construct($pharmacyId, $startDate, $endDate, $shift, $shiftType, $selectedType)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->shift        = $shift;
        $this->shiftType    = $shiftType;
        $this->selectedType = $selectedType;
    }

    public function array(): array
    {
        $transactions = MedicineTransactions::with(['transactions.medicine.factory'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->where('status', 1)
            ->whereBetween('updated_at', [$this->startDate, $this->endDate])
            ->get();

        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan Per Pabrik (' . ucfirst($this->selectedType) . ')'],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = $this->selectedType === 'rekap'
            ? $this->buildRecap($transactions)
            : $this->buildDetail($transactions);

        return array_merge($header, $body);
    }

    // REKAP
    private function buildRecap($transactions): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {

                $medicine = $item->medicine;
                if (!$medicine) continue;

                $factory = $medicine->factory->name ?? 'LAINNYA';

                if (!isset($grouped[$factory])) {
                    $grouped[$factory] = [
                        'name'  => $factory,
                        'qty'   => 0,
                        'total' => 0,
                    ];
                }

                $grouped[$factory]['qty'] += (int) ($item->quantity ?? 0);
                $grouped[$factory]['total'] += (int) ($item->final_price ?? 0);
            }
        }

        // sort by highest sales 
        $grouped = collect($grouped)->sortByDesc('total')->toArray();

        $rows   = [];
        $rows[] = ['No', 'Pabrik', 'Qty Jual', 'Nilai'];

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

        $rows[] = ['', 'TOTAL', $grandQty, $grandTotal];

        return $rows;
    }

    //  DETAIL
    private function buildDetail($transactions): array
    {
        $rows   = [];
        $rows[] = ['No', 'Pabrik', 'Nama Obat', 'Qty', 'Nilai'];

        $no = 1;
        $grandTotal = 0;

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {

                $medicine = $item->medicine;
                if (!$medicine) continue;

                $factory = $medicine->factory->name ?? 'LAINNYA';

                $rows[] = [
                    $no++,
                    $factory,
                    $medicine->name ?? '-',
                    (int) ($item->quantity ?? 0),
                    (int) ($item->final_price ?? 0),
                ];

                $grandTotal += (int) ($item->final_price ?? 0);
            }
        }

        $rows[] = ['', '', 'TOTAL PENJUALAN', '', $grandTotal];

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

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }

        // Align numbers
        $col = $this->selectedType === 'rekap' ? 'D' : 'E';

        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');
    }

    public function columnWidths(): array
    {
        return $this->selectedType === 'rekap'
            ? [
                'A' => 5,
                'B' => 40,
                'C' => 15,
                'D' => 20,
            ]
            : [
                'A' => 5,
                'B' => 25,
                'C' => 40,
                'D' => 10,
                'E' => 20,
            ];
    }

    public function title(): string
    {
        return 'FactoryExport';
    }
}
