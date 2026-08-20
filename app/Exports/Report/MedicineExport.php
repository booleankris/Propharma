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
        $query = MedicineTransactions::with(['transactions.medicine.factory'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->where('status', 1)
            ->whereBetween('updated_at', [$this->startDate, $this->endDate]);

        if ($this->shiftType === 'shift' && !empty($this->shift)) {
            $query->whereHas('shift_logs', function ($s) {
                $s->where('shift_id', $this->shift);
            });
        }

        $transactions = $query->get();

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
            : $this->buildDetail($transactions);

        return array_merge($header, $body);
    }

    private function buildRecap($transactions): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                if (!$medicine) {
                    $key = 'item_' . $item->id;
                    $name = $item->medicine_name ?? 'Obat (Tanpa Master)';
                    $factory = '-';
                } else {
                    $key = $medicine->id;
                    $name = $medicine->name ?? '-';
                    $factory = $medicine->factory?->name ?? '-';
                }

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'name'    => $name,
                        'factory' => $factory,
                        'qty'     => 0,
                        'total'   => 0,
                    ];
                }

                $grouped[$key]['qty'] += (float) ($item->quantity ?? 0);
                $grouped[$key]['total'] += (float) ($item->final_price ?? 0);
            }
        }

        // Sort alphabetically by medicine name
        uasort($grouped, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $rows   = [];
        $rows[] = ['No', 'Nama Obat', 'Pabrik', 'Qty Jual', 'Nilai Jual'];

        $no = 1;
        $grandQty = 0;
        $grandTotal = 0;

        foreach ($grouped as $data) {
            $rows[] = [
                $no++,
                $data['name'],
                $data['factory'],
                $data['qty'],
                $data['total'],
            ];

            $grandQty += $data['qty'];
            $grandTotal += $data['total'];
        }

        $rows[] = ['', 'TOTAL', '', $grandQty, $grandTotal];

        return $rows;
    }

    private function buildDetail($transactions): array
    {
        $itemsList = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                $medName = $medicine?->name ?? ($item->medicine_name ?? '-');

                $trxCode = $trx->transaction_code ?? '-';
                $cartType = $item->cart_type ? '/' . $item->cart_type : '';
                $nomor = $trxCode . $cartType;

                $qty   = (float) ($item->quantity ?? 0);
                $bruto = (float) ($item->total_price ?? ($item->raw_total ?? 0));
                $disc  = (float) ($item->discount ?? 0);
                $netto = (float) ($item->final_price ?? 0);

                $itemsList[] = [
                    'nomor'    => $nomor,
                    'name'     => $medName,
                    'qty'      => $qty,
                    'bruto'    => $bruto,
                    'disc'     => $disc,
                    'netto'    => $netto,
                ];
            }
        }

        // Sort by Nama Obat (A-Z), then by Nomor Transaksi
        usort($itemsList, function ($a, $b) {
            $cmp = strcasecmp($a['name'], $b['name']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcasecmp($a['nomor'], $b['nomor']);
        });

        $rows   = [];
        $rows[] = ['No.', 'Nomor', 'Nama Obat', 'Qty', 'Bruto', 'Disc', 'Netto'];

        $no = 1;
        $grandQty = 0;
        $grandBruto = 0;
        $grandDisc = 0;
        $grandNetto = 0;

        foreach ($itemsList as $row) {
            $rows[] = [
                $no++,
                $row['nomor'],
                $row['name'],
                $row['qty'],
                $row['bruto'],
                $row['disc'],
                $row['netto'],
            ];

            $grandQty += $row['qty'];
            $grandBruto += $row['bruto'];
            $grandDisc += $row['disc'];
            $grandNetto += $row['netto'];
        }

        $rows[] = ['', 'TOTAL', '', $grandQty, $grandBruto, $grandDisc, $grandNetto];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = $sheet->getHighestColumn();
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7; // header rows (1–6) + table starts at row 7

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

        // Bold the TOTAL summary row at the bottom
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
            ->getFont()->setBold(true);

        // Borders only on the data table (row 7 onward)
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = 7; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);
        }

        // Default alignment for data table
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Center No
        $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($this->selectedType === 'rekap') {
            // Align Qty & Nilai right
            $sheet->getStyle("D{$dataStartRow}:E{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Format Nilai as number
            $sheet->getStyle("E" . ($dataStartRow + 1) . ":E{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        } else {
            // Align Qty, Bruto, Disc, Netto right
            $sheet->getStyle("D{$dataStartRow}:G{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Format Bruto, Disc, Netto as number
            $sheet->getStyle("E" . ($dataStartRow + 1) . ":G{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }
    }

    public function columnWidths(): array
    {
        return $this->selectedType === 'rekap'
            ? [
                'A' => 6,
                'B' => 42,
                'C' => 28,
                'D' => 14,
                'E' => 20,
            ]
            : [
                'A' => 6,
                'B' => 24,
                'C' => 38,
                'D' => 12,
                'E' => 18,
                'F' => 16,
                'G' => 20,
            ];
    }

    public function title(): string
    {
        return 'MedicineExport';
    }
}
