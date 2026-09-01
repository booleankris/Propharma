<?php

namespace App\Console\Commands;

use App\Models\Batches;
use App\Models\Medicines;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class ImportGudangStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medicines:import-gudang-stock 
                            {--pharmacy-id=9 : Target pharmacy_id (defaults to 9 for Gudang PMI)}
                            {--file= : Path to custom Excel file (defaults to storage/app/stok_gudang_pmi.xlsx)}
                            {--init-missing-zero : Also create 0 stock batches for DB medicines not in Excel}
                            {--dry-run : Simulate the update without modifying the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert/Update Batches stock for Gudang (pharmacy_id = 9) based on "STOK AKHIR" and "CODE BARANG" from stok_gudang_pmi.xlsx';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $customFile = $this->option('file');
        $pharmacyId = (int) $this->option('pharmacy-id');
        $initMissingZero = $this->option('init-missing-zero');

        if ($isDryRun) {
            $this->warn("🔍 RUNNING IN DRY-RUN MODE: No database changes will be saved.");
        } elseif (App::environment('production')) {
            if (!$this->confirm("⚠️ YOU ARE IN PRODUCTION! This will update/create batches stock for pharmacy_id = {$pharmacyId}. Are you sure?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $path = $customFile ? base_path($customFile) : storage_path('app/stok_gudang_pmi.xlsx');

        if (!file_exists($path)) {
            $this->error('File not found: ' . $path);
            return 1;
        }

        $this->info("Target Pharmacy ID: {$pharmacyId} (GUDANG)");
        $this->info('Reading Excel file: ' . $path);
        
        ini_set('memory_limit', '-1');

        try {
            $sheets = Excel::toArray([], $path);
            $rows = $sheets[0] ?? [];
        } catch (\Exception $e) {
            $this->error('Failed to read Excel file: ' . $e->getMessage());
            return 1;
        }

        if (empty($rows)) {
            $this->error('Excel sheet is empty.');
            return 1;
        }

        // Header check
        $header = array_shift($rows);
        $this->info('Found ' . count($rows) . ' rows in Excel to process.');

        $batchesCreated = 0;
        $batchesUpdated = 0;
        $skippedEmpty = 0;
        $notFoundInDB = 0;
        $notFoundList = [];
        $processedMedicineIds = [];

        try {
            DB::beginTransaction();

            $bar = $this->output->createProgressBar(count($rows));
            $bar->start();

            foreach ($rows as $index => $row) {
                // Column A (index 0) = CODE BARANG
                $code = isset($row[0]) ? trim((string) $row[0]) : null;

                if (empty($code)) {
                    $skippedEmpty++;
                    $bar->advance();
                    continue;
                }

                // Column F (index 5) = STOK AKHIR
                $stockRaw = isset($row[5]) ? $row[5] : 0;
                $stock = is_numeric($stockRaw) ? (int) round($stockRaw) : 0;

                // Find medicine by code
                $medicine = Medicines::where('code', $code)->first();

                if (!$medicine) {
                    $notFoundInDB++;
                    if (count($notFoundList) < 10) {
                        $notFoundList[] = [
                            'code' => $code,
                            'name' => isset($row[1]) ? trim((string) $row[1]) : '-',
                            'stock' => $stock
                        ];
                    }
                    $bar->advance();
                    continue;
                }

                $processedMedicineIds[] = $medicine->id;

                if (!$isDryRun) {
                    // Check if batch already exists for this medicine in pharmacy_id
                    $batch = Batches::where('medicine_id', $medicine->id)
                        ->where('pharmacy_id', $pharmacyId)
                        ->first();

                    if ($batch) {
                        $batch->update([
                            'stock' => $stock
                        ]);
                        $batchesUpdated++;
                    } else {
                        Batches::create([
                            'medicine_id' => $medicine->id,
                            'pharmacy_id' => $pharmacyId,
                            'name' => 'INITIAL_INSERT',
                            'expired_date' => '2040-01-21',
                            'status' => 2,
                            'stock' => $stock,
                        ]);
                        $batchesCreated++;
                    }
                } else {
                    $batchExists = Batches::where('medicine_id', $medicine->id)
                        ->where('pharmacy_id', $pharmacyId)
                        ->exists();
                    if ($batchExists) {
                        $batchesUpdated++;
                    } else {
                        $batchesCreated++;
                    }
                }

                $bar->advance();
            }

            // Create 0 stock batch for medicines not in Excel (if requested or by default)
            $initializedZeroBatches = 0;
            if ($initMissingZero) {
                $allMedicines = Medicines::whereNotIn('id', $processedMedicineIds)->get(['id', 'code', 'name']);
                foreach ($allMedicines as $med) {
                    $existingBatch = Batches::where('medicine_id', $med->id)
                        ->where('pharmacy_id', $pharmacyId)
                        ->first();
                    if (!$existingBatch && !$isDryRun) {
                        Batches::create([
                            'medicine_id' => $med->id,
                            'pharmacy_id' => $pharmacyId,
                            'name' => 'INITIAL_INSERT',
                            'expired_date' => '2040-01-21',
                            'status' => 2,
                            'stock' => 0,
                        ]);
                        $initializedZeroBatches++;
                    } elseif (!$existingBatch && $isDryRun) {
                        $initializedZeroBatches++;
                    }
                }
            }

            $bar->finish();
            $this->newLine(2);

            if ($isDryRun) {
                DB::rollBack();
                $this->warn("🔍 Dry-run complete. Database was NOT modified.");
            } else {
                DB::commit();
                $this->info("✅ Batches for Gudang (pharmacy_id = {$pharmacyId}) updated successfully!");
            }

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Rows Processed from Excel', count($rows)],
                    ['New Batches Created in Gudang', $batchesCreated],
                    ['Existing Batches Updated in Gudang', $batchesUpdated],
                    ['Total Matched Medicines in Gudang', $batchesCreated + $batchesUpdated],
                    ['Skipped (Empty Code)', $skippedEmpty],
                    ['Skipped (New / Code Not in DB)', $notFoundInDB],
                    ['Zero Batches Initialized for Other DB Meds', $initializedZeroBatches],
                ]
            );

            if (!empty($notFoundList)) {
                $this->newLine();
                $this->warn("Sample of skipped medicines (not found in DB):");
                $this->table(['Code', 'Name', 'Stock in Excel'], $notFoundList);
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error during batch update: ' . $e->getMessage());
            return 1;
        }
    }
}
