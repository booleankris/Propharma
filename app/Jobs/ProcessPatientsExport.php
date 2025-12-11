<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\Patients;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessPatientsExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $exportJobId;
    public $filters;

    public function __construct($exportJobId, $filters)
    {
        $this->exportJobId = $exportJobId;
        $this->filters = $filters;
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
            // Apply filters
            $query = Patients::query();

            if (!empty($this->filters['start_date'])) {
                $query->whereDate('created_at', '>=', $this->filters['start_date']);
            }
            if (!empty($this->filters['end_date'])) {
                $query->whereDate('created_at', '<=', $this->filters['end_date']);
            }

            $total = $query->count();

            if ($total == 0) {
                $export->update([
                    'status' => 'finished',
                    'progress' => 100,
                    'file_path' => null,
                    'finished_at' => now()
                ]);
                return;
            }

            $export->update(['progress' => 5]);

            $filename = "exports/patients_export_{$export->id}_" . time() . ".csv";
            $path = storage_path("app/public/{$filename}");
            
            if (!is_dir(storage_path("app/public/exports"))) {
                mkdir(storage_path("app/public/exports"), 0777, true);
            }
            
            $file = fopen($path, 'w');
            

            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, [
                'ID',
                'Name',
                'Phone',
                'Address',
                'Birth',
                'Created At'
            ]);

            $processed = 0;

            // Chunked export
            $query->orderBy('id')->chunk(500, function ($rows) use (&$processed, $total, $file, $export) {
                foreach ($rows as $p) {
                    fputcsv($file, [
                        $p->id,
                        $p->name,
                        $p->phone,
                        $p->address,
                        $p->birth,
                        $p->created_at
                    ]);
                    $processed++;
                }

                $progress = intval(($processed / $total) * 100);
                $export->update(['progress' => $progress]);
            });

            fclose($file);

            // Mark as finished
            $export->update([
                'status' => 'finished',
                'progress' => 100,
                'file_path' => $filename,
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
