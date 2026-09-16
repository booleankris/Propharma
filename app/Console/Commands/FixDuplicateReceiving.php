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
                            {--all : Scan dan perbaiki seluruh faktur di database}
                            {--dry-run : Simulasi saja tanpa mengubah data di database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa dan membersihkan item penerimaan yang terduplikasi di faktur, mengembalikan stok obat/etalase, dan menghapus log duplikat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $code = trim((string) $this->argument('code'));
        $isDryRun = (bool) $this->option('dry-run');
        $scanAll = (bool) $this->option('all');

        if (!$code && !$scanAll) {
            $code = 'NT-26-09/0120';
        }

        $this->info("================================================================================");
        $this->info("       PROPHARMA - AUDIT & PEMULIHAN DUPLIKASI PENERIMAAN FAKTUR");
        $this->info("================================================================================");
        $this->line("Mode    : " . ($isDryRun ? "DRY-RUN (Simulasi)" : "LIVE EXECUTION (Database Update)"));
        $this->line("Cakupan : " . ($scanAll ? "SELURUH DATABASE" : "Faktur: [{$code}]"));
        $this->newLine();

        $detailsList = collect();

        if ($scanAll) {
            $this->info("[1/4] Memindai seluruh faktur di database...");
            $detailIdsWithDupes = ReceivingItems::select('receiving_details_id')
                ->groupBy('receiving_details_id', 'order_items_id', 'batch')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('receiving_details_id')
                ->unique();

            if ($detailIdsWithDupes->isEmpty()) {
                $this->info("[HASIL SCAN] Bersih! Tidak ditemukan duplikasi receiving_items di seluruh database.");
                return 0;
            }

            $detailsList = ReceivingDetails::with(['receiving', 'creditor'])
                ->whereIn('id', $detailIdsWithDupes)
                ->get();

            $this->warn("Ditemukan " . $detailsList->count() . " faktur yang memiliki item duplikat!");
        } else {
            $this->info("[1/4] Mencari faktur [{$code}]...");
            $rd = ReceivingDetails::with(['receiving', 'creditor'])
                ->where('receiving_details_code', $code)
                ->orWhere('invoice_number', $code)
                ->first();

            if (!$rd) {
                $this->error("Faktur '{$code}' tidak ditemukan di database.");
                return 1;
            }

            $detailsList->push($rd);
        }

        $this->info("[2/4] Melakukan audit relasi data...");
        $allAuditResults = [];
        $totalDuplicateRows = 0;
        $totalQtyRollback = 0;

        foreach ($detailsList as $rd) {
            $items = ReceivingItems::where('receiving_details_id', $rd->id)
                ->with(['order_items.medicines', 'batches'])
                ->orderBy('id', 'asc')
                ->get();

            $grouped = $items->groupBy(function ($it) {
                $medId = $it->order_items->medicine_id ?? 0;
                $batch = trim((string)($it->batch ?? '0'));
                return "{$medId}_{$batch}";
            });

            $fakturDupes = [];

            foreach ($grouped as $groupKey => $groupItems) {
                $count = $groupItems->count();
                $master = $groupItems->first();
                $med = $master->order_items->medicines ?? null;
                $medName = $med->name ?? ("Obat ID #" . ($master->order_items->medicine_id ?? '?'));
                $batchName = trim((string)($master->batch ?? '0'));

                if ($count <= 1) {
                    $this->line("  [OK] {$medName} (Batch: {$batchName}) - {$count} baris.");
                    continue;
                }

                $duplicates = $groupItems->slice(1);
                $this->warn("  [DUPLIKAT] {$medName} (Batch: {$batchName}) - Total {$count} baris! (1 Asli, {$duplicates->count()} Duplikat)");

                foreach ($duplicates as $dup) {
                    $totalDuplicateRows++;
                    $isPack = ($dup->order_items->pack == 1);
                    $content = $isPack ? (int) ($med->content ?? 1) : 1;
                    if ($content < 1) $content = 1;
                    $actualQty = (float) $dup->qty_received * $content;
                    $totalQtyRollback += $actualQty;

                    $transferItems = MedicineTransferItems::where('receiving_items_id', $dup->id)->get();

                    $candidateCodes = array_filter([
                        $rd->receiving->code ?? null,
                        $rd->receiving_details_code,
                        $rd->invoice_number,
                    ]);

                    $logQuery = ItemsLog::where('status', 2)
                        ->where('medicine_id', $med->id ?? 0)
                        ->where('qty', $actualQty);
                    if ($dup->batches_id) {
                        $logQuery->where('batches_id', $dup->batches_id);
                    }
                    if (!empty($candidateCodes)) {
                        $logQuery->whereIn('transaction_code', $candidateCodes);
                    }
                    $targetLog = $logQuery->orderByDesc('id')->first();

                    if (!$targetLog) {
                        $targetLog = ItemsLog::where('status', 2)
                            ->where('medicine_id', $med->id ?? 0)
                            ->where('qty', $actualQty)
                            ->orderByDesc('id')
                            ->first();
                    }

                    $fakturDupes[] = [
                        'dup' => $dup,
                        'master' => $master,
                        'medicine' => $med,
                        'actual_qty' => $actualQty,
                        'transfer_items' => $transferItems,
                        'target_log' => $targetLog,
                        'batch_id' => $dup->batches_id,
                    ];
                }
            }

            if (!empty($fakturDupes)) {
                $allAuditResults[] = [
                    'rd' => $rd,
                    'dupes' => $fakturDupes,
                ];
            }
        }

        $this->newLine();
        $this->info("[3/4] RINGKASAN AUDIT");
        $this->line("Total Faktur Bermasalah : " . count($allAuditResults));
        $this->line("Total Baris Duplikat    : {$totalDuplicateRows} baris");
        $this->line("Total Kelebihan Qty     : {$totalQtyRollback} satuan");

        if (empty($allAuditResults)) {
            $this->info("Semua data bersih dan tidak ada duplikasi.");
            return 0;
        }

        $tableData = [];
        foreach ($allAuditResults as $res) {
            $rdCode = $res['rd']->receiving_details_code ?: $res['rd']->invoice_number;
            foreach ($res['dupes'] as $d) {
                $tableData[] = [
                    'RI ID' => $d['dup']->id,
                    'Faktur' => $rdCode,
                    'Obat' => substr($d['medicine']->name ?? 'Obat', 0, 25),
                    'Batch' => $d['dup']->batch ?? '0',
                    'Rollback Qty' => "-{$d['actual_qty']}",
                    'Mutasi Etalase' => $d['transfer_items']->count() . " baris",
                    'ItemsLog' => $d['target_log'] ? "ID #{$d['target_log']->id}" : "-",
                ];
            }
        }
        $this->table(['RI ID', 'Faktur', 'Obat', 'Batch', 'Rollback Qty', 'Mutasi Etalase', 'ItemsLog'], $tableData);

        if ($isDryRun) {
            $this->warn("MODE DRY-RUN: Tidak ada data yang diubah di database.");
            return 0;
        }

        $this->info("[4/4] MENGEKSEKUSI PERBAIKAN DATABASE...");
        DB::beginTransaction();

        try {
            foreach ($allAuditResults as $res) {
                $rd = $res['rd'];
                $this->line("\nMemproses Faktur {$rd->receiving_details_code}...");

                foreach ($res['dupes'] as $d) {
                    $dup = $d['dup'];
                    $med = $d['medicine'];
                    $qty = $d['actual_qty'];
                    $medId = $med->id ?? null;

                    // 1. Medicines
                    if ($medId) {
                        $m = Medicines::find($medId);
                        if ($m) {
                            $m->decrement('stock', $qty);
                            $this->line("  - [Medicines] {$m->name}: Stok berkurang (-{$qty})");
                        }
                    }

                    // 2. Batches
                    if ($d['batch_id']) {
                        $b = Batches::find($d['batch_id']);
                        if ($b) {
                            $b->decrement('stock', $qty);
                            $this->line("  - [Batches #{$b->id}] Stok batch berkurang (-{$qty})");
                        }
                    }

                    // 3. Etalase
                    $transferHeaderIds = $d['transfer_items']->pluck('medicine_transfer_id')->filter()->unique();
                    $deletedTransfers = MedicineTransferItems::where('receiving_items_id', $dup->id)->delete();
                    $this->line("  - [Etalase] Menghapus {$deletedTransfers} mutasi transfer item.");

                    foreach ($transferHeaderIds as $thId) {
                        if (MedicineTransferItems::where('medicine_transfer_id', $thId)->count() === 0) {
                            MedicineTransfers::where('id', $thId)->delete();
                            $this->line("  - [Transfer Header] Menghapus header transfer kosong #{$thId}.");
                        }
                    }

                    // 4. ItemsLog
                    if ($d['target_log']) {
                        $d['target_log']->delete();
                        $this->line("  - [ItemsLog] Menghapus log mutasi kartu stok #{$d['target_log']->id}.");
                    }

                    // 5. ReceivingItems
                    $dup->delete();
                    $this->line("  - [ReceivingItems #{$dup->id}] Berhasil dihapus.");
                }
            }

            DB::commit();
            $this->newLine();
            $this->info("=== PERBAIKAN BERHASIL SELESAI SECARA UTUH & PRESISI ===");
            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("TERJADI KESALAHAN: " . $e->getMessage());
            return 1;
        }
    }
}
