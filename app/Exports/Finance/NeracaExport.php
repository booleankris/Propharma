<?php

namespace App\Exports\Finance;

use App\Models\Pharmacies;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NeracaExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected array $data;
    protected ?Pharmacies $activePharmacy;
    protected int $headerRow = 5;
    protected array $yellowRows = [];
    protected array $boldRows = [];
    protected int $lastRow = 5;

    public function __construct(array $data, ?Pharmacies $activePharmacy = null)
    {
        $this->data = $data;
        $this->activePharmacy = $activePharmacy;
    }

    public function title(): string
    {
        return 'Neraca';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,   // NO
            'B' => 32,  // KETERANGAN
            'C' => 20,  // AWAL
            'D' => 20,  // AKHIR
            'E' => 20,  // SELISIH
        ];
    }

    public function array(): array
    {
        $branchName = $this->activePharmacy ? $this->activePharmacy->name : 'Apotek';
        $startDate = $this->data['startDate'] ?? '';
        $endDate = $this->data['endDate'] ?? '';

        $rows = [
            ["APOTEK PROPHARMA - {$branchName}"],
            ["LAPORAN NERACA (BALANCE SHEET)"],
            ["Periode: {$startDate} s/d {$endDate} | Dicetak: " . date('d/m/Y H:i')],
            [''], // Row 4 Blank
            // Row 5 Header
            [
                'NO',
                'KETERANGAN',
                'AWAL',
                'AKHIR',
                'SELISIH',
            ],
        ];

        $currentRow = $this->headerRow;

        // Items (1..N: Kas, Banks, Piutang Dagang, Piutang Usaha, Hutang Dagang)
        $items = $this->data['items'] ?? [];
        $noCounter = 1;

        foreach ($items as $item) {
            $currentRow++;
            $rows[] = [
                $noCounter++,
                $item['label'],
                $item['awal'],
                $item['akhir'],
                $item['selisih'],
            ];
        }

        // LABA TANPA STOK (Yellow)
        $currentRow++;
        $this->yellowRows[] = $currentRow;
        $this->boldRows[] = $currentRow;
        $lts = $this->data['laba_tanpa_stok'] ?? [];
        $rows[] = [
            '',
            $lts['label'] ?? 'LABA TANPA STOK',
            $lts['awal'] ?? 0,
            $lts['akhir'] ?? 0,
            $lts['selisih'] ?? 0,
        ];

        // PERSEDIAAN
        $currentRow++;
        $persediaan = $this->data['persediaan'] ?? [];
        $rows[] = [
            $noCounter++,
            $persediaan['label'] ?? 'PERSEDIAAN',
            $persediaan['awal'] ?? 0,
            $persediaan['akhir'] ?? 0,
            $persediaan['selisih'] ?? 0,
        ];

        // LABA DENGAN STOK (Yellow)
        $currentRow++;
        $this->yellowRows[] = $currentRow;
        $this->boldRows[] = $currentRow;
        $lds = $this->data['laba_dengan_stok'] ?? [];
        $rows[] = [
            '',
            $lds['label'] ?? 'LABA DENGAN STOK',
            $lds['awal'] ?? 0,
            $lds['akhir'] ?? 0,
            $lds['selisih'] ?? 0,
        ];

        // Spacer Row
        $currentRow++;
        $rows[] = ['', '', '', '', ''];

        // HARTA
        $currentRow++;
        $this->boldRows[] = $currentRow;
        $harta = $this->data['harta'] ?? [];
        $rows[] = [
            '',
            $harta['label'] ?? 'HARTA',
            $harta['awal'] ?? 0,
            $harta['akhir'] ?? 0,
            '-',
        ];

        // KEWAJIBAN
        $currentRow++;
        $this->boldRows[] = $currentRow;
        $kewajiban = $this->data['kewajiban'] ?? [];
        $rows[] = [
            '',
            $kewajiban['label'] ?? 'KEWAJIBAN',
            $kewajiban['awal'] ?? 0,
            $kewajiban['akhir'] ?? 0,
            '-',
        ];

        // SALDO
        $currentRow++;
        $this->boldRows[] = $currentRow;
        $saldo = $this->data['saldo'] ?? [];
        $rows[] = [
            '',
            $saldo['label'] ?? 'SALDO',
            $saldo['awal'] ?? 0,
            $saldo['akhir'] ?? 0,
            $saldo['selisih'] ?? 0,
        ];

        $this->lastRow = $currentRow;

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        // 1. Title block styles
        $sheet->mergeCells('A1:E1');
        $sheet->mergeCells('A2:E2');
        $sheet->mergeCells('A3:E3');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF555555');

        // 2. Table Header styles
        $sheet->getStyle("A{$this->headerRow}:E{$this->headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E293B'], // Dark slate
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension($this->headerRow)->setRowHeight(24);

        // 3. Grid borders for table rows
        $dataStartRow = $this->headerRow + 1;
        $tableRange = "A{$this->headerRow}:E{$this->lastRow}";
        $sheet->getStyle($tableRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD1D5DB'],
                ],
            ],
        ]);

        // 4. Alignments
        $sheet->getStyle("A{$dataStartRow}:A{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$dataStartRow}:B{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C{$dataStartRow}:E{$this->lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // 5. Yellow highlight rows (Laba Tanpa Stok, Laba Dengan Stok)
        foreach ($this->yellowRows as $yRow) {
            $sheet->getStyle("A{$yRow}:E{$yRow}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF00'], // Bright yellow matching template
                ],
            ]);
        }

        // 6. Bold rows
        foreach ($this->boldRows as $bRow) {
            $sheet->getStyle("A{$bRow}:E{$bRow}")->getFont()->setBold(true);
        }

        // 7. Number format: #,##0;(#,##0);"-"
        $numFormat = '#,##0;(#,##0);"-"';
        for ($r = $dataStartRow; $r <= $this->lastRow; $r++) {
            $sheet->getStyle("C{$r}:E{$r}")->getNumberFormat()->setFormatCode($numFormat);
        }

        return [];
    }
}

