<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Medicines;
use App\Models\Batches;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class InitGudangStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:init-gudang 
                            {--qty=100 : Jumlah stok per obat di Gudang PMI} 
                            {--batch=INITIAL_INSERT : Nama batch} 
                            {--mode=set : Mode stok: "set" untuk setel menjadi jumlah ini, atau "add" untuk menambah} 
                            {--dry-run : Simulasi tanpa menyimpan perubahan ke database}
                            {--force : Lewati konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menambahkan/mengatur stok Gudang PMI (pharmacy_id = 9) untuk semua obat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $qty = (int) $this->option('qty');
        $batchName = (string) $this->option('batch');
        $mode = strtolower((string) $this->option('mode'));
        $isDryRun = (bool) $this->option('dry-run');
        $warehousePharmacyId = 9; // Gudang PMI

        $totalMedicines = Medicines::count();

        if ($totalMedicines === 0) {
            $this->warn("Tidak ada data master obat yang ditemukan.");
            return 0;
        }

        $this->info("=== INISIALISASI STOK GUDANG PMI ===");
        if ($isDryRun) {
            $this->warn("🔍 MODE DRY-RUN AKTIF: Simulasi saja, tidak ada data yang diubah di database.");
        }
        $this->line("Target Pharmacy  : Gudang PMI (ID: {$warehousePharmacyId})");
        $this->line("Total Master Obat: {$totalMedicines}");
        $this->line("Jumlah Stok      : {$qty} pcs");
        $this->line("Nama Batch       : {$batchName}");
        $this->line("Mode             : " . ($mode === 'add' ? 'Menambah (+)' : 'Setel Langsung (=)'));
        $this->newLine();

        if (!$isDryRun && !$this->option('force') && App::environment('production')) {
            if (!$this->confirm("Apakah Anda yakin ingin memproses stok Gudang PMI untuk semua ({$totalMedicines}) obat di PRODUCTION?")) {
                $this->info("Operasi dibatalkan.");
                return 0;
            }
        }

        $bar = $this->output->createProgressBar($totalMedicines);
        $bar->start();

        $batchesCreated = 0;
        $batchesUpdated = 0;
        $samplePreviews = [];

        Medicines::chunk(500, function ($medicines) use ($warehousePharmacyId, $batchName, $qty, $mode, $isDryRun, $bar, &$batchesCreated, &$batchesUpdated, &$samplePreviews) {
            if (!$isDryRun) {
                DB::beginTransaction();
            }

            try {
                foreach ($medicines as $m) {
                    $batch = Batches::where('medicine_id', $m->id)
                        ->where('pharmacy_id', $warehousePharmacyId)
                        ->where('name', $batchName)
                        ->first();

                    if (!$batch) {
                        // Coba cari batch gudang manapun jika INITIAL_INSERT belum ada
                        $batch = Batches::where('medicine_id', $m->id)
                            ->where('pharmacy_id', $warehousePharmacyId)
                            ->first();
                    }

                    if ($batch) {
                        $oldStock = (int) $batch->stock;
                        $newStock = ($mode === 'add') ? ($oldStock + $qty) : $qty;
                        
                        if (!$isDryRun) {
                            $batch->update([
                                'stock' => $newStock,
                                'status' => 1,
                            ]);
                        }
                        $batchesUpdated++;
                    } else {
                        $oldStock = 0;
                        $newStock = $qty;
                        
                        if (!$isDryRun) {
                            Batches::create([
                                'medicine_id' => $m->id,
                                'pharmacy_id' => $warehousePharmacyId,
                                'name' => $batchName,
                                'expired_date' => now()->addYears(2)->toDateString(),
                                'status' => 1,
                                'stock' => $newStock,
                            ]);
                        }
                        $batchesCreated++;
                    }

                    if (!$isDryRun) {
                        // Update master medicine stock agar sinkron dengan total fisik
                        $m->update(['stock' => DB::raw("GREATEST(COALESCE(stock, 0), {$newStock})")]);
                    }

                    if (count($samplePreviews) < 5) {
                        $samplePreviews[] = [
                            'code' => $m->code,
                            'name' => $m->name,
                            'old_stock' => $oldStock,
                            'new_stock' => $newStock,
                            'status' => $batch ? 'Update Batch' : 'Create Batch',
                        ];
                    }

                    $bar->advance();
                }

                if (!$isDryRun) {
                    DB::commit();
                }
            } catch (\Exception $e) {
                if (!$isDryRun) {
                    DB::rollBack();
                }
                $this->error("\nError saat memproses chunk: " . $e->getMessage());
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("✓ SIMULASI (DRY-RUN) SELESAI — Database TIDAK mengalami perubahan:");
        } else {
            $this->info("✓ Sukses memproses stok Gudang PMI:");
        }
        $this->line("  - Batch baru yang akan dibuat  : {$batchesCreated}");
        $this->line("  - Batch yang akan diperbarui    : {$batchesUpdated}");
        $this->line("  - Total master obat diproses    : {$totalMedicines}");

        $this->newLine();
        $this->info("Contoh 5 Obat Pertama Hasil Simulasi:");
        $this->table(
            ['Kode Obat', 'Nama Obat', 'Stok Gudang Awal', 'Stok Gudang Baru', 'Aksi'],
            $samplePreviews
        );

        if ($isDryRun) {
            $this->warn("Jika hasil simulasi di atas sudah sesuai, jalankan tanpa '--dry-run' untuk menerapkan perubahan:");
            $this->line("php artisan stock:init-gudang" . ($mode === 'add' ? ' --mode=add' : '') . " --qty={$qty}");
        } else {
            $this->info("Seluruh obat di Gudang PMI sekarang memiliki stok {$qty} pcs.");
        }

        return 0;
    }
}
