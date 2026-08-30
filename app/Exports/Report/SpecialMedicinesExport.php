<?php

namespace App\Exports\Report;

use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SpecialMedicinesExport implements WithMultipleSheets
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;

    public const CATEGORIES = [
        'NARKOTIKA' => [
            'title'      => 'NARKOTIKA',
            'type_query' => ['NARKOTIKA'],
            'color'      => 'FFA07A', // Salmon Orange
            'textColor'  => '991B1B',
        ],
        'PSIKOTROPIKA' => [
            'title'      => 'PSIKOTROPIKA',
            'type_query' => ['PSIKOTROPIKA'],
            'color'      => '93C5FD', // Light Blue
            'textColor'  => '1E3A8A',
        ],
        'OBAT TERTENTU' => [
            'title'      => 'OBAT OBAT TERTENTU',
            'type_query' => ['OBAT-OBAT TERTENTU (OOT)', 'OBAT TERTENTU', 'OBAT-OBAT TERTENTU', 'OOT'],
            'color'      => 'A7F3D0', // Mint Green
            'textColor'  => '065F46',
        ],
        'PREKURSOR' => [
            'title'      => 'PREKURSOR',
            'type_query' => ['PREKURSOR'],
            'color'      => 'FDE047', // Light Yellow
            'textColor'  => '854D0E',
        ],
    ];

    public function __construct($pharmacyId, $startDate, $endDate)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = $startDate;
        $this->endDate      = $endDate;
        $pharmacy           = Pharmacies::find($pharmacyId);
        $this->pharmacyName = $pharmacy?->name ?? 'APOTEK';
    }

    public function sheets(): array
    {
        $sheets = [];

        // Tab 1: Ringkasan Semua Golongan (All in one sheet)
        $sheets[] = new SpecialCategoryAllInOneSheet(
            $this->pharmacyId,
            $this->startDate,
            $this->endDate,
            $this->pharmacyName
        );

        // Tab 2-5: Per Golongan
        foreach (self::CATEGORIES as $key => $config) {
            $sheets[] = new SpecialCategorySingleSheet(
                $this->pharmacyId,
                $this->startDate,
                $this->endDate,
                $this->pharmacyName,
                $config
            );
        }

        return $sheets;
    }
}

/**
 * Single sheet containing all 4 sections stacked together (matching the exact reference screenshot).
 */
class SpecialCategoryAllInOneSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $headerRows = [];
    protected $totalDataRows = 0;

    public function __construct($pharmacyId, $startDate, $endDate, $pharmacyName)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName = $pharmacyName;
    }

    public function title(): string
    {
        return 'SEMUA GOLONGAN';
    }

    public function array(): array
    {
        $rows = [];
        $currentRow = 1;

        // Top Document Header
        $rows[] = [$this->pharmacyName];
        $rows[] = ['LAPORAN MUTASI OBAT GOLONGAN KHUSUS (SIPNAP)'];
        $rows[] = ['Periode: ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')];
        $rows[] = [''];
        // Pre-aggregate queries in bulk for high performance and low memory
        $inBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->where('created_at', '<', $this->startDate)
            ->whereIn('status', [2, 3, 5, 7])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->where('created_at', '<', $this->startDate)
            ->whereIn('status', [1, 4, 6])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $inRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('status', [2, 3, 5, 7])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('status', [1, 4, 6])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $batchesStockGroup = \Illuminate\Support\Facades\DB::table('batches')
            ->where(function ($q) {
                $q->where('pharmacy_id', $this->pharmacyId)
                  ->orWhere('pharmacy_id', 9);
            })
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(stock) as total_stock'))
            ->pluck('total_stock', 'medicine_id');

        $nearestBatches = \Illuminate\Support\Facades\DB::table('batches')
            ->whereNotNull('expired_date')
            ->where('stock', '>', 0)
            ->orderBy('expired_date', 'asc')
            ->get()
            ->groupBy('medicine_id')
            ->map(function ($items) {
                return $items->first()->expired_date ?? null;
            });

        foreach (SpecialMedicinesExport::CATEGORIES as $key => $config) {
            $sectionStartRow = $currentRow;

            // Section Banner (e.g. NARKOTIKA)
            $rows[] = [$config['title'], '', '', '', '', '', '', '', ''];
            $bannerRow = $currentRow;
            $currentRow++;

            // Table Column Headers
            $rows[] = ['NO', 'NAMA OBAT', 'AWAL', 'MASUK', 'KELUAR', 'JUMLAH', 'FISIK', 'SELISIH', 'KETERANGAN'];
            $colHeaderRow = $currentRow;
            $currentRow++;

            $medicines = Medicines::where(function ($q) use ($config) {
                foreach ($config['type_query'] as $t) {
                    $q->orWhere('type', 'LIKE', "%{$t}%");
                }
            })
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->get();

            $no = 1;
            $startDataRow = $currentRow;

            foreach ($medicines as $med) {
                $inBefore  = (int) ($inBeforeGroup[$med->id] ?? 0);
                $outBefore = (int) ($outBeforeGroup[$med->id] ?? 0);
                $stokAwal  = max(0, $inBefore - $outBefore);

                if ($stokAwal === 0 && $inBefore === 0 && $outBefore === 0) {
                    $stokAwal = (int) ($med->stock ?? 0);
                }

                $masuk  = (int) ($inRangeGroup[$med->id] ?? 0);
                $keluar = (int) ($outRangeGroup[$med->id] ?? 0);
                $jumlah = $stokAwal + $masuk - $keluar;

                $fisik = (int) ($batchesStockGroup[$med->id] ?? 0);
                if ($fisik === 0 && $jumlah > 0) {
                    $fisik = $jumlah;
                }

                $selisih = $jumlah - $fisik;

                $edDate = $nearestBatches[$med->id] ?? null;
                $keterangan = '';
                if ($edDate) {
                    $ed = safeDateFormat($edDate, 'm/Y');
                    if ($ed !== '-') {
                        $keterangan = 'ED ' . $ed;
                    }
                }

                $rows[] = [
                    $no++,
                    $med->name,
                    $stokAwal,
                    $masuk > 0 ? $masuk : 0,
                    $keluar > 0 ? $keluar : 0,
                    $jumlah,
                    $fisik,
                    $selisih,
                    $keterangan,
                ];
                $currentRow++;
            }

            $endDataRow = $currentRow - 1;

            $this->headerRows[] = [
                'bannerRow'     => $bannerRow,
                'colHeaderRow'  => $colHeaderRow,
                'startDataRow'  => $startDataRow,
                'endDataRow'    => $endDataRow,
                'color'         => $config['color'],
                'textColor'     => $config['textColor'],
            ];

            // Space between tables
            $rows[] = ['', '', '', '', '', '', '', '', ''];
            $currentRow++;
        }

        $this->totalDataRows = $currentRow;
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Top Titles
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');
        $sheet->getStyle('A1:I3')->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(12);
        $sheet->getStyle('A3')->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('64748B'));
        $sheet->getStyle('A1:I3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ($this->headerRows as $meta) {
            $banner = $meta['bannerRow'];
            $colH   = $meta['colHeaderRow'];
            $startD = $meta['startDataRow'];
            $endD   = $meta['endDataRow'];

            // Banner styling
            $sheet->mergeCells("A{$banner}:I{$banner}");
            $sheet->getStyle("A{$banner}:I{$banner}")->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => $meta['textColor']],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $meta['color']],
                ],
            ]);
            $sheet->getRowDimension($banner)->setRowHeight(24);

            // Column Header styling
            $sheet->getStyle("A{$colH}:I{$colH}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F1F5F9'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
            $sheet->getRowDimension($colH)->setRowHeight(20);

            // Data rows styling & borders
            if ($endD >= $startD) {
                $sheet->getStyle("A{$startD}:I{$endD}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Alignment
                $sheet->getStyle("A{$startD}:A{$endD}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$startD}:B{$endD}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("C{$startD}:H{$endD}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("I{$startD}:I{$endD}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

                // Highlight warning for items with ED
                for ($r = $startD; $r <= $endD; $r++) {
                    $ket = $sheet->getCell("I{$r}")->getValue();
                    if (!empty($ket)) {
                        $sheet->getStyle("I{$r}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('BE185D'));
                    }
                }
            }
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // NO
            'B' => 38,  // NAMA OBAT
            'C' => 10,  // AWAL
            'D' => 10,  // MASUK
            'E' => 10,  // KELUAR
            'F' => 11,  // JUMLAH
            'G' => 11,  // FISIK
            'H' => 10,  // SELISIH
            'I' => 20,  // KETERANGAN
        ];
    }
}

/**
 * Individual sheet per single special category (e.g. Narkotika only).
 */
class SpecialCategorySingleSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $config;
    protected $lastRow = 1;

    public function __construct($pharmacyId, $startDate, $endDate, $pharmacyName, array $config)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName = $pharmacyName;
        $this->config       = $config;
    }

    public function title(): string
    {
        return substr($this->config['title'], 0, 31);
    }

    public function array(): array
    {
        $rows = [];
        $rows[] = [$this->pharmacyName];
        $rows[] = ['LAPORAN MUTASI ' . $this->config['title']];
        $rows[] = ['Periode: ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')];
        $rows[] = [''];

        // Banner
        $rows[] = [$this->config['title'], '', '', '', '', '', '', '', ''];

        // Columns
        $rows[] = ['NO', 'NAMA OBAT', 'AWAL', 'MASUK', 'KELUAR', 'JUMLAH', 'FISIK', 'SELISIH', 'KETERANGAN'];

        // Pre-aggregate queries in bulk for single sheet
        $inBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->where('created_at', '<', $this->startDate)
            ->whereIn('status', [2, 3, 5, 7])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->where('created_at', '<', $this->startDate)
            ->whereIn('status', [1, 4, 6])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $inRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('status', [2, 3, 5, 7])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereIn('status', [1, 4, 6])
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $batchesStockGroup = \Illuminate\Support\Facades\DB::table('batches')
            ->where(function ($q) {
                $q->where('pharmacy_id', $this->pharmacyId)
                  ->orWhere('pharmacy_id', 9);
            })
            ->groupBy('medicine_id')
            ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(stock) as total_stock'))
            ->pluck('total_stock', 'medicine_id');

        $nearestBatches = \Illuminate\Support\Facades\DB::table('batches')
            ->whereNotNull('expired_date')
            ->where('stock', '>', 0)
            ->orderBy('expired_date', 'asc')
            ->get()
            ->groupBy('medicine_id')
            ->map(function ($items) {
                return $items->first()->expired_date ?? null;
            });

        $medicines = Medicines::where(function ($q) {
            foreach ($this->config['type_query'] as $t) {
                $q->orWhere('type', 'LIKE', "%{$t}%");
            }
        })
        ->where('status', 1)
        ->orderBy('name', 'asc')
        ->get();

        $no = 1;
        foreach ($medicines as $med) {
            $inBefore  = (int) ($inBeforeGroup[$med->id] ?? 0);
            $outBefore = (int) ($outBeforeGroup[$med->id] ?? 0);
            $stokAwal  = max(0, $inBefore - $outBefore);

            if ($stokAwal === 0 && $inBefore === 0 && $outBefore === 0) {
                $stokAwal = (int) ($med->stock ?? 0);
            }

            $masuk  = (int) ($inRangeGroup[$med->id] ?? 0);
            $keluar = (int) ($outRangeGroup[$med->id] ?? 0);
            $jumlah = $stokAwal + $masuk - $keluar;

            $fisik = (int) ($batchesStockGroup[$med->id] ?? 0);
            if ($fisik === 0 && $jumlah > 0) {
                $fisik = $jumlah;
            }

            $selisih = $jumlah - $fisik;

            $edDate = $nearestBatches[$med->id] ?? null;
            $keterangan = '';
            if ($edDate) {
                $ed = safeDateFormat($edDate, 'm/Y');
                if ($ed !== '-') {
                    $keterangan = 'ED ' . $ed;
                }
            }

            $rows[] = [
                $no++,
                $med->name,
                $stokAwal,
                $masuk > 0 ? $masuk : 0,
                $keluar > 0 ? $keluar : 0,
                $jumlah,
                $fisik,
                $selisih,
                $keterangan,
            ];
        }

        $this->lastRow = count($rows);
        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');
        $sheet->getStyle('A1:I3')->getFont()->setBold(true);

        $sheet->mergeCells('A5:I5');
        $sheet->getStyle('A5:I5')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => $this->config['textColor']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $this->config['color']],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(24);

        $sheet->getStyle('A6:I6')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(20);

        if ($this->lastRow >= 7) {
            $sheet->getStyle("A7:I{$this->lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle("A7:A{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B7:B{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C7:H{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I7:I{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 38,
            'C' => 10,
            'D' => 10,
            'E' => 10,
            'F' => 11,
            'G' => 11,
            'H' => 10,
            'I' => 20,
        ];
    }
}
