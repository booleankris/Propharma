<?php

namespace App\Services;

use App\Models\Batches;
use App\Models\Etalases;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Buat atau kurangi baris stok counter minus virtual ('MINUS-AUTO') saat cabang
     * melakukan penjualan ketika belum ada baris medicine_transfer_items sama sekali.
     * Harus dipanggil di dalam DB transaction setelah row medicines dikunci (lockForUpdate).
     */
    public function recordNegativeCounterSale(
        int $medicineId,
        int $pharmacyId,
        float $qtyToDeduct,
        int $actorId = 1
    ): ?MedicineTransferItems {
        if ($qtyToDeduct <= 0 || isWarehousePharmacy($pharmacyId)) {
            return null;
        }

        $minusBatch = Batches::where('medicine_id', $medicineId)
            ->where('pharmacy_id', $pharmacyId)
            ->where('name', 'MINUS-AUTO')
            ->lockForUpdate()
            ->first();

        if (!$minusBatch) {
            $minusBatch = Batches::create([
                'medicine_id' => $medicineId,
                'pharmacy_id' => $pharmacyId,
                'name' => 'MINUS-AUTO',
                'expired_date' => null,
                'stock' => 0,
                'status' => 0,
            ]);
        }

        $existingItem = MedicineTransferItems::where('batches_id', $minusBatch->id)
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('source_type')
                    ->orWhere('source_type', '!=', 'retur_gudang');
            })
            ->lockForUpdate()
            ->first();

        if ($existingItem) {
            if ($existingItem->original_qty === null) {
                $existingItem->original_qty = 0;
            }
            $existingItem->qty = (float) $existingItem->qty - $qtyToDeduct;
            $existingItem->save();

            return $existingItem;
        }

        $initCode = 'TRF-INIT-MINUS-' . $pharmacyId;
        $transferHeaderId = DB::table('medicine_transfers')
            ->where('code', $initCode)
            ->value('id');

        if (!$transferHeaderId) {
            $transferColumns = Schema::hasTable('medicine_transfers')
                ? array_flip(Schema::getColumnListing('medicine_transfers'))
                : [];

            $now = now();
            $headerData = [
                'code' => $initCode,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (isset($transferColumns['user_id'])) {
                $headerData['user_id'] = $actorId > 0 ? $actorId : 1;
            }
            if (isset($transferColumns['is_request'])) {
                $headerData['is_request'] = false;
            }
            if (isset($transferColumns['source_pharmacy_id'])) {
                $headerData['source_pharmacy_id'] = $pharmacyId;
            }
            if (isset($transferColumns['destination_pharmacy_id'])) {
                $headerData['destination_pharmacy_id'] = $pharmacyId;
            }

            $transferHeaderId = DB::table('medicine_transfers')->insertGetId($headerData);
        }

        $etalaseId = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
            ->where('batches.medicine_id', $medicineId)
            ->where('batches.pharmacy_id', $pharmacyId)
            ->whereNotNull('medicine_transfer_items.etalases_id')
            ->orderByDesc('medicine_transfer_items.id')
            ->value('medicine_transfer_items.etalases_id');

        if (!$etalaseId) {
            $etalaseId = Etalases::where('pharmacy_id', $pharmacyId)
                ->orderByDesc('status')
                ->orderBy('id')
                ->value('id')
                ?? Etalases::orderBy('id')->value('id');
        }

        return MedicineTransferItems::create([
            'medicine_transfer_id' => $transferHeaderId,
            'batches_id' => $minusBatch->id,
            'etalases_id' => $etalaseId,
            'qty' => -abs($qtyToDeduct),
            'original_qty' => 0,
            'source_type' => 'pelayanan',
            'status' => 1,
        ]);
    }

    /**
     * Kompensasikan stok counter minus (zero-sum) saat stok baru masuk ke cabang
     * (Mutasi Masuk ACC / Pembelian Langsung Cabang), tanpa mengubah original_qty.
     */
    public function settleNegativeCounterStock(
        int $medicineId,
        int $pharmacyId,
        MedicineTransferItems $incomingItem
    ): float {
        if (isWarehousePharmacy($pharmacyId)) {
            return 0.0;
        }

        $availableQty = (float) $incomingItem->qty;
        if ($availableQty <= 0 || (int) $incomingItem->status !== 1) {
            return 0.0;
        }

        // Konsistenkan hirarki lock dengan POS checkout: kunci baris medicines terlebih dulu.
        Medicines::whereKey($medicineId)->lockForUpdate()->first();

        $needsOriginalPreserve = $incomingItem->original_qty === null;
        if ($needsOriginalPreserve) {
            $incomingItem->original_qty = $availableQty;
        }

        $negativeItems = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
            ->where('batches.medicine_id', $medicineId)
            ->where('batches.pharmacy_id', $pharmacyId)
            ->where('medicine_transfer_items.status', 1)
            ->where('medicine_transfer_items.id', '!=', $incomingItem->id)
            ->where('medicine_transfer_items.qty', '<', 0)
            ->where(function ($q) {
                $q->whereNull('medicine_transfer_items.source_type')
                    ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
            })
            ->orderByRaw("CASE WHEN batches.name = 'MINUS-AUTO' THEN 0 ELSE 1 END ASC")
            ->orderByRaw('CASE WHEN batches.expired_date IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('batches.expired_date', 'asc')
            ->orderBy('medicine_transfer_items.id', 'asc')
            ->lockForUpdate()
            ->select('medicine_transfer_items.*')
            ->get();

        if ($negativeItems->isEmpty()) {
            if ($needsOriginalPreserve) {
                $incomingItem->save();
            }

            return 0.0;
        }

        $settledTotal = 0.0;
        foreach ($negativeItems as $negItem) {
            if ($availableQty <= 0) {
                break;
            }

            $deficit = abs((float) $negItem->qty);
            if ($deficit <= 0) {
                continue;
            }

            $offset = min($availableQty, $deficit);
            $negItem->qty = (float) $negItem->qty + $offset;
            $negItem->save();

            $availableQty -= $offset;
            $settledTotal += $offset;
        }

        if ($settledTotal > 0 || $needsOriginalPreserve) {
            $incomingItem->qty = $availableQty;
            $incomingItem->save();
        }

        return $settledTotal;
    }
}
