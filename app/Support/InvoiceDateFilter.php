<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

class InvoiceDateFilter
{
    public static function apply(Builder $query, string $column, CarbonInterface $start, CarbonInterface $end): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $normalizedDate = "CASE WHEN instr($column, '/') > 0 THEN "
                . "substr($column, 7, 4) || '-' || substr($column, 4, 2) || '-' || substr($column, 1, 2) "
                . "ELSE substr($column, 1, 10) END";
            $expression = "date($normalizedDate)";
        } elseif ($driver === 'pgsql') {
            $expression = "COALESCE(to_date($column, 'DD/MM/YYYY'), to_date($column, 'YYYY-MM-DD'))";
        } else {
            $expression = "COALESCE(STR_TO_DATE($column, '%d/%m/%Y'), STR_TO_DATE($column, '%Y-%m-%d'))";
        }

        return $query->whereRaw("$expression BETWEEN ? AND ?", [
            $start->toDateString(),
            $end->toDateString(),
        ]);
    }
}
