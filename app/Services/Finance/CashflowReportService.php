<?php

namespace App\Services\Finance;

use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class CashflowReportService
{
    private const BANKS = ['BNI', 'BRI', 'MANDIRI', 'BCA', 'BPD', 'BTN', 'OTHER'];

    /** Build the daily cashflow sheet for a bounded date period. */
    public function build(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $itemDiscounts = DB::table('medicine_cart')
            ->select('transaction_id')
            ->selectRaw("SUM(GREATEST(CAST(COALESCE(NULLIF(total_price, ''), '0') AS DECIMAL(15,2)) - CAST(COALESCE(NULLIF(final_price, ''), NULLIF(total_price, ''), '0') AS DECIMAL(15,2)), 0)) as item_discount")
            ->where('status', 1)
            ->groupBy('transaction_id');

        $records = DB::table('medicine_transactions as mt')
            ->leftJoin('shift_logs as sl', 'sl.id', '=', 'mt.shift_logs_id')
            ->leftJoin('shift as s', 's.id', '=', 'sl.shift_id')
            ->leftJoinSub($itemDiscounts, 'cart_discounts', fn ($join) => $join->on('cart_discounts.transaction_id', '=', 'mt.id'))
            ->where('mt.status', 1)
            ->whereIn('mt.pharmacy_id', $pharmacyIds)
            ->whereDate('mt.created_at', '>=', $startDate)
            ->whereDate('mt.created_at', '<=', $endDate)
            ->selectRaw("DATE(mt.created_at) as sale_date, s.name as shift_name, UPPER(TRIM(COALESCE(mt.payment_method, ''))) as payment_method, TRIM(COALESCE(mt.transfer_bank_name, '')) as bank_name, mt.transaction_type, COUNT(*) as transaction_count, SUM(CAST(COALESCE(NULLIF(mt.subtotal, ''), '0') AS DECIMAL(15,2)) - CAST(COALESCE(NULLIF(mt.discount, ''), '0') AS DECIMAL(15,2))) as amount, SUM(CAST(COALESCE(NULLIF(mt.subtotal, ''), '0') AS DECIMAL(15,2)) + COALESCE(cart_discounts.item_discount, 0)) as gross_amount, SUM(CAST(COALESCE(NULLIF(mt.discount, ''), '0') AS DECIMAL(15,2))) as transaction_discount, SUM(COALESCE(cart_discounts.item_discount, 0)) as item_discount")
            ->groupByRaw("DATE(mt.created_at), s.name, UPPER(TRIM(COALESCE(mt.payment_method, ''))), TRIM(COALESCE(mt.transfer_bank_name, '')), mt.transaction_type")
            ->orderBy('sale_date')
            ->get();

        $emptyShift = function () {
            $banks = [];
            foreach (self::BANKS as $bank) {
                $banks[$bank] = ['edc' => 0, 'transfer' => 0, 'qris' => 0, 'edcCount' => 0, 'transferCount' => 0, 'qrisCount' => 0];
            }

            return ['cash' => 0, 'cashCount' => 0, 'banks' => $banks, 'otherMethods' => [], 'discount' => 0, 'itemDiscount' => 0, 'total' => 0, 'edcPending' => 0, 'directDeposit' => 0, 'count' => 0];
        };

        $days = [];
        foreach ($records as $record) {
            $date = $record->sale_date;
            $day = $days[$date] ?? [
                'date' => $date,
                'morning' => $emptyShift(),
                'evening' => $emptyShift(),
                'otherShift' => $emptyShift(),
            ];

            $shiftName = mb_strtolower(trim((string) $record->shift_name));
            $shiftKey = str_contains($shiftName, 'pagi') ? 'morning' : (str_contains($shiftName, 'malam') ? 'evening' : 'otherShift');
            $method = $record->payment_method;
            $bank = mb_strtoupper(trim((string) $record->bank_name));
            $bankKey = 'OTHER';
            foreach (['BNI', 'BRI', 'MANDIRI', 'BCA', 'BPD', 'BTN'] as $knownBank) {
                if (str_contains($bank, $knownBank)) {
                    $bankKey = $knownBank;
                    break;
                }
            }

            // Kolom omzet memakai nilai bruto; kolom metode bayar/setoran tetap memakai nilai bersih.
            $amount = (float) $record->amount;
            $grossAmount = (float) $record->gross_amount;
            if ($record->transaction_type === 'RETUR JUAL') {
                $amount = -abs($amount);
                $grossAmount = -abs($grossAmount);
            }
            $count = (int) $record->transaction_count;
            $shift = &$day[$shiftKey];
            $shift['count'] += $count;
            $shift['discount'] += (float) $record->transaction_discount;
            $shift['itemDiscount'] += (float) $record->item_discount;
            $shift['total'] += $grossAmount;

            if (in_array($method, ['CASH', 'TUNAI'], true)) {
                $shift['cash'] += $amount;
                $shift['cashCount'] += $count;
                $shift['directDeposit'] += $amount;
            } elseif (in_array($method, ['DEBIT', 'EDC'], true)) {
                $shift['banks'][$bankKey]['edc'] += $amount;
                $shift['banks'][$bankKey]['edcCount'] += $count;
                $shift['edcPending'] += $amount;
            } elseif ($method === 'QRIS') {
                $shift['banks'][$bankKey]['transfer'] += $amount;
                $shift['banks'][$bankKey]['qris'] += $amount;
                $shift['banks'][$bankKey]['transferCount'] += $count;
                $shift['banks'][$bankKey]['qrisCount'] += $count;
                $shift['directDeposit'] += $amount;
            } elseif ($method === 'TRANSFER') {
                $shift['banks'][$bankKey]['transfer'] += $amount;
                $shift['banks'][$bankKey]['transferCount'] += $count;
                $shift['directDeposit'] += $amount;
            } else {
                $methodLabel = $method !== '' ? $method : 'Metode tidak tercatat';
                $shift['otherMethods'][$methodLabel] ??= ['label' => $methodLabel, 'amount' => 0, 'count' => 0];
                $shift['otherMethods'][$methodLabel]['amount'] += $amount;
                $shift['otherMethods'][$methodLabel]['count'] += $count;
            }

            unset($shift);
            $days[$date] = $day;
        }

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $key = $date->toDateString();
            $days[$key] ??= ['date' => $key, 'morning' => $emptyShift(), 'evening' => $emptyShift(), 'otherShift' => $emptyShift()];
        }

        ksort($days);
        foreach ($days as &$day) {
            foreach (['morning', 'evening', 'otherShift'] as $shiftKey) {
                $day[$shiftKey]['otherMethods'] = array_values($day[$shiftKey]['otherMethods']);
            }
            $day['total'] = $day['morning']['total'] + $day['evening']['total'] + $day['otherShift']['total'];
            $day['deposit'] = $day['morning']['directDeposit'] + $day['evening']['directDeposit'] + $day['otherShift']['directDeposit'];
            $day['edcPending'] = $day['morning']['edcPending'] + $day['evening']['edcPending'] + $day['otherShift']['edcPending'];
        }
        unset($day);

        return array_values($days);
    }
}
