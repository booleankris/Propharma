<?php

namespace App\Console\Commands;

use App\Models\MedicineTransferItems;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTransferOriginalQty extends Command
{
    protected $signature = 'transfers:backfill-original-qty {--apply : Save rows that have exactly one matching mutation log}';

    protected $description = 'Recover immutable transfer receipt quantities from uniquely linked mutation logs';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $matched = 0;
        $ambiguous = 0;
        $missing = 0;

        MedicineTransferItems::query()
            ->whereNull('original_qty')
            ->with('transfer:id,code')
            ->orderBy('id')
            ->chunkById(500, function ($items) use ($apply, &$matched, &$ambiguous, &$missing) {
                foreach ($items as $item) {
                    $code = $item->transfer?->code;
                    $sourceBatchId = $item->source_batches_id ?: $item->batches_id;

                    if (!$code || !$sourceBatchId) {
                        $missing++;
                        continue;
                    }

                    $logs = DB::table('items_log')
                        ->where('transaction_code', $code)
                        ->where('batches_id', $sourceBatchId)
                        ->where('type', 'MU')
                        ->where('status', 7)
                        ->get(['id', 'qty']);

                    if ($logs->count() !== 1) {
                        $logs->isEmpty() ? $missing++ : $ambiguous++;
                        continue;
                    }

                    $qty = (int) $logs->first()->qty;
                    $matched++;
                    $this->line("Detail {$item->id}: {$item->qty} saat ini → qty mutasi awal {$qty}");

                    if ($apply) {
                        $item->original_qty = $qty;
                        $item->save();
                    }
                }
            });

        $this->newLine();
        $this->info(($apply ? 'Disimpan' : 'Pratinjau') . ": {$matched} cocok, {$ambiguous} ambigu (dilewati), {$missing} tidak ditemukan (dilewati).");
        if (!$apply) {
            $this->comment('Jalankan ulang dengan --apply setelah hasil pratinjau diperiksa.');
        }

        return self::SUCCESS;
    }
}
