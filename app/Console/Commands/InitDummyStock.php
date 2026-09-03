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
                            {--limit=81 : Jumlah obat yang diproses} 
                            {--qty=100 : Jumlah stok yang diisi} 
                            {--pharmacy=1 : ID Apotek Pelayanan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inisialisasi batch dan stok meja counter pelayanan untuk obat baru agar bisa ditest di kasir';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $qty = (int) $this->option('qty');
        $pharmacyId = (int) $this->option('pharmacy');

        $this->info("Memulai inisialisasi stok dummy untuk {$limit} obat terbaru di Apotek ID {$pharmacyId} dengan Qty {$qty}...");

        $medicines = Medicines::latest('id')->take($limit)->get();

        if ($medicines->isEmpty()) {
            $this->warn("Tidak ada data obat yang ditemukan.");
            return 0;
        }

        $bar = $this->output->createProgressBar($medicines->count());
        $bar->start();

        $count = 0;
        foreach ($medicines as $m) {
            // 1. Buat batch untuk obat ini
            $batch = Batches::firstOrCreate(
                [
                    'medicine_id' => $m->id,
                    'pharmacy_id' => $pharmacyId,
                    'name' => 'INITIAL_INSERT',
                ],
                [
                    'expired_date' => now()->addYears(2)->toDateString(),
                    'stock' => 0,
                ]
            );

            // 2. Buat header transfer
            $transferHeader = MedicineTransfers::firstOrCreate(
                [
                    'code' => 'TRF-INIT-' . $pharmacyId,
                    'user_id' => 1,
                ],
                [
                    'status' => 1,
                ]
            );

            // 3. Buat / update stok counter pelayanan
            $transferItem = MedicineTransferItems::firstOrCreate(
                [
                    'batches_id' => $batch->id,
                    'status' => 1,
                ],
                [
                    'medicine_transfer_id' => $transferHeader->id,
                    'source_batches_id' => $batch->id,
                    'qty' => $qty,
                    'source_type' => 'pelayanan',
                    'etalases_id' => 99,
                ]
            );

            if ($transferItem->wasRecentlyCreated === false && $transferItem->qty <= 0) {
                $transferItem->update(['qty' => $qty]);
            }

            // 4. Update master medicine stock
            $m->update(['stock' => $qty]);

            $count++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Sukses! {$count} obat telah berhasil diisi stok masing-masing {$qty} pcs.");
        $this->info("✓ Seluruh obat kini sudah memiliki batch dan stok counter sehingga bisa langsung ditest di Kasir.");

        return 0;
    }
}
