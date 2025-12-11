<?php

namespace App\Jobs;

use App\Models\ExportJob;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Export\TransactionsExportExcel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TransactionExportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    protected $exportJob;
    protected $start;
    protected $end;

    public function __construct($exportJob, $start, $end)
    {
        $this->exportJob = $exportJob;
        $this->start     = $start;
        $this->end       = $end;
    }

    public function handle()
    {
        // Update job → started
        $this->exportJob->update([
            'status'   => 'processing',
            'progress' => 10
        ]);

        $fileName = 'transactions_export_' . time() . '.xlsx';
        $path = 'exports/' . $fileName;

        // Generate Excel file
        Excel::store(
            new TransactionsExportExcel($this->start, $this->end),
            $path,
            'public'
        );

        // Update job → finished
        $this->exportJob->update([
            'status'    => 'completed',
            'file_path' => $path,
            'progress'  => 100
        ]);
    }
}
