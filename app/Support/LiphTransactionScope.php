<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

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
                })
                // ReturController stores a separate RT header and copies the
                // original sale status. Returns from an unpaid credit sale
                // therefore have status 0 and no cart row, but are finalized
                // financial movements and must still reduce LIPH.
                ->orWhere(function (Builder $return) {
                    $return->whereIn('transaction_type', ['RETUR JUAL', 'RETUR KREDIT'])
                        ->where('transaction_code', 'RT');
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
        // LIPH dates follow the pharmacy's operational day: 07:30 through
        // 07:29:59 the next calendar day.
        $start = $start->copy()->startOfDay()->setTime(7, 30, 0);
        $until = $end->copy()->startOfDay()->addDay()->setTime(7, 30, 0);

        $query->where(function (Builder $period) use ($start, $until) {
            $period->where(function (Builder $main) use ($start, $until) {
                $main->where('created_at', '>=', $start)->where('created_at', '<', $until);
            })->orWhere(function (Builder $legacy) use ($start, $until) {
                $legacy->whereNull('created_at')
                    ->where('updated_at', '>=', $start)->where('updated_at', '<', $until);
            });
        });

        if (in_array($shiftType, ['shift', 'online'], true) && !empty($shift)) {
            $shiftObj = is_numeric($shift) ? \App\Models\Shifts::find($shift) : null;
            $shiftName = $shiftObj ? strtoupper(trim($shiftObj->name)) : strtoupper(trim((string) $shift));

            if ($shiftName === 'PAGI' || (string) $shift === '2') {
                $query->where(function (Builder $q) {
                    $q->where(function (Builder $time) {
                        $time->whereTime('created_at', '>=', '07:30:00')->whereTime('created_at', '<=', '15:30:59');
                    })->orWhere(function (Builder $sub) {
                        $sub->whereNull('created_at')->whereTime('updated_at', '>=', '07:30:00')->whereTime('updated_at', '<=', '15:30:59');
                    });
                });
            } elseif (in_array($shiftName, ['SORE', 'SIANG', 'MALAM'], true) || in_array((string) $shift, ['1', '3'], true)) {
                // Legacy shift IDs/names are normalized to the new Sore shift.
                $query->where(function (Builder $q) {
                    $q->where(function (Builder $time) {
                        $time->whereTime('created_at', '>=', '15:31:00')->orWhereTime('created_at', '<', '07:30:00');
                    })->orWhere(function (Builder $sub) {
                        $sub->whereNull('created_at')->where(function (Builder $time) {
                            $time->whereTime('updated_at', '>=', '15:31:00')->orWhereTime('updated_at', '<', '07:30:00');
                        });
                    });
                });
            } else {
                $query->whereHas('shift_logs', fn (Builder $q) => $q->where('shift_id', $shift));
            }
        }

        return $query;
    }
}
