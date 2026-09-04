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
                            {--qty=100 : Jumlah stok yang diisi per obat} 
                            {--pharmacy= : ID Apotek Pelayanan (contoh: 1, 2, atau "all" untuk semua cabang)}
                            {--all-pharmacies : Jalankan inisialisasi sekaligus untuk SEMUA cabang apotek}
                            {--dry-run : Simulasi tanpa menyimpan perubahan ke database}
                            {--force : Lewati konfirmasi jika di production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inisialisasi batch dan stok meja counter pelayanan untuk obat di cabang/semua apotek agar bisa ditest di kasir';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isAll = $this->option('all');
        $limitOption = $this->option('limit');
        $qty = (int) $this->option('qty');
        $pharmacyOption = $this->option('pharmacy');
        $isAllPharmacies = $this->option('all-pharmacies') || strtolower((string)$pharmacyOption) === 'all';
        $isDryRun = (bool) $this->option('dry-run');

        $totalMaster = Medicines::count();

        if ($totalMaster === 0) {
            $this->warn("Tidak ada data master obat yang ditemukan.");
            return 0;
        }

        // Tentukan daftar apotek/cabang yang diproses
        if ($isAllPharmacies) {
            // Ambil semua apotek pelayanan/cabang (exclude HO & Logistik jika ada, atau seluruh outlet)
            $targetPharmacies = \App\Models\Pharmacies::whereNotIn('id', [6, 8])->get(['id', 'name']);
            if ($targetPharmacies->isEmpty()) {
                $targetPharmacies = \App\Models\Pharmacies::all(['id', 'name']);
            }
        } else {
            $pharmacyId = !empty($pharmacyOption) ? (int)$pharmacyOption : 1;
            $pharmacy = \App\Models\Pharmacies::find($pharmacyId);
            $pharmacyName = $pharmacy ? $pharmacy->name : "Apotek ID {$pharmacyId}";
            $targetPharmacies = collect([
                (object)['id' => $pharmacyId, 'name' => $pharmacyName]
            ]);
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
        $this->line("Target Cabang    : " . ($isAllPharmacies ? "SEMUA CABANG (" . $targetPharmacies->pluck('name')->implode(', ') . ")" : $targetPharmacies->first()->name));
        $this->line("Target Obat      : {$limitLabel}");
        $this->line("Jumlah Stok      : {$qty} pcs per obat per cabang");
        $this->newLine();

        if (!$isDryRun && !$this->option('force') && \Illuminate\Support\Facades\App::environment('production')) {
            if (!$this->confirm("Apakah Anda yakin ingin mengisi stok counter pelayanan untuk {$limitLabel} di " . ($isAllPharmacies ? "SEMUA CABANG" : $targetPharmacies->first()->name) . " (PRODUCTION)?")) {
                $this->info("Operasi dibatalkan.");
                return 0;
            }
        }

        $medicines = $query->get();
        $totalOperations = $targetPharmacies->count() * $medicines->count();
        $bar = $this->output->createProgressBar($totalOperations);
        $bar->start();

        foreach ($targetPharmacies as $pharmacy) {
            $pharmacyId = $pharmacy->id;

            // Siapkan header transfer untuk cabang ini
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

            if (!$isDryRun) {
                \Illuminate\Support\Facades\DB::beginTransaction();
            }

            try {
                foreach ($medicines as $m) {
                    if (!$isDryRun) {
                        // 1. Buat / cari batch untuk obat ini di apotek pelayanan/cabang
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
                        }

                        // 3. Update master medicine stock jika perlu
                        $m->update(['stock' => \Illuminate\Support\Facades\DB::raw("GREATEST(COALESCE(stock, 0), {$qty})")]);
                    }

                    $bar->advance();
                }

                if (!$isDryRun) {
                    \Illuminate\Support\Facades\DB::commit();
                }
            } catch (\Exception $e) {
                if (!$isDryRun) {
                    \Illuminate\Support\Facades\DB::rollBack();
                }
                $this->error("\nError saat memproses stok untuk {$pharmacy->name}: " . $e->getMessage());
                return 1;
            }
        }

        $bar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("✓ SIMULASI SELESAI: {$medicines->count()} obat siap diinisialisasi untuk " . $targetPharmacies->count() . " cabang.");
            $this->line("Jalankan tanpa '--dry-run' untuk menerapkan: php artisan stock:init-dummy --all --all-pharmacies --qty={$qty}");
        } else {
            $this->info("✓ Sukses! {$medicines->count()} obat telah berhasil diisi stok counter masing-masing {$qty} pcs untuk seluruh cabang:");
            foreach ($targetPharmacies as $p) {
                $this->line("  ✓ {$p->name} (ID: {$p->id})");
            }
            $this->info("✓ Seluruh cabang kini sudah memiliki stok etalase/counter dan siap ditransaksikan di Kasir masing-masing cabang.");
        }

        return 0;
    }
}
