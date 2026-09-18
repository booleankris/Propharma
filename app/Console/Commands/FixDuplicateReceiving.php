<?php

namespace App\Console\Commands;

use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixDuplicateReceiving extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'receiving:fix-duplicate 
                            {code? : Nomor Terima (contoh: NT-26-09/0120) atau Nomor Faktur}
                            {--medicine= : Filter langsung berdasarkan nama obat (contoh: --medicine=SWAB)}
                            {--all : Scan dan perbaiki seluruh faktur di database}
                            {--dry-run : Simulasi saja tanpa mengubah data di database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa dan membersihkan kartu stock, stok etalase, dan item penerimaan yang terduplikasi secara presisi';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = trim((string) $this->argument('code'));
        $isDryRun = (bool) $this->option('dry-run');
        $medSearch = $this->option('medicine');
        $isAll = (bool) $this->option('all');

        $this->info("================================================================================");
        $this->info("   PROPHARMA - PEMULIHAN PRESISI KARTU STOCK & STOK ETALASE");
        $this->info("================================================================================");
        if ($isAll) {
            $this->line("Target Mode   : [SCAN SEMUA TRANSAKSI DI DATABASE]");
        } elseif ($medSearch) {
            $this->line("Target Obat   : [{$medSearch}]");
        } else {
            if (!$code) {
                $code = '2609RE0635';
            }
            $this->line("Target Input  : [{$code}]");
        }
        $this->line("Mode          : " . ($isDryRun ? "DRY-RUN (Simulasi)" : "EKSEKUSI NYATA (Live Database)"));
        $this->newLine();

        if ($isAll) {
            // Find all transactions with duplicate purchase logs
            $dupeTransactions = ItemsLog::select('transaction_code')
                ->where('status', 2)
                ->whereNotNull('transaction_code')
                ->groupBy('transaction_code', 'medicine_id')
                ->havingRaw('count(*) > 1')
                ->distinct()
                ->pluck('transaction_code')
                ->toArray();

            $this->info("Ditemukan " . count($dupeTransactions) . " transaksi penerimaan dengan log duplikat di database.");
            
            $totalFixed = 0;
            foreach ($dupeTransactions as $tCode) {
                $this->line("\n----------------------------------------------------------------");
                $this->line("Memproses Transaksi: {$tCode}");
                $this->line("----------------------------------------------------------------");
                $this->call('receiving:fix-duplicate', [
                    'code' => $tCode,
                    '--dry-run' => $isDryRun,
                ]);
                $totalFixed++;
            }
            $this->newLine();
            $this->info("=== SCAN & PERBAIKAN MENYELURUH SELESAI ({$totalFixed} Transaksi) ===");
            return 0;
        }

        $rd = null;
        $recCode = null;

        if (!$medSearch) {
            // 1. Check if code is receiving transaction code (e.g. 2609RE0635)
            $recObj = \App\Models\Receiving::where('code', $code)->first();
            if ($recObj) {
                $recCode = $recObj->code;
                $this->line("Transaksi Penerimaan Ditemukan: ID {$recObj->id} | Kode: {$recObj->code} | Cabang: {$recObj->pharmacy_id}");
            } else {
                // 2. Check if code is receiving_details_code (NT-...) or invoice_number
                $rd = ReceivingDetails::with(['receiving', 'creditor'])
                    ->where('receiving_details_code', $code)
                    ->orWhere('invoice_number', $code)
                    ->first();

                if ($rd) {
                    $recCode = $rd->receiving->code ?? null;
                    $this->line("Faktur Ditemukan: ID {$rd->id} | No Terima: {$rd->receiving_details_code} | Faktur: {$rd->invoice_number} | Trans Rec: " . ($recCode ?: '-'));
                } else {
                    // Try checking items_log directly for transaction_code
                    $hasLog = ItemsLog::where('transaction_code', $code)->exists();
                    if ($hasLog) {
                        $recCode = $code;
                        $this->line("Kode Transaksi Log Ditemukan: {$recCode}");
                    } else {
                        $this->error("Penerimaan/Faktur {$code} tidak ditemukan.");
                        return 1;
                    }
                }
            }
        }

        $logQuery = ItemsLog::where('status', 2)->with(['medicines', 'batches'])->orderBy('id', 'asc');

        if ($medSearch) {
            $logQuery->whereHas('medicines', function ($q) use ($medSearch) {
                $q->where('name', 'like', "%{$medSearch}%");
            });
            if ($code) {
                $logQuery->where('transaction_code', $code);
            }
        } else {
            $logQuery->where(function ($q) use ($rd, $recCode) {
                if ($recCode) {
                    $q->where('transaction_code', $recCode);
                }
                if ($rd) {
                    $q->orWhere('transaction_code', $rd->receiving_details_code)
                      ->orWhere('transaction_code', $rd->invoice_number);
                }
            });
        }

        $allLogs = $logQuery->get();
        $this->info("Total log pembelian (status 2) ditemukan: " . $allLogs->count() . " baris.");

        if ($allLogs->isEmpty()) {
            $this->info("Tidak ada log pembelian yang ditemukan untuk target ini.");
            return 0;
        }

        $logsByMedicine = $allLogs->groupBy('medicine_id');
        $dupesToFix = [];
        $allDupBatchIds = [];
        $dupTransferItemIds = [];

        foreach ($logsByMedicine as $medId => $medLogs) {
            $medName = $medLogs->first()->medicines->name ?? "Obat #{$medId}";
            $count = $medLogs->count();

            if ($count <= 1) {
                $this->line("  [OK] {$medName} (ID: {$medId}) -> {$count} baris log. Normal.");
                continue;
            }

            $masterLog = $medLogs->first();
            $duplicateLogs = $medLogs->slice(1);
            $excessQty = (float) $duplicateLogs->sum('qty');
            $masterBatchId = $masterLog->batches_id;

            $dupBatchesForMed = $duplicateLogs->pluck('batches_id')
                ->filter(fn($bId) => $bId && $bId != $masterBatchId)
                ->unique()
                ->values()
                ->toArray();

            $allDupBatchIds = array_merge($allDupBatchIds, $dupBatchesForMed);

            $this->warn("  [DUPLIKAT] {$medName} (ID: {$medId}) -> Ditemukan {$count} baris log (Kelebihan: +{$excessQty})");
            $this->line("      - Master Log : ID #{$masterLog->id} (Qty: +{$masterLog->qty}, Batch: #{$masterBatchId})");
            foreach ($duplicateLogs as $dupLog) {
                $this->line("      - Hapus Log  : ID #{$dupLog->id} (Qty: +{$dupLog->qty}, Batch: #{$dupLog->batches_id})");
            }

            $dupesToFix[$medId] = [
                'medicine_id' => $medId,
                'medicine_name' => $medName,
                'master_log' => $masterLog,
                'master_batch_id' => $masterBatchId,
                'duplicate_logs' => $duplicateLogs,
                'duplicate_batch_ids' => $dupBatchesForMed,
                'excess_qty' => $excessQty,
            ];
        }

        $allDupBatchIds = array_unique($allDupBatchIds);

        // Find transfer items to remove
        // If transfer headers exist from duplicate runs, or transfer items tied to duplicate batches
        if (!empty($dupesToFix)) {
            $allDupLogIds = collect($dupesToFix)->flatMap(fn($i) => $i['duplicate_logs']->pluck('id'))->toArray();
            
            // Look for transfer items created around the same timestamps or tied to duplicate batches
            $transferItemsQuery = MedicineTransferItems::where(function ($q) use ($allDupBatchIds, $dupesToFix) {
                if (!empty($allDupBatchIds)) {
                    $q->whereIn('batches_id', $allDupBatchIds);
                }
                foreach ($dupesToFix as $item) {
                    $medId = $item['medicine_id'];
                    $q->orWhereHas('batches', function ($bq) use ($medId) {
                        $bq->where('medicine_id', $medId);
                    });
                }
            })->where('status', 1);

            // If we have specific duplicate transfer headers
            $dupTransferHeaders = MedicineTransfers::whereHas('items', function ($q) use ($allDupBatchIds) {
                $q->whereIn('batches_id', $allDupBatchIds);
            })->pluck('id')->toArray();

            if (!empty($dupTransferHeaders)) {
                $dupTransferItems = MedicineTransferItems::whereIn('medicine_transfer_id', $dupTransferHeaders)->get();
                $dupTransferItemIds = $dupTransferItems->pluck('id')->toArray();
                $this->info("Ditemukan " . count($dupTransferHeaders) . " header transfer duplikat dengan " . count($dupTransferItemIds) . " item transfer.");
            }
        }

        if (empty($dupesToFix)) {
            $this->info("Tidak ditemukan duplikasi di Kartu Stock.");
            return 0;
        }

        if ($isDryRun) {
            $this->warn("\nMODE DRY-RUN: Database TIDAK diubah. Rangkuman:");
            $this->line("- Total Obat yang akan dikoreksi : " . count($dupesToFix));
            $this->line("- Total Log Duplikat dihapus     : " . collect($dupesToFix)->sum(fn($i) => $i['duplicate_logs']->count()));
            $this->line("- Total Batch Duplikat dihapus   : " . count($allDupBatchIds));
            $this->line("- Total Item Transfer dihapus    : " . count($dupTransferItemIds));
            return 0;
        }

        $this->info("\nMengeksekusi perbaikan database secara transaksional...");
        DB::beginTransaction();

        try {
            foreach ($dupesToFix as $medId => $item) {
                $medName = $item['medicine_name'];
                $excessQty = $item['excess_qty'];
                $masterBatchId = $item['master_batch_id'];
                $dupBatchIds = $item['duplicate_batch_ids'];

                // 1. Hapus duplicate items_log
                foreach ($item['duplicate_logs'] as $dupLog) {
                    $dupLog->delete();
                }
                $this->line("  - [items_log] Menghapus {$item['duplicate_logs']->count()} baris log duplikat {$medName}.");

                // 2. Koreksi receiving_items jika batches_id mengarah ke duplicate batch
                if (!empty($dupBatchIds) && $masterBatchId) {
                    $updatedRI = ReceivingItems::whereIn('batches_id', $dupBatchIds)
                        ->whereHas('order_items', fn($q) => $q->where('medicine_id', $medId))
                        ->update(['batches_id' => $masterBatchId]);
                    if ($updatedRI > 0) {
                        $this->line("  - [receiving_items] Memperbaiki {$updatedRI} item faktur agar kembali mengarah ke master batch #{$masterBatchId}.");
                    }
                }

                // 3. Koreksi master obat (medicines.stock)
                $medModel = Medicines::find($medId);
                if ($medModel) {
                    $medModel->decrement('stock', $excessQty);
                    $this->line("  - [Medicines] Stok master {$medName} berkurang -{$excessQty} (Sisa: {$medModel->fresh()->stock}).");
                }
            }

            // 4. Hapus transfer items duplikat
            if (!empty($dupTransferItemIds)) {
                MedicineTransferItems::whereIn('id', $dupTransferItemIds)->delete();
                $this->line("  - [Etalase] Menghapus " . count($dupTransferItemIds) . " baris transfer item etalase duplikat.");
            }

            // 5. Hapus duplicate batches jika tidak ada referensi lain
            if (!empty($allDupBatchIds)) {
                $batchesToDelete = Batches::whereIn('id', $allDupBatchIds)->get();
                $deletedBatchCount = 0;
                foreach ($batchesToDelete as $b) {
                    $hasLogs = ItemsLog::where('batches_id', $b->id)->exists();
                    $hasTransfers = MedicineTransferItems::where('batches_id', $b->id)->exists();
                    $hasRI = ReceivingItems::where('batches_id', $b->id)->exists();
                    if (!$hasLogs && !$hasTransfers && !$hasRI) {
                        $b->delete();
                        $deletedBatchCount++;
                    }
                }
                $this->line("  - [Batches] Menghapus {$deletedBatchCount} batch duplikat.");
            }

            // 6. Header transfer kosong
            $deletedTransfers = MedicineTransfers::doesntHave('items')->delete();
            if ($deletedTransfers > 0) {
                $this->line("  - [MedicineTransfers] Menghapus {$deletedTransfers} header transfer kosong.");
            }

            DB::commit();
            $this->info("\n=== PERBAIKAN SELESAI SECARA PRESISI ===");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("TERJADI KESALAHAN: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }
}
