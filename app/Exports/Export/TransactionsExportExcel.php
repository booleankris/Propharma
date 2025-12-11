<?php

namespace App\Exports\Export;

use App\Models\MedicineCart;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransactionsExportExcel implements FromCollection, WithHeadings
{
    protected $start;
    protected $end;

    public function __construct($start = null, $end = null)
    {
        $this->start = $start;
        $this->end   = $end;
    }

    public function headings(): array
    {
        return [
            'Transaction Code',
            'Medicine Name',
            'Quantity',
            'Discount',
            'Embalase',
            'Cart Type',
            'Package',
            'Dosage R',
            'Raw Total',
            'Total Price',
            'Final Price',
        ];
    }

    public function collection()
    {
        $query = MedicineCart::with(['medicine', 'transactions']);

        if ($this->start && $this->end) {
            $query->whereBetween('created_at', [$this->start, $this->end]);
        }

        return $query->get()->map(function ($row) {
            return [
                $row->transactions?->transaction_code,
                $row->medicine?->name,
                $row->quantity,
                $row->discount,
                $row->embalase,
                $row->cart_type,
                $row->package,
                $row->dosage_r,
                $row->raw_total,
                $row->total_price,
                $row->final_price,
            ];
        });
    }
}
