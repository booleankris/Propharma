<?php

namespace App\Jobs;

use App\Exports\Orders\BuyPriceHistoryExport;
use App\Models\ExportJob;
use App\Models\ReceivingItems;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class ProcessBuyPriceHistoryExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $exportJobId;
    public $filters;
    public $userId;

    public function __construct($exportJobId, array $filters = [], $userId = null)
    {
        $this->exportJobId = $exportJobId;
        $this->filters = $filters;
        $this->userId = $userId;
    }

    public function handle()
    {
        @ini_set('memory_limit', '1024M');
        @set_time_limit(600);

        $job = ExportJob::find($this->exportJobId);
        if (!$job) {
            return;
        }

        $job->update([
            'status'   => 'processing',
            'progress' => 15,
        ]);

        try {
            $exportDir = storage_path('app/public/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0777, true);
            }

            $job->update(['progress' => 30]);

            // Build query based on filters
            $query = ReceivingItems::query()
                ->with([
                    'receiving_details.receiving.pharmacy',
                    'receiving_details.creditor',
                    'order_items.medicines',
                    'order_items.creditors',
                    'batches',
                ])
                ->whereNotNull('batches_id')
                ->whereHas('order_items.medicines');

            if (!empty($this->filters['search_medicine'])) {
                $kw = trim($this->filters['search_medicine']);
                $query->whereHas('order_items.medicines', function ($q) use ($kw) {
                    $q->where('name', 'like', "%{$kw}%")
                      ->orWhere('code', 'like', "%{$kw}%");
                });
            }

            if (!empty($this->filters['search_invoice'])) {
                $inv = trim($this->filters['search_invoice']);
                $query->where(function ($q) use ($inv) {
                    $q->whereHas('receiving_details', function ($q2) use ($inv) {
                        $q2->where('invoice_number', 'like', "%{$inv}%")
                          ->orWhere('receiving_details_code', 'like', "%{$inv}%");
                    })->orWhereHas('receiving_details.receiving', function ($q2) use ($inv) {
                        $q2->where('code', 'like', "%{$inv}%");
                    });
                });
            }

            if (!empty($this->filters['creditor'])) {
                $cred = trim($this->filters['creditor']);
                $query->where(function ($q) use ($cred) {
                    $q->whereHas('receiving_details.creditor', function ($q2) use ($cred) {
                        $q2->where('name', 'like', "%{$cred}%")
                           ->orWhere('code', 'like', "%{$cred}%");
                    })->orWhereHas('order_items.creditors', function ($q2) use ($cred) {
                        $q2->where('name', 'like', "%{$cred}%")
                           ->orWhere('code', 'like', "%{$cred}%");
                    });
                });
            }

            if (!empty($this->filters['start_date'])) {
                $sDate = $this->filters['start_date'];
                $query->where(function ($q) use ($sDate) {
                    $q->whereHas('receiving_details', function ($rd) use ($sDate) {
                        $rd->whereDate('invoice_date', '>=', $sDate);
                    })->orWhereDate('created_at', '>=', $sDate);
                });
            }
            if (!empty($this->filters['end_date'])) {
                $eDate = $this->filters['end_date'];
                $query->where(function ($q) use ($eDate) {
                    $q->whereHas('receiving_details', function ($rd) use ($eDate) {
                        $rd->whereDate('invoice_date', '<=', $eDate);
                    })->orWhereDate('created_at', '<=', $eDate);
                });
            }

            if (!empty($this->filters['pharmacy_id']) && $this->filters['pharmacy_id'] !== 'all') {
                $pId = (int) $this->filters['pharmacy_id'];
                $query->whereHas('receiving_details.receiving', function ($q) use ($pId) {
                    $q->where('pharmacy_id', $pId);
                });
            } elseif (!empty($this->filters['target_pharmacy_ids'])) {
                $tIds = (array) $this->filters['target_pharmacy_ids'];
                $query->whereHas('receiving_details.receiving', function ($q) use ($tIds) {
                    $q->whereIn('pharmacy_id', $tIds);
                });
            }

            if (!empty($this->filters['price_diff'])) {
                $diff = $this->filters['price_diff'];
                $unitPriceSql = "CASE WHEN order_items.pack = 1 AND CAST(medicines.content AS UNSIGNED) > 1 THEN (CAST(receiving_items.raw_price AS DECIMAL(15,4)) / CAST(medicines.content AS DECIMAL(15,4))) ELSE CAST(receiving_items.raw_price AS DECIMAL(15,4)) END";

                $query->join('order_items', 'receiving_items.order_items_id', '=', 'order_items.id')
                      ->join('medicines', 'order_items.medicine_id', '=', 'medicines.id')
                      ->select('receiving_items.*');

                if ($diff === 'naik') {
                    $query->whereRaw("{$unitPriceSql} > (CAST(medicines.raw_price AS DECIMAL(15,4)) + 0.5)");
                } elseif ($diff === 'turun') {
                    $query->whereRaw("{$unitPriceSql} < (CAST(medicines.raw_price AS DECIMAL(15,4)) - 0.5)");
                } elseif ($diff === 'beda') {
                    $query->whereRaw("ABS({$unitPriceSql} - CAST(medicines.raw_price AS DECIMAL(15,4))) > 0.5");
                } elseif ($diff === 'sama') {
                    $query->whereRaw("ABS({$unitPriceSql} - CAST(medicines.raw_price AS DECIMAL(15,4))) <= 0.5");
                }
            }

            $job->update(['progress' => 50]);

            $items = $query->orderByDesc('receiving_items.id')->get();

            $job->update(['progress' => 70]);

            $fileName = 'riwayat_harga_beli_' . date('Ymd_His') . '_' . uniqid() . '.xlsx';
            $relativePath = 'exports/' . $fileName;

            Excel::store(
                new BuyPriceHistoryExport($items, $this->filters),
                $relativePath,
                'public'
            );

            $job->update([
                'status'    => 'completed',
                'progress'  => 100,
                'file_path' => $relativePath,
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status'   => 'failed',
                'progress' => 0,
            ]);
            Log::error('Buy Price History Export Job Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
