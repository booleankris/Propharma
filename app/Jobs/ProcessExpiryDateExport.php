<?php

namespace App\Jobs;

use App\Exports\Report\ExpiryDateExport;
use App\Models\ExportJob;
use App\Models\Pharmacies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProcessExpiryDateExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $exportJobId;
    protected $pharmacyId;

    public function __construct($exportJobId, $pharmacyId)
    {
        $this->exportJobId = $exportJobId;
        $this->pharmacyId  = $pharmacyId;
    }

    public function handle()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $job = ExportJob::find($this->exportJobId);
        if (!$job) return;

        $job->update([
            'status'     => 'processing',
            'progress'   => 15,
            'started_at' => now(),
        ]);

        $pharmacy = Pharmacies::find($this->pharmacyId);
        $pharmacyName = $pharmacy?->name ?? 'GUDANG_HO';
        $safeName = preg_replace('/[^A-Za-z0-9_]/', '_', $pharmacyName);
        $fileName = 'Monitoring_ED_Kadaluarsa_' . $safeName . '_' . date('Ymd_His') . '.xlsx';
        $path = 'exports/' . $fileName;

        try {
            if (!is_dir(storage_path('app/public/exports'))) {
                mkdir(storage_path('app/public/exports'), 0777, true);
            }

            $job->update(['progress' => 45]);

            Excel::store(
                new ExpiryDateExport($this->pharmacyId),
                $path,
                'public'
            );

            $job->update([
                'status'      => 'completed',
                'file_path'   => $path,
                'progress'    => 100,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $job->update([
                'status'   => 'failed',
                'progress' => 0,
            ]);
            Log::error("Expiry Date Export Failed: " . $e->getMessage());
        }
    }
}
