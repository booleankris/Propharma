<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Medicines;
use App\Models\Batches;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;

class InitDummyStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:init-dummy 
                            {--all : Proses seluruh master obat yang ada}
                            {--limit= : Jumlah obat yang diproses (default: 100 jika tanpa --all)} 
                            {--qty=100 : Jumlah stok yang diisi} 
                            {--pharmacy=1 : ID Apotek Pelayanan}
                            {--dry-run : Simulasi tanpa menyimpan perubahan ke database}
                            {--force : Lewati konfirmasi jika di production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inisialisasi batch dan stok meja counter pelayanan untuk obat agar bisa ditest di kasir';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isAll = $this->option('all');
        $limitOption = $this->option('limit');
        $qty = (int) $this->option('qty');
        $pharmacyId = (int) $this->option('pharmacy');
        $isDryRun = (bool) $this->option('dry-run');

        $totalMaster = Medicines::count();

        if ($totalMaster === 0) {
            $this->warn("Tidak ada data master obat yang ditemukan.");
            return 0;
        }

        if ($isAll || $limitOption === 'all' || $limitOption === '0') {
            $limit = $totalMaster;
            $limitLabel = "SEMUA ({$totalMaster})";
            $query = Medicines::orderBy('id', 'asc');
        } else {
            $limit = !empty($limitOption) ? (int) $limitOption : 100;
            $limit = min($limit, $totalMaster);
            $limitLabel = "{$limit} obat terbaru";
            $query = Medicines::latest('id')->take($limit);
        }

        $this->info("=== INISIALISASI STOK COUNTER PELAYANAN KASIR ===");
        if ($isDryRun) {
            $this->warn("🔍 MODE DRY-RUN AKTIF: Simulasi saja, tidak ada data yang disimpan.");
        }
        $this->line("Target Pharmacy  : Apotek Pelayanan (ID: {$pharmacyId})");
        $this->line("Target Obat      : {$limitLabel}");
        $this->line("Jumlah Stok      : {$qty} pcs per obat");
        $this->newLine();

        if (!$isDryRun && !$this->option('force') && \Illuminate\Support\Facades\App::environment('production')) {
            if (!$this->confirm("Apakah Anda yakin ingin mengisi stok counter pelayanan untuk {$limitLabel} di PRODUCTION?")) {
                $this->info("Operasi dibatalkan.");
                return 0;
            }
        }

        // Siapkan header transfer
        $transferHeaderId = 1;
        if (!$isDryRun) {
            $transferHeader = MedicineTransfers::firstOrCreate(
                [
                    'code' => 'TRF-INIT-' . $pharmacyId,
                    'user_id' => 1,
                ],
                [
                    'status' => 1,
                ]
            );
            $transferHeaderId = $transferHeader->id;
        }

        $bar = $this->output->createProgressBar($limit);
        $bar->start();

        $processedCount = 0;
        $batchesCreated = 0;
        $batchesUpdated = 0;

        $medicines = $query->get();

        if (!$isDryRun) {
            \Illuminate\Support\Facades\DB::beginTransaction();
        }

        try {
            foreach ($medicines as $m) {
                if (!$isDryRun) {
                    // 1. Buat / cari batch untuk obat ini di apotek pelayanan
                    $batch = Batches::firstOrCreate(
                        [
                            'medicine_id' => $m->id,
                            'pharmacy_id' => $pharmacyId,
                            'name' => 'INITIAL_INSERT',
                        ],
                        [
                            'expired_date' => now()->addYears(2)->toDateString(),
                            'stock' => 0,
                            'status' => 1,
                        ]
                    );

                    // 2. Buat / update stok counter pelayanan di medicine_transfer_items
                    $transferItem = MedicineTransferItems::firstOrCreate(
                        [
                            'batches_id' => $batch->id,
                            'status' => 1,
                        ],
                        [
                            'medicine_transfer_id' => $transferHeaderId,
                            'source_batches_id' => $batch->id,
                            'qty' => $qty,
                            'source_type' => 'pelayanan',
                            'etalases_id' => 99,
                        ]
                    );

                    if ($transferItem->wasRecentlyCreated === false && $transferItem->qty <= 0) {
                        $transferItem->update(['qty' => $qty]);
                        $batchesUpdated++;
                    } else {
                        $batchesCreated++;
                    }

                    // 3. Update master medicine stock
                    $m->update(['stock' => \Illuminate\Support\Facades\DB::raw("GREATEST(COALESCE(stock, 0), {$qty})")]);
                }

                $processedCount++;
                $bar->advance();
            }

            if (!$isDryRun) {
                \Illuminate\Support\Facades\DB::commit();
            }
        } catch (\Exception $e) {
            if (!$isDryRun) {
                \Illuminate\Support\Facades\DB::rollBack();
            }
            $this->error("\nError saat memproses stok: " . $e->getMessage());
            return 1;
        }

        $bar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("✓ SIMULASI SELESAI: {$processedCount} obat siap diinisialisasi.");
            $this->line("Jalankan tanpa '--dry-run' untuk menerapkan: php artisan stock:init-dummy --all --qty={$qty}");
        } else {
            $this->info("✓ Sukses! {$processedCount} obat telah berhasil diisi stok counter pelayanan masing-masing {$qty} pcs.");
            $this->info("✓ Seluruh obat kini sudah memiliki batch dan stok counter (etalase) sehingga bisa langsung ditransaksikan di Kasir.");
        }

        return 0;
    }
}
