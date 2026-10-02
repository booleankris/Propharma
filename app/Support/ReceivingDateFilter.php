<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

class ReceivingDateFilter
{
    public static function apply(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $receivingDate = "CASE WHEN receiving.date LIKE '__/__/____' THEN "
                . "substr(receiving.date, 7, 4) || '-' || substr(receiving.date, 4, 2) || '-' || substr(receiving.date, 1, 2) "
                . "WHEN receiving.date LIKE '____-__-__' THEN substr(receiving.date, 1, 10) "
                . "ELSE date(receiving.created_at) END";
        } elseif ($driver === 'pgsql') {
            $receivingDate = "CASE WHEN receiving.date ~ '^\\d{2}/\\d{2}/\\d{4}$' "
                . "THEN to_date(receiving.date, 'DD/MM/YYYY') "
                . "WHEN receiving.date ~ '^\\d{4}-\\d{2}-\\d{2}$' THEN to_date(receiving.date, 'YYYY-MM-DD') "
                . "ELSE DATE(receiving.created_at) END";
        } else {
            $receivingDate = "COALESCE("
                . "STR_TO_DATE(NULLIF(receiving.date, ''), '%d/%m/%Y'), "
                . "STR_TO_DATE(NULLIF(receiving.date, ''), '%Y-%m-%d'), "
                . "DATE(receiving.created_at)"
                . ")";
        }

        return $query->whereRaw("$receivingDate BETWEEN ? AND ?", [
            $start->toDateString(),
            $end->toDateString(),
        ]);
    }

    public static function format(?string $receivingDate, $fallback = null): string
    {
        $value = trim((string) ($receivingDate ?: $fallback));
        if ($value === '') {
            return '-';
        }

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            return $value;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return '-';
        }
    }
}
