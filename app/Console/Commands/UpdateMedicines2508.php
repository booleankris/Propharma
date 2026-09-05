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
    protected $signature = 'medicines:update2508 
                            {--dry-run : Simulate the import without modifying the database}
                            {--only-type : Only update the type (Surat Pesanan) column}';
    protected $description = 'Update Medicines raw_price, het_price, net_price, pharmacy_net_price, barcode, and type (Surat Pesanan) from 2508_UPDATE.xlsx';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $onlyType = $this->option('only-type');

        if ($isDryRun) {
            $this->warn("🔍 RUNNING IN DRY-RUN MODE: No database changes will be saved.");
        } elseif (App::environment('production')) {
            $actionDesc = $onlyType ? "medicine 'type' (Surat Pesanan)" : "prices, barcodes, and 'type'";
            if (!$this->confirm("⚠️ YOU ARE IN PRODUCTION! This will update {$actionDesc}. Are you sure?")) {
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
        $typeCounts = [];

        $modeText = $onlyType ? "ONLY TYPE (Surat Pesanan)" : "PRICES, BARCODE & TYPE";
        $this->info("Processing " . count($rows) . " rows in [{$modeText}] mode...");

        try {
            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                // Column A (index 0) = code barang
                $code = isset($row[0]) ? trim((string) $row[0]) : null;

                if (empty($code)) {
                    $skipped++;
                    continue;
                }

                $medicine = Medicines::where('code', $code)->first();

                if (!$medicine) {
                    $skipped++;
                    continue;
                }

                // Column Q (index 16) = SURAT PESANAN -> type
                $rawType = isset($row[16]) ? trim((string) $row[16]) : null;
                $type = $this->normalizeType($rawType);

                if ($onlyType) {
                    $updateData = [];
                    if (!empty($type)) {
                        $updateData['type'] = $type;
                    }
                } else {
                    // Column D (index 3) = HNA BARUU -> raw_price, pharmacy_net_price, net_price
                    $rawPrice = $this->parseNumber($row[3] ?? 0);
                    $netPrice = (int) round($rawPrice * 1.11); // 11% PPN

                    // Column E (index 4) = HET -> het_price
                    $hetPrice = $this->parseNumber($row[4] ?? 0);

                    // Column F (index 5) = BARCODE -> barcode
                    $barcode = $this->parseBarcode($row[5] ?? null);

                    $updateData = [
                        'raw_price'          => $rawPrice,
                        'net_price'          => $netPrice,
                        'het_price'          => $hetPrice,
                        'pharmacy_net_price' => $rawPrice,
                        'barcode'            => $barcode,
                    ];

                    if (!empty($type)) {
                        $updateData['type'] = $type;
                    }
                }

                if (!empty($updateData)) {
                    if (!$isDryRun) {
                        $medicine->update($updateData);
                    }
                    $updated++;

                    $countedType = $type ?: ($medicine->type ?: 'EMPTY');
                    $typeCounts[$countedType] = ($typeCounts[$countedType] ?? 0) + 1;
                }

                if ($updated % 500 === 0 && $updated > 0) {
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
            $this->info("Type Distribution:");
            foreach ($typeCounts as $tName => $count) {
                $this->line(" - {$tName}: {$count}");
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("CRITICAL ERROR on processing! Rollback executed.");
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Normalize type from Excel 'SURAT PESANAN' column
     */
    private function normalizeType(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $type = strtoupper(trim($value));

        // Standardize common typos/variants
        if ($type === 'REGULAR') {
            return 'REGULER';
        }

        return $type;
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
