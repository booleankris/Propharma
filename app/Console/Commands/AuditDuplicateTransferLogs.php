<?php

namespace App\Console\Commands;

use App\Models\ItemsLog;
use App\Models\MedicineTransfers;
use Illuminate\Console\Command;

class AuditDuplicateTransferLogs extends Command
{
    protected $signature = 'transfers:audit-duplicate-logs {--code= : Audit one transfer code only}';

    protected $description = 'Report possible repeated incoming transfer stock logs without changing data';

    public function handle(): int
    {
        $query = ItemsLog::query()
            ->select('transaction_code', 'medicine_id', 'batches_id', 'qty')
            ->selectRaw('COUNT(*) AS log_count, MIN(date) AS first_log, MAX(date) AS last_log')
            ->where('type', 'MU')
            ->where('status', 7)
            ->whereNotNull('transaction_code')
            ->groupBy('transaction_code', 'medicine_id', 'batches_id', 'qty')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('transaction_code');

        if ($code = $this->option('code')) {
            $query->where('transaction_code', $code);
        }

        $groups = $query->get();

        if ($groups->isEmpty()) {
            $this->info('Tidak ditemukan kelompok log mutasi masuk berulang. Tidak ada data yang diubah.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($groups as $group) {
            $logs = ItemsLog::with(['medicines:id,name', 'batches:id,name'])
                ->where('transaction_code', $group->transaction_code)
                ->where('medicine_id', $group->medicine_id)
                ->where('batches_id', $group->batches_id)
                ->where('qty', $group->qty)
                ->where('type', 'MU')
                ->where('status', 7)
                ->orderBy('date')
                ->orderBy('id')
                ->get();

            $transfers = MedicineTransfers::with([
                'sourcePharmacy:id,name',
                'destinationPharmacy:id,name',
                'items:id,medicine_transfer_id,source_batches_id,batches_id,source_type,qty,status',
            ])
                ->where('code', $group->transaction_code)
                ->get();

            $transferSummary = $transfers->map(function ($transfer) {
                $itemSummary = $transfer->items->map(function ($item) {
                    return sprintf(
                        'item#%s %s→%s qty=%s status=%s',
                        $item->id,
                        $item->source_batches_id ?? '?',
                        $item->batches_id ?? '?',
                        $item->qty,
                        $item->status
                    );
                })->implode(', ');

                return sprintf(
                    '#%s %s → %s status=%s [%s]',
                    $transfer->id,
                    $transfer->sourcePharmacy?->name ?? $transfer->source_pharmacy_id ?? '?',
                    $transfer->destinationPharmacy?->name ?? $transfer->destination_pharmacy_id ?? '?',
                    $transfer->status,
                    $itemSummary ?: 'tanpa item'
                );
            })->implode('; ');

            $beforeAfter = $logs->map(fn ($log) => "{$log->qty_before}→{$log->qty_after} [log #{$log->id}]")->implode('; ');
            $rows[] = [
                $group->transaction_code,
                $logs->first()?->medicines?->name ?? "Medicine #{$group->medicine_id}",
                $logs->first()?->batches?->name ?? "Batch #{$group->batches_id}",
                $group->qty,
                $group->log_count,
                $group->first_log,
                $group->last_log,
                $beforeAfter,
                $transferSummary ?: 'Transfer header tidak ditemukan',
            ];
        }

        $this->table(
            ['Kode Mutasi', 'Obat', 'Batch Tujuan', 'Qty / log', 'Jumlah log', 'Log pertama', 'Log terakhir', 'Sebelum→Sesudah', 'Transfer / Cabang'],
            $rows
        );
        $this->newLine();
        $this->warn('Ini hanya kandidat duplikasi berdasarkan kode, obat, batch, dan qty. Baris dengan atribut identik bisa juga merupakan item sah yang berulang. Tidak ada stok atau data yang diubah.');

        return self::SUCCESS;
    }
}
