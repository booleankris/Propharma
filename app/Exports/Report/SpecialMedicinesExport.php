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
            'filter_by'  => 'type',
            'type_query' => ['NARKOTIKA'],
            'color'      => 'FFA07A', // Salmon Orange
            'textColor'  => '991B1B',
        ],
        'PSIKOTROPIKA' => [
            'title'      => 'PSIKOTROPIKA',
            'filter_by'  => 'type',
            'type_query' => ['PSIKOTROPIKA'],
            'color'      => '93C5FD', // Light Blue
            'textColor'  => '1E3A8A',
        ],
        'OBAT TERTENTU' => [
            'title'          => 'OBAT OBAT TERTENTU',
            'filter_by'      => 'category',
            'category_names' => ['OBAT-OBAT TERTENTU (OOT)', 'OBAT TERTENTU', 'OBAT-OBAT TERTENTU', 'OOT'],
            'color'          => 'A7F3D0', // Mint Green
            'textColor'      => '065F46',
        ],
        'PREKURSOR' => [
            'title'          => 'PREKURSOR',
            'filter_by'      => 'category',
            'category_names' => ['OBAT PREKURSOR', 'PREKURSOR'],
            'color'          => 'FDE047', // Light Yellow
            'textColor'      => '854D0E',
        ],
    ];

    /**
     * Get query for medicines based on category configuration
     */
    public static function getMedicinesQuery(array $config)
    {
        $query = Medicines::where('status', 1);

        if (($config['filter_by'] ?? 'type') === 'category') {
            $catNames = $config['category_names'] ?? [];
            $categoryIds = \App\Models\MedicineCategory::where(function ($q) use ($catNames) {
                foreach ($catNames as $name) {
                    $q->orWhere('name', 'LIKE', "%{$name}%");
                }
            })->pluck('id')->toArray();

            $query->whereIn('medicine_category_id', $categoryIds);
        } else {
            $query->where(function ($q) use ($config) {
                foreach ($config['type_query'] ?? [] as $t) {
                    $q->orWhere('type', 'LIKE', "%{$t}%");
                }
            });
        }

        return $query->orderBy('name', 'asc');
    }

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

        // Top Document Header
        $rows[] = [$this->pharmacyName];
        $rows[] = ['LAPORAN MUTASI OBAT GOLONGAN KHUSUS (SIPNAP)'];
        $rows[] = ['Periode: ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')];
        $rows[] = [''];
        // Pre-aggregate queries in bulk for high performance and low memory - FILTERED BY PHARMACY
        $inBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->where('items_log.created_at', '<', $this->startDate)
            ->whereIn('items_log.status', [2, 3, 5, 7])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->where('items_log.created_at', '<', $this->startDate)
            ->whereIn('items_log.status', [1, 4, 6])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $inRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->whereBetween('items_log.created_at', [$this->startDate, $this->endDate])
            ->whereIn('items_log.status', [2, 3, 5, 7])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->whereBetween('items_log.created_at', [$this->startDate, $this->endDate])
            ->whereIn('items_log.status', [1, 4, 6])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        // Stock queries: Gudang (9) uses batches, Cabang uses etalase/pelayanan (medicine_transfer_items)
        $batchesStockGroup = collect();
        $counterStockGroup = collect();
        if ((int)$this->pharmacyId === 9) {
            $batchesStockGroup = \Illuminate\Support\Facades\DB::table('batches')
                ->where('pharmacy_id', 9)
                ->where('stock', '>', 0)
                ->groupBy('medicine_id')
                ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(stock) as total_stock'))
                ->pluck('total_stock', 'medicine_id');

            $nearestBatches = \Illuminate\Support\Facades\DB::table('batches')
                ->where('pharmacy_id', 9)
                ->whereNotNull('expired_date')
                ->where('stock', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->get()
                ->groupBy('medicine_id')
                ->map(fn($items) => $items->first()->expired_date ?? null);
        } else {
            $counterStockGroup = \Illuminate\Support\Facades\DB::table('medicine_transfer_items')
                ->join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                ->where('batches.pharmacy_id', $this->pharmacyId)
                ->where('medicine_transfer_items.status', 1)
                ->where('medicine_transfer_items.qty', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('medicine_transfer_items.source_type')
                      ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                })
                ->groupBy('batches.medicine_id')
                ->select('batches.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(medicine_transfer_items.qty) as total_qty'))
                ->pluck('total_qty', 'batches.medicine_id');

            $nearestBatches = \Illuminate\Support\Facades\DB::table('batches')
                ->where('pharmacy_id', $this->pharmacyId)
                ->whereNotNull('expired_date')
                ->whereExists(function($sub) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('medicine_transfer_items')
                        ->whereColumn('medicine_transfer_items.batches_id', 'batches.id')
                        ->where('medicine_transfer_items.status', 1)
                        ->where('medicine_transfer_items.qty', '>', 0);
                })
                ->orderBy('expired_date', 'asc')
                ->get()
                ->groupBy('medicine_id')
                ->map(fn($items) => $items->first()->expired_date ?? null);
        }

        foreach (SpecialMedicinesExport::CATEGORIES as $key => $config) {
            // Section Banner (e.g. NARKOTIKA)
            $rows[] = [$config['title'], '', '', '', '', '', '', '', ''];
            $bannerRow = count($rows);

            // Table Column Headers
            $rows[] = ['NO', 'NAMA OBAT', 'AWAL', 'MASUK', 'KELUAR', 'JUMLAH', 'FISIK', 'SELISIH', 'KETERANGAN'];
            $colHeaderRow = count($rows);

            $medicines = SpecialMedicinesExport::getMedicinesQuery($config)->get();

            $no = 1;
            $startDataRow = count($rows) + 1;

            foreach ($medicines as $med) {
                $inBefore  = (int) ($inBeforeGroup[$med->id] ?? 0);
                $outBefore = (int) ($outBeforeGroup[$med->id] ?? 0);
                $stokAwal  = max(0, $inBefore - $outBefore);

                $fisik = (int)$this->pharmacyId === 9
                    ? (int) ($batchesStockGroup[$med->id] ?? 0)
                    : (int) ($counterStockGroup[$med->id] ?? 0);

                if ($stokAwal === 0 && $inBefore === 0 && $outBefore === 0) {
                    $masukCheck = (int) ($inRangeGroup[$med->id] ?? 0);
                    $keluarCheck = (int) ($outRangeGroup[$med->id] ?? 0);
                    if ($masukCheck === 0 && $keluarCheck === 0 && $fisik > 0) {
                        $stokAwal = $fisik;
                    }
                }

                $masuk  = (int) ($inRangeGroup[$med->id] ?? 0);
                $keluar = (int) ($outRangeGroup[$med->id] ?? 0);
                $jumlah = $stokAwal + $masuk - $keluar;

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

            $endDataRow = count($rows);

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
        }

        $this->totalDataRows = count($rows);
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

        // Pre-aggregate queries in bulk for single sheet - FILTERED BY PHARMACY
        $inBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->where('items_log.created_at', '<', $this->startDate)
            ->whereIn('items_log.status', [2, 3, 5, 7])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outBeforeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->where('items_log.created_at', '<', $this->startDate)
            ->whereIn('items_log.status', [1, 4, 6])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $inRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->whereBetween('items_log.created_at', [$this->startDate, $this->endDate])
            ->whereIn('items_log.status', [2, 3, 5, 7])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        $outRangeGroup = \Illuminate\Support\Facades\DB::table('items_log')
            ->join('batches', 'items_log.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $this->pharmacyId)
            ->whereBetween('items_log.created_at', [$this->startDate, $this->endDate])
            ->whereIn('items_log.status', [1, 4, 6])
            ->groupBy('items_log.medicine_id')
            ->select('items_log.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(items_log.qty) as total_qty'))
            ->pluck('total_qty', 'medicine_id');

        // Stock queries: Gudang (9) uses batches, Cabang uses etalase/pelayanan (medicine_transfer_items)
        $batchesStockGroup = collect();
        $counterStockGroup = collect();
        if ((int)$this->pharmacyId === 9) {
            $batchesStockGroup = \Illuminate\Support\Facades\DB::table('batches')
                ->where('pharmacy_id', 9)
                ->where('stock', '>', 0)
                ->groupBy('medicine_id')
                ->select('medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(stock) as total_stock'))
                ->pluck('total_stock', 'medicine_id');

            $nearestBatches = \Illuminate\Support\Facades\DB::table('batches')
                ->where('pharmacy_id', 9)
                ->whereNotNull('expired_date')
                ->where('stock', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->get()
                ->groupBy('medicine_id')
                ->map(fn($items) => $items->first()->expired_date ?? null);
        } else {
            $counterStockGroup = \Illuminate\Support\Facades\DB::table('medicine_transfer_items')
                ->join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                ->where('batches.pharmacy_id', $this->pharmacyId)
                ->where('medicine_transfer_items.status', 1)
                ->where('medicine_transfer_items.qty', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('medicine_transfer_items.source_type')
                      ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                })
                ->groupBy('batches.medicine_id')
                ->select('batches.medicine_id', \Illuminate\Support\Facades\DB::raw('SUM(medicine_transfer_items.qty) as total_qty'))
                ->pluck('total_qty', 'batches.medicine_id');

            $nearestBatches = \Illuminate\Support\Facades\DB::table('batches')
                ->where('pharmacy_id', $this->pharmacyId)
                ->whereNotNull('expired_date')
                ->whereExists(function($sub) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('medicine_transfer_items')
                        ->whereColumn('medicine_transfer_items.batches_id', 'batches.id')
                        ->where('medicine_transfer_items.status', 1)
                        ->where('medicine_transfer_items.qty', '>', 0);
                })
                ->orderBy('expired_date', 'asc')
                ->get()
                ->groupBy('medicine_id')
                ->map(fn($items) => $items->first()->expired_date ?? null);
        }

        $medicines = SpecialMedicinesExport::getMedicinesQuery($this->config)->get();

        $no = 1;
        foreach ($medicines as $med) {
            $inBefore  = (int) ($inBeforeGroup[$med->id] ?? 0);
            $outBefore = (int) ($outBeforeGroup[$med->id] ?? 0);
            $stokAwal  = max(0, $inBefore - $outBefore);

            $fisik = (int)$this->pharmacyId === 9
                ? (int) ($batchesStockGroup[$med->id] ?? 0)
                : (int) ($counterStockGroup[$med->id] ?? 0);

            if ($stokAwal === 0 && $inBefore === 0 && $outBefore === 0) {
                $masukCheck = (int) ($inRangeGroup[$med->id] ?? 0);
                $keluarCheck = (int) ($outRangeGroup[$med->id] ?? 0);
                if ($masukCheck === 0 && $keluarCheck === 0 && $fisik > 0) {
                    $stokAwal = $fisik;
                }
            }

            $masuk  = (int) ($inRangeGroup[$med->id] ?? 0);
            $keluar = (int) ($outRangeGroup[$med->id] ?? 0);
            $jumlah = $stokAwal + $masuk - $keluar;

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
