<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\Batches;
use App\Models\ItemsLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncPurchaseReturnManualStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'retur:sync-manual-stock 
                            {--dry-run : Simulasi tanpa mengubah database} 
                            {--pharmacy= : Filter pharmacy_id tertentu (default: semua cabang)}
                            {--return-no= : Filter nomor retur tertentu (mis. RET-2610/0007)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi dan selaraskan stok batch, master obat, serta Kartu Stok (ItemsLog) untuk retur pembelian di semua cabang.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $pharmacyFilter = $this->option('pharmacy');
        $returnNoFilter = $this->option('return-no');

        $this->info("==================================================================");
        $this->info("   SINKRONISASI STOK BATCH & KARTU STOK RETUR PEMBELIAN");
        $this->info("==================================================================");
        if ($isDryRun) {
            $this->warn("MODE DRY-RUN: Database TIDAK akan dimodifikasi.");
        }
        if ($pharmacyFilter) {
            $this->info("Filter Cabang: Pharmacy ID {$pharmacyFilter}");
        }
        if ($returnNoFilter) {
            $this->info("Filter Nomor Retur: {$returnNoFilter}");
        }

        $query = PurchaseReturnItem::with(['purchaseReturn', 'medicine'])
            ->whereHas('purchaseReturn', function ($q) use ($pharmacyFilter, $returnNoFilter) {
                $q->where('status', '!=', 'dibatalkan');
                if ($pharmacyFilter) {
                    $q->where('pharmacy_id', $pharmacyFilter);
                }
                if ($returnNoFilter) {
                    $q->where('return_no', $returnNoFilter);
                }
            })
            ->where(function ($q) {
                $q->whereIn('status', ['dikonfirmasi', 'sudah_diambil', 'sudah_potong', 'barang_kembali_pmi'])
                  ->orWhereHas('purchaseReturn', fn($pr) => $pr->where('is_manual', true));
            })
            ->orderBy('id', 'asc');

        $items = $query->get();
        $this->info("Ditemukan " . $items->count() . " item retur untuk diperiksa.");

        $processedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        // Kelompokkan item aktif (selain barang_kembali_pmi) berdasarkan return_no + medicine_id
        // agar item multi-batch pada obat yang sama (misal RET-2610/0007) masing-masing terpetakan ke ItemsLog-nya sendiri.
        $claimedLogIdsByGroup = [];
        $preMatchedLogByItemId = [];

        $activeItems = $items->filter(fn($it) => $it->status !== 'barang_kembali_pmi' && $it->purchaseReturn && $it->medicine_id);
        foreach ($activeItems->groupBy(fn($it) => $it->purchaseReturn->return_no . '|' . $it->medicine_id) as $groupKey => $groupItems) {
            $firstItem = $groupItems->first();
            $returnNo = $firstItem->purchaseReturn->return_no;
            $medId = (int) $firstItem->medicine_id;
            $med = $firstItem->medicine ?: Medicines::find($medId);
            if (!$med) {
                continue;
            }
            $pharmacyId = (int) ($firstItem->purchaseReturn->pharmacy_id ?: 9);

            $logs = ItemsLog::where('transaction_code', $returnNo)
                ->where('medicine_id', $medId)
                ->where('status', 4)
                ->orderBy('id', 'asc')
                ->get();

            $claimedLogIds = [];

            // Pass 1: Klaim log yang batch_id DAN qty-nya sudah persis cocok dengan targetBatch item
            foreach ($groupItems as $gItem) {
                $targetBatch = $this->findOrResolveBatch($gItem, $med, $pharmacyId, !$isDryRun);
                if (!$targetBatch) {
                    continue;
                }
                $factor = $this->resolveStockFactor($gItem, $med);
                $qtyDeduct = (float) $gItem->qty * $factor;

                $exactLog = $logs->first(function ($l) use ($targetBatch, $qtyDeduct, $claimedLogIds) {
                    return !in_array($l->id, $claimedLogIds, true)
                        && (int) $l->batches_id === (int) $targetBatch->id
                        && abs((float) $l->qty - $qtyDeduct) < 0.001;
                });

                if ($exactLog) {
                    $claimedLogIds[] = $exactLog->id;
                    $preMatchedLogByItemId[$gItem->id] = $exactLog;
                }
            }

            // Pass 2: Untuk item yang belum mendapat log persis, pasangkan dengan log yang tersisa
            foreach ($groupItems as $gItem) {
                if (isset($preMatchedLogByItemId[$gItem->id])) {
                    continue;
                }

                $targetBatch = $this->findOrResolveBatch($gItem, $med, $pharmacyId, !$isDryRun);
                $factor = $this->resolveStockFactor($gItem, $med);
                $qtyDeduct = (float) $gItem->qty * $factor;

                $fallbackLog = ($targetBatch ? $logs->first(function ($l) use ($targetBatch, $claimedLogIds) {
                    return !in_array($l->id, $claimedLogIds, true)
                        && (int) $l->batches_id === (int) $targetBatch->id;
                }) : null) ?? $logs->first(function ($l) use ($gItem, $claimedLogIds) {
                    return !in_array($l->id, $claimedLogIds, true)
                        && $gItem->batch_id
                        && (int) $l->batches_id === (int) $gItem->batch_id;
                }) ?? $logs->first(function ($l) use ($qtyDeduct, $claimedLogIds) {
                    return !in_array($l->id, $claimedLogIds, true)
                        && abs((float) $l->qty - $qtyDeduct) < 0.001;
                }) ?? $logs->first(function ($l) use ($claimedLogIds) {
                    return !in_array($l->id, $claimedLogIds, true);
                });

                if ($fallbackLog) {
                    $claimedLogIds[] = $fallbackLog->id;
                    $preMatchedLogByItemId[$gItem->id] = $fallbackLog;
                }
            }

            $claimedLogIdsByGroup[$groupKey] = $claimedLogIds;
        }

        foreach ($items as $item) {
            $p = $item->purchaseReturn;
            if (!$p) {
                $skippedCount++;
                continue;
            }

            $med = $item->medicine ?: Medicines::find($item->medicine_id);
            if (!$med) {
                $this->error("[ID {$item->id}] Obat tidak ditemukan (Medicine ID: {$item->medicine_id}). Dilewati.");
                $errorCount++;
                continue;
            }

            $pharmacyId = (int) ($p->pharmacy_id ?: 9);
            $factor = $this->resolveStockFactor($item, $med);
            $qtyDeduct = (float) $item->qty * $factor;
            $actionDate = $item->confirmed_date ? $item->confirmed_date->toDateString() : ($item->status_date ? $item->status_date->toDateString() : now()->toDateString());

            // Tangani status 'barang_kembali_pmi': barang fisik kembali ke PMI, stok harus dipulihkan jika pernah terpotong
            if ($item->status === 'barang_kembali_pmi') {
                if ($item->stock_deducted) {
                    $batch = $this->findOrResolveBatch($item, $med, $pharmacyId, !$isDryRun);
                    $qtyToRestore = $qtyDeduct;
                    $batchLabel = $batch?->name ?: ($item->batch_number ?: '-');
                    $this->warn("Barang Kembali PMI [Item ID: {$item->id}] {$med->name}: Memulihkan stok {$qtyToRestore} {$med->unit} ke batch [{$batchLabel}].");
                    if (!$isDryRun && $batch) {
                        DB::transaction(function () use ($item, $p, $med, $batch, $qtyToRestore, $actionDate, $pharmacyId) {
                            $qtyBefore = (float) $med->stock;
                            $med->increment('stock', $qtyToRestore);
                            $qtyAfter = $qtyBefore + $qtyToRestore;
                            $batch->increment('stock', $qtyToRestore);
                            $this->adjustOutletTransferStock($batch, $pharmacyId, $qtyToRestore);

                            ItemsLog::create([
                                'transaction_code' => $p->return_no . '-BATAL',
                                'code' => $this->generateLogCode(),
                                'type' => 'RT_BATAL',
                                'medicine_id' => $med->id,
                                'qty' => $qtyToRestore,
                                'qty_before' => $qtyBefore,
                                'qty_after' => $qtyAfter,
                                'total' => $item->subtotal,
                                'date' => $actionDate,
                                'status' => 6,
                                'batches_id' => $batch->id,
                                'user_id' => $p->user_id ?: 1,
                            ]);

                            $item->batch_id = $batch->id;
                            $item->batch_number = $batch->name;
                            $item->stock_deducted = false;
                            $item->save();
                        });
                        $this->info("✓ BERHASIL: Stok {$med->name} telah dipulihkan ke batch [{$batch->name}].");
                    }
                    $processedCount++;
                } else {
                    $skippedCount++;
                }
                continue;
            }

            // Tentukan batch yang seharusnya untuk item ini berdasarkan batch_number / batch_id
            $targetBatch = $this->findOrResolveBatch($item, $med, $pharmacyId, !$isDryRun);
            $targetBatchName = $targetBatch?->name ?: ((!empty($item->batch_number) && trim((string) $item->batch_number) !== '-') ? trim((string) $item->batch_number) : ('RET-' . date('ymd')));
            $matchedLog = $preMatchedLogByItemId[$item->id] ?? null;

            // Cek apakah item sudah pernah dipotong dan memiliki log yang terpetakan
            if ($matchedLog && $matchedLog->batches_id) {
                $loggedBatchId = (int) $matchedLog->batches_id;
                $previouslyDeducted = (float) $matchedLog->qty;
                $batchMatches = $targetBatch && ($loggedBatchId === (int) $targetBatch->id);
                $qtyMatches = (abs($previouslyDeducted - $qtyDeduct) < 0.001);
                $itemBatchSynced = $targetBatch
                    && ((int) $item->batch_id === (int) $targetBatch->id)
                    && (strcasecmp(trim((string) $item->batch_number), trim((string) $targetBatch->name)) === 0)
                    && (bool) $item->stock_deducted;

                if ($batchMatches && $qtyMatches && $itemBatchSynced) {
                    $unappliedOutlet = $this->getUnappliedOutletDeduction((int) $med->id, $pharmacyId);
                    if ($unappliedOutlet >= 0.001 && $targetBatch) {
                        $deductOutlet = min($qtyDeduct, $unappliedOutlet);
                        $this->warn("SINKRONISASI ETALASE CABANG [Item ID: {$item->id} | {$p->return_no}] {$med->name}:");
                        $this->line("Stok etalase cabang belum terpotong sebesar {$deductOutlet} {$med->unit} untuk batch retur [{$targetBatch->name}].");
                        if (!$isDryRun) {
                            DB::transaction(function () use ($targetBatch, $pharmacyId, $deductOutlet) {
                                $this->adjustOutletTransferStock($targetBatch, $pharmacyId, -$deductOutlet);
                            });
                            $this->info("✓ BERHASIL: Stok etalase cabang telah dipotong sebesar {$deductOutlet}.");
                        } else {
                            $this->info("[SIMULASI] Akan memotong stok etalase cabang sebesar {$deductOutlet}.");
                        }
                        $processedCount++;
                        continue;
                    }

                    $skippedCount++;
                    continue;
                }

                $oldBatch = Batches::find($loggedBatchId);
                $delta = $qtyDeduct - $previouslyDeducted;

                $this->warn("KOREKSI SINKRONISASI [Item ID: {$item->id} | {$p->return_no}] {$med->name}:");
                $this->line(
                    "Log Sebelumnya: Batch [" . ($oldBatch?->name ?? "ID:{$loggedBatchId}") . "] Qty {$previouslyDeducted} -> "
                    . "Seharusnya: Batch [{$targetBatchName}] Qty {$qtyDeduct} (Selisih Qty: {$delta})"
                );

                if (!$isDryRun && $targetBatch) {
                    DB::transaction(function () use ($item, $med, $oldBatch, $targetBatch, $loggedBatchId, $previouslyDeducted, $qtyDeduct, $delta, $matchedLog, $factor, $pharmacyId) {
                        if ($loggedBatchId !== (int) $targetBatch->id) {
                            if ($oldBatch) {
                                $oldBatch->increment('stock', $previouslyDeducted);
                                $this->adjustOutletTransferStock($oldBatch, $pharmacyId, $previouslyDeducted);
                            }
                            $targetBatch->decrement('stock', $qtyDeduct);
                            $this->adjustOutletTransferStock($targetBatch, $pharmacyId, -$qtyDeduct);
                        } elseif (abs($delta) >= 0.001) {
                            if ($delta > 0) {
                                $targetBatch->decrement('stock', $delta);
                            } else {
                                $targetBatch->increment('stock', abs($delta));
                            }
                            $this->adjustOutletTransferStock($targetBatch, $pharmacyId, -$delta);
                        }

                        if (abs($delta) >= 0.001) {
                            if ($delta > 0) {
                                $med->decrement('stock', $delta);
                            } else {
                                $med->increment('stock', abs($delta));
                            }
                        }

                        $matchedLog->update([
                            'batches_id' => $targetBatch->id,
                            'qty' => $qtyDeduct,
                            'qty_after' => (float) $matchedLog->qty_before - $qtyDeduct,
                            'total' => $item->subtotal,
                        ]);

                        $item->stock_unit_factor = $factor;
                        $item->retur_type = ($factor > 1) ? 'packaging' : 'unit';
                        $item->batch_id = $targetBatch->id;
                        $item->batch_number = $targetBatch->name;
                        if (empty($item->expired_date) && $targetBatch->expired_date) {
                            $item->expired_date = $targetBatch->expired_date;
                        }
                        $item->stock_deducted = true;
                        $item->save();
                    });
                    $this->info("✓ BERHASIL: Diselaraskan ke Batch [{$targetBatch->name}] (Qty: {$qtyDeduct}).");
                } else {
                    $this->info("[SIMULASI] Akan menyelaraskan ke Batch [{$targetBatchName}] (Qty: {$qtyDeduct}).");
                }

                $processedCount++;
                continue;
            }

            $this->line("------------------------------------------------------------------");
            $this->info("Memproses [Item ID: {$item->id}] No: {$p->return_no} | Cabang ID: {$pharmacyId}");
            $this->line("Obat: {$med->name} | Qty Retur: {$item->qty} {$item->unit} ({$qtyDeduct} satuan dasar @ factor {$factor})");

            $batch = $targetBatch;

            if (!$batch && !$isDryRun) {
                $this->error("Gagal menemukan/membuat batch untuk obat {$med->name}.");
                $errorCount++;
                continue;
            }

            if ($batch) {
                $this->line("Batch Dialokasikan: [ID: {$batch->id}] {$batch->name} (Stok Saat Ini: {$batch->stock})");
            } else {
                $this->line("Batch Dialokasikan (Simulasi Baru): {$targetBatchName}");
            }

            if (!$isDryRun && $batch) {
                DB::transaction(function () use ($item, $p, $med, $batch, $qtyDeduct, $actionDate, $factor, $matchedLog, $pharmacyId) {
                    $qtyBefore = (float) $med->stock;

                    // 1. Potong stok batch
                    $batch->decrement('stock', $qtyDeduct);
                    $this->adjustOutletTransferStock($batch, $pharmacyId, -$qtyDeduct);

                    // 2. Jika stock_deducted pada item retur masih false, potong stok master medicines
                    if (!$item->stock_deducted) {
                        $med->decrement('stock', $qtyDeduct);
                        $qtyAfter = $qtyBefore - $qtyDeduct;
                    } else {
                        $qtyAfter = $qtyBefore;
                    }

                    // 3. Update log yang belum memiliki batch atau buat ItemsLog kartu stok baru
                    if ($matchedLog) {
                        $matchedLog->update([
                            'batches_id' => $batch->id,
                            'qty' => $qtyDeduct,
                            'qty_after' => (float) $matchedLog->qty_before - $qtyDeduct,
                            'total' => $item->subtotal,
                        ]);
                    } else {
                        ItemsLog::create([
                            'transaction_code' => $p->return_no,
                            'code' => $this->generateLogCode(),
                            'type' => 'RT',
                            'medicine_id' => $med->id,
                            'qty' => $qtyDeduct,
                            'qty_before' => $qtyBefore,
                            'qty_after' => $qtyAfter,
                            'total' => $item->subtotal,
                            'date' => $actionDate,
                            'status' => 4,
                            'batches_id' => $batch->id,
                            'user_id' => $p->user_id ?: 1,
                        ]);
                    }

                    // 4. Update data item retur
                    $item->batch_id = $batch->id;
                    $item->batch_number = $batch->name;
                    $item->retur_type = ($factor > 1) ? 'packaging' : 'unit';
                    $item->stock_unit_factor = $factor;
                    if (empty($item->expired_date) && $batch->expired_date) {
                        $item->expired_date = $batch->expired_date;
                    }
                    $item->stock_deducted = true;
                    $item->save();
                });

                $this->info("✓ BERHASIL: Stok batch [{$batch->name}] telah dipotong sebanyak {$qtyDeduct}.");
            } else {
                $this->info("[SIMULASI] Akan memotong batch [{$targetBatchName}] sebanyak {$qtyDeduct}.");
            }

            $processedCount++;
        }

        $this->info("==================================================================");
        $this->info("SINKRONISASI SELESAI!");
        $this->info("Total Diproses & Diselaraskan: {$processedCount}");
        $this->info("Total Sudah Valid (Dilewati): {$skippedCount}");
        $this->info("Total Gagal/Error: {$errorCount}");
        $this->info("==================================================================");

        return 0;
    }

    /**
     * Hitung kelebihan stok etalase (MedicineTransferItems) di cabang non-gudang
     * yang belum terpotong dibanding posisi seharusnya (SO-SALDO-AWAL + total mutasi log).
     */
    protected function getUnappliedOutletDeduction(int $medicineId, int $pharmacyId): float
    {
        if (function_exists('isWarehousePharmacy') && isWarehousePharmacy($pharmacyId)) {
            return 0.0;
        }
        if (!Schema::hasTable('medicine_transfer_items')) {
            return 0.0;
        }

        $openingLog = ItemsLog::where('medicine_id', $medicineId)
            ->where('type', 'SO-SALDO-AWAL')
            ->whereHas('batches', fn($b) => $b->where('pharmacy_id', $pharmacyId))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->first();

        if (!$openingLog) {
            return 0.0;
        }

        $hasStatusCol = Schema::hasColumn('medicine_transfer_items', 'status');
        $hasSourceTypeCol = Schema::hasColumn('medicine_transfer_items', 'source_type');

        $mtiQuery = MedicineTransferItems::whereHas('batches', function ($b) use ($medicineId, $pharmacyId) {
            $b->where('medicine_id', $medicineId)->where('pharmacy_id', $pharmacyId);
        });
        if ($hasStatusCol) {
            $mtiQuery->where('status', 1);
        }
        if ($hasSourceTypeCol) {
            $mtiQuery->where(function ($q) {
                $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
            });
        }
        $currentCounterStock = (float) $mtiQuery->sum('qty');

        $logsQuery = ItemsLog::where('medicine_id', $medicineId)
            ->where('date', '>=', $openingLog->date)
            ->where(function ($q) use ($pharmacyId) {
                $q->whereHas('batches', fn($b) => $b->where('pharmacy_id', $pharmacyId));
                if (Schema::hasTable('users')) {
                    $q->orWhere(function ($noBatchQ) use ($pharmacyId) {
                        $noBatchQ->whereNull('batches_id')
                            ->whereHas('users', fn($u) => $u->where('pharmacy_id', $pharmacyId));
                    });
                }
            });

        $logs = $logsQuery->get();

        $totalMovement = 0.0;
        foreach ($logs as $log) {
            $st = (int) $log->status;
            if (in_array($st, [1, 4, 9], true)) {
                $totalMovement -= abs((float) $log->qty);
            } elseif (in_array($st, [2, 3], true)) {
                $totalMovement += abs((float) $log->qty);
            } elseif ($st === 8) {
                $totalMovement += (float) $log->qty;
            } else {
                $totalMovement += ((float) $log->qty_after - (float) $log->qty_before);
            }
        }

        $expectedCounterStock = (float) $openingLog->qty_before + $totalMovement;
        $diff = $currentCounterStock - $expectedCounterStock;

        return $diff > 0.0001 ? $diff : 0.0;
    }

    /**
     * Sesuaikan stok etalase (MedicineTransferItems) untuk farmasi outlet non-gudang.
     */
    protected function adjustOutletTransferStock(?Batches $batch, int $pharmacyId, float $delta): void
    {
        if (!$batch || abs($delta) < 0.0001) {
            return;
        }

        if (function_exists('isWarehousePharmacy') && isWarehousePharmacy($pharmacyId)) {
            return;
        }

        if (!Schema::hasTable('medicine_transfer_items')) {
            return;
        }

        $hasStatusCol = Schema::hasColumn('medicine_transfer_items', 'status');
        $hasSourceTypeCol = Schema::hasColumn('medicine_transfer_items', 'source_type');

        $applyActiveScope = function ($query) use ($hasStatusCol, $hasSourceTypeCol) {
            if ($hasStatusCol) {
                $query->where('medicine_transfer_items.status', 1);
            }
            if ($hasSourceTypeCol) {
                $query->where(function ($q) {
                    $q->whereNull('medicine_transfer_items.source_type')
                      ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                });
            }
            return $query;
        };

        $exactItems = $applyActiveScope(MedicineTransferItems::where('batches_id', $batch->id))
            ->orderByDesc('qty')
            ->orderBy('id', 'asc')
            ->get();

        $siblingQuery = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
            ->where('batches.medicine_id', $batch->medicine_id)
            ->where('batches.pharmacy_id', $pharmacyId)
            ->where('medicine_transfer_items.batches_id', '!=', $batch->id)
            ->select('medicine_transfer_items.*');
        $applyActiveScope($siblingQuery);

        if (!empty($batch->expired_date)) {
            $siblingQuery->orderByRaw('CASE WHEN batches.expired_date = ? THEN 0 ELSE 1 END ASC', [$batch->expired_date]);
        }
        $siblingItems = $siblingQuery
            ->orderBy('batches.expired_date', 'asc')
            ->orderBy('medicine_transfer_items.id', 'asc')
            ->get();

        if ($delta < 0) {
            $remaining = abs($delta);

            foreach ($exactItems as $item) {
                if ($remaining <= 0.0001) {
                    break;
                }
                if ((float) $item->qty <= 0) {
                    continue;
                }
                $take = min((float) $item->qty, $remaining);
                $item->decrement('qty', $take);
                $remaining -= $take;
            }

            if ($remaining > 0.0001) {
                foreach ($siblingItems as $sib) {
                    if ($remaining <= 0.0001) {
                        break;
                    }
                    if ((float) $sib->qty <= 0) {
                        continue;
                    }
                    $take = min((float) $sib->qty, $remaining);
                    $sib->decrement('qty', $take);
                    $remaining -= $take;
                }
            }

            if ($remaining > 0.0001) {
                $targetMinusItem = $exactItems->first();
                if ($targetMinusItem) {
                    $targetMinusItem->decrement('qty', $remaining);
                } else {
                    $created = $this->createOutletTransferItem($batch, $pharmacyId, -$remaining);
                    if (!$created && $siblingItems->isNotEmpty()) {
                        $siblingItems->last()->decrement('qty', $remaining);
                    }
                }
            }

            return;
        }

        $remainingRestore = $delta;

        foreach ($exactItems->concat($siblingItems) as $item) {
            if ($remainingRestore <= 0.0001) {
                break;
            }
            if ((float) $item->qty < 0) {
                $heal = min(abs((float) $item->qty), $remainingRestore);
                $item->increment('qty', $heal);
                $remainingRestore -= $heal;
            }
        }

        if ($remainingRestore <= 0.0001) {
            return;
        }

        if (Schema::hasColumn('medicine_transfer_items', 'original_qty')) {
            foreach ($exactItems->concat($siblingItems) as $item) {
                if ($remainingRestore <= 0.0001) {
                    break;
                }
                $orig = (float) ($item->original_qty ?? 0);
                $curr = (float) $item->qty;
                if ($orig > 0 && $curr < $orig) {
                    $restoreAmt = min($orig - $curr, $remainingRestore);
                    $item->increment('qty', $restoreAmt);
                    $remainingRestore -= $restoreAmt;
                }
            }
        }

        if ($remainingRestore <= 0.0001) {
            return;
        }

        if ($exactItems->isNotEmpty()) {
            $exactItems->first()->increment('qty', $remainingRestore);
        } elseif ($siblingItems->isNotEmpty()) {
            $siblingItems->first()->increment('qty', $remainingRestore);
        } else {
            $this->createOutletTransferItem($batch, $pharmacyId, $remainingRestore);
        }
    }

    /**
     * Buat baris MedicineTransferItems aktif untuk batch di cabang non-gudang.
     */
    protected function createOutletTransferItem(Batches $batch, int $pharmacyId, float $qty): ?MedicineTransferItems
    {
        $data = [
            'batches_id' => $batch->id,
            'qty' => $qty,
        ];

        if (Schema::hasColumn('medicine_transfer_items', 'medicine_transfer_id')) {
            $headerId = null;
            if (Schema::hasTable('medicine_transfers')) {
                $initCode = 'TRF-INIT-RET-' . $pharmacyId;
                $headerId = DB::table('medicine_transfers')
                    ->where('code', $initCode)
                    ->value('id');

                if (!$headerId) {
                    $headerData = [
                        'code' => $initCode,
                        'status' => 1,
                        'user_id' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    if (Schema::hasColumn('medicine_transfers', 'source_pharmacy_id')) {
                        $headerData['source_pharmacy_id'] = $pharmacyId;
                    }
                    if (Schema::hasColumn('medicine_transfers', 'destination_pharmacy_id')) {
                        $headerData['destination_pharmacy_id'] = $pharmacyId;
                    }
                    $headerId = DB::table('medicine_transfers')->insertGetId($headerData);
                }
            }

            if (!$headerId) {
                $headerId = MedicineTransferItems::whereHas('batches', function ($q) use ($pharmacyId) {
                        $q->where('pharmacy_id', $pharmacyId);
                    })
                    ->whereNotNull('medicine_transfer_id')
                    ->orderByDesc('id')
                    ->value('medicine_transfer_id');
            }

            if ($headerId) {
                $data['medicine_transfer_id'] = $headerId;
            }
        }

        if (Schema::hasColumn('medicine_transfer_items', 'source_batches_id')) {
            $data['source_batches_id'] = $batch->id;
        }

        if (Schema::hasColumn('medicine_transfer_items', 'source_type')) {
            $data['source_type'] = 'pelayanan';
        }

        if (Schema::hasColumn('medicine_transfer_items', 'status')) {
            $data['status'] = 1;
        }

        if (Schema::hasColumn('medicine_transfer_items', 'original_qty')) {
            $data['original_qty'] = $qty > 0 ? $qty : 0;
        }

        if (Schema::hasColumn('medicine_transfer_items', 'etalases_id')) {
            $etalaseId = MedicineTransferItems::whereHas('batches', function ($q) use ($batch, $pharmacyId) {
                    $q->where('pharmacy_id', $pharmacyId)
                      ->where('medicine_id', $batch->medicine_id);
                })
                ->whereNotNull('etalases_id')
                ->value('etalases_id');

            if (!$etalaseId && Schema::hasTable('etalases')) {
                $etalaseId = DB::table('etalases')
                    ->where('pharmacy_id', $pharmacyId)
                    ->orderByDesc('status')
                    ->orderBy('id')
                    ->value('id')
                    ?? DB::table('etalases')->orderBy('id')->value('id');
            }

            if ($etalaseId) {
                $data['etalases_id'] = $etalaseId;
            }
        }

        return MedicineTransferItems::create($data);
    }

    /**
     * Resolusi faktor konversi kemasan ke satuan stok fisik dasar.
     */
    protected function resolveStockFactor(PurchaseReturnItem $item, Medicines $med): int
    {
        if ($item->retur_type === 'packaging' && (int)$item->stock_unit_factor > 1) {
            return (int) $item->stock_unit_factor;
        }

        $content = (int) ($med->content ?: 1);
        if ($content <= 1) {
            return 1;
        }

        $itUnit = strtoupper(trim((string) ($item->unit ?? '')));
        $medPack = strtoupper(trim((string) ($med->packaging ?? '')));
        $medUnit = strtoupper(trim((string) ($med->unit ?? '')));

        if (!empty($itUnit)) {
            if ($itUnit === $medPack || in_array($itUnit, ['BOX', 'DUS', 'PACK', 'KEMASAN'])) {
                return $content;
            }
            if (!empty($medUnit) && $itUnit !== $medUnit && !in_array($itUnit, ['TAB', 'KAPS', 'CAP', 'PCS', 'BTL', 'VIAL', 'AMP', 'TUBE', 'POT', 'SACHET', 'STRIP'])) {
                return $content;
            }
        }

        if ($item->retur_type === 'packaging') {
            return $content;
        }

        return 1;
    }

    /**
     * Cari atau tentukan batch yang tepat di farmasi cabang.
     * Menjamin jika batch_number diisi eksplisit (misal LWJ24346), tidak akan tertimpa oleh batch_id lama atau FIFO batch lain.
     */
    protected function findOrResolveBatch(PurchaseReturnItem $item, Medicines $med, int $pharmacyId, bool $createIfMissing = true): ?Batches
    {
        $batch = null;
        $batchName = (!empty($item->batch_number) && trim((string) $item->batch_number) !== '-')
            ? trim((string) $item->batch_number)
            : '';

        // 1. Berdasarkan batch_id langsung jika ada DAN sesuai dengan batch_number (jika batch_number diisi)
        if (!empty($item->batch_id)) {
            $candidate = Batches::find($item->batch_id);
            if ($candidate && (int) $candidate->medicine_id === (int) $med->id) {
                if ($batchName === '' || strcasecmp(trim((string) $candidate->name), $batchName) === 0) {
                    $batch = $candidate;
                }
            }
        }

        // 2. Berdasarkan nomor batch eksplisit di cabang ini (atau legacy NULL pharmacy_id)
        if (!$batch && $batchName !== '') {
            $batch = Batches::where('medicine_id', $med->id)
                ->where('pharmacy_id', $pharmacyId)
                ->where(function ($q) use ($batchName) {
                    $q->where('name', $batchName)
                      ->orWhereRaw('TRIM(name) = ?', [$batchName]);
                })
                ->first();

            // Fallback batch legacy dengan pharmacy_id NULL
            if (!$batch) {
                $batch = Batches::where('medicine_id', $med->id)
                    ->whereNull('pharmacy_id')
                    ->where(function ($q) use ($batchName) {
                        $q->where('name', $batchName)
                          ->orWhereRaw('TRIM(name) = ?', [$batchName]);
                    })
                    ->first();
            }

            if (!$batch && $createIfMissing) {
                $batch = Batches::create([
                    'medicine_id' => $med->id,
                    'pharmacy_id' => $pharmacyId,
                    'name' => $batchName,
                    'expired_date' => $item->expired_date ?: now()->addYears(2)->toDateString(),
                    'stock' => 0,
                    'status' => 1,
                ]);
            }

            if (!$batch) {
                return null;
            }
        }

        // 3. AUTO-FALLBACK (hanya jika batch_number kosong): Ambil batch dengan stok > 0 di cabang ini (prioritas FIFO / ED terdekat)
        if (!$batch) {
            $batch = Batches::where('medicine_id', $med->id)
                ->where('pharmacy_id', $pharmacyId)
                ->where('stock', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();
        }

        // 4. Jika semua batch stoknya 0 / minus, ambil batch terbaru yang ada di cabang ini
        if (!$batch) {
            $batch = Batches::where('medicine_id', $med->id)
                ->where('pharmacy_id', $pharmacyId)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // 5. Jika sama sekali belum pernah ada batch di cabang ini, buat batch default (kecuali mode simulasi dry-run)
        if (!$batch && $createIfMissing) {
            $batch = Batches::create([
                'medicine_id' => $med->id,
                'pharmacy_id' => $pharmacyId,
                'name' => $batchName !== '' ? $batchName : ('RET-' . date('ymd')),
                'expired_date' => $item->expired_date ?: now()->addYears(2)->toDateString(),
                'stock' => 0,
                'status' => 1,
            ]);
        }

        return $batch;
    }

    protected function generateLogCode(): string
    {
        $now = Carbon::now();
        $year = $now->format('y');
        $month = $now->format('m');
        $prefix = "{$year}{$month}LOG-";

        $lastCode = ItemsLog::where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->value('code');

        if ($lastCode) {
            $lastNumber = (int) substr($lastCode, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}

