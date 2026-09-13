<?php

namespace App\Jobs;

use App\Exports\Report\PatientTransactionsExport;
use App\Models\ExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessPatientTransactionsExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $exportJobId;
    public $patientId;
    public $startDate;
    public $endDate;
    public $mode;
    public $pharmacyId;

    public function __construct($exportJobId, $patientId = null, $startDate = null, $endDate = null, $mode = 'rekap', $pharmacyId = null)
    {
        $this->exportJobId = $exportJobId;
        $this->patientId   = $patientId;
        $this->startDate   = $startDate;
        $this->endDate     = $endDate;
        $this->mode        = $mode;
        $this->pharmacyId  = $pharmacyId;
    }

    public function handle()
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $job = ExportJob::find($this->exportJobId);
        if (!$job) {
            return;
        }

        $job->update([
            'status'   => 'processing',
            'progress' => 20,
        ]);

        try {
            $exportDir = storage_path('app/public/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0777, true);
            }

            $job->update(['progress' => 40]);

            $modeName = ucfirst($this->mode ?? 'rekap');
            $fileName = 'Transaksi_Pasien_' . $modeName . '_' . date('Ymd_His') . '.xlsx';
            $relativePath = 'exports/' . $fileName;

            Excel::store(
                new PatientTransactionsExport(
                    $this->patientId,
                    $this->startDate,
                    $this->endDate,
                    $this->mode,
                    $this->pharmacyId
                ),
                $relativePath,
                'public'
            );

            $job->update([
                'status'    => 'completed',
                'progress'  => 100,
                'file_path' => $relativePath,
            ]);
        } catch (Throwable $e) {
            Log::error('Patient Transactions Export Failed: ' . $e->getMessage(), [
                'job_id' => $this->exportJobId,
                'trace'  => $e->getTraceAsString(),
            ]);

            $job->update([
                'status'   => 'failed',
                'progress' => 0,
            ]);
        }
    }
}
