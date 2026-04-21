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

class MedicineExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
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
        if ($this->shiftType == 'semua') {
            $transactions = MedicineTransactions::with(['transactions.medicine.factory'])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->get();
            $transactionsdetail = MedicineTransactions::with(['transactions.medicine.factory'])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->get();
        } else {
            $transactions = MedicineTransactions::with(['transactions.medicine.factory'])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->when($this->shiftType === 'shift', function ($q) {
                    $q->whereHas('shift_logs', function ($s) {
                        $s->where('shift_id', $this->shift);
                    });
                })
                ->get();
            $transactionsdetail = MedicineTransactions::with(['transactions.medicine.factory'])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereBetween('created_at', [$this->startDate, $this->endDate])
                ->when($this->shiftType === 'shift', function ($q) {
                    $q->whereHas('shift_logs', function ($s) {
                        $s->where('shift_id', $this->shift);
                    });
                })
                ->get();
        }


        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan (' . ucfirst($this->selectedType) . ' Obat)'],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = $this->selectedType === 'rekap'
            ? $this->buildRecap($transactions)
            : $this->buildDetail($transactionsdetail);

        return array_merge($header, $body);
    }

    private function buildRecap($transactions): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {

                $medicine = $item->medicine;
                if (!$medicine) continue;

                $key = $medicine->id;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'name'    => $medicine->name ?? '-',
                        'factory' => $medicine->factory->name ?? '-',
                        'qty'     => 0,
                        'total'   => 0,
                    ];
                }

                $grouped[$key]['qty'] += (int) ($item->quantity ?? 0);
                $grouped[$key]['total'] += (int) ($item->final_price ?? 0);
            }
        }

        $rows   = [];
        $rows[] = ['No', 'Nama Obat', 'Pabrik', 'Qty Jual', 'Nilai Jual'];

        $no = 1;

        foreach ($grouped as $data) {
            $rows[] = [
                $no++,
                $data['name'],
                $data['factory'],
                $data['qty'],
                $data['total'],
            ];
        }

        return $rows;
    }

    private function buildDetail($transactions): array
    {
        $rows   = [];

        $rows[] = ['No','No. Transaksi','Nama Obat', 'Qty', 'Bruto', 'Disc', 'Netto'];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {

                $medicine = $item->medicine;
                if (!$medicine) continue;

                $price    = (int) ($item->price ?? 0);
                $qty      = (int) ($item->quantity ?? 0);
                $subtotal = (int) ($item->final_price ?? 0);

                $unit = $item->unit ?? ''; 
                $no = 1;
                $rows[] = [
                    $no++,
                    $item->transactions->transaction_code . '/' . $item->cart_type ?? '-',
                    $medicine->name ?? '-',
                    $qty,
                    $item->total_price,
                    $item->discount,
                    $item->final_price,
                ];
            }
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = $sheet->getHighestColumn();
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7; // header rows (1–6) + table starts at row 7
        $col = $this->selectedType === 'rekap' ? 'E' : 'F';

        $sheet->getStyle("{$col}2:{$col}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');


        // Merge header info cells across all columns
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        // Pharmacy name — bold, larger font
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Bold the table column header row
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        // Borders only on the data table (row 7 onward)
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = 7; $i <= $sheet->getHighestRow(); $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }
        // Alignment for the whole table
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->getStyle("F2:F{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("E2:E{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    public function columnWidths(): array
    {
        return $this->selectedType === 'rekap'
            ? [
                'A' => 5,
                'B' => 40,
                'C' => 25,
                'D' => 12,
                'E' => 18,
            ]
            : [
                'A' => 5,
                'B' => 20,
                'C' => 35,
                'D' => 25,
                'E' => 10,
                'F' => 18,
            ];
    }

    public function title(): string
    {
        return 'MedicineExport';
    }
}
