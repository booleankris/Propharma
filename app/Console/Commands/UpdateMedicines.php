<?php

namespace App\Console\Commands;
use App\Models\Medicines;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Console\Command;

class UpdateMedicines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'medicines:updatemedicines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updating Medicines';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $path = storage_path('app/updatemedicine.xlsx');

        if (!file_exists($path)) {
            $this->error('File not found: ' . $path);
            return;
        }

        $rows = Excel::toArray([], $path)[0];
        array_shift($rows); // remove header row

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $code     = trim((string) $row[0]); // column A — code barang
            $newRaw   = $row[3];                // column D — HNA BARU
            $barcode  = $row[4];                // column E — Barcode

            if (empty($code) || is_null($newRaw)) {
                $skipped++;
                continue;
            }

            // Remove thousand separators if Excel sends "12,500" as string
            $newRaw = (int) str_replace(',', '', $newRaw);

            // Calculate net_price with PPN 11%
            $newNet = (int) round($newRaw * 1.11);

            $barcode = !empty(trim((string) $barcode)) ? trim((string) $barcode) : null;

            $affected = Medicines::where('code', $code)
                ->update([
                    'raw_price' => $newRaw,
                    'net_price' => $newNet,
                    'barcode'   => $barcode,
                ]);

            if ($affected) {
                $updated++;
            } else {
                $this->warn("Code not found in DB: {$code}");
                $skipped++;
            }
        }

        $this->info("Done! Updated: {$updated}, Skipped/Not found: {$skipped}");
    }
}
