<?php

namespace App\Jobs;

use App\Exports\Stocks\StockDataExport;
use App\Models\ExportJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProcessStockDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $exportJobId;
    protected $requestData;

    public function __construct($exportJobId, $requestData = [])
    {
        $this->exportJobId = $exportJobId;
        $this->requestData = $requestData;
    }

    public function handle()
    {
        $job = ExportJob::find($this->exportJobId);
        if (!$job) return;

        $job->update([
            'status'   => 'processing',
            'progress' => 15,
            'started_at' => now(),
        ]);

        $fileName = 'Data_Stok_Gudang_PMI_' . date('Ymd_His') . '.xlsx';
        $path = 'exports/' . $fileName;

        try {
            // Ensure directory exists
            if (!is_dir(storage_path('app/public/exports'))) {
                mkdir(storage_path('app/public/exports'), 0777, true);
            }

            $job->update(['progress' => 40]);

            $requestObj = new \Illuminate\Http\Request($this->requestData);

            Excel::store(
                new StockDataExport($requestObj),
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
            Log::error("Stock Data Export Failed: " . $e->getMessage());
        }
    }
}
