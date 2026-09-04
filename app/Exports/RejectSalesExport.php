<?php

namespace App\Exports;

use App\Models\Reject;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RejectSalesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;

    public function __construct($pharmacyId, $startDate, $endDate)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function query()
    {
        $query = Reject::with(['medicines'])->where('pharmacy_id', $this->pharmacyId);

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('date', [$this->startDate, $this->endDate]);
        }

        return $query->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID Transaksi',
            'Kode Penolakan',
            'Tanggal',
            'Nama Obat',
            'Jumlah Ditolak',
            'Satuan',
            'Total (Rp)',
            'Alasan',
        ];
    }

    public function map($reject): array
    {
        return [
            $reject->id,
            $reject->code,
            $reject->date,
            $reject->medicines ? $reject->medicines->name : ($reject->medicine_name ?? '-'),
            $reject->quantity,
            $reject->unit ?? $reject->medicines?->unit ?? '-',
            $reject->total,
            $reject->reason,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
