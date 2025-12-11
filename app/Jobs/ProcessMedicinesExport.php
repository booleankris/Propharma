<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\Medicines;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessMedicinesExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $exportJobId;

    public function __construct($exportJobId)
    {
        $this->exportJobId = $exportJobId;
    }

    public function handle()
    {
        $export = ExportJob::find($this->exportJobId);

        if (!$export) {
            return;
        }

        $export->update([
            'status' => 'running',
            'progress' => 0,
            'started_at' => now()
        ]);

        try {
            $query = Medicines::query();

            $total = $query->count();

            if ($total == 0) {
                $export->update([
                    'status'      => 'finished',
                    'progress'    => 100,
                    'file_path'   => null,
                    'finished_at' => now()
                ]);
                return;
            }

            $export->update(['progress' => 5]);

            // File setup
            $filename = "exports/medicines_export_{$export->id}_" . time() . ".csv";
            $path = storage_path("app/public/{$filename}");

            // Ensure directory exists
            if (!is_dir(storage_path("app/public/exports"))) {
                mkdir(storage_path("app/public/exports"), 0777, true);
            }

            $file = fopen($path, 'w');

            // UTF-8 BOM
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($file, [
                "ID",
                "Name",
                "Generic Name",
                "Factory",
                "Category",
                "Composition",
                "Package",
                "Stock",
                "Pharmacy Net Price",
                "Created At",
            ]);

            $processed = 0;

            // Chunked export
            $query->with(['factory','category','composition'])
                ->orderBy('id')
                ->chunk(500, function ($rows) use (&$processed, $total, $file, $export) {
                    foreach ($rows as $m) {
                        fputcsv($file, [
                            $m->id,
                            $m->name,
                            $m->generic_name,
                            $m->factory->name ?? "-",
                            $m->category->name ?? "-",
                            $m->composition->name ?? "-",
                            $m->package,
                            $m->stock,
                            $m->pharmacy_net_price,
                            $m->created_at,
                        ]);
                        $processed++;
                    }

                    // Update progress
                    $progress = intval(($processed / $total) * 100);
                    $export->update(['progress' => $progress]);
                });

            fclose($file);

            // Job done
            $export->update([
                'status'      => 'finished',
                'progress'    => 100,
                'file_path'   => $filename,
                'finished_at' => now()
            ]);

        } catch (Throwable $e) {

            $export->update([
                'status' => 'failed',
                'progress' => 0
            ]);

            throw $e;
        }
    }
}
