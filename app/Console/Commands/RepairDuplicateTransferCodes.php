<?php

namespace App\Console\Commands;

use App\Models\Batches;
use App\Models\ItemsLog;
use App\Models\MedicineTransfers;
use App\Services\MedicineTransferCodeGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RepairDuplicateTransferCodes extends Command
{
    protected $signature = 'transfers:repair-duplicate-codes {--code= : Repair one duplicated code only} {--apply : Apply changes; without this option the command is a dry run}';

    protected $description = 'Assign unique, ordered codes to duplicate transfer headers and their unambiguous stock logs';

    public function handle(): int
    {
        $groups = MedicineTransfers::query()
            ->select('code')
            ->selectRaw('COUNT(*) AS duplicate_count')
            ->whereNotNull('code')
            ->whereRaw('code REGEXP ?', ['^[0-9]{4}MUT[0-9]+$'])
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('code');

        if ($code = $this->option('code')) {
            $groups->where('code', $code);
        }

        $groups = $groups->get();
        if ($groups->isEmpty()) {
            $this->info('Tidak ada nomor mutasi duplikat.');
            return self::SUCCESS;
        }

        $apply = (bool) $this->option('apply');
        $this->info(($apply ? 'MODE APPLY' : 'MODE DRY-RUN') . ": {$groups->count()} kelompok nomor mutasi duplikat ditemukan.");

        foreach ($groups as $group) {
            $transfers = MedicineTransfers::with('items:id,medicine_transfer_id,batches_id,source_batches_id,requested_medicine_id')
                ->where('code', $group->code)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();
            $logs = ItemsLog::where('transaction_code', $group->code)->where('type', 'MU')->get();
            $logOwner = [];
            $ambiguous = [];

            foreach ($logs as $log) {
                $owners = [];
                foreach ($transfers as $transfer) {
                    foreach ($transfer->items as $item) {
                        $batchIds = array_filter([(int) $item->batches_id, (int) $item->source_batches_id]);
                        $medicineId = (int) ($item->requested_medicine_id
                            ?: Batches::whereKey($item->source_batches_id ?: $item->batches_id)->value('medicine_id'));

                        if ($medicineId === (int) $log->medicine_id && in_array((int) $log->batches_id, $batchIds, true)) {
                            $owners[$transfer->id] = true;
                        }
                    }
                }

                if (count($owners) !== 1) {
                    $ambiguous[] = $log->id;
                    continue;
                }
                $logOwner[$log->id] = (int) array_key_first($owners);
            }

            if ($ambiguous) {
                $this->warn("{$group->code}: dilewati, log #" . implode(', #', $ambiguous) . ' tidak bisa dipasangkan dengan tepat ke satu header mutasi.');
                continue;
            }

            $keep = $transfers->first();
            $this->line("{$group->code}: {$transfers->count()} header; mempertahankan kode pada mutasi #{$keep->id}.");

            foreach ($transfers->slice(1) as $transfer) {
                $monthDate = Carbon::createFromFormat('!ym', substr((string) $group->code, 0, 4))->startOfMonth();
                $newCode = $apply
                    ? MedicineTransferCodeGenerator::generate($monthDate)
                    : $this->nextPreviewCode((string) $group->code);

                $ownedLogIds = collect($logOwner)
                    ->filter(fn ($ownerId) => $ownerId === (int) $transfer->id)
                    ->keys()
                    ->all();

                if ($apply) {
                    DB::transaction(function () use ($transfer, $newCode, $ownedLogIds) {
                        MedicineTransfers::whereKey($transfer->id)->update(['code' => $newCode]);
                        if ($ownedLogIds) {
                            ItemsLog::whereIn('id', $ownedLogIds)->update(['transaction_code' => $newCode]);
                        }
                    });
                }

                $this->line("  Mutasi #{$transfer->id} -> {$newCode}; log terkait: " . count($ownedLogIds));
            }
        }

        $this->info($apply ? 'Perapian selesai.' : 'Simulasi selesai. Tidak ada data yang diubah. Jalankan ulang dengan --apply untuk menyimpan.');
        return self::SUCCESS;
    }

    private function nextPreviewCode(string $existingCode): string
    {
        static $nextByPrefix = [];
        $prefix = substr($existingCode, 0, 7);

        if (!isset($nextByPrefix[$prefix])) {
            $suffixPosition = strlen($prefix) + 1;
            $nextByPrefix[$prefix] = ((int) (MedicineTransfers::query()
                ->where('code', 'like', $prefix . '%')
                ->whereRaw('SUBSTRING(code, ?) REGEXP ?', [$suffixPosition, '^[0-9]+$'])
                ->selectRaw('MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) AS max_number', [$suffixPosition])
                ->value('max_number') ?? 0)) + 1;
        }

        $number = $nextByPrefix[$prefix]++;
        return $prefix . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }
}
