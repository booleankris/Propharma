<?php

namespace App\Exports\Stocks;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StockDataExport implements WithMultipleSheets
{
    protected $request;
    protected int $pharmacyId;

    public function __construct($request = null, $pharmacyId = null)
    {
        $this->request = $request;
        $this->pharmacyId = (int) ($pharmacyId ?? getActivePharmacyId());
    }

    public function sheets(): array
    {
        [$medicines, $metricsMap] = StockDataSheetExport::fetchMedicinesAndMetrics(
            $this->request,
            $this->pharmacyId
        );

        return [
            new StockDataSheetExport($this->request, $this->pharmacyId, $medicines, $metricsMap),
            new FinanceStockReconciliationSheetExport($this->pharmacyId, $medicines, $metricsMap),
        ];
    }
}
