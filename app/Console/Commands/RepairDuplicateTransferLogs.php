<?php

namespace App\Console\Commands;

use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransfers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairDuplicateTransferLogs extends Command
{
    protected $signature = 'transfers:repair-duplicate-logs {--code= : Repair one transfer code only} {--dry-run : Only simulate changes} {--deduct-mti-batch= : Batch ID to manually deduct MTI} {--deduct-qty= : Qty to deduct from MTI}';

    protected $description = 'Clean up duplicate incoming transfer logs and adjust over-counted stock';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('MODE DRY-RUN: Tidak ada data yang diubah di database.');
        }

        if ($batchId = $this->option('deduct-mti-batch')) {
            $deductQty = (int) $this->option('deduct-qty');
            if ($deductQty <= 0) {
                $this->error('Parameter --deduct-qty harus lebih besar dari 0.');
                return self::FAILURE;
            }
            $destBatch = Batches::find($batchId);
            if (!$destBatch) {
                $this->error("Batch ID {$batchId} tidak ditemukan.");
                return self::FAILURE;
            }
            $mtiList = MedicineTransferItems::where('batches_id', $destBatch->id)
                ->where('status', 1)
                ->where('qty', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->orderBy('id', 'desc')
                ->get();

            $this->info("Penyesuaian stok MTI untuk Batch {$batchId} (Total sebelum: {$mtiList->sum('qty')}, Potong: {$deductQty}).");
            if (!$dryRun) {
                DB::transaction(function () use ($mtiList, $deductQty) {
                    $remaining = $deductQty;
                    foreach ($mtiList as $mti) {
                        if ($remaining <= 0) break;
                        $cut = min((int) $mti->qty, $remaining);
                        $mti->decrement('qty', $cut);
                        $remaining -= $cut;
                    }
                });
                $afterTotal = MedicineTransferItems::where('batches_id', $destBatch->id)
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                    })
                    ->sum('qty');
                $this->info("Berhasil! Stok MTI Batch {$batchId} sekarang menjadi: {$afterTotal}.");
            }
            return self::SUCCESS;
        }

        $query = ItemsLog::query()
            ->select('transaction_code', 'medicine_id', 'batches_id', 'qty')
            ->selectRaw('COUNT(*) AS log_count, MIN(id) AS keep_id')
            ->where('type', 'MU')
            ->where('status', 7)
            ->whereRaw('CAST(qty_after AS SIGNED) > CAST(qty_before AS SIGNED)')
            ->whereNotNull('transaction_code')
            ->groupBy('transaction_code', 'medicine_id', 'batches_id', 'qty')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('transaction_code');

        if ($code = $this->option('code')) {
            $query->where('transaction_code', $code);
        }

        $groups = $query->get();

        if ($groups->isEmpty()) {
            $this->info('Tidak ditemukan duplikasi log mutasi masuk.');
            return self::SUCCESS;
        }

        $this->info("Ditemukan {$groups->count()} kelompok duplikasi log mutasi masuk.");

        foreach ($groups as $group) {
            $duplicateLogs = ItemsLog::where('transaction_code', $group->transaction_code)
                ->where('medicine_id', $group->medicine_id)
                ->where('batches_id', $group->batches_id)
                ->where('qty', $group->qty)
                ->where('type', 'MU')
                ->where('status', 7)
                ->whereRaw('CAST(qty_after AS SIGNED) > CAST(qty_before AS SIGNED)')
                ->where('id', '!=', $group->keep_id)
                ->get();

            $excessQty = $duplicateLogs->sum('qty');
            $this->line("Kode {$group->transaction_code} | Batch {$group->batches_id} | Obat {$group->medicine_id}: Hapus {$duplicateLogs->count()} log duplikat, kelebihan qty: {$excessQty}");

            if (!$dryRun) {
                DB::transaction(function () use ($group, $duplicateLogs, $excessQty) {
                    $destBatch = Batches::find($group->batches_id);
                    $destPharmacyId = $destBatch?->pharmacy_id;
                    $isWarehouse = isWarehousePharmacy($destPharmacyId);

                    if ($destBatch) {
                        if ($isWarehouse) {
                            $destBatch->decrement('stock', $excessQty);
                        } else {
                            // Untuk cabang non-gudang (pelayanan), kurangi kelebihan qty dari MedicineTransferItems aktif
                            $remainingDeduct = $excessQty;
                            $mtiList = MedicineTransferItems::where('batches_id', $destBatch->id)
                                ->where('status', 1)
                                ->where('qty', '>', 0)
                                ->where(function ($q) {
                                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                                })
                                ->orderBy('id', 'desc')
                                ->lockForUpdate()
                                ->get();

                            foreach ($mtiList as $mti) {
                                if ($remainingDeduct <= 0) break;
                                $deduct = min((int) $mti->qty, $remainingDeduct);
                                $mti->decrement('qty', $deduct);
                                $remainingDeduct -= $deduct;
                            }
                        }
                    }

                    // Check if there are duplicate MedicineTransferItems for the same transfer and batch
                    $transfer = MedicineTransfers::where('code', $group->transaction_code)->first();
                    if ($transfer) {
                        $mtiItems = MedicineTransferItems::where('medicine_transfer_id', $transfer->id)
                            ->where('batches_id', $group->batches_id)
                            ->orderBy('id')
                            ->get();

                        if ($mtiItems->count() > 1) {
                            // Keep the first one, delete subsequent duplicates
                            $toDeleteMti = $mtiItems->slice(1);
                            foreach ($toDeleteMti as $extraMti) {
                                $extraMti->delete();
                            }
                        }
                    }

                    // Delete extra duplicate ItemsLog rows
                    ItemsLog::whereIn('id', $duplicateLogs->pluck('id'))->delete();
                });
            }
        }

        $this->info($dryRun ? 'Simulasi selesai.' : 'Perbaikan log duplikat mutasi berhasil diselesaikan.');
        return self::SUCCESS;
    }
}
