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
                            {--only-type : Only update the type (Surat Pesanan) column}
                            {--only-category : Only update the medicine_category_id column from GOLONGAN}';
    protected $description = 'Update Medicines prices, barcode, type (Surat Pesanan), and category (GOLONGAN) from 2508_UPDATE.xlsx';

    private ?int $ootCatId = null;
    private ?int $minumanCatId = null;

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $onlyType = $this->option('only-type');
        $onlyCategory = $this->option('only-category');

        if ($isDryRun) {
            $this->warn("🔍 RUNNING IN DRY-RUN MODE: No database changes will be saved.");
        } elseif (App::environment('production')) {
            $actionDesc = $onlyCategory 
                ? "medicine_category_id (GOLONGAN)" 
                : ($onlyType ? "medicine 'type' (Surat Pesanan)" : "prices, barcodes, 'type', and categories");
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
        $categoryCounts = [];

        $modeText = $onlyCategory ? "ONLY CATEGORY (GOLONGAN)" : ($onlyType ? "ONLY TYPE (Surat Pesanan)" : "PRICES, BARCODE, TYPE & CATEGORY");
        $this->info("Processing " . count($rows) . " rows in [{$modeText}] mode...");

        // Ensure OOT and MINUMAN categories exist
        $this->initSpecialCategories();

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

                // Column AA (index 26) = GOLONGAN -> medicine_category_id
                $rawGolongan = isset($row[26]) ? (string) $row[26] : null;
                $categoryId = $this->normalizeGolongan($rawGolongan);

                $updateData = [];

                if ($onlyCategory) {
                    if (!empty($categoryId)) {
                        $updateData['medicine_category_id'] = $categoryId;
                    }
                } elseif ($onlyType) {
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

                    if (!empty($categoryId)) {
                        $updateData['medicine_category_id'] = $categoryId;
                    }
                }

                if (!empty($updateData)) {
                    if (!$isDryRun) {
                        $medicine->update($updateData);
                    }
                    $updated++;

                    if ($onlyCategory) {
                        $cId = $categoryId ?: ($medicine->medicine_category_id ?: 0);
                        $categoryCounts[$cId] = ($categoryCounts[$cId] ?? 0) + 1;
                    } else {
                        $countedType = $type ?: ($medicine->type ?: 'EMPTY');
                        $typeCounts[$countedType] = ($typeCounts[$countedType] ?? 0) + 1;
                        if (!empty($categoryId)) {
                            $categoryCounts[$categoryId] = ($categoryCounts[$categoryId] ?? 0) + 1;
                        }
                    }
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

            if (!empty($typeCounts)) {
                $this->info("Type Distribution:");
                foreach ($typeCounts as $tName => $count) {
                    $this->line(" - {$tName}: {$count}");
                }
            }

            if (!empty($categoryCounts)) {
                $this->info("Category (GOLONGAN) Distribution:");
                $catNames = \App\Models\MedicineCategory::pluck('name', 'id')->toArray();
                foreach ($categoryCounts as $cId => $count) {
                    $cName = $catNames[$cId] ?? "ID: {$cId}";
                    $this->line(" - [ID {$cId}] {$cName}: {$count}");
                }
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
     * Initialize special categories if not exist
     */
    private function initSpecialCategories(): void
    {
        $oot = \App\Models\MedicineCategory::firstOrCreate(
            ['name' => 'OBAT-OBAT TERTENTU (OOT)'],
            ['code' => '113', 'status' => 0]
        );
        $this->ootCatId = $oot->id;

        $minuman = \App\Models\MedicineCategory::firstOrCreate(
            ['name' => 'MINUMAN'],
            ['code' => '114', 'status' => 0]
        );
        $this->minumanCatId = $minuman->id;
    }

    /**
     * Normalize GOLONGAN text from Excel to medicine_category_id
     */
    public function normalizeGolongan(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        // Clean hidden Unicode non-breaking spaces and collapse spaces
        $clean = trim(preg_replace('/[\s\x{00a0}\x{200b}]+/u', ' ', $value));
        $upper = strtoupper($clean);

        if (empty($upper) || $upper === '#N/A' || $upper === '0') {
            return null;
        }

        // Exact & Pattern Mappings to Category IDs
        if ($upper === 'OBAT KERAS (G)' || $upper === 'OBAT KERAS (K)' || str_starts_with($upper, 'OBAT KERAS')) {
            return 3; // OBAT KERAS (G)
        }
        if ($upper === 'OBAT BEBAS/OTC (B)' || $upper === 'OBAT BEBAS' || str_starts_with($upper, 'OBAT BEBAS/OTC')) {
            return 1; // OBAT BEBAS/OTC (B)
        }
        if ($upper === 'ALAT KESEHATAN (ALKES)' || $upper === 'BENANG') {
            return 5; // ALAT KESEHATAN (ALKES)
        }
        if ($upper === 'OBAT BEBAS TERBATAS (W)') {
            return 2; // OBAT BEBAS TERBATAS (W)
        }
        if ($upper === 'PERSONAL CARE') {
            return 85; // PERSONAL CARE
        }
        if ($upper === 'SUSU') {
            return 11; // SUSU
        }
        if ($upper === 'CONSUMER GOOD') {
            return 10; // CONSUMER GOOD
        }
        if ($upper === 'OBAT NARKOTIKA (O)' || $upper === 'NARKOTIKA') {
            return 4; // OBAT NARKOTIKA (O)
        }
        if ($upper === 'OBAT PREKURSOR') {
            return 9; // OBAT PREKURSOR
        }
        if ($upper === 'HERBAL' || $upper === 'JAMU') {
            return 8; // HERBAL
        }
        if ($upper === 'OBAT GOLONGAN PSIKOTROPIKA (P)') {
            return 7; // OBAT GOLONGAN PSIKOTROPIKA (P)
        }
        if ($upper === 'OBAT PSIKOTROPIKA' || $upper === 'PSIKOTROPIKA') {
            return 6; // OBAT PSIKOTROPIKA (P)
        }
        if ($upper === 'OBAT-OBAT TERTENTU (OOT)') {
            return $this->ootCatId;
        }
        if ($upper === 'MINUMAN') {
            return $this->minumanCatId;
        }
        if ($upper === 'SUPLEMEN') {
            return 60; // VITAMIN & SUPLEMEN
        }
        if ($upper === 'PEMPERS') {
            return 63; // PAMPERS
        }

        return null;
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
