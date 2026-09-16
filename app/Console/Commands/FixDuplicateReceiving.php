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

        if (!$code && !$medSearch) {
            $code = 'NT-26-09/0120';
        }

        $this->info("================================================================================");
        $this->info("   PROPHARMA - PEMULIHAN PRESISI KARTU STOCK & STOK ETALASE");
        $this->info("================================================================================");
        if ($medSearch) {
            $this->line("Target Obat   : [{$medSearch}]");
        } else {
            $this->line("Target Faktur : [{$code}]");
        }
        $this->line("Mode          : " . ($isDryRun ? "DRY-RUN (Simulasi)" : "EKSEKUSI NYATA (Live Database)"));
        $this->newLine();

        $rd = null;
        $recCode = null;

        if (!$medSearch) {
            $rd = ReceivingDetails::with(['receiving', 'creditor'])
                ->where('receiving_details_code', $code)
                ->orWhere('invoice_number', $code)
                ->first();

            if (!$rd) {
                $this->error("Faktur {$code} tidak ditemukan di tabel receiving_details.");
                return 1;
            }

            $recCode = $rd->receiving->code ?? null;
            $this->line("Faktur Ditemukan: ID {$rd->id} | No Terima: {$rd->receiving_details_code} | Faktur: {$rd->invoice_number} | Trans Rec: " . ($recCode ?: '-'));
        }

        $logQuery = ItemsLog::where('status', 2)->with(['medicines', 'batches'])->orderBy('id', 'asc');

        if ($medSearch) {
            $logQuery->whereHas('medicines', function ($q) use ($medSearch) {
                $q->where('name', 'like', "%{$medSearch}%");
            });
        } else {
            $logQuery->where(function ($q) use ($rd, $recCode) {
                $q->whereHas('receiving.receiving_details', function ($rq) use ($rd) {
                    $rq->where('id', $rd->id)
                       ->orWhere('receiving_details_code', $rd->receiving_details_code)
                       ->orWhere('invoice_number', $rd->invoice_number);
                });
                if ($recCode) {
                    $q->orWhere('transaction_code', $recCode);
                }
                $q->orWhere('transaction_code', $rd->receiving_details_code)
                  ->orWhere('transaction_code', $rd->invoice_number);
            });
        }

        $allLogs = $logQuery->get();
        $this->info("Total log pembelian (status 2) ditemukan: " . $allLogs->count() . " baris.");

        $logsByMedicine = $allLogs->groupBy('medicine_id');
        $dupesToFix = [];

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

            $this->warn("  [DUPLIKAT] {$medName} (ID: {$medId}) -> Ditemukan {$count} baris log!");
            $this->line("      - Master : ID #{$masterLog->id} (Qty: +{$masterLog->qty})");
            foreach ($duplicateLogs as $dupLog) {
                $this->line("      - Hapus  : ID #{$dupLog->id} (Qty: +{$dupLog->qty}, Kode: {$dupLog->code})");
            }

            $dupesToFix[$medId] = [
                'medicine_id' => $medId,
                'medicine_name' => $medName,
                'master_log' => $masterLog,
                'duplicate_logs' => $duplicateLogs,
                'excess_qty' => $excessQty,
                'batches_id' => $masterLog->batches_id,
            ];
        }

        $duplicateRI = collect();
        if ($rd) {
            $receivingItems = ReceivingItems::where('receiving_details_id', $rd->id)->orderBy('id', 'asc')->get();
            $riGrouped = $receivingItems->groupBy(fn($i) => ($i->order_items->medicine_id ?? 0) . '_' . ($i->batch ?? '0'));
            foreach ($riGrouped as $group) {
                if ($group->count() > 1) {
                    $duplicateRI = $duplicateRI->merge($group->slice(1));
                }
            }
        }

        if (empty($dupesToFix) && $duplicateRI->isEmpty()) {
            $this->info("Tidak ditemukan duplikasi di Kartu Stock maupun Faktur.");
            return 0;
        }

        if ($isDryRun) {
            $this->warn("MODE DRY-RUN: Database tidak diubah.");
            return 0;
        }

        $this->info("Mengeksekusi perbaikan database...");
        DB::beginTransaction();

        try {
            foreach ($dupesToFix as $medId => $item) {
                $medName = $item['medicine_name'];
                $excessQty = $item['excess_qty'];

                // 1. Hapus duplicate items_log
                foreach ($item['duplicate_logs'] as $dupLog) {
                    $dupLog->delete();
                }
                $this->line("  - [items_log] Menghapus {$item['duplicate_logs']->count()} baris log duplikat {$medName}.");

                // 2. Koreksi stok etalase
                $transferItems = MedicineTransferItems::whereHas('batches', function ($b) use ($medId) {
                        $b->where('medicine_id', $medId);
                    })
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                    })
                    ->orderBy('id', 'desc')
                    ->get();

                $qtyToDeduct = $excessQty;
                foreach ($transferItems as $ti) {
                    if ($qtyToDeduct <= 0) break;
                    if ($ti->qty <= $qtyToDeduct) {
                        $qtyToDeduct -= $ti->qty;
                        $ti->delete();
                    } else {
                        $ti->decrement('qty', $qtyToDeduct);
                        $qtyToDeduct = 0;
                    }
                }
                $this->line("  - [Etalase] Mengurangi {$excessQty} dari stok etalase {$medName}.");

                // 3. Koreksi master obat
                $medModel = Medicines::find($medId);
                if ($medModel) {
                    $medModel->decrement('stock', $excessQty);
                    $this->line("  - [Medicines] Stok master {$medName} berkurang -{$excessQty}.");
                }

                // 4. Koreksi batch jika ada
                if ($item['batches_id']) {
                    $batchModel = Batches::find($item['batches_id']);
                    if ($batchModel && $batchModel->stock >= $excessQty) {
                        $batchModel->decrement('stock', $excessQty);
                        $this->line("  - [Batches] Stok batch berkurang -{$excessQty}.");
                    }
                }
            }

            // 5. Hapus receiving_items duplikat jika masih ada
            if ($duplicateRI->isNotEmpty()) {
                foreach ($duplicateRI as $ri) {
                    $ri->delete();
                }
                $this->line("  - [receiving_items] Menghapus {$duplicateRI->count()} baris item faktur duplikat.");
            }

            // 6. Header transfer kosong
            MedicineTransfers::doesntHave('items')->delete();

            DB::commit();
            $this->info("=== PERBAIKAN SELESAI SECARA PRESISI ===");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("TERJADI KESALAHAN: " . $e->getMessage());
            return 1;
        }
    }
}
