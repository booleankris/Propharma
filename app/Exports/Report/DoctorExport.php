<?php

namespace App\Exports\Report;

use App\Models\Doctors;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
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
        $pharmacy = Pharmacies::find($this->pharmacyId);
        $doctorName = null;
        if (!empty($this->doctorId)) {
            $doctorObj = Doctors::find($this->doctorId);
            $doctorName = $doctorObj?->name;
        }

        $titleSuffix = $doctorName ? " - {$doctorName}" : '';

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan Dokter (' . ucfirst($this->selectedType) . "){$titleSuffix}"],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = $this->selectedType === 'rekap'
            ? $this->buildRecap()
            : $this->buildDetail();

        return array_merge($header, $body);
    }

    // REKAP — 5 Kolom: No, Nama Dokter, Nilai Resep, Lembar, Jumlah R/
    private function buildRecap(): array
    {
        $query = MedicineTransactions::with(['transactions', 'doctors'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', ['RESEP TUNAI', 'KREDIT'])
            ->where('status', 1)
            ->whereBetween('updated_at', [$this->startDate, $this->endDate]);

        if (!empty($this->doctorId)) {
            $query->where('doctor_id', $this->doctorId);
        } else {
            $query->whereNotNull('doctor_id');
        }

        if ($this->shiftType === 'shift' && !empty($this->shift)) {
            $query->whereHas('shift_logs', function ($q) {
                $q->where('shift_id', $this->shift);
            });
        }

        $transactions = $query->get();

        $grouped = [];

        foreach ($transactions as $trx) {
            $docId   = $trx->doctor_id ?? 0;
            $docName = $trx->doctors?->name ?? 'TANPA DOKTER';

            if (!isset($grouped[$docId])) {
                $grouped[$docId] = [
                    'doctor_name' => $docName,
                    'nilai_resep' => 0,
                    'lembar'      => 0,
                    'jumlah_r'    => 0,
                ];
            }

            $grouped[$docId]['nilai_resep'] += (float) ($trx->subtotal ?? 0);
            $grouped[$docId]['lembar']      += 1;

            foreach ($trx->transactions ?? [] as $item) {
                $grouped[$docId]['jumlah_r'] += (float) ($item->quantity ?? 0);
            }
        }

        // Sort alphabetically by Doctor Name (A-Z)
        uasort($grouped, fn($a, $b) => strcasecmp($a['doctor_name'], $b['doctor_name']));

        $rows   = [];
        $rows[] = ['No.', 'Nama Dokter', 'Nilai Resep', 'Lembar', 'Jumlah R/'];

        $no           = 1;
        $grandNilai   = 0;
        $grandLembar  = 0;
        $grandJumlahR = 0;

        foreach ($grouped as $doc) {
            $rows[] = [
                $no++,
                $doc['doctor_name'],
                $doc['nilai_resep'],
                $doc['lembar'],
                $doc['jumlah_r'],
            ];

            $grandNilai   += $doc['nilai_resep'];
            $grandLembar  += $doc['lembar'];
            $grandJumlahR += $doc['jumlah_r'];
        }

        $rows[] = ['', 'TOTAL', $grandNilai, $grandLembar, $grandJumlahR];

        return $rows;
    }

    // DETAIL — 5 Kolom: No, Nama Dokter, Nama Obat, Qty, Jumlah
    private function buildDetail(): array
    {
        $query = MedicineTransactions::with(['transactions.medicine', 'doctors'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', ['RESEP TUNAI', 'KREDIT'])
            ->where('status', 1)
            ->whereBetween('updated_at', [$this->startDate, $this->endDate]);

        if (!empty($this->doctorId)) {
            $query->where('doctor_id', $this->doctorId);
        } else {
            $query->whereNotNull('doctor_id');
        }

        if ($this->shiftType === 'shift' && !empty($this->shift)) {
            $query->whereHas('shift_logs', function ($q) {
                $q->where('shift_id', $this->shift);
            });
        }

        $transactions = $query->get();

        $grouped = [];

        foreach ($transactions as $trx) {
            $docId   = $trx->doctor_id ?? 0;
            $docName = $trx->doctors?->name ?? 'TANPA DOKTER';

            foreach ($trx->transactions ?? [] as $item) {
                $med     = $item->medicine;
                $medId   = $med?->id ?? ('item_' . $item->id);
                $medName = $med?->name ?? ($item->medicine_name ?? '-');
                $key     = "{$docId}_{$medId}";

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'doctor'   => $docName,
                        'medicine' => $medName,
                        'qty'      => 0,
                        'total'    => 0,
                    ];
                }

                $grouped[$key]['qty']   += (float) ($item->quantity ?? 0);
                $grouped[$key]['total'] += (float) ($item->final_price ?? 0);
            }
        }

        // Sort by Doctor Name (A-Z), then Medicine Name (A-Z)
        uasort($grouped, function ($a, $b) {
            $cmpDoc = strcasecmp($a['doctor'], $b['doctor']);
            return $cmpDoc !== 0 ? $cmpDoc : strcasecmp($a['medicine'], $b['medicine']);
        });

        $rows   = [];
        $rows[] = ['No.', 'Nama Dokter', 'Nama Obat', 'Qty', 'Jumlah'];

        $no         = 1;
        $grandQty   = 0;
        $grandTotal = 0;

        foreach ($grouped as $item) {
            $rows[] = [
                $no++,
                $item['doctor'],
                $item['medicine'],
                $item['qty'],
                $item['total'],
            ];

            $grandQty   += $item['qty'];
            $grandTotal += $item['total'];
        }

        $rows[] = ['', 'TOTAL', '', $grandQty, $grandTotal];

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

        // Borders on data area
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Row height
        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }

        // Center-align No column
        $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($this->selectedType === 'rekap') {
            // Rekap: C (Nilai Resep), D (Lembar), E (Jumlah R/) -> Right-aligned & Number format
            $sheet->getStyle("C{$dataStartRow}:E{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("C{$dataStartRow}:E{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        } else {
            // Detail: D (Qty), E (Jumlah) -> Right-aligned & Number format
            $sheet->getStyle("D{$dataStartRow}:E{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("D{$dataStartRow}:E{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }
    }

    public function columnWidths(): array
    {
        return $this->selectedType === 'rekap'
            ? [
                'A' => 6,
                'B' => 35,
                'C' => 20,
                'D' => 12,
                'E' => 14,
            ]
            : [
                'A' => 6,
                'B' => 32,
                'C' => 38,
                'D' => 12,
                'E' => 20,
            ];
    }

    public function title(): string
    {
        return 'Penjualan Dokter';
    }
}
