<?php

namespace App\Jobs;

use App\Models\ExportJob;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RejectSalesExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessRejectSalesExport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $exportJobId;
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;

    public function __construct($exportJobId, $pharmacyId, $startDate, $endDate)
    {
        $this->exportJobId = $exportJobId;
        $this->pharmacyId = $pharmacyId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function handle()
    {
        $job = ExportJob::find($this->exportJobId);
        if (!$job) return;

        $job->update([
            'status'   => 'processing',
            'progress' => 10
        ]);

        $fileName = 'Penolakan_Apotek_' . time() . '.xlsx';
        $path = 'exports/' . $fileName;

        try {
            Excel::store(
                new RejectSalesExport($this->pharmacyId, $this->startDate, $this->endDate),
                $path,
                'public'
            );

            $job->update([
                'status'    => 'completed',
                'file_path' => $path,
                'progress'  => 100
            ]);
        } catch (\Exception $e) {
            $job->update([
                'status'    => 'failed',
                'progress'  => 0
            ]);
            \Log::error("Reject Sales Export Failed: " . $e->getMessage());
        }
    }
}
