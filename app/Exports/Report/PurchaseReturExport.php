<?php

namespace App\Exports\Report;

use App\Models\ItemsLog;
use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchaseReturExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $lastRow = 1;

    public function __construct($pharmacyId, $startDate, $endDate)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $pharmacy           = Pharmacies::find($pharmacyId);
        $this->pharmacyName = $pharmacy?->name ?? 'APOTEK';
    }

    public function title(): string
    {
        return 'RETUR PEMBELIAN';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->pharmacyName];
        $rows[] = ['LAPORAN RETUR PEMBELIAN KE SUPPLIER / PBF'];
        $rows[] = ['Periode: ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')];
        $rows[] = [''];

        // Table headers
        $rows[] = ['NO', 'KODE RETUR / FAKTUR', 'TANGGAL', 'NAMA OBAT', 'SUPPLIER / PBF', 'QTY RETUR', 'TOTAL (RP)'];

        $logs = ItemsLog::with(['medicines.creditor', 'medicines.creditors', 'batches'])
            ->where('status', 4) // 4 = Retur Pembelian
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        $no = 1;
        $totalQty = 0;
        $grandTotal = 0;

        foreach ($logs as $log) {
            $totalVal = is_numeric($log->total) ? (float) $log->total : 0;
            $supplier = $log->medicines?->creditor?->name ?? ($log->medicines?->creditors?->first()?->name ?? '-');

            $rows[] = [
                $no++,
                $log->transaction_code ?? '-',
                safeDateFormat($log->created_at, 'd/m/Y H:i'),
                $log->medicines?->name ?? '-',
                $supplier,
                $log->qty ?? 0,
                $totalVal,
            ];
            $totalQty += (int) ($log->qty ?? 0);
            $grandTotal += $totalVal;
        }

        // Summary row
        $rows[] = ['', 'TOTAL', '', '', '', $totalQty, $grandTotal];

        $this->lastRow = count($rows);
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');
        $sheet->getStyle('A1:G3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(12);

        // Header Row
        $sheet->getStyle('A5:G5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F46E5'], // Indigo 600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(22);

        if ($this->lastRow >= 6) {
            $sheet->getStyle("A6:G{$this->lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Alignment
            $sheet->getStyle("A6:A{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B6:C{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D6:E{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("F6:G{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Format Currency
            $sheet->getStyle("G6:G{$this->lastRow}")->getNumberFormat()->setFormatCode('#,##0');

            // Total Row
            $sheet->getStyle("A{$this->lastRow}:G{$this->lastRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$this->lastRow}:G{$this->lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 22,
            'C' => 18,
            'D' => 35,
            'E' => 25,
            'F' => 12,
            'G' => 16,
        ];
    }
}
