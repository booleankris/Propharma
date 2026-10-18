<?php

namespace App\Exports\Stocks;

use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\Pharmacies;
use Carbon\Carbon;
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

class StockDataExport extends DefaultValueBinder implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
{
    protected $request;
    protected $pharmacyId;
    protected bool $canSeeWarehouse;
    protected int $dataRowCount = 0;
    protected int $dataStartRow = 6;
    protected ?Pharmacies $pharmacy = null;
    protected string $periodLabel = '';
    protected string $unitLabel = '';

    public function __construct($request = null, $pharmacyId = null)
    {
        $this->request = $request;
        $this->pharmacyId = $pharmacyId ?? getActivePharmacyId();
        $this->canSeeWarehouse = (bool) canAccessWarehouseStock($this->pharmacyId);
        $this->pharmacy = Pharmacies::find($this->pharmacyId);
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
                'SATUAN',
                'QTY AWAL',
                'QTY BELI (GUDANG)',
                'QTY JUAL (PMI)',
                'SISA STOK',
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
            'SATUAN',
            'QTY AWAL',
            'QTY BELI',
            'QTY JUAL',
            'SISA STOK',
            'STOK ETALASE',
            'TOTAL STOK',
            'KETERANGAN',
        ];
    }

    public function columnWidths(): array
    {
        if ($this->canSeeWarehouse) {
            return [
                'A' => 7,   // No
                'B' => 16,  // Kode Obat
                'C' => 44,  // Nama Obat
                'D' => 12,  // Satuan
                'E' => 14,  // QTY Awal
                'F' => 20,  // QTY Beli
                'G' => 20,  // QTY Jual
                'H' => 14,  // Sisa Stok
                'I' => 17,  // Stok Gudang
                'J' => 18,  // Stok Pelayanan PMI
                'K' => 16,  // Total Stok
                'L' => 16,  // Keterangan
            ];
        }

        return [
            'A' => 7,   // No
            'B' => 16,  // Kode Obat
            'C' => 44,  // Nama Obat
            'D' => 12,  // Satuan
            'E' => 14,  // QTY Awal
            'F' => 16,  // QTY Beli
            'G' => 16,  // QTY Jual
            'H' => 14,  // Sisa Stok
            'I' => 16,  // Stok Etalase
            'J' => 16,  // Total Stok
            'K' => 16,  // Keterangan
        ];
    }

    public function array(): array
    {
        $warehouseId = getWarehousePharmacyId();
        $pmiPharmacyId = 1;
        $req = $this->request;
        $pharmacyId = $this->pharmacyId;
        $canSeeWarehouse = $this->canSeeWarehouse;

        $branchName = $this->pharmacy ? $this->pharmacy->name : 'Apotek Propharma';
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
            [''], // Row 4 Blank
            $this->headings(), // Row 5 Table Headers
        ];

        $ordersPharmacyId = $canSeeWarehouse ? $warehouseId : $pharmacyId;
        $salesPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;
        $startPharmacyIds = $canSeeWarehouse ? [$warehouseId, $pmiPharmacyId] : [$pharmacyId];
        $counterPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;

        // 1. Medicines List
        $medicines = Medicines::query()
            ->when($req && $req->filled('status'), function ($q) use ($req) {
                if ($req->status !== 'all') {
                    $q->where('medicines.status', $req->status);
                }
            }, function ($q) {
                $q->where('medicines.status', 1);
            })
            ->when($req && $req->filled('searchMedicine'), function ($q) use ($req) {
                $q->where(function ($sub) use ($req) {
                    $sub->where('medicines.name', 'like', "%{$req->searchMedicine}%")
                        ->orWhere('medicines.code', 'like', "%{$req->searchMedicine}%");
                });
            })
            ->when($req && $req->filled('medicine_id'), fn($q) => $q->where('medicines.id', $req->medicine_id))
            ->select([
                'medicines.id',
                'medicines.code',
                'medicines.name',
                'medicines.unit',
            ])
            ->orderBy('medicines.name', 'asc')
            ->orderBy('medicines.code', 'asc')
            ->get();

        $this->dataRowCount = $medicines->count();
        if ($this->dataRowCount === 0) {
            $rows[] = ['Tidak ada data stok obat yang ditemukan.'];
            return $rows;
        }

        // 2. Storage stock map
        $storageStockMap = [];
        if ($canSeeWarehouse) {
            $storageStockMap = Batches::where('pharmacy_id', $warehouseId)
                ->selectRaw('medicine_id, SUM(stock) as total')
                ->groupBy('medicine_id')
                ->pluck('total', 'medicine_id')
                ->all();
        }

        // 3. Counter stock map
        $counterStockMap = MedicineTransferItems::join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
            ->where('batches.pharmacy_id', $counterPharmacyId)
            ->where('medicine_transfer_items.status', 1)
            ->where(function ($q) {
                $q->whereNull('medicine_transfer_items.source_type')
                  ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
            })
            ->selectRaw('batches.medicine_id, SUM(medicine_transfer_items.qty) as total')
            ->groupBy('batches.medicine_id')
            ->pluck('total', 'batches.medicine_id')
            ->all();

        // 4. Latest Opening Balances (SO-SALDO-AWAL)
        $latestOpeningLogs = DB::table('items_log as il')
            ->join('batches as b', 'b.id', '=', 'il.batches_id')
            ->joinSub(
                DB::table('items_log as il2')
                    ->join('batches as b2', 'b2.id', '=', 'il2.batches_id')
                    ->where('il2.type', 'SO-SALDO-AWAL')
                    ->whereIn('b2.pharmacy_id', $startPharmacyIds)
                    ->groupBy('il2.medicine_id')
                    ->select('il2.medicine_id', DB::raw('MAX(il2.id) as max_id')),
                'latest',
                'latest.max_id', '=', 'il.id'
            )
            ->select('il.medicine_id', 'il.qty_before', DB::raw('DATE(il.date) as op_date'))
            ->get()
            ->keyBy('medicine_id');

        // 5. Movements from items_log
        $hasDateFilter = ($startDate || $endDate);
        $allPharmacyIds = array_unique(array_merge([$ordersPharmacyId, $salesPharmacyId], $startPharmacyIds));

        $logsQuery = DB::table('items_log')
            ->join('batches', 'batches.id', '=', 'items_log.batches_id')
            ->whereIn('batches.pharmacy_id', $allPharmacyIds)
            ->whereIn('items_log.status', [1, 2, 3, 4, 5, 6, 7]);

        if ($startDate) {
            $logsQuery->whereDate('items_log.date', '>=', $startDate);
        }
        if ($endDate) {
            $logsQuery->whereDate('items_log.date', '<=', $endDate);
        }

        $logs = $logsQuery->select([
            'items_log.medicine_id',
            'batches.pharmacy_id',
            'items_log.status',
            'items_log.date',
            'items_log.qty',
            'items_log.qty_before',
            'items_log.qty_after',
            'items_log.total',
        ])->get();

        $aggOrders = [];
        $aggSales = [];
        $aggOrdersRt = [];
        $aggSalesRt = [];
        $aggAdjustments = [];

        foreach ($logs as $log) {
            $mId = $log->medicine_id;
            $pId = $log->pharmacy_id;
            $st = (int) $log->status;
            $logDate = substr($log->date, 0, 10);

            if (!$hasDateFilter) {
                $opDate = isset($latestOpeningLogs[$mId]) ? $latestOpeningLogs[$mId]->op_date : '1000-01-01';
                if ($logDate <= $opDate) {
                    continue;
                }
            }

            $qty = (int) $log->qty;

            if ($st === 2 && $pId == $ordersPharmacyId) {
                $aggOrders[$mId] = ($aggOrders[$mId] ?? 0) + $qty;
            } elseif ($st === 1 && $pId == $salesPharmacyId) {
                $aggSales[$mId] = ($aggSales[$mId] ?? 0) + $qty;
            } elseif ($st === 4 && $pId == $ordersPharmacyId) {
                $aggOrdersRt[$mId] = ($aggOrdersRt[$mId] ?? 0) + $qty;
            } elseif ($st === 3 && $pId == $salesPharmacyId) {
                $aggSalesRt[$mId] = ($aggSalesRt[$mId] ?? 0) + $qty;
            } elseif (in_array($st, [5, 6, 7]) && in_array($pId, $startPharmacyIds)) {
                if ($st === 5) {
                    if ($log->total !== null && preg_match('/^[+-]?[0-9]+([.][0-9]+)?([eE][+-]?[0-9]+)?$/', trim($log->total))) {
                        $diff = (int) $log->total;
                    } else {
                        $diff = (int) $log->qty_after - (int) $log->qty_before;
                    }
                } else {
                    $diff = (int) $log->qty_after - (int) $log->qty_before;
                }
                $aggAdjustments[$mId] = ($aggAdjustments[$mId] ?? 0) + $diff;
            }
        }

        // 6. Payment Methods
        $paymentMethods = DB::table('receiving_items as ri')
            ->join('receiving_details as rd', 'rd.id', '=', 'ri.receiving_details_id')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->leftJoin('batches as b', 'b.id', '=', 'ri.batches_id')
            ->leftJoin('order_items as oi', 'oi.id', '=', 'ri.order_items_id')
            ->where('r.pharmacy_id', $ordersPharmacyId)
            ->when($endDate, fn($q) => $q->whereDate('rd.invoice_date', '<=', $endDate))
            ->select(
                DB::raw('COALESCE(b.medicine_id, oi.medicine_id) as medicine_id'),
                'rd.invoice_payment'
            )
            ->orderBy('rd.invoice_date', 'desc')
            ->orderBy('rd.id', 'desc')
            ->get()
            ->filter(fn($row) => !empty($row->medicine_id))
            ->unique('medicine_id')
            ->pluck('invoice_payment', 'medicine_id')
            ->all();

        // 7. Assemble Rows
        $sumQtyStart = 0;
        $sumQtyOrders = 0;
        $sumQtySales = 0;
        $sumSisaStok = 0;
        $sumQtyStorage = 0;
        $sumQtyCounter = 0;
        $sumTotalStok = 0;

        foreach ($medicines as $index => $m) {
            $mId = $m->id;
            $qtyStorage = $canSeeWarehouse ? (int) ($storageStockMap[$mId] ?? 0) : 0;
            $qtyCounter = (int) ($counterStockMap[$mId] ?? 0);
            $totalStok = $qtyStorage + $qtyCounter;

            $qtyOrders = (int) ($aggOrders[$mId] ?? 0);
            $qtyOrdersRt = (int) ($aggOrdersRt[$mId] ?? 0);
            $qtySales = (int) ($aggSales[$mId] ?? 0);
            $qtySalesRt = (int) ($aggSalesRt[$mId] ?? 0);

            $netIn = $qtyOrders - $qtyOrdersRt;
            $netOut = $qtySales - $qtySalesRt;
            $adjustments = (int) ($aggAdjustments[$mId] ?? 0);

            $opLog = $latestOpeningLogs[$mId] ?? null;
            $qtyStart = (!$hasDateFilter && $opLog !== null)
                ? (int) $opLog->qty_before
                : $totalStok - $netIn + $netOut - $adjustments;
            $sisaStok = $totalStok;

            $rawPayment = $paymentMethods[$mId] ?? null;
            $p = $rawPayment ? strtoupper(trim($rawPayment)) : null;
            $keterangan = ($p === 'TUNAI' || $p === 'CASH') ? 'Cash' : (($p === 'KREDIT') ? 'Kredit' : (($p === 'KONSINYASI') ? 'Konsinyasi' : ($p ? ucfirst(strtolower($p)) : '-')));

            $sumQtyStart += $qtyStart;
            $sumQtyOrders += $qtyOrders;
            $sumQtySales += $qtySales;
            $sumSisaStok += $sisaStok;
            $sumQtyStorage += $qtyStorage;
            $sumQtyCounter += $qtyCounter;
            $sumTotalStok += $totalStok;

            if ($canSeeWarehouse) {
                $rows[] = [
                    $index + 1,
                    (string) ($m->code ?? '-'),
                    $m->name,
                    $m->unit ? strtoupper($m->unit) : '-',
                    $qtyStart,
                    $qtyOrders,
                    $qtySales,
                    $sisaStok,
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
                    $m->unit ? strtoupper($m->unit) : '-',
                    $qtyStart,
                    $qtyOrders,
                    $qtySales,
                    $sisaStok,
                    $qtyCounter,
                    $totalStok,
                    $keterangan,
                ];
            }
        }

        // Summary row
        if ($canSeeWarehouse) {
            $rows[] = [
                'TOTAL KESELURUHAN',
                '',
                '',
                '',
                $sumQtyStart,
                $sumQtyOrders,
                $sumQtySales,
                $sumSisaStok,
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
                $sumQtyStart,
                $sumQtyOrders,
                $sumQtySales,
                $sumSisaStok,
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
        $lastCol = $this->canSeeWarehouse ? 'L' : 'K';
        $startRow = $this->dataStartRow; // 6
        $count = $this->dataRowCount;

        // 1. Title Block (Rows 1 to 3)
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle("A2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle("A3")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => '64748B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Row 4 Blank Separator
        $sheet->getRowDimension(4)->setRowHeight(8);

        // 2. Table Header Styling (Row 5)
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

        // Segmented Header Background Colors
        // Section A-D: Info Identitas Obat (Dark Slate 900)
        $sheet->getStyle("A5:D5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');

        // Section E-H: Arus Mutasi Stok (Royal Indigo 800)
        $sheet->getStyle("E5:H5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');

        if ($this->canSeeWarehouse) {
            // Col I: STOK GUDANG (Warm Amber/Bronze - distinctive warehouse highlight!)
            $sheet->getStyle("I5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B45309');
            $sheet->getStyle("I5")->getFont()->setSize(10.5);

            // Col J: STOK PELAYANAN (Teal 700)
            $sheet->getStyle("J5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');

            // Col K: TOTAL STOK (Emerald 700)
            $sheet->getStyle("K5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('047857');
            $sheet->getStyle("K5")->getFont()->setSize(10.5);

            // Col L: KETERANGAN (Slate 600)
            $sheet->getStyle("L5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('475569');
        } else {
            // Col I: STOK ETALASE (Teal 700)
            $sheet->getStyle("I5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');

            // Col J: TOTAL STOK (Emerald 700)
            $sheet->getStyle("J5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('047857');
            $sheet->getStyle("J5")->getFont()->setSize(10.5);

            // Col K: KETERANGAN (Slate 600)
            $sheet->getStyle("K5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('475569');
        }

        // 3. Data Rows Styling (Optimized bulk ranges for instant generation on 8,000+ rows)
        if ($count > 0) {
            $endRow = $startRow + $count - 1;
            $stockCol = $this->canSeeWarehouse ? 'K' : 'J';

            // Set default data row height
            $sheet->getDefaultRowDimension()->setRowHeight(22);
            $sheet->getRowDimension(5)->setRowHeight(30);

            // General Grid Borders & Vertical Alignment
            $sheet->getStyle("A{$startRow}:{$lastCol}{$endRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Column Alignments & Typography
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getFont()->setSize(9.5)->getColor()->setRGB('64748B');

            $sheet->getStyle("B{$startRow}:B{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:B{$endRow}")->getFont()->setSize(9.5)->setBold(true)->getColor()->setRGB('334155');

            $sheet->getStyle("C{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$startRow}:C{$endRow}")->getFont()->setSize(9.5)->setBold(true)->getColor()->setRGB('0F172A');

            $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$startRow}:D{$endRow}")->getFont()->setSize(9)->getColor()->setRGB('475569');

            // Numeric Columns E through Movement/Stock
            $sheet->getStyle("E{$startRow}:{$stockCol}{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            // Movements text color accents
            $sheet->getStyle("F{$startRow}:F{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('0284C7');
            $sheet->getStyle("G{$startRow}:G{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('4F46E5');
            $sheet->getStyle("H{$startRow}:H{$endRow}")->getFont()->setBold(true)->getColor()->setRGB('0F766E');

            // Dedicated Stock Columns Styling
            if ($this->canSeeWarehouse) {
                // Col I (Stok Gudang): Prominent warm Amber lane for warehouse visibility
                $sheet->getStyle("I{$startRow}:I{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
                ]);

                // Col J (Stok Pelayanan): Soft Sky lane
                $sheet->getStyle("J{$startRow}:J{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '0369A1']],
                ]);

                // Col K (Total Stok): Soft Emerald lane
                $sheet->getStyle("K{$startRow}:K{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '15803D']],
                ]);
            } else {
                // Col I (Stok Etalase): Soft Sky lane
                $sheet->getStyle("I{$startRow}:I{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0F2FE']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '0369A1']],
                ]);

                // Col J (Total Stok): Soft Emerald lane
                $sheet->getStyle("J{$startRow}:J{$endRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
                    'font' => ['bold' => true, 'color' => ['rgb' => '15803D']],
                ]);
            }

            // Red Alert conditional formatting on Total Stok for empty / <= 0 items
            $alertRule = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
            $alertRule->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS);
            $alertRule->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHANOREQUAL);
            $alertRule->addCondition('0');
            $alertRule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB('FEE2E2');
            $alertRule->getStyle()->getFont()->getColor()->setRGB('DC2626');
            $alertRule->getStyle()->getFont()->setBold(true);
            $sheet->getStyle("{$stockCol}{$startRow}:{$stockCol}{$endRow}")->setConditionalStyles([$alertRule]);

            // Keterangan column
            $sheet->getStyle("{$lastCol}{$startRow}:{$lastCol}{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$lastCol}{$startRow}:{$lastCol}{$endRow}")->getFont()->setSize(9);

            // 4. Summary / Total Row
            $totalRow = $endRow + 1;
            $sheet->getRowDimension($totalRow)->setRowHeight(26);

            $sheet->mergeCells("A{$totalRow}:D{$totalRow}");
            $sheet->getStyle("A{$totalRow}:D{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
            ]);

            $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EFF6FF'], // Soft blue
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E3A8A']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                ],
            ]);

            $sheet->getStyle("E{$totalRow}:{$stockCol}{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['bold' => true],
            ]);

            if ($this->canSeeWarehouse) {
                $sheet->getStyle("I{$totalRow}")->getFont()->getColor()->setRGB('B45309');
                $sheet->getStyle("J{$totalRow}")->getFont()->getColor()->setRGB('0369A1');
                $sheet->getStyle("K{$totalRow}")->getFont()->getColor()->setRGB('15803D');
            } else {
                $sheet->getStyle("I{$totalRow}")->getFont()->getColor()->setRGB('0369A1');
                $sheet->getStyle("J{$totalRow}")->getFont()->getColor()->setRGB('15803D');
            }

            // 5. Freeze Pane & AutoFilter
            $sheet->freezePane('A6');
            $sheet->setAutoFilter("A5:{$lastCol}{$endRow}");
            $sheet->setShowGridlines(true);

        } else {
            // Empty data row
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

