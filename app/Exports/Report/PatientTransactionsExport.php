<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Models\Patients;
use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PatientTransactionsExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $patientId;
    protected $startDate;
    protected $endDate;
    protected $mode;  // 'detail' | 'rekap'
    protected $pharmacyId;

    public function __construct($patientId = null, $startDate = null, $endDate = null, $mode = 'rekap', $pharmacyId = null)
    {
        $this->patientId = ($patientId === 'all' || empty($patientId)) ? null : $patientId;
        $this->startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
        $this->endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();
        $this->mode = strtolower($mode) === 'detail' ? 'detail' : 'rekap';
        $this->pharmacyId = ($pharmacyId === 'all' || empty($pharmacyId)) ? null : $pharmacyId;
    }

    public function title(): string
    {
        return 'Transaksi Pasien (' . ucfirst($this->mode) . ')';
    }

    public function columnWidths(): array
    {
        if ($this->mode === 'rekap') {
            return [
                'A' => 8,  // No
                'B' => 25,  // Nama Pasien
                'C' => 16,  // No. Telp
                'D' => 20,  // Kode Transaksi
                'E' => 18,  // Tanggal Transaksi
                'F' => 12,  // Jam
                'G' => 25,  // Apotek
                'H' => 18,  // Total
                'I' => 16,  // Pembayaran
                'J' => 18,  // Tipe
            ];
        }

        return [
            'A' => 8,  // No
            'B' => 25,  // Nama Pasien
            'C' => 16,  // No. Telp
            'D' => 20,  // Kode Transaksi
            'E' => 18,  // Tanggal Transaksi
            'F' => 12,  // Jam
            'G' => 25,  // Apotek
            'H' => 35,  // Nama Obat
            'I' => 16,  // Harga
            'J' => 12,  // Qty
            'K' => 18,  // Total
            'L' => 16,  // Pembayaran
            'M' => 18,  // Tipe
        ];
    }

    public function array(): array
    {
        $pharmacyTitle = 'SEMUA APOTEK';
        if ($this->pharmacyId) {
            $ph = Pharmacies::find($this->pharmacyId);
            if ($ph) {
                $pharmacyTitle = strtoupper($ph->name);
            }
        }

        $patientTitle = 'SEMUA PASIEN';
        if ($this->patientId) {
            $p = Patients::find($this->patientId);
            if ($p) {
                $patientTitle = strtoupper($p->name) . ($p->phone ? ' (' . $p->phone . ')' : '');
            }
        }

        $header = [
            [$pharmacyTitle],
            ['LAPORAN TRANSAKSI / PENJUALAN PER PASIEN (' . strtoupper($this->mode) . ')'],
            ['Pasien   : ' . $patientTitle],
            ['Periode  : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = $this->mode === 'detail' ? $this->buildDetail() : $this->buildRecap();

        return array_merge($header, $body);
    }

    private function getBaseQuery()
    {
        $query = MedicineTransactions::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->where('status', 1);

        if ($this->patientId) {
            $query->where('patient_id', $this->patientId);
        }

        if ($this->pharmacyId) {
            $query->where('pharmacy_id', $this->pharmacyId);
        }

        return $query->orderBy('created_at', 'ASC');
    }

    private function buildRecap(): array
    {
        $rows = [];
        $rows[] = [
            'No.',
            'Nama Pasien',
            'No. Telp',
            'Kode Transaksi',
            'Tanggal Transaksi',
            'Jam',
            'Apotek',
            'Total',
            'Pembayaran',
            'Tipe'
        ];

        $transactions = $this
            ->getBaseQuery()
            ->with(['patients', 'pharmacy'])
            ->get();

        $no = 1;
        $grandTotal = 0;

        foreach ($transactions as $trx) {
            $patientName = $trx->patients?->name ?? 'Umum / Tanpa Pasien';
            $patientPhone = $trx->patients?->phone ?? '-';
            $pharmacyName = $trx->pharmacy?->name ?? '-';
            $total = (float) ($trx->subtotal ?? 0);

            $rows[] = [
                $no++,
                $patientName,
                $patientPhone,
                $trx->transaction_code ?? '-',
                $trx->created_at ? $trx->created_at->format('d/m/Y') : '-',
                $trx->created_at ? $trx->created_at->format('H:i:s') : '-',
                $pharmacyName,
                $total,
                $trx->payment_method ?? '-',
                $trx->transaction_type ?? '-',
            ];

            $grandTotal += $total;
        }

        $rows[] = ['', 'TOTAL', '', '', '', '', '', $grandTotal, '', ''];

        return $rows;
    }

    private function buildDetail(): array
    {
        $rows = [];
        $rows[] = [
            'No.',
            'Nama Pasien',
            'No. Telp',
            'Kode Transaksi',
            'Tanggal Transaksi',
            'Jam',
            'Apotek',
            'Nama Obat',
            'Harga',
            'Qty',
            'Total',
            'Pembayaran',
            'Tipe'
        ];

        $transactions = $this
            ->getBaseQuery()
            ->with(['patients', 'pharmacy', 'transactions.medicine'])
            ->get();

        $no = 1;
        $grandQty = 0;
        $grandTotal = 0;

        foreach ($transactions as $trx) {
            $patientName = $trx->patients?->name ?? 'Umum / Tanpa Pasien';
            $patientPhone = $trx->patients?->phone ?? '-';
            $pharmacyName = $trx->pharmacy?->name ?? '-';
            $dateStr = $trx->created_at ? $trx->created_at->format('d/m/Y') : '-';
            $timeStr = $trx->created_at ? $trx->created_at->format('H:i:s') : '-';
            $payMethod = $trx->payment_method ?? '-';
            $trxType = $trx->transaction_type ?? '-';

            $items = $trx->transactions ?? collect();

            if ($items->isEmpty()) {
                $total = (float) ($trx->subtotal ?? 0);
                $rows[] = [
                    $no++,
                    $patientName,
                    $patientPhone,
                    $trx->transaction_code ?? '-',
                    $dateStr,
                    $timeStr,
                    $pharmacyName,
                    '-',
                    0,
                    0,
                    $total,
                    $payMethod,
                    $trxType,
                ];
                $grandTotal += $total;
            } else {
                foreach ($items as $item) {
                    $medName = $item->medicine?->name ?? ($item->medicine_name ?? '-');
                    $price = (float) ($item->item_price ?? 0);
                    $qty = (float) ($item->quantity ?? 0);
                    $itemTot = (float) ($item->final_price ?? $item->total_price ?? ($price * $qty));

                    $rows[] = [
                        $no++,
                        $patientName,
                        $patientPhone,
                        $trx->transaction_code ?? '-',
                        $dateStr,
                        $timeStr,
                        $pharmacyName,
                        $medName,
                        $price,
                        $qty,
                        $itemTot,
                        $payMethod,
                        $trxType,
                    ];

                    $grandQty += $qty;
                    $grandTotal += $itemTot;
                }
            }
        }

        $rows[] = ['', 'TOTAL', '', '', '', '', '', '', '', $grandQty, $grandTotal, '', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $headerRowIdx = 6;  // Header row for table columns

        // Merge title header rows
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->mergeCells("A4:{$lastCol}4");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3:A4')->getFont()->setBold(true)->setSize(10);

        // Column header row style
        $sheet
            ->getStyle("A{$headerRowIdx}:{$lastCol}{$headerRowIdx}")
            ->getFont()
            ->setBold(true);
        $sheet
            ->getStyle("A{$headerRowIdx}:{$lastCol}{$headerRowIdx}")
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FFF1F5F9');

        // Borders on data area
        if ($lastRow >= $headerRowIdx) {
            $sheet
                ->getStyle("A{$headerRowIdx}:{$lastCol}{$lastRow}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            // Row heights
            for ($i = $headerRowIdx; $i <= $lastRow; $i++) {
                $sheet->getRowDimension($i)->setRowHeight(22);
            }

            // Center-align No column & Date & Time & Pay & Type
            $sheet
                ->getStyle("A{$headerRowIdx}:A{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Summary / Total row bold
            $sheet
                ->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
                ->getFont()
                ->setBold(true);
            $sheet
                ->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
                ->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB('FFF8FAFC');

            if ($this->mode === 'rekap') {
                // E (Tanggal), F (Jam), I (Pembayaran), J (Tipe) centered
                $sheet
                    ->getStyle("E{$headerRowIdx}:F{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("I{$headerRowIdx}:J{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // H (Total) -> Right align & format
                $dataStart = $headerRowIdx + 1;
                $sheet
                    ->getStyle("H{$dataStart}:H{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet
                    ->getStyle("H{$dataStart}:H{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            } else {
                // E (Tanggal), F (Jam), L (Pembayaran), M (Tipe) centered
                $sheet
                    ->getStyle("E{$headerRowIdx}:F{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet
                    ->getStyle("L{$headerRowIdx}:M{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // I (Harga), J (Qty), K (Total) -> Right align & format
                $dataStart = $headerRowIdx + 1;
                $sheet
                    ->getStyle("I{$dataStart}:K{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet
                    ->getStyle("I{$dataStart}:I{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
                $sheet
                    ->getStyle("J{$dataStart}:J{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.##');
                $sheet
                    ->getStyle("K{$dataStart}:K{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            }
        }
    }
}
