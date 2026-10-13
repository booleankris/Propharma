<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Shared transaction filters for LIPH and payment-method sales reports.
 */
class LiphTransactionScope
{
    public static function applySalesStatus(Builder $query): Builder
    {
        return $query->where(function (Builder $status) {
            $status->where('status', 1)
                ->orWhere(function (Builder $unpaid) {
                    $unpaid->whereNotNull('transaction_code')
                        ->whereHas('transactions', fn (Builder $cart) => $cart->where('status', 1))
                        ->where(function (Builder $unpaidType) {
                            $unpaidType->whereRaw("UPPER(TRIM(COALESCE(payment_method, ''))) = 'BELUM BAYAR'")
                                ->orWhereIn('transaction_type', ['KREDIT', 'RESEP KREDIT']);
                        });
                });
        });
    }

    public static function applyPeriod(
        Builder $query,
        Carbon $start,
        Carbon $end,
        ?string $shiftType = 'semua',
        $shift = null
    ): Builder {
        $start = $start->copy()->startOfDay();
        $until = $end->copy()->endOfDay()->addDay()->startOfDay();

        $query->where(function (Builder $period) use ($start, $until) {
            $period->whereHas('shift_logs', function (Builder $shiftQuery) use ($start, $until) {
                $shiftQuery->where('clock_in', '>=', $start)->where('clock_in', '<', $until);
            })->orWhere(function (Builder $withoutShiftTime) use ($start, $until) {
                $withoutShiftTime->whereDoesntHave('shift_logs', fn (Builder $q) => $q->whereNotNull('clock_in'))
                    ->where(function (Builder $recorded) use ($start, $until) {
                        $recorded->where('created_at', '>=', $start)->where('created_at', '<', $until)
                            ->orWhere(function (Builder $legacy) use ($start, $until) {
                                $legacy->whereNull('created_at')
                                    ->where('updated_at', '>=', $start)->where('updated_at', '<', $until);
                            });
                    });
            });
        });

        if (in_array($shiftType, ['shift', 'online'], true) && !empty($shift)) {
            $query->whereHas('shift_logs', fn (Builder $q) => $q->where('shift_id', $shift));
        }

        return $query;
    }
}
