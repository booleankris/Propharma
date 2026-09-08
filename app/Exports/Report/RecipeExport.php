<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\Shifts;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecipeExport implements WithMultipleSheets, FromArray
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;

    public function __construct($pharmacyId, $startDate, $endDate, $shift = null, $shiftType = 'semua')
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate  = Carbon::parse($startDate)->startOfDay();
        $this->endDate    = Carbon::parse($endDate)->endOfDay();
        $this->shift      = $shift;
        $this->shiftType  = $shiftType;
    }

    public function sheets(): array
    {
        return [
            new RecipeDetailSheet(
                $this->pharmacyId,
                $this->startDate,
                $this->endDate,
                $this->shift,
                $this->shiftType
            ),
            new RecipeMonthlySummarySheet(
                $this->pharmacyId,
                $this->startDate
            ),
        ];
    }

    public function array(): array
    {
        return (new RecipeDetailSheet(
            $this->pharmacyId,
            $this->startDate,
            $this->endDate,
            $this->shift,
            $this->shiftType
        ))->array();
    }

    public function title(): string
    {
        return 'Laporan Resep';
    }
}

/**
 * Sheet 1: Daftar Transaksi Resep Harian
 */
class RecipeDetailSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;

    public function __construct($pharmacyId, $startDate, $endDate, $shift, $shiftType)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate  = $startDate;
        $this->endDate    = $endDate;
        $this->shift      = $shift;
        $this->shiftType  = $shiftType;
    }

    public function title(): string
    {
        return 'DAFTAR RESEP';
    }

    public function array(): array
    {
        $pharmacy   = Pharmacies::find($this->pharmacyId);
        $shift      = $this->shift ? Shifts::find($this->shift) : null;
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
}

/**
 * Sheet 2: Rekapitulasi Lembar Resep per Golongan per Bulan di Tahun Berjalan
 */
class RecipeMonthlySummarySheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $year;

    public function __construct($pharmacyId, $startDate)
    {
        $this->pharmacyId = $pharmacyId;
        $this->year       = $startDate ? Carbon::parse($startDate)->year : (int) date('Y');
    }

    public function title(): string
    {
        return 'REKAP LEMBAR RESEP';
    }

    /**
     * Klasifikasi golongan satu obat:
     * 1. NARKOTIK
     * 2. PSIKOTROPIKA
     * 3. PREKURSOR
     * 4. OOT
     * 5. UMUM
     */
    public static function classifyMedicine($medicine): string
    {
        if (!$medicine) {
            return 'UMUM';
        }

        $type           = strtoupper(trim((string) ($medicine->type ?? '')));
        $catName        = strtoupper(trim((string) ($medicine->category->name ?? '')));
        $isPsychotropic = (int) ($medicine->psychotropic ?? 0) === 1;
        $isPrecursor    = (int) ($medicine->precursor ?? 0) === 1;

        // 1. NARKOTIK
        if (str_contains($type, 'NARKOTIK') || str_contains($catName, 'NARKOTIK')) {
            return 'NARKOTIK';
        }

        // 2. PSIKOTROPIKA
        if ($isPsychotropic || str_contains($type, 'PSIKOTROPIKA') || str_contains($catName, 'PSIKOTROPIKA')) {
            return 'PSIKOTROPIKA';
        }

        // 3. PREKURSOR
        if ($isPrecursor || str_contains($type, 'PREKURSOR') || str_contains($catName, 'PREKURSOR')) {
            return 'PREKURSOR';
        }

        // 4. OOT (Obat-Obat Tertentu)
        if (
            str_contains($type, 'OOT') ||
            str_contains($type, 'OBAT TERTENTU') ||
            str_contains($type, 'OBAT-OBAT TERTENTU') ||
            str_contains($catName, 'OOT') ||
            str_contains($catName, 'OBAT TERTENTU') ||
            str_contains($catName, 'OBAT-OBAT TERTENTU')
        ) {
            return 'OOT';
        }

        // 5. UMUM
        return 'UMUM';
    }

    /**
     * Klasifikasi 1 lembar resep berdasarkan prioritas golongan tertinggi
     */
    public static function classifyPrescription($trx): string
    {
        $hasNarkotik  = false;
        $hasPsiko     = false;
        $hasPrekursor = false;
        $hasOot       = false;

        foreach ($trx->transactions ?? [] as $cartItem) {
            $med = $cartItem->medicine;
            if (!$med) {
                continue;
            }

            $cls = self::classifyMedicine($med);
            if ($cls === 'NARKOTIK') {
                $hasNarkotik = true;
                break; // Prioritas tertinggi
            } elseif ($cls === 'PSIKOTROPIKA') {
                $hasPsiko = true;
            } elseif ($cls === 'PREKURSOR') {
                $hasPrekursor = true;
            } elseif ($cls === 'OOT') {
                $hasOot = true;
            }
        }

        if ($hasNarkotik) {
            return 'NARKOTIK';
        }
        if ($hasPsiko) {
            return 'PSIKOTROPIKA';
        }
        if ($hasPrekursor) {
            return 'PREKURSOR';
        }
        if ($hasOot) {
            return 'OOT';
        }
        return 'UMUM';
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['REKAPITULASI LEMBAR RESEP PER GOLONGAN OBAT'],
            ['Tahun : ' . $this->year],
            [''],
        ];

        $yearStart = Carbon::create($this->year, 1, 1, 0, 0, 0)->startOfDay();
        $yearEnd   = Carbon::create($this->year, 12, 31, 23, 59, 59)->endOfDay();

        $transactions = MedicineTransactions::with(['transactions.medicine.category'])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', ['RESEP TUNAI', 'KREDIT'])
            ->where('status', 1)
            ->whereBetween('updated_at', [$yearStart, $yearEnd])
            ->get();

        $categories = ['NARKOTIK', 'PSIKOTROPIKA', 'PREKURSOR', 'OOT', 'UMUM'];
        $matrix     = [];
        foreach ($categories as $cat) {
            $matrix[$cat] = array_fill(1, 12, 0);
        }

        foreach ($transactions as $trx) {
            $date  = Carbon::parse($trx->updated_at ?? $trx->created_at);
            $month = (int) $date->format('n');

            if ($month >= 1 && $month <= 12) {
                $cat = self::classifyPrescription($trx);
                $matrix[$cat][$month]++;
            }
        }

        $tableHeaders = ['No.', 'Golongan', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des', 'Total'];

        $body        = [$tableHeaders];
        $monthTotals = array_fill(1, 12, 0);
        $grandTotal  = 0;
        $no          = 1;

        foreach ($categories as $cat) {
            $row      = [$no++, $cat];
            $rowTotal = 0;

            for ($m = 1; $m <= 12; $m++) {
                $cnt = $matrix[$cat][$m];
                $row[] = $cnt;
                $rowTotal += $cnt;
                $monthTotals[$m] += $cnt;
            }

            $row[]      = $rowTotal;
            $grandTotal += $rowTotal;
            $body[]     = $row;
        }

        // Baris Total
        $totalRow = ['', 'TOTAL'];
        for ($m = 1; $m <= 12; $m++) {
            $totalRow[] = $monthTotals[$m];
        }
        $totalRow[] = $grandTotal;
        $body[]     = $totalRow;

        return array_merge($header, $body);
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = 'O';
        $lastRow      = 13;
        $dataStartRow = 7;

        // Merge header rows
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Column header row bold & center
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Borders on entire data area
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Row height
        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(25);
        }

        // Center align No (Col A)
        $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Left align Golongan (Col B)
        $sheet->getStyle("B8:B12")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Right align and format numbers (Col C to Col O)
        $sheet->getStyle("C8:{$lastCol}{$lastRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("C8:{$lastCol}{$lastRow}")
            ->getNumberFormat()->setFormatCode('#,##0');

        // Total row bold
        $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
            ->getFont()->setBold(true);

        // Total column bold (Col O)
        $sheet->getStyle("O8:O{$lastRow}")
            ->getFont()->setBold(true);
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 18,
            'C' => 10,
            'D' => 10,
            'E' => 10,
            'F' => 10,
            'G' => 10,
            'H' => 10,
            'I' => 10,
            'J' => 10,
            'K' => 10,
            'L' => 10,
            'M' => 10,
            'N' => 10,
            'O' => 14,
        ];
    }
}
