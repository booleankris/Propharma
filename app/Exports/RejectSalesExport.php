<?php

namespace App\Exports;

use App\Models\Reject;
use App\Models\RejectFollowUpOption;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class RejectSalesExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected array $filters;
    protected int $rowNum = 0;

    public function __construct($pharmacyId, $startDate = null, $endDate = null, array $filters = [])
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->filters = $filters;
    }

    public function query()
    {
        RejectFollowUpOption::ensureTableExists();

        $query = Reject::with(['medicines.composition'])->where('pharmacy_id', $this->pharmacyId);

        if (!empty($this->startDate) && !empty($this->endDate)) {
            $query->whereBetween('date', [$this->startDate, $this->endDate]);
        } elseif (!empty($this->startDate)) {
            $query->whereDate('date', '>=', $this->startDate);
        } elseif (!empty($this->endDate)) {
            $query->whereDate('date', '<=', $this->endDate);
        }

        $search = trim((string) ($this->filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('medicines', function ($mq) use ($search) {
                    $mq->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('composition', fn($cq) => $cq->where('name', 'like', "%{$search}%"));
                })
                ->orWhere('medicine_name', 'like', "%{$search}%")
                ->orWhere('location', 'like', "%{$search}%")
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhere('follow_up_note', 'like', "%{$search}%")
                ->orWhere('stock_status', 'like', "%{$search}%")
                ->orWhere('follow_up', 'like', "%{$search}%")
                ->orWhere('follow_up_update', 'like', "%{$search}%");
            });
        }

        foreach (['location', 'stock_status', 'follow_up', 'follow_up_update'] as $col) {
            $val = $this->filters[$col] ?? '';
            if ($val !== '' && $val !== null) {
                if ($val === '__EMPTY__') {
                    $query->where(fn($q) => $q->whereNull($col)->orWhere($col, ''));
                } else {
                    $query->where($col, $val);
                }
            }
        }

        return $query->orderBy('date', 'desc')->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'TANGGAL',
            'NO',
            'NAMA OBAT',
            'SATUAN',
            'JUMLAH PENOLAKAN',
            'LOKASI',
            'ALASAN PENOLAKAN',
            'STATUS BARANG',
            'PERSAMAAN',
            'TINDAK LANJUT',
            'KETERANGAN',
            'UPDATE',
        ];
    }

    public function map($reject): array
    {
        $this->rowNum++;
        $composition = $reject->medicines?->composition?->name
            ?? $reject->medicines?->component
            ?? $reject->equivalent
            ?? '';
        $unit = $reject->unit ?? $reject->medicines?->unit ?? '';

        return [
            $reject->date ? \Carbon\Carbon::parse($reject->date)->format('d/m/Y') : '-',
            $this->rowNum,
            $reject->medicines ? $reject->medicines->name : ($reject->medicine_name ?? '-'),
            $unit,
            $reject->quantity,
            $reject->location ?? '',
            $reject->reason ?? '-',
            $reject->stock_status ?? '',
            $composition,
            $reject->follow_up ?? '',
            $reject->follow_up_note ?? '',
            $reject->follow_up_update ?? '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = max(1, $sheet->getHighestRow());
        $sheet->getStyle("A1:L1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '1E293B'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DBEAFE'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle("A1:L{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        if ($highestRow >= 2) {
            $sheet->getStyle("A2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D2:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J2:L{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        return [];
    }
}
