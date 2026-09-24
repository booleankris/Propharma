<?php

namespace App\Console\Commands;

use App\Models\Batches;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\Pharmacies;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileDuplicateOpnameStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:reconcile-opname {--pharmacy_id= : ID Cabang Farmasi (opsional)} {--dry-run : Simulasi tanpa menyimpan perubahan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rekonsiliasi stok obat yang terduplikasi akibat akumulasi batch impor Stock Opname lama';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pharmacyId = $this->option('pharmacy_id');
        $isDryRun = $this->option('dry-run');

        $this->info("=== MEMULAI REKONSILIASI AKUMULASI STOCK OPNAME ===");
        if ($isDryRun) {
            $this->warn("[DRY-RUN MODE] Tidak ada perubahan database yang akan disimpan.");
        }

        $pharmaciesQuery = Pharmacies::query();
        if ($pharmacyId) {
            $pharmaciesQuery->where('id', $pharmacyId);
        }
        $pharmacies = $pharmaciesQuery->get();

        $warehouseId = getWarehousePharmacyId();
        $totalFixed = 0;

        foreach ($pharmacies as $ph) {
            $this->line("\nMemeriksa Cabang: {$ph->name} (ID: {$ph->id})...");
            $isWarehouse = ($ph->id == $warehouseId);

            if ($isWarehouse) {
                // Gudang: cek Batches
                $duplicateMeds = Batches::where('pharmacy_id', $ph->id)
                    ->where('stock', '>', 0)
                    ->where('name', 'like', 'OPN-%')
                    ->select('medicine_id', DB::raw('count(*) as opn_count'))
                    ->groupBy('medicine_id')
                    ->having('opn_count', '>', 1)
                    ->pluck('medicine_id');

                foreach ($duplicateMeds as $medId) {
                    $med = Medicines::find($medId);
                    $batches = Batches::where('medicine_id', $medId)
                        ->where('pharmacy_id', $ph->id)
                        ->where('stock', '>', 0)
                        ->orderBy('id', 'desc')
                        ->get();

                    if ($batches->count() <= 1) continue;

                    $latest = $batches->first();
                    $superseded = $batches->slice(1);

                    $this->info("Obat: {$med->name} (ID: {$medId})");
                    $this->line(" - Batch terkini: ID {$latest->id} ({$latest->name}) Stok: {$latest->stock}");

                    foreach ($superseded as $old) {
                        $this->warn(" - Mereset batch lama: ID {$old->id} ({$old->name}) dari {$old->stock} menjadi 0");
                        if (!$isDryRun) {
                            $old->update(['stock' => 0]);
                        }
                    }
                    $totalFixed++;
                }
            } else {
                // Pelayanan: cek MedicineTransferItems
                $activeTransfers = MedicineTransferItems::where('medicine_transfer_items.status', 1)
                    ->where('medicine_transfer_items.qty', '>', 0)
                    ->where(function ($q) {
                        $q->whereNull('medicine_transfer_items.source_type')
                          ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                    })
                    ->join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                    ->where('batches.pharmacy_id', $ph->id)
                    ->where('batches.name', 'like', 'OPN-%')
                    ->select('batches.medicine_id', DB::raw('count(medicine_transfer_items.id) as item_count'))
                    ->groupBy('batches.medicine_id')
                    ->having('item_count', '>', 1)
                    ->pluck('batches.medicine_id');

                foreach ($activeTransfers as $medId) {
                    $med = Medicines::find($medId);

                    $transfers = MedicineTransferItems::where('medicine_transfer_items.status', 1)
                        ->where('medicine_transfer_items.qty', '>', 0)
                        ->where(function ($q) {
                            $q->whereNull('medicine_transfer_items.source_type')
                              ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                        })
                        ->join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                        ->where('batches.medicine_id', $medId)
                        ->where('batches.pharmacy_id', $ph->id)
                        ->orderBy('medicine_transfer_items.id', 'desc')
                        ->select('medicine_transfer_items.*', 'batches.name as batch_name', 'batches.expired_date as batch_ed')
                        ->get();

                    if ($transfers->count() <= 1) continue;

                    $latest = $transfers->first();
                    $superseded = $transfers->slice(1);

                    $this->info("Obat: {$med->name} (ID: {$medId})");
                    $this->line(" - Transfer terkini: ID {$latest->id} (Batch: {$latest->batch_name}, ED: {$latest->batch_ed}) Qty: {$latest->qty}");

                    foreach ($superseded as $old) {
                        $this->warn(" - Mereset transfer akumulasi lama: ID {$old->id} (Batch: {$old->batch_name}, ED: {$old->batch_ed}) dari {$old->qty} menjadi 0");
                        if (!$isDryRun) {
                            MedicineTransferItems::where('id', $old->id)->update(['qty' => 0]);
                        }
                    }

                    if (!$isDryRun) {
                        // Sinkronkan stok total master obat
                        $totalReal = Batches::where('medicine_id', $medId)->sum('stock') +
                            MedicineTransferItems::whereHas('batches', fn($b) => $b->where('medicine_id', $medId))
                                ->where('status', 1)
                                ->where(function ($q) {
                                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                                })
                                ->sum('qty');
                        $med->update(['stock' => $totalReal]);
                    }

                    $totalFixed++;
                }
            }
        }

        $this->info("\n=== SELESAI: Berhasil merekonsiliasi {$totalFixed} obat yang terduplikasi ===");
        return 0;
    }
}
