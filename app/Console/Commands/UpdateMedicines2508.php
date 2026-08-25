<?php

namespace App\Console\Commands;

use App\Models\Medicines;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class UpdateMedicines2508 extends Command
{
    protected $signature = 'medicines:update2508 {--dry-run : Simulate the import without modifying the database}';
    protected $description = 'Update Medicines raw_price, het_price, net_price, pharmacy_net_price, barcode from 2508_UPDATE.xlsx';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn("🔍 RUNNING IN DRY-RUN MODE: No database changes will be saved.");
        } elseif (App::environment('production')) {
            if (!$this->confirm('⚠️ YOU ARE IN PRODUCTION! This will update prices and barcodes. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $path = storage_path('app/2508_UPDATE.xlsx');

        if (!file_exists($path)) {
            $this->error('File not found: ' . $path);
            return 1;
        }

        $this->info('Reading Excel file... (this may take a moment)');
        
        // Increase memory limit for parsing the Excel file
        ini_set('memory_limit', '-1');
        
        try {
            $rows = Excel::toArray([], $path)[0];
        } catch (\Exception $e) {
            $this->error('Failed to read excel file: ' . $e->getMessage());
            return 1;
        }
        
        array_shift($rows); // Remove header row

        $updated = 0;
        $skipped = 0;

        $this->info('Processing ' . count($rows) . ' rows...');

        try {
            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                // Read from Excel structure
                // Column A (index 0) = code barang
                $code = isset($row[0]) ? trim((string) $row[0]) : null;

                if (empty($code)) {
                    $skipped++;
                    continue;
                }

                // Column D (index 3) = HNA BARUU -> raw_price, pharmacy_net_price, net_price
                $rawPrice = $this->parseNumber($row[3] ?? 0);
                $netPrice = (int) round($rawPrice * 1.11); // 11% PPN

                // Column E (index 4) = HET -> het_price
                $hetPrice = $this->parseNumber($row[4] ?? 0);

                // Column F (index 5) = BARCODE -> barcode
                $barcode = $this->parseBarcode($row[5] ?? null);

                $medicine = Medicines::where('code', $code)->first();

                if (!$medicine) {
                    // $this->warn("Row {$rowNumber}: Medicine code not found in DB [{$code}]");
                    $skipped++;
                    continue;
                }

                $updateData = [
                    'raw_price'          => $rawPrice,
                    'net_price'          => $netPrice,
                    'het_price'          => $hetPrice,
                    'pharmacy_net_price' => $rawPrice,
                    'barcode'            => $barcode,
                ];

                if (!$isDryRun) {
                    $medicine->update($updateData);
                }

                $updated++;
                
                if ($updated % 500 === 0) {
                    $this->info("Processed {$updated} records...");
                }
            }

            if (!$isDryRun) {
                DB::commit();
                $this->info("Database successfully updated!");
            } else {
                DB::rollBack();
                $this->info("Dry-run complete. No changes were saved.");
            }

            $this->info("Summary -> Updated: {$updated}, Skipped/Not found: {$skipped}");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("CRITICAL ERROR on processing! Rollback executed.");
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Parse a numeric Excel cell that may come as int, float, or
     * a comma-formatted string like "12,500".
     */
    private function parseNumber($value): int
    {
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        $clean = str_replace(',', '', (string) $value);

        return is_numeric($clean) ? (int) round((float) $clean) : 0;
    }
    
    /**
     * Parse barcode to prevent scientific notation (e.g., 8.99E+12 -> 8990000000000)
     */
    private function parseBarcode($value): ?string
    {
        if ($value === null || trim((string)$value) === '') {
            return null;
        }
        
        $value = trim((string)$value);
        
        // If it looks like scientific notation, format it as string without decimals
        if (preg_match('/^\d+(\.\d+)?E\+\d+$/i', $value)) {
            return sprintf('%.0f', (float) $value);
        }
        
        return $value;
    }
}
