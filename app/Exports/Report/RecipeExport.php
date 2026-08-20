<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class RecipeExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;

    public function __construct($pharmacyId, $startDate, $endDate, $shift, $shiftType)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate  = Carbon::parse($startDate)->startOfDay();
        $this->endDate    = Carbon::parse($endDate)->endOfDay();
        $this->shift      = $shift;
        $this->shiftType  = $shiftType;
    }

    public function array(): array
    {
        $pharmacy  = \App\Models\Pharmacies::find($this->pharmacyId);
        $shift     = $this->shift ? \App\Models\Shifts::find($this->shift) : null;
        $shiftLabel = $shift ? 'Shift ' . ucfirst(strtolower($shift->name)) : 'Semua Shift';

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Daftar Resep (' . $shiftLabel . ')'],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = $this->buildBody();

        return array_merge($header, $body);
    }

    private function buildBody(): array
    {
        $query = MedicineTransactions::with(['doctors', 'patients', 'shift_logs.shift'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', ['RESEP TUNAI', 'KREDIT'])
            ->where('status', 1)
            ->whereBetween('updated_at', [$this->startDate, $this->endDate]);

        if ($this->shiftType === 'shift' && !empty($this->shift)) {
            $query->whereHas('shift_logs', function ($q) {
                $q->where('shift_id', $this->shift);
            });
        }

        $transactions = $query->get();

        $rows   = [];
        $rows[] = ['No.', 'Tanggal', 'No. Resep', 'Layanan', 'Dokter', 'Pasien', 'Netto', 'Shift'];

        $no         = 1;
        $grandTotal = 0;

        foreach ($transactions as $trx) {
            $layanan = '-';
            if ($trx->transaction_type === 'KREDIT') {
                $layanan = 'UK';
            } elseif ($trx->transaction_type === 'RESEP TUNAI') {
                $layanan = 'UM';
            }

            $netto     = (int) ($trx->subtotal ?? 0);
            $shiftName = $trx->shift_logs?->shift?->name ?? '-';

            $rows[] = [
                $no++,
                Carbon::parse($trx->created_at)->format('d/m/Y'),
                $trx->transaction_code ?? '-',
                $layanan,
                $trx->doctors?->name  ?? '-',
                $trx->patients?->name ?? '-',
                $netto,
                $shiftName,
            ];

            $grandTotal += $netto;
        }

        $rows[] = ['', '', '', '', '', 'TOTAL', $grandTotal, ''];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = $sheet->getHighestColumn();
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7;

        // Merge header rows
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Column header row bold
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        // Borders on entire data area
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Row height
        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }

        // Left-align all data rows
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Number format on Netto (col G)
        $sheet->getStyle("G{$dataStartRow}:G{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 15,
            'C' => 15,
            'D' => 10,
            'E' => 30,
            'F' => 30,
            'G' => 20,
            'H' => 15,
        ];
    }

    public function title(): string
    {
        return 'RecipeExport';
    }
}
