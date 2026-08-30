<?php

namespace App\Exports\Report;

use App\Models\Batches;
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

class ExpiryDateExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $pharmacyName;
    protected $lastRow = 1;
    protected $statusRows = [];

    public function __construct($pharmacyId = null)
    {
        $this->pharmacyId   = $pharmacyId ?? getActivePharmacyId();
        $pharmacy           = Pharmacies::find($this->pharmacyId);
        $this->pharmacyName = $pharmacy?->name ?? 'GUDANG / HO';
    }

    public function title(): string
    {
        return 'DATA KADALUARSA (ED)';
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->pharmacyName];
        $rows[] = ['LAPORAN MONITORING OBAT & TANGGAL KADALUARSA (ED)'];
        $rows[] = ['Tanggal Export: ' . now()->format('d/m/Y H:i')];
        $rows[] = [''];

        // Table headers
        $rows[] = [
            'NO',
            'KODE OBAT',
            'NAMA OBAT',
            'KATEGORI / TYPE',
            'SATUAN',
            'NO BATCH',
            'TANGGAL ED',
            'STATUS KADALUARSA',
            'STOK GUDANG',
            'STOK PELAYANAN',
            'TOTAL STOK',
        ];

        $warehouseId = getWarehousePharmacyId();

        // Pre-aggregate counter stocks efficiently
        $counterStocks = \Illuminate\Support\Facades\DB::table('medicine_transfer_items')
            ->where('status', 1)
            ->where(function ($sub) {
                $sub->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            })
            ->groupBy('batches_id')
            ->select('batches_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_counter'))
            ->pluck('total_counter', 'batches_id');

        $batches = \Illuminate\Support\Facades\DB::table('batches')
            ->leftJoin('medicines', 'medicines.id', '=', 'batches.medicine_id')
            ->leftJoin('medicine_categories', 'medicine_categories.id', '=', 'medicines.medicine_category_id')
            ->whereNotNull('batches.expired_date')
            ->where('batches.stock', '>=', 0)
            ->select([
                'batches.id',
                'batches.name as batch_name',
                'batches.expired_date',
                'batches.stock as batch_stock',
                'batches.pharmacy_id',
                'medicines.code as medicine_code',
                'medicines.name as medicine_name',
                'medicines.type as medicine_type',
                'medicines.unit as medicine_unit',
                'medicine_categories.name as category_name',
            ])
            ->orderBy('batches.expired_date', 'asc')
            ->cursor();

        $no = 1;
        $now = now();
        $startRow = 6;
        $currentRow = $startRow;

        foreach ($batches as $batch) {
            if (!$batch->medicine_name) continue;

            $ed = null;
            try {
                $dateStr = str_replace('/', '-', (string) $batch->expired_date);
                $ed = Carbon::parse($dateStr);
            } catch (\Throwable $e) {
                $ed = null;
            }

            $statusText = 'AMAN';
            $statusCode = 'safe';

            if ($ed) {
                $days = $now->diffInDays($ed, false);
                if ($days < 0) {
                    $statusText = 'KADALUARSA (EXPIRED)';
                    $statusCode = 'expired';
                } elseif ($days <= 90) {
                    $statusText = 'KRITIS (< 3 BULAN)';
                    $statusCode = 'critical';
                } elseif ($days <= 180) {
                    $statusText = 'WARNING (< 6 BULAN)';
                    $statusCode = 'warning';
                } else {
                    $statusText = 'AMAN';
                    $statusCode = 'safe';
                }
            }

            $storageStock = ($batch->pharmacy_id == $warehouseId) ? (int) $batch->batch_stock : 0;
            $counterStock = (int) ($counterStocks[$batch->id] ?? 0);
            if ($batch->pharmacy_id != $warehouseId && $counterStock === 0) {
                $counterStock = (int) $batch->batch_stock;
            }
            $totalStock = $storageStock + $counterStock;

            // Only list items that have stock or are active
            if ($totalStock <= 0 && $statusCode === 'safe') {
                continue;
            }

            $rows[] = [
                $no++,
                $batch->medicine_code ?? '-',
                $batch->medicine_name ?? '-',
                $batch->medicine_type ?? ($batch->category_name ?? '-'),
                $batch->medicine_unit ?? '-',
                $batch->batch_name ?? '-',
                $ed ? $ed->format('d/m/Y') : (string) $batch->expired_date,
                $statusText,
                $storageStock,
                $counterStock,
                $totalStock,
            ];

            $this->statusRows[$currentRow] = $statusCode;
            $currentRow++;
        }

        $this->lastRow = count($rows);
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');
        $sheet->mergeCells('A3:K3');
        $sheet->getStyle('A1:K3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(12);

        // Header Row
        $sheet->getStyle('A5:K5')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'], // Teal 700
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(24);

        if ($this->lastRow >= 6) {
            $sheet->getStyle("A6:K{$this->lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Alignment
            $sheet->getStyle("A6:A{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B6:B{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C6:D{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("E6:H{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I6:K{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Conditional Row / Status coloring
            foreach ($this->statusRows as $rowNum => $code) {
                if ($code === 'expired') {
                    $sheet->getStyle("A{$rowNum}:K{$rowNum}")->getFont()->setBold(true);
                    $sheet->getStyle("H{$rowNum}")->applyFromArray([
                        'font' => ['color' => ['rgb' => '991B1B']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']],
                    ]);
                } elseif ($code === 'critical') {
                    $sheet->getStyle("H{$rowNum}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '9A3412']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFEDD5']],
                    ]);
                } elseif ($code === 'warning') {
                    $sheet->getStyle("H{$rowNum}")->applyFromArray([
                        'font' => ['color' => ['rgb' => '854D0E']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF9C3']],
                    ]);
                }
            }
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 14,
            'C' => 36,
            'D' => 20,
            'E' => 10,
            'F' => 18,
            'G' => 14,
            'H' => 24,
            'I' => 14,
            'J' => 16,
            'K' => 14,
        ];
    }
}
