<?php

namespace App\Services;

use App\Models\MedicineTransfers;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MedicineTransferCodeGenerator
{
    /**
     * Reserve the next monthly transfer number under a database row lock.
     * The BIGINT sequence and full numeric suffix support values through
     * trillions while remaining safe across concurrent application workers.
     */
    public static function generate(?Carbon $now = null): string
    {
        $now ??= now();
        $prefix = $now->format('ym') . 'MUT';

        return DB::transaction(function () use ($prefix) {
            $timestamp = now();
            DB::table('medicine_transfer_sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'last_number' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $sequence = DB::table('medicine_transfer_sequences')
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->first();

            $suffixPosition = strlen($prefix) + 1;
            $largestExisting = (int) (MedicineTransfers::query()
                ->where('code', 'like', $prefix . '%')
                ->whereRaw('SUBSTRING(code, ?) REGEXP ?', [$suffixPosition, '^[0-9]+$'])
                ->selectRaw('MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) AS max_number', [$suffixPosition])
                ->value('max_number') ?? 0);

            $currentNumber = max((int) $sequence->last_number, $largestExisting);
            if ($currentNumber >= PHP_INT_MAX) {
                throw new RuntimeException("Nomor mutasi untuk prefix {$prefix} sudah melewati kapasitas BIGINT.");
            }
            $nextNumber = $currentNumber + 1;

            $code = $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            while (MedicineTransfers::where('code', $code)->exists()) {
                $nextNumber++;
                if ($nextNumber > PHP_INT_MAX) {
                    throw new RuntimeException("Nomor mutasi untuk prefix {$prefix} sudah melewati kapasitas BIGINT.");
                }
                $code = $prefix . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }

            DB::table('medicine_transfer_sequences')->where('prefix', $prefix)->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

            return $code;
        }, 5);
    }
}
