<?php

namespace App\Console\Commands;

use App\Models\Medicines;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;

class UpdateMedicines extends Command
{
    /**
     * The name and signature of the console command.
     * Added --dry-run option for safety!
     *
     * @var string
     */
    protected $signature = 'medicines:updatemedicines {--dry-run : Simulate the import without modifying the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely updating Medicines data from Excel';

    /**
     * Execute the console command.
     *
     * @return int
     */
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
        if ($isDryRun) {
            dump($rows[0]); // show the first data row's raw contents, indexed
        }
        $updated = 0;
        $skipped = 0;

        // Wrap execution in a DB Transaction
        DB::beginTransaction();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // Real Excel row number

                // Index 27 = Column AB (NEW MEDICINE CODE)
                $code = isset($row[27]) ? trim((string) $row[27]) : null;

                if (empty($code)) {
                    $skipped++;
                    continue;
                }

                // Parse Prices
                $rawPrice = (int) str_replace(',', '', $row[28] ?? 0);
                $netPrice = (int) round($rawPrice * 1.11); // 11% PPN
                $hetPrice = (int) str_replace(',', '', $row[29] ?? 0);

                // Barcode parsing with Scientific Notation Protection
                $barcodeInput = isset($row[31]) ? trim((string) $row[31]) : null;
                $barcode = null;

                if (!empty($barcodeInput)) {
                    // Prevent scientific notation like 4.01563E+11
                    if (is_numeric($barcodeInput) && str_contains(strtolower($barcodeInput), 'e')) {
                        $barcode = sprintf('%.0f', (float)$barcodeInput);
                    } else {
                        $barcode = $barcodeInput;
                    }
                }

                // Index 32 = Column AG (Strip)
                $strip = (int) str_replace(',', '', $row[32] ?? 1);

                // Build Payload
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

                // Check if medicine exists
                $medicine = Medicines::where('code', $code)->first();

                if ($medicine) {
                    if (!$isDryRun) {
                        $medicine->update($updateData);
                    }
                    $updated++;
                } else {
                    $this->warn("Row {$rowNumber}: Medicine Code not found [{$code}]");
                    $skipped++;
                }
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
