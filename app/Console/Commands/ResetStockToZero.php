<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pharmacies;
use App\Models\Batches;
use App\Models\Medicines;
use App\Models\MedicineTransferItems;
use App\Models\ItemsLog;
use App\Models\StockOpname;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class ResetStockToZero extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:reset-zero
                            {--pharmacy= : ID Cabang / Apotek yang akan di-reset (contoh: 1 untuk SAHABAT PMI, 9 untuk GUDANG PMI)}
                            {--all-pharmacies : Reset stok untuk SEMUA cabang apotek dan gudang}
                            {--clear-logs : Bersihkan juga riwayat ItemsLog dan StockOpname untuk cabang terkait}
                            {--dry-run : Simulasi saja tanpa mengubah data di database}
                            {--force : Lewati konfirmasi interaktif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mereset stok fisik (Gudang & Etalase) menjadi 0 per cabang atau untuk semua cabang untuk persiapan Go-Live Stock Opname';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pharmacyOption = $this->option('pharmacy');
        $isAllPharmacies = (bool) $this->option('all-pharmacies');
        $isDryRun = (bool) $this->option('dry-run');
        $clearLogs = (bool) $this->option('clear-logs');
        $force = (bool) $this->option('force');

        // Validasi parameter
        if (!$isAllPharmacies && empty($pharmacyOption)) {
            $this->error("Tentukan cabang yang ingin di-reset menggunakan opsi --pharmacy=[ID] atau --all-pharmacies.");
            $this->newLine();
            $this->line("Daftar Cabang yang Tersedia:");
            $list = Pharmacies::all(['id', 'name']);
            foreach ($list as $p) {
                $this->line("  [ID: {$p->id}] {$p->name}");
            }
            $this->newLine();
            $this->info("Contoh: php artisan stock:reset-zero --pharmacy=1");
            return 1;
        }

        // Tentukan daftar cabang target
        if ($isAllPharmacies) {
            $targetPharmacies = Pharmacies::all();
        } else {
            $pharmacy = Pharmacies::find((int) $pharmacyOption);
            if (!$pharmacy) {
                $this->error("Cabang dengan ID {$pharmacyOption} tidak ditemukan!");
                return 1;
            }
            $targetPharmacies = collect([$pharmacy]);
        }

        $this->info("===============================================================");
        $this->info("           RESET STOK PERSIAPAN GO-LIVE (PROPHARMA)           ");
        $this->info("===============================================================");
        if ($isDryRun) {
            $this->warn("MODE DRY-RUN AKTIF: Simulasi saja, tidak ada data yang disimpan.");
        }
        $this->line("Target Cabang : " . $targetPharmacies->pluck('name')->implode(', '));
        $this->line("Hapus Log SO  : " . ($clearLogs ? "YA (ItemsLog & StockOpname dibersihkan)" : "TIDAK (Hanya stok diset 0)"));
        $this->newLine();

        // Konfirmasi jika bukan dry-run
        if (!$isDryRun && !$force) {
            $msg = $isAllPharmacies
                ? "PERINGATAN: Anda akan me-reset stok SEMUA CABANG menjadi 0! Lanjutkan?"
                : "PERINGATAN: Anda akan me-reset stok untuk cabang '" . $targetPharmacies->first()->name . "' menjadi 0! Lanjutkan?";

            if (!$this->confirm($msg, false)) {
                $this->info("Operasi dibatalkan.");
                return 0;
            }
        }

        $summary = [];

        foreach ($targetPharmacies as $pharmacy) {
            $pharmacyId = $pharmacy->id;

            // 1. Hitung / Ambil batch gudang & counter
            $batchesWithStock = Batches::where('pharmacy_id', $pharmacyId)
                ->where('stock', '!=', 0);
            $countBatches = $batchesWithStock->count();

            // 2. Hitung / Ambil etalase counter (medicine_transfer_items)
            $transfersWithStock = MedicineTransferItems::whereHas('batches', function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId);
            })
            ->where('qty', '!=', 0);
            $countTransfers = $transfersWithStock->count();

            $countLogs = 0;
            $countOpnames = 0;

            if ($clearLogs) {
                $countLogs = ItemsLog::whereHas('batches', fn($q) => $q->where('pharmacy_id', $pharmacyId))->count();
                $countOpnames = StockOpname::whereHas('batches', fn($q) => $q->where('pharmacy_id', $pharmacyId))->count();
            }

            if (!$isDryRun) {
                DB::beginTransaction();
                try {
                    // Set batch stock = 0
                    Batches::where('pharmacy_id', $pharmacyId)
                        ->where('stock', '!=', 0)
                        ->update(['stock' => 0]);

                    // Set transfer items qty = 0
                    MedicineTransferItems::whereHas('batches', function ($q) use ($pharmacyId) {
                        $q->where('pharmacy_id', $pharmacyId);
                    })
                    ->where('qty', '!=', 0)
                    ->update(['qty' => 0]);

                    if ($clearLogs) {
                        ItemsLog::whereHas('batches', fn($q) => $q->where('pharmacy_id', $pharmacyId))->delete();
                        StockOpname::whereHas('batches', fn($q) => $q->where('pharmacy_id', $pharmacyId))->delete();
                    }

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error("Gagal memproses cabang {$pharmacy->name}: " . $e->getMessage());
                    continue;
                }
            }

            $summary[] = [
                'id' => $pharmacyId,
                'name' => $pharmacy->name,
                'batches_reset' => $countBatches,
                'transfers_reset' => $countTransfers,
                'logs_cleared' => $clearLogs ? "{$countLogs} log, {$countOpnames} SO" : "Dilewati",
                'status' => $isDryRun ? 'Simulasi Berhasil' : 'Reset Selesai (0)',
            ];
        }

        // Sinkronisasi total master obat jika tidak dalam mode dry-run
        if (!$isDryRun) {
            $this->info("Menyinkronkan total real-time stock pada master obat (medicines)...");
            // Hitung ulang stok master obat
            $warehouseId = function_exists('getWarehousePharmacyId') ? getWarehousePharmacyId() : 9;
            
            // Sinkronisasi master medicine stock:
            // Stock dihitung dari storage gudang + counter stock cabang 1
            $medicines = Medicines::all();
            $bar = $this->output->createProgressBar($medicines->count());
            $bar->start();

            foreach ($medicines as $med) {
                $storageStock = (int) Batches::where('medicine_id', $med->id)
                    ->where('pharmacy_id', $warehouseId)
                    ->sum('stock');

                $counterStock = (int) MedicineTransferItems::whereHas('batches', function ($b) use ($med) {
                    $b->where('medicine_id', $med->id)
                      ->where('pharmacy_id', 1);
                })
                ->where('status', 1)
                ->where(function ($q) {
                    $q->whereNull('source_type')->orWhere('source_type', '!=', 'retur_gudang');
                })
                ->sum('qty');

                $med->update(['stock' => $storageStock + $counterStock]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->table(
            ['ID Cabang', 'Nama Cabang', 'Batch Gudang Di-0-kan', 'Etalase Di-0-kan', 'Riwayat Log', 'Status'],
            $summary
        );

        $this->newLine();
        if ($isDryRun) {
            $this->info("Simulasi selesai. Jalankan tanpa opsi --dry-run untuk benar-benar menerapkan reset.");
        } else {
            $this->info("SUKSES: Stok cabang yang dipilih telah berhasil di-nol-kan!");
            $this->line("Sekarang cabang siap untuk dilakukan Stock Opname Saldo Awal (cut-off) menggunakan nomor batch dan tanggal expired yang asli.");
        }

        return 0;
    }
}
