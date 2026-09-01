<?php

namespace App\Console\Commands;

use App\Models\Medicines;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class UpdateMedicinesComponent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medicines:update-component 
                            {--file= : Path to custom Excel file (defaults to storage/app/2508_UPDATE.xlsx)}
                            {--dry-run : Simulate the update without modifying the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Medicines "component" column based on "code barang" from 2508_UPDATE.xlsx';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $customFile = $this->option('file');

        if ($isDryRun) {
            $this->warn("🔍 RUNNING IN DRY-RUN MODE: No database changes will be saved.");
        } elseif (App::environment('production')) {
            if (!$this->confirm('⚠️ YOU ARE IN PRODUCTION! This will update medicine components based on code. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $path = $customFile ? base_path($customFile) : storage_path('app/2508_UPDATE.xlsx');

        if (!file_exists($path)) {
            $this->error('File not found: ' . $path);
            return 1;
        }

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
        $this->info('Found ' . count($rows) . ' rows to process.');

        $updated = 0;
        $skipped = 0;
        $notFound = 0;
        $emptyComponent = 0;

        try {
            DB::beginTransaction();

            $bar = $this->output->createProgressBar(count($rows));
            $bar->start();

            foreach ($rows as $index => $row) {
                // Column A (index 0) = code barang
                $code = isset($row[0]) ? trim((string) $row[0]) : null;

                if (empty($code)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Column R (index 17) = KOMPONEN
                $c17 = isset($row[17]) ? trim((string) $row[17]) : '';
                
                // Column AC (index 28) = KOMPOSISI (fallback if column 17 is formula or empty)
                $c28 = isset($row[28]) ? trim((string) $row[28]) : '';

                $component = null;

                if (!empty($c17) && strpos($c17, '=') !== 0) {
                    $component = $c17;
                } elseif (!empty($c28) && strpos($c28, '=') !== 0) {
                    $component = $c28;
                }

                if (!empty($component)) {
                    // Clean up whitespace
                    $component = preg_replace('/\s+/', ' ', $component);
                } else {
                    $component = null;
                    $emptyComponent++;
                }

                $medicine = Medicines::where('code', $code)->first();

                if (!$medicine) {
                    $notFound++;
                    $bar->advance();
                    continue;
                }

                if (!$isDryRun) {
                    $medicine->update([
                        'component' => $component,
                    ]);
                }

                $updated++;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            if (!$isDryRun) {
                DB::commit();
                $this->info("✅ Database successfully updated!");
            } else {
                DB::rollBack();
                $this->info("🔍 Dry-run complete. No changes were saved.");
            }

            $this->table(
                ['Keterangan', 'Jumlah'],
                [
                    ['Total Diupdate', $updated],
                    ['Obat Tidak Ditemukan (Code DB)', $notFound],
                    ['Komponen Kosong / Formula Rusak', $emptyComponent],
                    ['Baris Kosong / Dilewati', $skipped],
                ]
            );

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("CRITICAL ERROR: Rollback executed.");
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
