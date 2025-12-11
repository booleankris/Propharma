<?php

namespace App\Exports\Export;

use App\Models\Patients;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PatientsExportExcel implements FromQuery, WithMapping, WithHeadings
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $q = Patients::query()->orderBy('id');

        if (!empty($this->filters['start_date'])) {
            $q->whereDate('created_at', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $q->whereDate('created_at', '<=', $this->filters['end_date']);
        }

        return $q;
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->name,
            $row->gender,
            $row->phone,
            $row->address,
            $row->created_at,
        ];
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Gender', 'Phone', 'Address', 'Created At'];
    }
}
