<?php

namespace App\Services;

use App\Models\MedicineTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MedicineTransactionCodeGenerator
{
    /**
     * Reserve a transaction number while holding a database row lock.
     * The lock is shared by all workers, computers, and tabs using this DB.
     */
    public static function generate(string $typeCode, ?int $pharmacyId = null): string
    {
        $pharmacyId ??= (int) getActivePharmacyId();
        $pharmacyIdPadded = str_pad((string) $pharmacyId, 2, '0', STR_PAD_LEFT);
        $prefix = now()->format('ym') . strtoupper($typeCode);
        $sequenceKey = $prefix . $pharmacyIdPadded;

        return DB::transaction(function () use ($prefix, $sequenceKey, $pharmacyIdPadded, $pharmacyId) {
            $timestamp = now();
            DB::table('medicine_transaction_sequences')->insertOrIgnore([
                'sequence_key' => $sequenceKey,
                'last_number' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $sequence = DB::table('medicine_transaction_sequences')
                ->where('sequence_key', $sequenceKey)
                ->lockForUpdate()
                ->first();

            // Recover safely from codes created before the sequence table existed.
            $suffixStart = strlen($prefix) + 1;
            $largestExisting = (int) (MedicineTransactions::query()
                ->where('pharmacy_id', $pharmacyId)
                ->where('transaction_code', 'like', $prefix . '%' . $pharmacyIdPadded)
                ->whereRaw('LENGTH(transaction_code) > ?', [strlen($prefix) + strlen($pharmacyIdPadded)])
                ->whereRaw('SUBSTRING(transaction_code, ?, LENGTH(transaction_code) - ? - ?) REGEXP ?', [
                    $suffixStart,
                    strlen($prefix),
                    strlen($pharmacyIdPadded),
                    '^[0-9]+$',
                ])
                ->selectRaw('MAX(CAST(SUBSTRING(transaction_code, ?, LENGTH(transaction_code) - ? - ?) AS UNSIGNED)) AS max_number', [
                    $suffixStart,
                    strlen($prefix),
                    strlen($pharmacyIdPadded),
                ])
                ->value('max_number') ?? 0);

            $nextNumber = max((int) $sequence->last_number, $largestExisting) + 1;
            $code = self::format($prefix, $nextNumber, $pharmacyIdPadded);

            // Also skip any legacy collision. The sequence-row lock prevents
            // concurrent callers for this branch/type/month from selecting it.
            while (MedicineTransactions::where('transaction_code', $code)->exists()) {
                $nextNumber++;
                if ($nextNumber > PHP_INT_MAX) {
                    throw new RuntimeException("Nomor transaksi untuk {$sequenceKey} melewati kapasitas integer.");
                }
                $code = self::format($prefix, $nextNumber, $pharmacyIdPadded);
            }

            DB::table('medicine_transaction_sequences')
                ->where('sequence_key', $sequenceKey)
                ->update(['last_number' => $nextNumber, 'updated_at' => now()]);

            return $code;
        }, 5);
    }

    private static function format(string $prefix, int $number, string $pharmacyIdPadded): string
    {
        return $prefix . str_pad((string) $number, 4, '0', STR_PAD_LEFT) . $pharmacyIdPadded;
    }
}
