<?php

namespace App\Exports\Report;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Multi-sheet LIPH export for Online channels.
 * Creates 3 separate sheets:
 *   1. Online (WA)     → role "Online"
 *   2. Online Grab     → role "Online Grab"
 *   3. Online Shopee   → role "Online Shopee"
 */
class LiphOnlineExport implements WithMultipleSheets
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $pharmacyAddress;
    protected $shift;

    public const ONLINE_CHANNELS = [
        'Semua Online'   => ['Online', 'Online Grab', 'Online Shopee'],
        'Online (WA)'    => 'Online',
        'Online Grab'    => 'Online Grab',
        'Online Shopee'  => 'Online Shopee',
    ];

    public function __construct($pharmacyId, $startDate, $endDate, $pharmacyName = '', $pharmacyAddress = '', $shift = null)
    {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = $startDate;
        $this->endDate         = $endDate;
        $this->pharmacyName    = $pharmacyName;
        $this->pharmacyAddress = $pharmacyAddress;
        $this->shift           = $shift;
    }

    public function sheets(): array
    {
        $sheets = [];

        foreach (self::ONLINE_CHANNELS as $sheetTitle => $roleName) {
            $sheets[] = new LiphExport(
                $this->pharmacyId,
                $this->startDate,
                $this->endDate,
                $this->pharmacyName,
                $this->pharmacyAddress,
                $this->shift,
                'online',       // shiftType
                $roleName,      // onlineRole
                $sheetTitle,    // customTitle (sheet tab name)
            );
        }

        return $sheets;
    }
}
