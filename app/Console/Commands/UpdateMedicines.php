<?php

namespace App\Console\Commands;

use App\Models\Medicines;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class UpdateMedicines extends Command
{
    protected $signature = 'medicines:updatemedicines {--dry-run : Simulate the import without modifying the database}';
    protected $description = 'Safely updating Medicines data from Excel';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn("🔍 RUNNING IN DRY-RUN MODE: No database changes will be saved.");
        } elseif (App::environment('production')) {
            if (!$this->confirm('⚠️ YOU ARE IN PRODUCTION! Are you sure you want to run this update?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $path = storage_path('app/update_medicine_small.xlsx');

        if (!file_exists($path)) {
            $this->error('File not found: ' . $path);
            return 1;
        }

        $this->info('Reading Excel file...');
        $rows = Excel::toArray([], $path)[0];
        array_shift($rows); // Remove header row

        $updated = 0;
        $skipped = 0;
        $records = []; // id => updateData, collected first so we can do a two-phase code update

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $id = isset($row[30]) ? trim((string) $row[30]) : null;

            if (empty($id) || !is_numeric($id)) {
                $this->warn("Row {$rowNumber}: Invalid or missing ID [{$id}]");
                $skipped++;
                continue;
            }

            $code = isset($row[27]) ? trim((string) $row[27]) : null;

            $rawPrice = (int) str_replace(',', '', $row[28] ?? 0);
            $netPrice = (int) round($rawPrice * 1.11); // 11% PPN
            $hetPrice = (int) str_replace(',', '', $row[29] ?? 0);

            $barcodeInput = isset($row[31]) ? trim((string) $row[31]) : null;
            $barcode = null;

            if (!empty($barcodeInput)) {
                if (is_numeric($barcodeInput) && str_contains(strtolower($barcodeInput), 'e')) {
                    $barcode = sprintf('%.0f', (float)$barcodeInput);
                } else {
                    $barcode = $barcodeInput;
                }
            }

            $strip = (int) str_replace(',', '', $row[32] ?? 1);

            $updateData = [
                'code'      => $code,
                'raw_price' => $rawPrice,
                'net_price' => $netPrice,
                'het_price' => $hetPrice,
                'strip'     => $strip,
            ];

            if (!is_null($barcode)) {
                $updateData['barcode'] = $barcode;
            }

            $records[$id] = ['row' => $rowNumber, 'data' => $updateData];
        }

        // Only touch DB for IDs that actually exist
        $ids = array_keys($records);
        $existingIds = Medicines::whereIn('id', $ids)->pluck('id')->all();

        foreach ($ids as $id) {
            if (!in_array($id, $existingIds)) {
                $this->warn("Row {$records[$id]['row']}: Medicine ID not found [{$id}]");
                $skipped++;
                unset($records[$id]);
            }
        }

        DB::beginTransaction();

        try {
            // PHASE 1: set every code to a temp unique placeholder to avoid
            // unique constraint collisions when codes are being swapped/reassigned
            foreach ($records as $id => $info) {
                if (!$isDryRun) {
                    Medicines::where('id', $id)->update(['code' => 'TMP-' . $id]);
                }
            }

            // PHASE 2: apply the real final data (including the true code)
            foreach ($records as $id => $info) {
                if (!$isDryRun) {
                    Medicines::where('id', $id)->update($info['data']);
                }
                $updated++;
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info("Dry-run complete. Everything looks good!");
            } else {
                DB::commit();
                $this->info("Database successfully updated and committed!");
            }

            $this->info("Summary -> Updated: {$updated}, Skipped/Not found: {$skipped}");
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("CRITICAL ERROR on processing! All database changes have been rolled back.");
            $this->error($e->getMessage());
            return 1;
        }

        return 0;
    }
}
