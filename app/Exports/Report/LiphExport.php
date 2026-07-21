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
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class LiphExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $pharmacyAddress;
    protected $shift;
    protected $shiftType;

    const TYPE_MAP = [
        'KREDIT'      => ['kredit', 'Resep Kredit'],
        'HV/OTC'      => ['tunai',  'Obat Bebas'],
        'RETUR JUAL'  => ['tunai',  'Retur Tunai'],
        'RESEP TUNAI' => ['tunai',  'Resep Tunai'],
        'UPDS'        => ['tunai',  'UPDS'],
    ];

    const TUNAI_ORDER = ['Obat Bebas', 'Retur Tunai', 'Resep Tunai', 'UPDS'];

    private function nz($value)
    {
        return is_null($value) || $value === '' ? 0 : $value;
    }

    public function __construct($pharmacyId, $startDate, $endDate, $pharmacyName = 'APOTEK SAHABAT', $pharmacyAddress = '', $shift, $shiftType)
    {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = Carbon::parse($startDate)->startOfDay();
        $this->endDate         = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName    = $pharmacyName;
        $this->pharmacyAddress = $pharmacyAddress;
        $this->shift           = $shift;
        $this->shiftType       = $shiftType;
    }

    public function array(): array
    {
        return $this->buildRows($this->buildReportData());
    }

    private function safeSum($collection, $field)
    {
        return $collection->sum(fn($item) => (int) ($item->{$field} ?? 0));
    }

    private function buildReportData(): array
    {
        if ($this->shiftType == "shift") {
            $transactions = MedicineTransactions::with(['transactions','shift_logs'])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereDate('created_at', '>=', $this->startDate->toDateString())
                ->whereDate('created_at', '<=', $this->endDate->toDateString())
                ->whereIn('transaction_type', array_keys(self::TYPE_MAP))
                ->wherehas('shift_logs', function($shift){
                    $shift->where('shift_id', $this->shift);
                })
                ->get();

            $grouped = [];

            foreach ($transactions as $trx) {
                $map = self::TYPE_MAP[$trx->transaction_type] ?? null;
                if (!$map) continue;

                [$group, $label] = $map;

                if (!isset($grouped[$group][$label])) {
                    $grouped[$group][$label] = [
                        'lembar' => 0,
                        'r' => 0,
                        'jasa' => 0,
                        'embalase' => 0,
                        'potongan' => 0,
                        'netto' => 0,
                    ];
                }

                $ref = &$grouped[$group][$label];

                $ref['lembar']++;
                $ref['r'] += $trx->transactions->count();
                $ref['jasa'] += $this->safeSum($trx->transactions, 'service_fee');
                $ref['embalase'] += $this->safeSum($trx->transactions, 'embalase');
                $ref['potongan'] += (int) ($trx->discount ?? 0) + $this->safeSum($trx->transactions, 'discount');

                $netto = (int) ($trx->subtotal ?? 0);
                if ($label === 'Retur Tunai') $netto = -abs($netto);

                $ref['netto'] += $netto;
            }
        } else if ($this->shiftType == 'semua') {
            $transactions = MedicineTransactions::with('transactions')
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereDate('created_at', '>=', $this->startDate->toDateString())
                ->whereDate('created_at', '<=', $this->endDate->toDateString())
                ->whereIn('transaction_type', array_keys(self::TYPE_MAP))
                ->get();

            $grouped = [];

            foreach ($transactions as $trx) {
                $map = self::TYPE_MAP[$trx->transaction_type] ?? null;
                if (!$map) continue;

                [$group, $label] = $map;

                if (!isset($grouped[$group][$label])) {
                    $grouped[$group][$label] = [
                        'lembar' => 0,
                        'r' => 0,
                        'jasa' => 0,
                        'embalase' => 0,
                        'potongan' => 0,
                        'netto' => 0,
                    ];
                }

                $ref = &$grouped[$group][$label];

                $ref['lembar']++;
                $ref['r'] += $trx->transactions->count();
                $ref['jasa'] += $this->safeSum($trx->transactions, 'service_fee');
                $ref['embalase'] += $this->safeSum($trx->transactions, 'embalase');
                $ref['potongan'] += (int) ($trx->discount ?? 0) + $this->safeSum($trx->transactions, 'discount');

                $netto = (int) ($trx->subtotal ?? 0);
                if ($label === 'Retur Tunai') $netto = -abs($netto);

                $ref['netto'] += $netto;
            }
        }
        return $grouped;
    }

    private function buildRows(array $grouped): array
    {
        $rows = [];
        $no   = 1;

        $grand = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];

        // HEADER
        $rows[] = [$this->pharmacyName];
        $rows[] = [$this->pharmacyAddress];
        $rows[] = [];
        $rows[] = ['Laporan Penjualan Harian (LIPH)'];
        $rows[] = ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y') . ' (Seluruh)'];
        $rows[] = [];

        // TABLE HEADER
        $rows[] = ['No.', 'Pelanggan', 'Lembar', 'R/', 'Jasa', 'Embalase', 'Potongan', 'Netto'];

        // KREDIT
        $rows[] = ['Penjualan Kredit', '', '', '', '', '', '', ''];

        $kredit = $grouped['kredit'] ?? [];
        $sub = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];

        foreach ($kredit as $label => $d) {
            $rows[] = [
                $no++,
                $label,
                $this->nz($d['lembar']),
                $this->nz($d['r']),
                $this->nz($d['jasa']),
                $this->nz($d['embalase']),
                $this->nz($d['potongan']),
                $this->nz($d['netto'])
            ];

            foreach ($sub as $k => $v) $sub[$k] += $d[$k];
        }

        $rows[] = [
            '',
            'Sub Total',
            $this->nz($sub['lembar']),
            $this->nz($sub['r']),
            $this->nz($sub['jasa']),
            $this->nz($sub['embalase']),
            $this->nz($sub['potongan']),
            $this->nz($sub['netto'])
        ];

        foreach ($grand as $k => $v) $grand[$k] += $sub[$k];

        // TUNAI
        $rows[] = ['Penjualan Tunai', '', '', '', '', '', '', ''];

        $tunai = $grouped['tunai'] ?? [];
        $sub = ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];

        foreach (self::TUNAI_ORDER as $label) {

            $d = $tunai[$label] ?? ['lembar' => 0, 'r' => 0, 'jasa' => 0, 'embalase' => 0, 'potongan' => 0, 'netto' => 0];

            if ($label === 'Retur Tunai') {
                [$d['lembar'], $d['r']] = [$d['r'], $d['lembar']];
            }

            $rows[] = [
                $no++,
                $label,
                $this->nz($d['lembar']),
                $this->nz($d['r']),
                $this->nz($d['jasa']),
                $this->nz($d['embalase']),
                $this->nz($d['potongan']),
                $this->nz($d['netto'])
            ];

            foreach ($sub as $k => $v) $sub[$k] += $d[$k];
        }

        $rows[] = [
            '',
            'Sub Total',
            $this->nz($sub['lembar']),
            $this->nz($sub['r']),
            $this->nz($sub['jasa']),
            $this->nz($sub['embalase']),
            $this->nz($sub['potongan']),
            $this->nz($sub['netto'])
        ];

        foreach ($grand as $k => $v) $grand[$k] += $sub[$k];

        // GRAND TOTAL
        $rows[] = [
            '',
            'Grand Total',
            $this->nz($grand['lembar']),
            $this->nz($grand['r']),
            $this->nz($grand['jasa']),
            $this->nz($grand['embalase']),
            $this->nz($grand['potongan']),
            $this->nz($grand['netto'])
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $thin   = ['borderStyle' => Border::BORDER_THIN];
        $medium = ['borderStyle' => Border::BORDER_THIN];
        $numFmt = '#,##0';

        $lastRow = $sheet->getHighestRow();

        /*
    | FONT (PDF style)
    */
        $sheet->getStyle("A1:H{$lastRow}")
            ->getFont()
            ->setName('Arial')
            ->setSize(10);

        /*
    | ROW HEIGHT (tight)
    */
        for ($i = 1; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(26);
        }

        /*
    | MERGE HEADER TEXT
    */
        $sheet->mergeCells('A1:H1');
        $sheet->mergeCells('A2:H2');
        $sheet->mergeCells('A3:H3');
        $sheet->mergeCells('A4:H4');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->getStyle('A1:H4')
            ->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A1:H4')
            ->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
        /*
    | TABLE HEADER (ROW 5 — IMPORTANT)
    */
        $sheet->getStyle('A5:H5')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => $thin,
            ],
        ]);

        /*
    | CONTENT
    */
        for ($row = 6; $row <= $lastRow; $row++) {

            $A = trim((string)$sheet->getCell("A{$row}")->getValue());
            $B = trim((string)$sheet->getCell("B{$row}")->getValue());

            /*
        | SECTION TITLE (NO BORDER)
        */
            if (in_array($A, ['Penjualan Kredit', 'Penjualan Tunai'])) {
                $sheet->mergeCells("A{$row}:H{$row}");

                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                continue;
            }

            /*
        | NORMAL ROW (THIN GRID)
        */
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'borders' => [
                    'allBorders' => $thin,
                ],
            ]);

            /*
        | ALIGNMENT (MATCH PDF)
        */
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("A{$row}:H{$row}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);

            /*
        | NUMBER FORMAT
        */

            if ($B === 'Sub Total' || $B === 'Grand Total') {

                // Center label
                $sheet->getStyle("B{$row}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Center left side (No, Pelanggan, Lembar, R/)
                $sheet->getStyle("A{$row}:D{$row}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            for ($col = 3; $col <= 8; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, $row);

                if ($cell->getValue() === null || $cell->getValue() === '') {
                    $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                }

                $cell->getStyle()->getNumberFormat()->setFormatCode($numFmt);
            }

            /*
        | SUB TOTAL (MEDIUM BORDER)
        */
            if ($B === 'Sub Total') {
                $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'outline' => $medium,
                        'inside'  => $thin,
                    ],
                ]);
            }

            /*
        | GRAND TOTAL (STRONGEST)
        */
            if ($B === 'Grand Total') {
                $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'outline' => $medium,
                        'inside'  => $thin,
                    ],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 30,  // Pelanggan
            'C' => 9,   // Lembar
            'D' => 7,   // R/
            'E' => 14,  // Jasa
            'F' => 14,  // Embalase
            'G' => 14,  // Potongan
            'H' => 18,  // Netto
        ];
    }

    public function title(): string
    {
        return 'LIPH';
    }
}
