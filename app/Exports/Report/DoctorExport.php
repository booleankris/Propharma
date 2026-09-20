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
        $query = MedicineTransactions::with([
            'transactions' => fn($q) => $q->where('status', 1),
            'doctors',
        ])
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
            $docName  = trim($trx->doctors?->name ?? 'TANPA DOKTER');
            $groupKey = strtoupper($docName);

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'doctor_name' => $docName,
                    'nilai_resep' => 0,
                    'lembar'      => 0,
                    'jumlah_r'    => 0,
                ];
            }

            $grouped[$groupKey]['nilai_resep'] += (float) ($trx->subtotal ?? 0);
            $grouped[$groupKey]['lembar']      += 1;

            // Hitung Jumlah R/ secara presisi:
            // 1 racikan (recipe_number yang sama) dihitung 1 R/
            // Setiap obat non-racikan (recipe_number null/kosong) dihitung 1 R/
            $activeItems = $trx->transactions ?? collect();
            $racikanCount = $activeItems->whereNotNull('recipe_number')
                ->filter(fn($item) => trim((string)$item->recipe_number) !== '')
                ->pluck('recipe_number')
                ->unique()
                ->count();

            $nonRacikanCount = $activeItems->filter(fn($item) => empty($item->recipe_number))->count();

            $grouped[$groupKey]['jumlah_r'] += ($racikanCount + $nonRacikanCount);
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
        $query = MedicineTransactions::with([
            'transactions' => fn($q) => $q->where('status', 1)->with('medicine'),
            'doctors',
        ])
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
            $docName  = trim($trx->doctors?->name ?? 'TANPA DOKTER');
            $groupDoc = strtoupper($docName);

            foreach ($trx->transactions ?? [] as $item) {
                $med     = $item->medicine;
                $medId   = $med?->id ?? ('item_' . $item->id);
                $medName = $med?->name ?? ($item->medicine_name ?? '-');
                $key     = "{$groupDoc}_{$medId}";

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

        // Column header row bold & centered
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

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

        $dataRowsStart = $dataStartRow + 1;

        if ($dataRowsStart <= $lastRow) {
            if ($this->selectedType === 'rekap') {
                // Rekap: C (Nilai Resep), D (Lembar), E (Jumlah R/) -> Right-aligned & Number format
                $sheet->getStyle("C{$dataRowsStart}:E{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("C{$dataRowsStart}:E{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            } else {
                // Detail: D (Qty), E (Jumlah) -> Right-aligned & Number format
                $sheet->getStyle("D{$dataRowsStart}:E{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("D{$dataRowsStart}:E{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0');
            }
        }

        // Total row bold & border formatting
        if ($lastRow >= $dataRowsStart) {
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
                ->getFont()->setBold(true);
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
                ->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_DOUBLE);
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
