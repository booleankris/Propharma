<?php

namespace App\Exports\Report;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesDataExport implements WithMultipleSheets
{
    protected $pharmacyId;
    protected $dateFrom;
    protected $dateTo;
    protected $channel;
    protected $search;
    protected $mode;
    protected $pharmacyName;

    public function __construct(
        $pharmacyId,
        $dateFrom = null,
        $dateTo = null,
        $channel = null,
        $search = null,
        $mode = 'all',
        $pharmacyName = ''
    ) {
        $this->pharmacyId   = $pharmacyId;
        $this->dateFrom     = $dateFrom;
        $this->dateTo       = $dateTo;
        $this->channel      = $channel;
        $this->search       = $search;
        $this->mode         = strtolower(trim((string) $mode));
        $this->pharmacyName = $pharmacyName;
    }

    public function sheets(): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $sheets = [];

        if ($this->mode === 'rekap') {
            $sheets[] = new SalesDataRecapSheet(
                $this->pharmacyId,
                $this->dateFrom,
                $this->dateTo,
                $this->channel,
                $this->search,
                $this->pharmacyName
            );
        } elseif ($this->mode === 'detail') {
            $sheets[] = new SalesDataDetailSheet(
                $this->pharmacyId,
                $this->dateFrom,
                $this->dateTo,
                $this->channel,
                $this->search,
                $this->pharmacyName
            );
        } else {
            // Mode 'all': Sheet 1 Rekap, Sheet 2 Detail
            $sheets[] = new SalesDataRecapSheet(
                $this->pharmacyId,
                $this->dateFrom,
                $this->dateTo,
                $this->channel,
                $this->search,
                $this->pharmacyName
            );
            $sheets[] = new SalesDataDetailSheet(
                $this->pharmacyId,
                $this->dateFrom,
                $this->dateTo,
                $this->channel,
                $this->search,
                $this->pharmacyName
            );
        }

        return $sheets;
    }
}
