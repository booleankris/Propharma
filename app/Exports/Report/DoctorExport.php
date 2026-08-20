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

class DoctorExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;
    protected $selectedType;
    protected $doctorId;

    public function __construct($pharmacyId, $startDate, $endDate, $shift, $shiftType, $selectedType, $doctorId = null)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->shift        = $shift;
        $this->shiftType    = $shiftType;
        $this->selectedType = $selectedType;
        $this->doctorId     = $doctorId;
    }

    public function array(): array
    {
        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan ' . ($this->selectedType === 'rekap' ? 'Dokter' : 'Daftar Transaksi Penjualan') . ' (' . ucfirst($this->selectedType) . ')'],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = $this->selectedType === 'rekap'
            ? $this->buildRecap()
            : $this->buildDetail();

        return array_merge($header, $body);
    }

    // REKAP — transactions with a specific doctor, show medicines sold
    private function buildRecap(): array
    {
        $query = MedicineTransactions::with(['transactions.medicine', 'doctors', 'patients'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', ['RESEP TUNAI', 'KREDIT'])
            ->where('status', 1)
            ->whereBetween('updated_at', [$this->startDate, $this->endDate]);
   

        $transactions = $query->get();

        $rows   = [];
        $rows[] = ['No.', 'Nama Dokter', 'Nama Obat', 'Qty', 'Jumlah'];

        $no         = 1;
        $grandQty   = 0;
        $grandTotal = 0;

        foreach ($transactions as $trx) {
            $doctorName = $trx->doctors?->name ?? '-';

            foreach ($trx->transactions ?? [] as $item) {
                $medicineName = $item->medicine?->name ?? '-';
                $qty          = (int) ($item->quantity ?? 0);
                $jumlah       = (int) ($item->final_price ?? 0);

                $rows[] = [
                    $no++,
                    $doctorName,
                    $medicineName,
                    $qty,
                    $jumlah,
                ];

                $grandQty   += $qty;
                $grandTotal += $jumlah;
            }
        }

        $rows[] = ['', 'Total Penjualan Dokter', '', $grandQty, $grandTotal];

        return $rows;
    }

    // DETAIL — transactions where doctor_id IS NULL
    private function buildDetail(): array
    {
        $transactions = MedicineTransactions::with(['doctors', 'patients'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', ['RESEP TUNAI', 'KREDIT'])
            ->where('status', 1)
            ->where('doctor_id', $this->doctorId)
            ->whereBetween('updated_at', [$this->startDate, $this->endDate])
            ->get();

        $rows   = [];
        $rows[] = ['No.', 'Tanggal', 'No Resep', 'Layanan', 'Dokter', 'Pasien', 'Jumlah'];

        $no         = 1;
        $grandTotal = 0;

        foreach ($transactions as $trx) {
            $layanan = '-';
            if ($trx->transaction_type === 'KREDIT') {
                $layanan = 'UK';
            } elseif ($trx->transaction_type === 'RESEP TUNAI') {
                $layanan = 'UM';
            }

            $jumlah = (int) ($trx->subtotal ?? 0);

            $rows[] = [
                $no++,
                Carbon::parse($trx->created_at)->format('d/m/Y'),
                $trx->transaction_code ?? '-',
                $layanan,
                $trx->doctors?->name ?? '-',
                $trx->patients?->name ?? '-',
                $jumlah,
            ];

            $grandTotal += $jumlah;
        }

        $rows[] = ['', '', '', '', '', 'TOTAL', $grandTotal];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol     = $sheet->getHighestColumn();
        $lastRow     = $sheet->getHighestRow();
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

        // Borders on data area
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Row height
        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }

        // Right-align & number format on Jumlah column
        $jumlahCol = $this->selectedType === 'rekap' ? 'E' : 'G';

        $sheet->getStyle("{$jumlahCol}{$dataStartRow}:{$jumlahCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("{$jumlahCol}{$dataStartRow}:{$jumlahCol}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Right-align Qty column in rekap
        if ($this->selectedType === 'rekap') {
            $sheet->getStyle("D{$dataStartRow}:D{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // Center-align Layanan column in detail
        if ($this->selectedType === 'detail') {
            $sheet->getStyle("D{$dataStartRow}:D{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function columnWidths(): array
    {
        return $this->selectedType === 'rekap'
            ? [
                'A' => 5,
                'B' => 35,
                'C' => 40,
                'D' => 10,
                'E' => 20,
            ]
            : [
                'A' => 5,
                'B' => 15,
                'C' => 15,
                'D' => 10,
                'E' => 30,
                'F' => 30,
                'G' => 20,
            ];
    }

    public function title(): string
    {
        return 'DoctorExport';
    }
}
