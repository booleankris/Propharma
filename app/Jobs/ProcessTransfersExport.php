<?php

namespace App\Jobs;

use App\Models\ExportJob;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransfersExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessTransfersExport implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $exportJobId;
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $search;
    protected $type;
    protected $expiredDate;
    protected $status;

    public function __construct($exportJobId, $pharmacyId, $startDate, $endDate, $search, $type, $expiredDate = null, $status = null)
    {
        $this->exportJobId = $exportJobId;
        $this->pharmacyId = $pharmacyId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
        $this->type = $type;
        $this->expiredDate = $expiredDate;
        $this->status = $status;
    }

    public function handle()
    {
        $job = ExportJob::find($this->exportJobId);
        if (!$job) return;

        $job->update([
            'status'   => 'processing',
            'progress' => 10
        ]);

        $suffix = match ($this->status) {
            'unreceived' => '_Belum_Diterima',
            'received' => '_Sudah_Diterima',
            default => '',
        };
        $fileName = 'Mutasi_Apotek' . $suffix . '_' . time() . '.xlsx';
        $path = 'exports/' . $fileName;

        try {
            Excel::store(
                new TransfersExport(
                    $this->pharmacyId,
                    $this->startDate,
                    $this->endDate,
                    $this->search,
                    $this->type,
                    $this->expiredDate,
                    $this->status
                ),
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
            \Log::error("Transfer Export Failed: " . $e->getMessage());
        }
    }
}
