<?php

namespace App\Exports\Stocks;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockNotificationExport implements WithMultipleSheets
{
    /** @var array<int, array<string, mixed>> */
    protected array $lowStockItems;

    /** @var array<int, array<string, mixed>> */
    protected array $nearExpiryItems;

    protected string $branchLabel;
    protected string $tab;
    protected int $daysThreshold;

    /**
     * @param array<int, array<string, mixed>> $lowStockItems
     * @param array<int, array<string, mixed>> $nearExpiryItems
     */
    public function __construct(
        array $lowStockItems,
        array $nearExpiryItems,
        string $branchLabel,
        string $tab = 'all',
        int $daysThreshold = 90
    ) {
        $this->lowStockItems = $lowStockItems;
        $this->nearExpiryItems = $nearExpiryItems;
        $this->branchLabel = $branchLabel;
        $this->tab = in_array($tab, ['all', 'low_stock', 'near_expiry'], true) ? $tab : 'all';
        $this->daysThreshold = $daysThreshold;
    }

    public function sheets(): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $sheets = [];

        if ($this->tab === 'all' || $this->tab === 'low_stock') {
            $sheets[] = new StockNotificationLowStockSheet($this->lowStockItems, $this->branchLabel);
        }

        if ($this->tab === 'all' || $this->tab === 'near_expiry') {
            $sheets[] = new StockNotificationNearExpirySheet($this->nearExpiryItems, $this->branchLabel, $this->daysThreshold);
        }

        return $sheets;
    }
}
