<?php

namespace App\Exports\Stocks;

use App\Models\Medicines;
use App\Models\Pharmacies;
use App\Services\StockDataReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockDataSheetExport extends DefaultValueBinder implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
{
    protected $request;
    protected $pharmacyId;
    protected bool $canSeeWarehouse;
    protected int $dataRowCount = 0;
    protected int $dataStartRow = 6;
    protected ?Pharmacies $pharmacy = null;
    protected string $periodLabel = '';
    protected string $unitLabel = '';
    protected ?Collection $preloadedMedicines;
    protected ?array $preloadedMetricsMap;

    public function __construct($request = null, $pharmacyId = null, ?Collection $preloadedMedicines = null, ?array $preloadedMetricsMap = null)
    {
        $this->request = $request;
        $this->pharmacyId = $pharmacyId ?? getActivePharmacyId();
        $this->canSeeWarehouse = (bool) canAccessWarehouseStock($this->pharmacyId);
        $this->pharmacy = Pharmacies::find($this->pharmacyId);
        $this->preloadedMedicines = $preloadedMedicines;
        $this->preloadedMetricsMap = $preloadedMetricsMap;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Preserve leading zeros for medicine codes in Column B
        if ($cell->getColumn() === 'B' && $cell->getRow() >= 6) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        if (get_class($this) === PrintStockOpnameExport::class || $this instanceof PrintStockOpnameExport) {
            return 'Hasil Stock Opname';
        }

        return $this->canSeeWarehouse ? 'Data Stok Gudang' : 'Data Stok Cabang';
    }

    public function headings(): array
    {
        if ($this->canSeeWarehouse) {
            return [
                'NO',
                'KODE OBAT',
                'NAMA OBAT',
                'NAMA PABRIK',
                'SATUAN',
                'ISI',
                'HNA',
                'QTY AWAL',
                'QTY BELI PBF',
                'QTY JUAL (PMI)',
                'MUTASI KE PMI',
                'MUTASI CABANG',
                'PENYESUAIAN',
                'STOK GUDANG',
                'STOK PELAYANAN',
                'TOTAL STOK',
                'KETERANGAN',
            ];
        }

        return [
            'NO',
            'KODE OBAT',
            'NAMA OBAT',
            'NAMA PABRIK',
            'SATUAN',
            'ISI',
            'HNA',
            'QTY AWAL',
            'QTY MASUK',
            'QTY JUAL',
            'MUTASI KELUAR',
            'PENYESUAIAN',
            'STOK ETALASE',
            'TOTAL STOK',
            'KETERANGAN',
        ];
    }

    public function columnWidths(): array
    {
        if ($this->canSeeWarehouse) {
            return [
                'A' => 7,
                'B' => 16,
                'C' => 42,
                'D' => 26,
                'E' => 12,
                'F' => 10,
                'G' => 16,
                'H' => 16,
                'I' => 16,
                'J' => 16,
                'K' => 16,
                'L' => 16,
                'M' => 16,
                'N' => 17,
                'O' => 18,
                'P' => 16,
                'Q' => 16,
            ];
        }

        return [
            'A' => 7,
            'B' => 16,
            'C' => 42,
            'D' => 26,
            'E' => 12,
            'F' => 10,
            'G' => 16,
            'H' => 16,
            'I' => 16,
            'J' => 16,
            'K' => 16,
            'L' => 16,
            'M' => 16,
            'N' => 16,
            'O' => 16,
        ];
    }

    public static function fetchMedicinesAndMetrics($req, int $pharmacyId): array
    {
        $startDate = ($req && $req->filled('start_date')) ? Carbon::parse($req->start_date)->toDateString() : null;
        $endDate = ($req && $req->filled('end_date')) ? Carbon::parse($req->end_date)->toDateString() : null;

        $medicines = Medicines::query()
            ->leftJoin('factories', 'factories.id', '=', 'medicines.factory_id')
            ->when($req && $req->filled('status'), function ($q) use ($req) {
                if ($req->status !== 'all') {
                    $q->where('medicines.status', $req->status);
                }
            }, function ($q) {
                $q->where('medicines.status', 1);
            })
            ->when($req && $req->filled('searchMedicine'), function ($q) use ($req) {
                $q->where(function ($sub) use ($req) {
                    $sub
                        ->where('medicines.name', 'like', "%{$req->searchMedicine}%")
                        ->orWhere('medicines.code', 'like', "%{$req->searchMedicine}%");
                });
            })
            ->when($req && $req->filled('medicine_id'), fn($q) => $q->where('medicines.id', $req->medicine_id))
            ->select([
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.unit',
                'medicines.content',
                'medicines.raw_price',
                'medicines.status',
                DB::raw("COALESCE(factories.name, '-') as factory_name"),
            ])
            ->orderBy('medicines.name', 'asc')
            ->orderBy('medicines.code', 'asc')
            ->get();

        $metricsMap = [];
        if ($medicines->isNotEmpty()) {
            $medicineIds = $medicines->pluck('id')->all();
            $isAllMedicines = !($req && ($req->filled('searchMedicine') || $req->filled('medicine_id') || ($req->filled('status') && $req->status !== '1')));

            $metricsMap = app(StockDataReportService::class)->computeMetrics(
                $medicineIds,
                $pharmacyId,
                $startDate,
                $endDate,
                $isAllMedicines
            );
        }

        return [$medicines, $metricsMap];
    }

    public function array(): array
    {
        $req = $this->request;
        $pharmacyId = $this->pharmacyId;
        $canSeeWarehouse = $this->canSeeWarehouse;

        $branchName = $this->pharmacy ? $this->pharmacy->name : 'Apotek Sahabat';
        $this->unitLabel = $canSeeWarehouse ? "Gudang Pusat / PMI ({$branchName})" : $branchName;

        $startDate = ($req && $req->filled('start_date')) ? Carbon::parse($req->start_date)->toDateString() : null;
        $endDate = ($req && $req->filled('end_date')) ? Carbon::parse($req->end_date)->toDateString() : null;

        if ($startDate && $endDate) {
            $this->periodLabel = Carbon::parse($startDate)->format('d/m/Y') . ' s/d ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($startDate) {
            $this->periodLabel = 'Mulai ' . Carbon::parse($startDate)->format('d/m/Y');
        } elseif ($endDate) {
            $this->periodLabel = 'Sampai ' . Carbon::parse($endDate)->format('d/m/Y');
        } else {
            $this->periodLabel = 'Semua Waktu (Stok Real-time Saat Ini)';
        }

        $isOpname = (get_class($this) === PrintStockOpnameExport::class || $this instanceof PrintStockOpnameExport);
        $docTitle = $isOpname ? 'LAPORAN HASIL STOCK OPNAME OBAT & GUDANG' : 'DATA MASTER STOK OBAT & GUDANG';

        $rows = [
            ["APOTEK PROPHARMA — {$branchName}"],
            [$docTitle],
            ["Unit: {$this->unitLabel}  |  Periode: {$this->periodLabel}  |  Dicetak: " . now()->format('d/m/Y H:i') . ' WIB'],
            [''],
            $this->headings(),
        ];

        if ($this->preloadedMedicines !== null && $this->preloadedMetricsMap !== null) {
            $medicines = $this->preloadedMedicines;
            $metricsMap = $this->preloadedMetricsMap;
        } else {
            [$medicines, $metricsMap] = self::fetchMedicinesAndMetrics($req, (int) $pharmacyId);
        }

        $this->dataRowCount = $medicines->count();
        if ($this->dataRowCount === 0) {
            $rows[] = ['Tidak ada data stok obat yang ditemukan.'];
            return $rows;
        }

        $sumQtyStart = 0;
        $sumQtyOrders = 0;
        $sumQtySales = 0;
        $sumQtyTransferredPmi = 0;
        $sumQtyTransferredBranch = 0;
        $sumQtyAdjustments = 0;
        $sumQtyStorage = 0;
        $sumQtyCounter = 0;
        $sumTotalStok = 0;

        foreach ($medicines as $index => $m) {
            $mId = (int) $m->id;
            $metrics = $metricsMap[$mId] ?? [
                'qty_start' => 0,
                'qty_orders' => 0,
                'qty_sales' => 0,
                'qty_transferred_pmi' => 0,
                'qty_transferred_branch' => 0,
                'qty_adjustments' => 0,
                'qty_storage' => 0,
                'qty_counter' => 0,
                'qty_now' => 0,
                'keterangan' => '-',
            ];

            $qtyStart = (int) $metrics['qty_start'];
            $qtyOrders = (int) $metrics['qty_orders'];
            $qtySales = (int) $metrics['qty_sales'];
            $qtyTransferredPmi = (int) $metrics['qty_transferred_pmi'];
            $qtyTransferredBranch = (int) $metrics['qty_transferred_branch'];
            $qtyAdjustments = (int) $metrics['qty_adjustments'];
            $qtyStorage = (int) $metrics['qty_storage'];
            $qtyCounter = (int) $metrics['qty_counter'];
            $totalStok = (int) $metrics['qty_now'];
            $keterangan = $metrics['keterangan'];

            $sumQtyStart += $qtyStart;
            $sumQtyOrders += $qtyOrders;
            $sumQtySales += $qtySales;
            $sumQtyTransferredPmi += $qtyTransferredPmi;
            $sumQtyTransferredBranch += $qtyTransferredBranch;
            $sumQtyAdjustments += $qtyAdjustments;
            $sumQtyStorage += $qtyStorage;
            $sumQtyCounter += $qtyCounter;
            $sumTotalStok += $totalStok;

            $factoryName = $m->factory_name ?: '-';
            $contentVal = (float) ($m->content ?: 1);
            $rawPriceVal = (float) ($m->raw_price ?? 0);

            if ($canSeeWarehouse) {
                $rows[] = [
                    $index + 1,
                    (string) ($m->code ?? '-'),
                    $m->name,
                    $factoryName,
                    $m->unit ? strtoupper($m->unit) : '-',
                    $contentVal,
                    $rawPriceVal,
                    $qtyStart,
                    $qtyOrders,
                    $qtySales,
                    $qtyTransferredPmi,
                    $qtyTransferredBranch,
                    $qtyAdjustments,
                    $qtyStorage,
                    $qtyCounter,
                    $totalStok,
                    $keterangan,
                ];
            } else {
                $rows[] = [
                    $index + 1,
                    (string) ($m->code ?? '-'),
                    $m->name,
                    $factoryName,
                    $m->unit ? strtoupper($m->unit) : '-',
                    $contentVal,
                    $rawPriceVal,
                    $qtyStart,
                    $qtyOrders,
                    $qtySales,
                    $qtyTransferredBranch,
                    $qtyAdjustments,
                    $qtyCounter,
                    $totalStok,
                    $keterangan,
                ];
            }
        }

        if ($canSeeWarehouse) {
            $rows[] = [
                'TOTAL KESELURUHAN',
                '',
                '',
                '',
                '',
                '',
                '',
                $sumQtyStart,
                $sumQtyOrders,
                $sumQtySales,
                $sumQtyTransferredPmi,
                $sumQtyTransferredBranch,
                $sumQtyAdjustments,
                $sumQtyStorage,
                $sumQtyCounter,
                $sumTotalStok,
                '',
            ];
        } else {
            $rows[] = [
                'TOTAL KESELURUHAN',
                '',
                '',
                '',
                '',
                '',
                '',
                $sumQtyStart,
                $sumQtyOrders,
                $sumQtySales,
                $sumQtyTransferredBranch,
                $sumQtyAdjustments,
                $sumQtyCounter,
                $sumTotalStok,
                '',
            ];
        }

        return $rows;
    }

    public function collection()
    {
        return collect($this->array());
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $this->canSeeWarehouse ? 'Q' : 'O';
        $startRow = $this->dataStartRow;
        $count = $this->dataRowCount;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => '64748B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        $sheet->getRowDimension(4)->setRowHeight(8);
        $sheet->getRowDimension(5)->setRowHeight(30);

        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']],
            ],
        ]);

        $sheet->getStyle('A5:G5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');

        $flowEndCol = $this->canSeeWarehouse ? 'M' : 'L';
        $sheet->getStyle("H5:{$flowEndCol}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');

        if ($this->canSeeWarehouse) {
            $sheet->getStyle('N5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B45309');
            $sheet->getStyle('N5')->getFont()->setSize(10.5);

            $sheet->getStyle('O5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');

            $sheet->getStyle('P5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('047857');
            $sheet->getStyle('P5')->getFont()->setSize(10.5);

            $sheet->getStyle('Q5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('475569');
        } else {
            $sheet->getStyle('M5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');

            $sheet->getStyle('N5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('047857');
            $sheet->getStyle('N5')->getFont()->setSize(10.5);

            $sheet->getStyle('O5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('475569');
        }

        if ($count > 0) {
            $endRow = $startRow + $count - 1;
            $stockCol = $this->canSeeWarehouse ? 'P' : 'N';

            $sheet->getDefaultRowDimension()->setRowHeight(22);
            $sheet->getRowDimension(5)->setRowHeight(30);

            $sheet->getStyle("A{$startRow}:{$lastCol}{$endRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getFont()->setSize(9.5)->getColor()->setRGB('64748B');

            $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:B{$endRow}")->getFont()->setSize(9.5)->setBold(true)->getColor()->setRGB('334155');

            $sheet->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$startRow}:C{$endRow}")->getFont()->setSize(9.5)->setBold(true)->getColor()->setRGB('0F172A');

            $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$startRow}:D{$endRow}")->getFont()->setSize(9)->getColor()->setRGB('334155');

            $sheet->getStyle("E{$startRow}:E{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$startRow}:E{$endRow}")->getFont()->setSize(9)->getColor()->setRGB('475569');

            $sheet->getStyle("F{$startRow}:F{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$startRow}:F{$endRow}")->getFont()->setSize(9)->getColor()->setRGB('475569');
            $sheet->getStyle("F{$startRow}:F{$endRow}")->getNumberFormat()->setFormatCode('#,##0');

            $sheet->getStyle("G{$startRow}:{$stockCol}{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);
            $sheet->getStyle("G{$startRow}:G{$endRow}")->getFont()->setSize(9.5)->getColor()->setRGB('334155');

            $sheet->getStyle("I{$startRow}:I{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('059669');
            $sheet->getStyle("J{$startRow}:J{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('2563EB');
            $sheet->getStyle("K{$startRow}:K{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('475569');
            $sheet->getStyle("L{$startRow}:L{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('4F46E5');

            if ($this->canSeeWarehouse) {
                $sheet->getStyle("M{$startRow}:M{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('D97706');

                $sheet->getStyle("N{$startRow}:N{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
                ]);

                $sheet->getStyle("O{$startRow}:O{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '0369A1']],
                ]);

                $sheet->getStyle("P{$startRow}:P{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '15803D']],
                ]);
            } else {
                $sheet->getStyle("M{$startRow}:M{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '0369A1']],
                ]);

                $sheet->getStyle("N{$startRow}:N{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '15803D']],
                ]);
            }

            $alertRule = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $alertRule->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
            $alertRule->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHANOREQUAL);
            $alertRule->addCondition('0');
            $alertRule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB('FEE2E2');
            $alertRule->getStyle()->getFont()->getColor()->setRGB('DC2626');
            $alertRule->getStyle()->getFont()->setBold(true);
            $sheet->getStyle("{$stockCol}{$startRow}:{$stockCol}{$endRow}")->setConditionalStyles([$alertRule]);

            $sheet->getStyle("{$lastCol}{$startRow}:{$lastCol}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$lastCol}{$startRow}:{$lastCol}{$endRow}")->getFont()->setSize(9);

            $totalRow = $endRow + 1;
            $sheet->getRowDimension($totalRow)->setRowHeight(26);

            $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
            $sheet->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
            ]);

            $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EFF6FF'],
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E3A8A']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                ],
            ]);

            $sheet->getStyle("H{$totalRow}:{$stockCol}{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['bold' => true],
            ]);

            if ($this->canSeeWarehouse) {
                $sheet->getStyle("N{$totalRow}")->getFont()->getColor()->setRGB('B45309');
                $sheet->getStyle("O{$totalRow}")->getFont()->getColor()->setRGB('0369A1');
                $sheet->getStyle("P{$totalRow}")->getFont()->getColor()->setRGB('15803D');
            } else {
                $sheet->getStyle("M{$totalRow}")->getFont()->getColor()->setRGB('0369A1');
                $sheet->getStyle("N{$totalRow}")->getFont()->getColor()->setRGB('15803D');
            }

            $sheet->freezePane('A6');
            $sheet->setAutoFilter("A5:{$lastCol}{$endRow}");
            $sheet->setShowGridlines(true);
        } else {
            $emptyRow = $startRow;
            $sheet->mergeCells("A{$emptyRow}:{$lastCol}{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:{$lastCol}{$emptyRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
            ]);
            $sheet->getRowDimension($emptyRow)->setRowHeight(32);
        }

        return [];
    }
}
