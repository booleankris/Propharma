<?php

namespace App\Services;

use App\Models\Batches;
use App\Models\MedicineTransferItems;
use Illuminate\Support\Facades\DB;

class StockDataReportService
{
    /**
     * Compute consolidated stock ledger metrics for a set of medicine IDs.
     * Guarantees: qty_start + qty_masuk - qty_sales - qty_transferred_branch + qty_adjustments == qty_now
     * whenever items_log and stock balances are in sync (and reconciles branch vs internal transfers cleanly).
     */
    public function computeMetrics(
        array $medicineIds,
        int $pharmacyId,
        ?string $startDate = null,
        ?string $endDate = null,
        bool $allMedicines = false
    ): array {
        if (empty($medicineIds) && !$allMedicines) {
            return [];
        }

        $warehouseId = (int) getWarehousePharmacyId(); // 9 (Gudang PMI)
        $pmiPharmacyId = 1; // SAHABAT PMI
        $canSeeWarehouse = (bool) canAccessWarehouseStock($pharmacyId);

        $ordersPharmacyId = $canSeeWarehouse ? $warehouseId : $pharmacyId;
        $salesPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;
        $startPharmacyIds = $canSeeWarehouse ? [$warehouseId, $pmiPharmacyId] : [$pharmacyId];
        $counterPharmacyId = $canSeeWarehouse ? $pmiPharmacyId : $pharmacyId;
        $allPharmacyIds = array_values(array_unique(array_merge([$ordersPharmacyId, $salesPharmacyId], $startPharmacyIds)));
        $transferSourcePharmacyIds = $canSeeWarehouse ? [$warehouseId, $pmiPharmacyId] : [$pharmacyId];
        $hasDateFilter = ($startDate !== null || $endDate !== null);

        // 1. Storage Stock (Gudang PMI)
        $storageStockMap = [];
        if ($canSeeWarehouse) {
            $storageStockMap = Batches::query()
                ->when(!$allMedicines, fn($q) => $q->whereIn('medicine_id', $medicineIds))
                ->where('pharmacy_id', $warehouseId)
                ->selectRaw('medicine_id, SUM(stock) as total')
                ->groupBy('medicine_id')
                ->pluck('total', 'medicine_id')
                ->all();
        }

        // 2. Counter Stock (Pelayanan PMI / Etalase Cabang)
        $counterStockMap = MedicineTransferItems::join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
            ->when(!$allMedicines, fn($q) => $q->whereIn('batches.medicine_id', $medicineIds))
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

        // 3. Latest Opening Balances (SO-SALDO-AWAL) per (medicine_id, pharmacy_id)
        $openingByMed = [];
        $openingSub = DB::table('items_log as il2')
            ->join('batches as b2', 'b2.id', '=', 'il2.batches_id')
            ->where('il2.type', 'SO-SALDO-AWAL')
            ->whereIn('b2.pharmacy_id', $startPharmacyIds)
            ->when(!$allMedicines, fn($q) => $q->whereIn('il2.medicine_id', $medicineIds))
            ->groupBy('il2.medicine_id', 'b2.pharmacy_id')
            ->select(
                'il2.medicine_id',
                'b2.pharmacy_id',
                DB::raw('MAX(il2.id) as max_id')
            );

        $openingRows = DB::table('items_log as il')
            ->join('batches as b', 'b.id', '=', 'il.batches_id')
            ->joinSub($openingSub, 'latest', 'latest.max_id', '=', 'il.id')
            ->select(
                'il.medicine_id',
                'b.pharmacy_id',
                'il.qty_before',
                DB::raw('DATE(il.date) as op_date')
            )
            ->get();

        foreach ($openingRows as $row) {
            $mId = (int) $row->medicine_id;
            $pId = (int) $row->pharmacy_id;
            $openingByMed[$mId]['by_pharmacy'][$pId] = [
                'qty_before' => (int) $row->qty_before,
                'op_date' => $row->op_date,
            ];
            if (!isset($openingByMed[$mId]['anchor_op_date']) || $row->op_date > $openingByMed[$mId]['anchor_op_date']) {
                $openingByMed[$mId]['anchor_op_date'] = $row->op_date;
            }
        }

        // 4. Movements from items_log (statuses 1..9)
        $logsQuery = DB::table('items_log')
            ->join('batches', 'batches.id', '=', 'items_log.batches_id')
            ->when(!$allMedicines, fn($q) => $q->whereIn('items_log.medicine_id', $medicineIds))
            ->whereIn('batches.pharmacy_id', $allPharmacyIds)
            ->whereIn('items_log.status', [1, 2, 3, 4, 5, 6, 7, 8, 9]);

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
            'items_log.type',
            'items_log.date',
            'items_log.qty',
            'items_log.qty_before',
            'items_log.qty_after',
            'items_log.total',
        ])->get();

        $aggOrdersNet = [];
        $aggSalesNet = [];
        $aggAdjustments = [];
        $logDeltaByMedPharmacy = [];

        foreach ($logs as $log) {
            $mId = (int) $log->medicine_id;
            $pId = (int) $log->pharmacy_id;
            $st = (int) $log->status;
            $logDate = substr((string) $log->date, 0, 10);

            if (!$hasDateFilter) {
                $anchorDate = $openingByMed[$mId]['anchor_op_date'] ?? '1000-01-01';
                if ($logDate <= $anchorDate) {
                    continue;
                }
            }

            $qty = (int) $log->qty;
            $signedDelta = $this->computeSignedLogDelta($log);

            $logDeltaByMedPharmacy[$mId][$pId] = ($logDeltaByMedPharmacy[$mId][$pId] ?? 0) + $signedDelta;

            // Orders net (Beli PBF + Revisi Penerimaan - Hapus Penerimaan - Retur Beli)
            if ($pId === $ordersPharmacyId) {
                if ($st === 2) {
                    $aggOrdersNet[$mId] = ($aggOrdersNet[$mId] ?? 0) + abs($qty);
                } elseif ($st === 8) {
                    $aggOrdersNet[$mId] = ($aggOrdersNet[$mId] ?? 0) + $qty;
                } elseif ($st === 9 || $st === 4) {
                    $aggOrdersNet[$mId] = ($aggOrdersNet[$mId] ?? 0) - abs($qty);
                }
            }

            // Sales net (Penjualan - Retur Jual)
            if ($pId === $salesPharmacyId) {
                if ($st === 1) {
                    $aggSalesNet[$mId] = ($aggSalesNet[$mId] ?? 0) + abs($qty);
                } elseif ($st === 3) {
                    $aggSalesNet[$mId] = ($aggSalesNet[$mId] ?? 0) - abs($qty);
                }
            }

            // Penyesuaian / Stock Opname ONLY (status 5 & 6; exclude SO-SALDO-AWAL markers and status 7 mutations)
            if (in_array($st, [5, 6], true) && in_array($pId, $startPharmacyIds, true) && $log->type !== 'SO-SALDO-AWAL') {
                $aggAdjustments[$mId] = ($aggAdjustments[$mId] ?? 0) + $signedDelta;
            }
        }

        // 5. Outbound Transfers / Mutations (Gudang PMI + Sahabat PMI for warehouse mode, or branch outbound)
        $batchIds = !$allMedicines
            ? Batches::whereIn('medicine_id', $medicineIds)->pluck('id')->all()
            : [];

        $transferQuery = DB::table('medicine_transfer_items as mti')
            ->join('medicine_transfers as mt', 'mt.id', '=', 'mti.medicine_transfer_id')
            ->leftJoin('batches as src_batches', 'src_batches.id', '=', 'mti.source_batches_id')
            ->leftJoin('batches as dst_batches', 'dst_batches.id', '=', 'mti.batches_id')
            ->when(!$allMedicines, function ($q) use ($medicineIds, $batchIds) {
                $q->where(function ($sub) use ($medicineIds, $batchIds) {
                    if (!empty($batchIds)) {
                        $sub->whereIn('mti.source_batches_id', $batchIds)
                            ->orWhereIn('mti.batches_id', $batchIds);
                    }
                    $sub->orWhereIn('mti.requested_medicine_id', $medicineIds);
                });
            })
            ->where(function ($sq) use ($transferSourcePharmacyIds) {
                $sq->whereIn('mt.source_pharmacy_id', $transferSourcePharmacyIds)
                   ->orWhere(function ($sq2) use ($transferSourcePharmacyIds) {
                       $sq2->whereNull('mt.source_pharmacy_id')
                           ->whereIn('src_batches.pharmacy_id', $transferSourcePharmacyIds);
                   });
            })
            ->where('mt.status', '!=', 2)
            ->where(function ($q) {
                $q->whereNull('mt.is_request')
                  ->orWhere('mt.is_request', 0)
                  ->orWhere('mt.request_status', 1);
            })
            ->where('mt.code', 'not like', 'TRF-INIT%')
            ->where(function ($q) {
                $q->whereNull('mti.source_type')
                  ->orWhere('mti.source_type', '!=', 'retur_gudang');
            })
            ->where(function ($q) {
                $q->whereNull('mt.destination_pharmacy_id')
                  ->orWhereRaw('COALESCE(mt.source_pharmacy_id, src_batches.pharmacy_id, 0) != mt.destination_pharmacy_id');
            })
            ->where(DB::raw('COALESCE(mti.original_qty, mti.qty)'), '>', 0);

        if ($canSeeWarehouse) {
            // Exclude returns back to Gudang PMI (warehouse consolidation)
            $transferQuery->where(function ($q) use ($warehouseId) {
                $q->whereNull('mt.destination_pharmacy_id')
                  ->orWhere('mt.destination_pharmacy_id', '!=', $warehouseId);
            });
        }

        if ($startDate) {
            $transferQuery->whereDate('mt.created_at', '>=', $startDate);
        }
        if ($endDate) {
            $transferQuery->whereDate('mt.created_at', '<=', $endDate);
        }

        $transfers = $transferQuery->select([
            DB::raw('COALESCE(src_batches.medicine_id, dst_batches.medicine_id, mti.requested_medicine_id) as medicine_id'),
            DB::raw('DATE(mt.created_at) as transfer_date'),
            'mt.destination_pharmacy_id',
            DB::raw('COALESCE(mti.original_qty, mti.qty) as qty'),
        ])->get();

        $aggTransfersPmi = [];
        $aggTransfersBranch = [];

        foreach ($transfers as $tr) {
            $mId = (int) $tr->medicine_id;
            if (!$mId) continue;
            if (!$hasDateFilter) {
                $anchorDate = $openingByMed[$mId]['anchor_op_date'] ?? '1000-01-01';
                if ($tr->transfer_date <= $anchorDate) {
                    continue;
                }
            }
            $trQty = (int) $tr->qty;
            if ($canSeeWarehouse && (int) $tr->destination_pharmacy_id === $pmiPharmacyId) {
                $aggTransfersPmi[$mId] = ($aggTransfersPmi[$mId] ?? 0) + $trQty;
            } else {
                $aggTransfersBranch[$mId] = ($aggTransfersBranch[$mId] ?? 0) + $trQty;
            }
        }

        // 5b. Inbound Transfers for Branch Mode (counted in Masuk)
        $aggTransfersIn = [];
        if (!$canSeeWarehouse) {
            $inboundQuery = DB::table('medicine_transfer_items as mti')
                ->join('medicine_transfers as mt', 'mt.id', '=', 'mti.medicine_transfer_id')
                ->leftJoin('batches as src_batches', 'src_batches.id', '=', 'mti.source_batches_id')
                ->leftJoin('batches as dst_batches', 'dst_batches.id', '=', 'mti.batches_id')
                ->when(!$allMedicines, function ($q) use ($medicineIds, $batchIds) {
                    $q->where(function ($sub) use ($medicineIds, $batchIds) {
                        if (!empty($batchIds)) {
                            $sub->whereIn('mti.batches_id', $batchIds)
                                ->orWhereIn('mti.source_batches_id', $batchIds);
                        }
                        $sub->orWhereIn('mti.requested_medicine_id', $medicineIds);
                    });
                })
                ->where('mt.destination_pharmacy_id', $pharmacyId)
                ->where('mt.status', 1)
                ->where('mti.status', 1)
                ->where('mt.code', 'not like', 'TRF-INIT%')
                ->where(function ($q) {
                    $q->whereNull('mti.source_type')
                      ->orWhere('mti.source_type', '!=', 'retur_gudang');
                })
                ->whereRaw('COALESCE(mt.source_pharmacy_id, src_batches.pharmacy_id, 0) != ?', [$pharmacyId])
                ->where(DB::raw('COALESCE(mti.original_qty, mti.qty)'), '>', 0);

            if ($startDate) {
                $inboundQuery->whereDate('mt.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $inboundQuery->whereDate('mt.created_at', '<=', $endDate);
            }

            $inboundRows = $inboundQuery->select([
                DB::raw('COALESCE(dst_batches.medicine_id, src_batches.medicine_id, mti.requested_medicine_id) as medicine_id'),
                DB::raw('DATE(mt.created_at) as transfer_date'),
                DB::raw('COALESCE(mti.original_qty, mti.qty) as qty'),
            ])->get();

            foreach ($inboundRows as $tr) {
                $mId = (int) $tr->medicine_id;
                if (!$mId) continue;
                if (!$hasDateFilter) {
                    $anchorDate = $openingByMed[$mId]['anchor_op_date'] ?? '1000-01-01';
                    if ($tr->transfer_date <= $anchorDate) {
                        continue;
                    }
                }
                $aggTransfersIn[$mId] = ($aggTransfersIn[$mId] ?? 0) + (int) $tr->qty;
            }
        }

        // 6. Payment Methods (Keterangan)
        $paymentMethods = DB::table('receiving_items as ri')
            ->join('receiving_details as rd', 'rd.id', '=', 'ri.receiving_details_id')
            ->join('receiving as r', 'r.id', '=', 'rd.receiving_id')
            ->leftJoin('batches as b', 'b.id', '=', 'ri.batches_id')
            ->leftJoin('order_items as oi', 'oi.id', '=', 'ri.order_items_id')
            ->when(!$allMedicines, function ($q) use ($medicineIds, $batchIds) {
                $q->where(function ($sub) use ($medicineIds, $batchIds) {
                    if (!empty($batchIds)) {
                        $sub->whereIn('ri.batches_id', $batchIds);
                    }
                    $sub->orWhereIn('oi.medicine_id', $medicineIds);
                });
            })
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

        // 7. Assemble Metrics per Medicine
        $result = [];
        foreach ($medicineIds as $rawId) {
            $mId = (int) $rawId;
            $qtyStorage = $canSeeWarehouse ? (int) ($storageStockMap[$mId] ?? 0) : 0;
            $qtyCounter = (int) ($counterStockMap[$mId] ?? 0);
            $totalStok = $qtyStorage + $qtyCounter;

            $qtyOrdersNet = (int) ($aggOrdersNet[$mId] ?? 0);
            $qtyTransfersIn = (int) ($aggTransfersIn[$mId] ?? 0);
            $qtyMasuk = $canSeeWarehouse ? $qtyOrdersNet : ($qtyOrdersNet + $qtyTransfersIn);

            $qtySalesNet = (int) ($aggSalesNet[$mId] ?? 0);
            $qtyTransfersPmi = (int) ($aggTransfersPmi[$mId] ?? 0);
            $qtyTransfersBranch = (int) ($aggTransfersBranch[$mId] ?? 0);
            $qtyTransferredTotal = $qtyTransfersPmi + $qtyTransfersBranch;

            $adjustments = (int) ($aggAdjustments[$mId] ?? 0);
            $qtyKeluar = $qtySalesNet + $qtyTransfersBranch;

            $medOpenings = $openingByMed[$mId] ?? null;
            if (!$hasDateFilter && $medOpenings !== null) {
                $anchorDate = $medOpenings['anchor_op_date'];
                $byPharm = $medOpenings['by_pharmacy'] ?? [];

                if ($canSeeWarehouse) {
                    $openGudang = (isset($byPharm[$warehouseId]) && $byPharm[$warehouseId]['op_date'] === $anchorDate)
                        ? $byPharm[$warehouseId]['qty_before']
                        : ($qtyStorage - (int) ($logDeltaByMedPharmacy[$mId][$warehouseId] ?? 0));

                    $openPmi = (isset($byPharm[$pmiPharmacyId]) && $byPharm[$pmiPharmacyId]['op_date'] === $anchorDate)
                        ? $byPharm[$pmiPharmacyId]['qty_before']
                        : ($qtyCounter - (int) ($logDeltaByMedPharmacy[$mId][$pmiPharmacyId] ?? 0));

                    $qtyStart = $openGudang + $openPmi;
                } else {
                    $qtyStart = isset($byPharm[$pharmacyId])
                        ? $byPharm[$pharmacyId]['qty_before']
                        : ($totalStok - $qtyMasuk + $qtyKeluar - $adjustments);
                }
            } else {
                $qtyStart = $totalStok - $qtyMasuk + $qtyKeluar - $adjustments;
            }

            $rawPayment = $paymentMethods[$mId] ?? null;
            $p = $rawPayment ? strtoupper(trim($rawPayment)) : null;
            $keterangan = ($p === 'TUNAI' || $p === 'CASH')
                ? 'Cash'
                : (($p === 'KREDIT') ? 'Kredit' : (($p === 'KONSINYASI') ? 'Konsinyasi' : ($p ? ucfirst(strtolower($p)) : '-')));

            $result[$mId] = [
                'qty_start' => $qtyStart,
                'qty_orders' => $qtyMasuk,
                'qty_sales' => $qtySalesNet,
                'qty_transferred_pmi' => $qtyTransfersPmi,
                'qty_transferred_branch' => $qtyTransfersBranch,
                'qty_transferred' => $qtyTransferredTotal,
                'qty_adjustments' => $adjustments,
                'qty_storage' => $qtyStorage,
                'qty_counter' => $qtyCounter,
                'qty_now' => $totalStok,
                'keterangan' => $keterangan,
            ];
        }

        return $result;
    }

    private function computeSignedLogDelta(object $log): int
    {
        $st = (int) $log->status;
        $qty = (int) $log->qty;

        if ($st === 1 || $st === 4 || $st === 9) {
            return -abs($qty);
        }
        if ($st === 2 || $st === 3) {
            return abs($qty);
        }
        if ($st === 8) {
            return $qty;
        }
        if ($st === 5) {
            if ($log->total !== null && preg_match('/^[+-]?[0-9]+([.][0-9]+)?([eE][+-]?[0-9]+)?$/', trim((string) $log->total))) {
                return (int) $log->total;
            }
        }

        return (int) $log->qty_after - (int) $log->qty_before;
    }
}

