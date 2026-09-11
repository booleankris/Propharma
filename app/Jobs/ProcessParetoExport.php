<?php

namespace App\Jobs;

use App\Exports\Export\ParetoExport;
use App\Models\ExportJob;
use App\Models\Pharmacies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProcessParetoExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $exportJobId;
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $searchMedicine;

    public function __construct($exportJobId, $pharmacyId, $startDate = null, $endDate = null, $searchMedicine = null)
    {
        $this->exportJobId    = $exportJobId;
        $this->pharmacyId     = $pharmacyId;
        $this->startDate      = $startDate;
        $this->endDate        = $endDate;
        $this->searchMedicine = $searchMedicine;
    }

    public function handle()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $job = ExportJob::find($this->exportJobId);
        if (!$job) {
            return;
        }

        $job->update([
            'status'   => 'processing',
            'progress' => 20,
        ]);

        $pharmacy = Pharmacies::find($this->pharmacyId);
        $pharmacyName = $pharmacy?->name ?? 'APOTEK';
        $safeName = preg_replace('/[^A-Za-z0-9_]/', '_', $pharmacyName);
        $fileName = 'Pareto_' . $safeName . '_' . date('Ymd_His') . '.xlsx';
        $path = 'exports/' . $fileName;

        try {
            if (!is_dir(storage_path('app/public/exports'))) {
                mkdir(storage_path('app/public/exports'), 0777, true);
            }

            $job->update(['progress' => 50]);

            Excel::store(
                new ParetoExport(
                    $this->pharmacyId,
                    $this->startDate,
                    $this->endDate,
                    $this->searchMedicine
                ),
                $path,
                'public'
            );

            $job->update([
                'status'    => 'completed',
                'file_path' => $path,
                'progress'  => 100,
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status'   => 'failed',
                'progress' => 0,
            ]);
            Log::error("Pareto Export Failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
