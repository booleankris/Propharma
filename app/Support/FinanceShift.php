<?php

namespace App\Support;

use Carbon\Carbon;

/** Operational business-day and shift rules used by Finance reports. */
class FinanceShift
{
    public const MORNING_START = '07:30:00';
    public const MORNING_END = '15:30:59';
    public const EVENING_START = '15:31:00';

    public static function start(string|Carbon $date): Carbon
    {
        return Carbon::parse($date)->startOfDay()->setTime(7, 30, 0);
    }

    /** Exclusive end of the operational date (07:30 on the following day). */
    public static function endExclusive(string|Carbon $date): Carbon
    {
        return Carbon::parse($date)->startOfDay()->addDay()->setTime(7, 30, 0);
    }

    public static function operationalDate(?Carbon $at): ?string
    {
        if (!$at) return null;
        return ($at->format('H:i:s') < self::MORNING_START
            ? $at->copy()->subDay()
            : $at)->toDateString();
    }

    public static function key(?Carbon $at): string
    {
        if (!$at) return 'otherShift';
        $time = $at->format('H:i:s');
        return $time >= self::MORNING_START && $time <= self::MORNING_END
            ? 'morning'
            : 'evening';
    }

    public static function sqlOperationalDate(string $column): string
    {
        return "CASE WHEN TIME({$column}) < '".self::MORNING_START."' THEN DATE(DATE_SUB({$column}, INTERVAL 1 DAY)) ELSE DATE({$column}) END";
    }
}
