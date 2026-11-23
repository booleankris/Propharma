<?php

namespace App\Services;

use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\MedicineTransferItems;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StockLedgerService
{
    /**
     * Ekspresi SQL untuk mengurutkan transaksi secara kronologis berdasarkan jam aktual.
     */
    public function effectiveDateSql(string $tableAlias = ''): string
    {
        $prefix = $tableAlias !== '' ? "{$tableAlias}." : '';
        return "CASE WHEN (LENGTH({$prefix}date) <= 10 OR TIME({$prefix}date) = '00:00:00') AND {$prefix}created_at IS NOT NULL THEN CONCAT(DATE({$prefix}date), ' ', TIME({$prefix}created_at)) ELSE {$prefix}date END";
    }

    /**
     * Hitung perubahan bertanda (+/-) untuk 1 baris ItemsLog pada Kartu Stok.
     */
    public function stockLogDelta(ItemsLog $log): int
    {
        $status = (int) $log->status;

        if ($status === 1 || $status === 4) {
            return -abs((int) $log->qty);
        }

        if (in_array($status, [2, 3], true)) {
            return abs((int) $log->qty);
        }

        if ($status === 8) {
            return (int) $log->qty;
        }

        if ($status === 9) {
            return -abs((int) $log->qty);
        }

        if ($status === 5) {
            $totalVal = $log->total;
            if ($totalVal !== null && $totalVal !== '' && $totalVal !== '-' && is_numeric($totalVal)) {
                return (int) $totalVal;
            }
            return (int) $log->qty_after - (int) $log->qty_before;
        }

        return (int) $log->qty_after - (int) $log->qty_before;
    }

    /**
     * Hitung stok real-time di database (Gudang / Etalase Cabang / Total).
     */
    public function calculateRealtimeStock(int $medicineId, int $pharmacyId, string $type = 'total'): int
    {
        if (!$medicineId) {
            return 0;
        }

        $warehouseId = getWarehousePharmacyId();
        $canSeeWarehouse = canAccessWarehouseStock($pharmacyId);

        $storageStock = $canSeeWarehouse
            ? (int) Batches::where('medicine_id', $medicineId)
                ->where('pharmacy_id', $warehouseId)
                ->sum('stock')
            : 0;

        if ($type === 'storage') {
            return $storageStock;
        }

        $counterPharmacyId = isWarehousePharmacy($pharmacyId) ? 1 : $pharmacyId;

        $counterStock = (int) MedicineTransferItems::whereHas('batches', function ($b) use ($medicineId, $counterPharmacyId) {
                $b->where('medicine_id', $medicineId)
                  ->where('pharmacy_id', $counterPharmacyId);
            })
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            })
            ->sum('qty');

        if ($type === 'counter') {
            return $counterStock;
        }

        return $storageStock + $counterStock;
    }

    /**
     * Bangun running balance Kartu Stok yang mengunci SO-SALDO-AWAL sebagai patokan maju.
     *
     * @param Collection<int, ItemsLog> $orderedRows
     * @return array{runningMap: array<int, array{start: int, end: int, before: int, after: int}>, initialStart: int, finalEnd: int, hasOpeningAnchor: bool}
     */
    public function buildRunningLedger(Collection $orderedRows, int $fallbackRealtimeStock): array
    {
        $rows = $orderedRows->values();
        if ($rows->isEmpty()) {
            return [
                'runningMap'       => [],
                'initialStart'     => 0,
                'finalEnd'         => $fallbackRealtimeStock,
                'hasOpeningAnchor' => false,
            ];
        }

        $anchorIdx = null;
        foreach ($rows as $idx => $row) {
            if (($row->type ?? null) === 'SO-SALDO-AWAL') {
                $anchorIdx = $idx;
            }
        }

        $runningMap = [];

        if ($anchorIdx !== null) {
            $anchorQty = (int) $rows[$anchorIdx]->qty_before;

            $backRunning = $anchorQty;
            for ($i = $anchorIdx - 1; $i >= 0; $i--) {
                $r = $rows[$i];
                $delta = $this->stockLogDelta($r);
                $rowEnd = $backRunning;
                $rowStart = $rowEnd - $delta;
                $backRunning = $rowStart;

                $runningMap[$r->id] = [
                    'start'  => $rowStart,
                    'end'    => $rowEnd,
                    'before' => $rowStart,
                    'after'  => $rowEnd,
                ];
            }

            $initialStart = $backRunning;
            $currRunning = $anchorQty;
            $count = $rows->count();

            for ($i = $anchorIdx; $i < $count; $i++) {
                $r = $rows[$i];
                if ($i === $anchorIdx) {
                    $rowStart = $currRunning;
                    $rowEnd = $currRunning;
                } else {
                    $rowStart = $currRunning;
                    $delta = $this->stockLogDelta($r);
                    $rowEnd = $rowStart + $delta;
                    $currRunning = $rowEnd;
                }

                $runningMap[$r->id] = [
                    'start'  => $rowStart,
                    'end'    => $rowEnd,
                    'before' => $rowStart,
                    'after'  => $rowEnd,
                ];
            }

            return [
                'runningMap'       => $runningMap,
                'initialStart'     => $initialStart,
                'finalEnd'         => $currRunning,
                'hasOpeningAnchor' => true,
            ];
        }

        $totalMovement = $rows->sum(fn ($log) => $this->stockLogDelta($log));
        $currRunning = $fallbackRealtimeStock - $totalMovement;
        $initialStart = $currRunning;

        foreach ($rows as $r) {
            $rowStart = $currRunning;
            $delta = $this->stockLogDelta($r);
            $rowEnd = $rowStart + $delta;
            $currRunning = $rowEnd;

            $runningMap[$r->id] = [
                'start'  => $rowStart,
                'end'    => $rowEnd,
                'before' => $rowStart,
                'after'  => $rowEnd,
            ];
        }

        return [
            'runningMap'       => $runningMap,
            'initialStart'     => $initialStart,
            'finalEnd'         => $currRunning,
            'hasOpeningAnchor' => false,
        ];
    }

    /**
     * Query scope Kartu Stok per cabang & obat (sama persis dengan SuppliesController::getSupplies).
     */
    public function branchMedicineLogsQuery(int $pharmacyId, int $medicineId): Builder
    {
        $targetPharmacyIds = [$pharmacyId];

        return ItemsLog::query()
            ->where('medicine_id', $medicineId)
            ->where(function ($q) use ($targetPharmacyIds) {
                $q->where(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 2)
                        ->where(function ($buyQ) use ($targetPharmacyIds) {
                            $buyQ->whereHas('batches', function ($b) use ($targetPharmacyIds) {
                                $b->whereIn('pharmacy_id', $targetPharmacyIds);
                            })->orWhereHas('receiving', function ($r) use ($targetPharmacyIds) {
                                $r->whereIn('pharmacy_id', $targetPharmacyIds);
                            });
                        });
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 4)
                        ->where(function ($returQ) use ($targetPharmacyIds) {
                            $returQ->whereHas('receiving', function ($r) use ($targetPharmacyIds) {
                                $r->whereIn('pharmacy_id', $targetPharmacyIds);
                            })->orWhereHas('batches', function ($b) use ($targetPharmacyIds) {
                                $b->whereIn('pharmacy_id', $targetPharmacyIds);
                            })->orWhere(function ($fallbackQ) use ($targetPharmacyIds) {
                                $fallbackQ->whereNull('batches_id')
                                    ->whereHas('users', function ($u) use ($targetPharmacyIds) {
                                        $u->whereIn('pharmacy_id', $targetPharmacyIds);
                                    });
                            });
                        });
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('status', 7)
                        ->whereHas('batches', function ($b) use ($targetPharmacyIds) {
                            $b->whereIn('pharmacy_id', $targetPharmacyIds);
                        });
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->whereNotIn('status', [2, 4, 7])
                        ->where(function ($otherQ) use ($targetPharmacyIds) {
                            $otherQ->whereHas('batches', function ($b) use ($targetPharmacyIds) {
                                $b->whereIn('pharmacy_id', $targetPharmacyIds);
                            })->orWhere(function ($noBatchQ) use ($targetPharmacyIds) {
                                $noBatchQ->whereNull('batches_id')
                                    ->whereHas('users', function ($u) use ($targetPharmacyIds) {
                                        $u->whereIn('pharmacy_id', $targetPharmacyIds);
                                    });
                            });
                        });
                });
            });
    }

    /**
     * Ringkasan posisi Kartu Stok vs stok database aktual untuk 1 obat pada target lokasi.
     *
     * @return array{
     *   target_pharmacy_id: int,
     *   target_mode: string,
     *   db_stock: int,
     *   opening_stock: int,
     *   ledger_balance: int,
     *   discrepancy: int,
     *   has_opening_anchor: bool
     * }
     */
    public function getMedicineLedgerSummary(int $medicineId, int $activePharmacyId, string $targetMode): array
    {
        $warehouseId = getWarehousePharmacyId();
        $isGudang = ($targetMode === 'gudang');
        $targetPharmacyId = $isGudang
            ? $warehouseId
            : (isWarehousePharmacy($activePharmacyId) ? 1 : $activePharmacyId);

        $dbStock = $isGudang
            ? (int) Batches::where('medicine_id', $medicineId)
                ->where('pharmacy_id', $targetPharmacyId)
                ->sum('stock')
            : (int) MedicineTransferItems::whereHas('batches', function ($b) use ($medicineId, $targetPharmacyId) {
                    $b->where('medicine_id', $medicineId)
                      ->where('pharmacy_id', $targetPharmacyId);
                })
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->sum('qty');

        $rows = $this->branchMedicineLogsQuery($targetPharmacyId, $medicineId)
            ->orderByRaw($this->effectiveDateSql() . ' ASC')
            ->orderBy('id', 'asc')
            ->get();

        $ledger = $this->buildRunningLedger($rows, $dbStock);
        $ledgerBalance = $rows->isEmpty()
            ? $dbStock
            : ($ledger['hasOpeningAnchor'] ? $ledger['finalEnd'] : $dbStock);

        return [
            'target_pharmacy_id' => $targetPharmacyId,
            'target_mode'        => $targetMode,
            'db_stock'           => $dbStock,
            'opening_stock'      => $ledger['initialStart'],
            'ledger_balance'     => $ledgerBalance,
            'discrepancy'        => $dbStock - $ledgerBalance,
            'has_opening_anchor' => $ledger['hasOpeningAnchor'],
        ];
    }
}
