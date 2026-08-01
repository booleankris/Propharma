<?php

namespace App\Console\Commands;

use App\Models\Medicines;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class UpdateMedicines extends Command
{
    protected $signature = 'medicines:updatebycode {--dry-run : Simulate the import without modifying the database}';
    protected $description = 'Safely update Medicines raw_price, net_price, het_price, content, strip by matching code barang';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn("🔍 RUNNING IN DRY-RUN MODE: No database changes will be saved.");
        } elseif (App::environment('production')) {
            if (!$this->confirm('⚠️ YOU ARE IN PRODUCTION! This will update raw_price, net_price, het_price, content, and strip. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $path = storage_path('app/1AGUSTUS_UPDATE_HNA.xlsx');

        if (!file_exists($path)) {
            $this->error('File not found: ' . $path);
            return 1;
        }

        $this->info('Reading Excel file...');
        $rows = Excel::toArray([], $path)[0];
        array_shift($rows); // Remove header row

        $updated = 0;
        $skipped = 0;
        $zeroHetWarnings = 0;

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                // Column A (index 0) = code barang
                $code = isset($row[0]) ? trim((string) $row[0]) : null;

                if (empty($code)) {
                    $this->warn("Row {$rowNumber}: Missing code barang, skipped.");
                    $skipped++;
                    continue;
                }

                // Column C (index 2) = HNA Baru -> raw_price
                $rawPrice = $this->parseNumber($row[2] ?? 0);
                $netPrice = (int) round($rawPrice * 1.11); // 11% PPN

                // Column D (index 3) = HET -> het_price
                $hetPrice = $this->parseNumber($row[3] ?? 0);
                if ($hetPrice === 0) {
                    $zeroHetWarnings++;
                }

                // Column H (index 7) = ISI/BOX -> content
                $content = $this->parseNumber($row[7] ?? 0);

                // Column I (index 8) = ISI/STRIP -> strip
                $strip = $this->parseNumber($row[8] ?? 1);

                $medicine = Medicines::where('code', $code)->first();

                if (!$medicine) {
                    $this->warn("Row {$rowNumber}: Medicine code not found in DB [{$code}]");
                    $skipped++;
                    continue;
                }

                $updateData = [
                    'raw_price' => $rawPrice,
                    'net_price' => $netPrice,
                    'het_price' => $hetPrice,
                    'content'   => $content,
                    'strip'     => $strip,
                ];

                if (!$isDryRun) {
                    $medicine->update($updateData);
                }

                $updated++;
            }

            $this->info($isDryRun ? "Dry-run complete. No changes were saved." : "Database successfully updated!");
            $this->info("Summary -> Updated: {$updated}, Skipped/Not found: {$skipped}");

            if ($zeroHetWarnings > 0) {
                $this->warn("⚠️ {$zeroHetWarnings} row(s) had HET = 0 in the source file. Double-check this is intentional before trusting het_price for those rows.");
            }

        } catch (\Exception $e) {
            $this->error("CRITICAL ERROR on processing!");
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
}