<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Models\MedicineTransactions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessTransactionsExport implements ShouldQueue
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
        if (!$export) return;

        $export->update([
            'status' => 'running',
            'progress' => 0
        ]);

        try {
            $query = MedicineTransactions::query();

            if ($this->filters['start']) {
                $query->whereDate('created_at', '>=', $this->filters['start']);
            }

            if ($this->filters['end']) {
                $query->whereDate('created_at', '<=', $this->filters['end']);
            }

            $total = $query->count();

            $filename = "exports/transactions_export_{$export->id}_" . time() . ".csv";
            $path = storage_path("app/{$filename}");

            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            $file = fopen($path, 'w');

            fputcsv($file, [
                'ID',
                'Transaction Code',
                'Type',
                'Subtotal',
                'Discount',
                'Created At'
            ]);

            $processed = 0;

            $query->orderBy('id')->chunk(300, function ($rows) use (&$processed, $total, $file, $export) {
                foreach ($rows as $t) {
                    fputcsv($file, [
                        $t->id,
                        $t->transaction_code,
                        $t->transaction_type,
                        $t->subtotal,
                        $t->discount,
                        $t->created_at
                    ]);

                    $processed++;
                }

                $export->update([
                    'progress' => intval(($processed / $total) * 100)
                ]);
            });

            fclose($file);

            $export->update([
                'status' => 'completed',
                'progress' => 100,
                'file_path' => $filename,
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
