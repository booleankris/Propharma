<?php

namespace App\Exports\Orders;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SmartOrderExport implements WithMultipleSheets
{
    public function __construct(
        private array $factorySheets,
        private array $filters = []
    ) {}

    public function sheets(): array
    {
        $sheets = [];
        $usedTitles = [];

        foreach ($this->factorySheets as $factorySheet) {
            $factoryName = $factorySheet['title'] ?? 'Tanpa Pabrik';
            $items = $factorySheet['items'] ?? [];
            $baseTitle = trim(preg_replace('/[\\\\\/\\?\\*:\\[\\]]/', ' ', (string) $factoryName)) ?: 'Tanpa Pabrik';
            $baseTitle = mb_substr($baseTitle, 0, 31);
            $title = $baseTitle;
            $suffix = 2;
            while (isset($usedTitles[mb_strtolower($title)])) {
                $ending = ' (' . $suffix++ . ')';
                $title = mb_substr($baseTitle, 0, 31 - mb_strlen($ending)) . $ending;
            }
            $usedTitles[mb_strtolower($title)] = true;
            $sheets[] = new SmartOrderFactorySheet($title, $items, $this->filters);
        }

        return $sheets ?: [new SmartOrderFactorySheet('Tanpa Pabrik', [], $this->filters)];
    }
}
