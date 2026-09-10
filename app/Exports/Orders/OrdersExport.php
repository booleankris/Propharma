<?php

namespace App\Exports\Orders;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OrdersExport implements WithMultipleSheets
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;

    public function __construct($pharmacyId, $startDate, $endDate)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate  = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate)->startOfDay();
        $this->endDate    = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate)->endOfDay();
    }

    public function sheets(): array
    {
        $targetPharmacyIds = in_array((int) $this->pharmacyId, [1, 6, 9])
            ? [9, 1]
            : [(int) $this->pharmacyId];

        $distinctPayments = DB::table('receiving_details')
            ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
            ->whereIn('receiving.pharmacy_id', $targetPharmacyIds)
            ->whereBetween('receiving_details.created_at', [$this->startDate, $this->endDate])
            ->whereNotNull('receiving_details.invoice_payment')
            ->where('receiving_details.invoice_payment', '!=', '')
            ->distinct()
            ->pluck('receiving_details.invoice_payment')
            ->map(fn($v) => strtoupper(trim($v)))
            ->unique()
            ->values()
            ->toArray();

        $sheetDefs = [
            ['title' => 'Semua', 'type' => null],
            ['title' => 'Kredit', 'type' => 'KREDIT'],
            ['title' => 'Tunai', 'type' => 'TUNAI'],
            ['title' => 'Konsinyasi', 'type' => 'KONSINYASI'],
        ];

        $standardTypes = ['KREDIT', 'TUNAI', 'KONSINYASI'];
        foreach ($distinctPayments as $payment) {
            if (!in_array($payment, $standardTypes, true)) {
                $sheetDefs[] = [
                    'title' => ucfirst(strtolower($payment)),
                    'type'  => $payment,
                ];
            }
        }

        $hasNullPayment = DB::table('receiving_details')
            ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
            ->whereIn('receiving.pharmacy_id', $targetPharmacyIds)
            ->whereBetween('receiving_details.created_at', [$this->startDate, $this->endDate])
            ->where(function ($q) {
                $q->whereNull('receiving_details.invoice_payment')
                  ->orWhere('receiving_details.invoice_payment', '');
            })
            ->exists();

        if ($hasNullPayment) {
            $sheetDefs[] = [
                'title' => 'Lainnya',
                'type'  => 'OTHER',
            ];
        }

        $sheets = [];
        foreach ($sheetDefs as $def) {
            $sheets[] = new OrdersSingleSheetExport(
                $this->pharmacyId,
                $this->startDate,
                $this->endDate,
                $def['type'],
                $def['title']
            );
        }

        return $sheets;
    }

    /**
     * Fallback for single sheet / array callers
     */
    public function array(): array
    {
        return (new OrdersSingleSheetExport(
            $this->pharmacyId,
            $this->startDate,
            $this->endDate,
            null,
            'Semua'
        ))->array();
    }
}
