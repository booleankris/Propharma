<?php

namespace App\Exports\Master;

use App\Models\Pharmacies;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MasterMedicinesExport extends DefaultValueBinder implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
{
    protected ?string $status;
    protected ?int $pharmacyId;
    protected int $dataRowCount = 0;
    protected int $dataStartRow = 6;
    protected ?Pharmacies $pharmacy = null;
    protected string $statusLabel = '';

    public function __construct($status = '1', $pharmacyId = null)
    {
        $this->status = ($status === null || $status === '') ? '1' : (string) $status;
        $this->pharmacyId = $pharmacyId ?? getActivePharmacyId();
        $this->pharmacy = Pharmacies::find($this->pharmacyId);

        if ($this->status === '1') {
            $this->statusLabel = 'Aktif Saja';
        } elseif ($this->status === '0') {
            $this->statusLabel = 'Nonaktif Saja';
        } else {
            $this->statusLabel = 'Semua Status (Aktif & Nonaktif)';
        }
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Preserve leading zeroes for Kode Obat (Col B) and Barcode (Col C)
        $col = $cell->getColumn();
        if (($col === 'B' || $col === 'C') && $cell->getRow() >= 6) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        return 'Master Obat';
    }

    public function headings(): array
    {
        return [
            'NO',
            'KODE OBAT',
            'BARCODE',
            'NAMA OBAT',
            'GENERIK',
            'KATEGORI',
            'KOMPOSISI',
            'KOMPONEN SP',
            'PABRIK (PRODUSEN)',
            'SUPPLIER UTAMA',
            'KEMASAN',
            'SATUAN',
            'ISI',
            'STRIP',
            'DOSIS',
            'SEDIAAN',
            'HNA',
            'HNA + PPN',
            'HARGA JUAL NETTO',
            'HET',
            'STOK MINIMAL',
            'RESEP',
            'STATUS',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // NO
            'B' => 16,  // KODE OBAT
            'C' => 18,  // BARCODE
            'D' => 42,  // NAMA OBAT
            'E' => 26,  // GENERIK
            'F' => 22,  // KATEGORI
            'G' => 26,  // KOMPOSISI
            'H' => 22,  // KOMPONEN SP
            'I' => 24,  // PABRIK
            'J' => 24,  // SUPPLIER UTAMA
            'K' => 14,  // KEMASAN
            'L' => 12,  // SATUAN
            'M' => 10,  // ISI
            'N' => 10,  // STRIP
            'O' => 14,  // DOSIS
            'P' => 14,  // SEDIAAN
            'Q' => 18,  // HNA
            'R' => 18,  // HNA + PPN
            'S' => 18,  // HARGA JUAL NETTO
            'T' => 18,  // HET
            'U' => 14,  // STOK MINIMAL
            'V' => 12,  // RESEP
            'W' => 14,  // STATUS
        ];
    }

    public function array(): array
    {
        $branchName = $this->pharmacy ? $this->pharmacy->name : 'Apotek Propharma';

        $medicinesQuery = DB::table('medicines')
            ->leftJoin('pharmacies', 'pharmacies.id', '=', 'medicines.pharmacy_id')
            ->leftJoin('medicine_categories', 'medicine_categories.id', '=', 'medicines.medicine_category_id')
            ->leftJoin('compositions', 'compositions.id', '=', 'medicines.composition_id')
            ->leftJoin('factories', 'factories.id', '=', 'medicines.factory_id')
            ->leftJoin('creditors', 'creditors.id', '=', 'medicines.creditors_id');

        if ($this->status !== 'all' && $this->status !== '') {
            $medicinesQuery->where('medicines.status', $this->status);
        }

        $medicines = $medicinesQuery->select([
            'medicines.id',
            'medicines.code',
            'medicines.barcode',
            'medicines.name',
            'medicines.generic',
            'medicine_categories.name as category_name',
            'compositions.name as composition_name',
            'medicines.component',
            'factories.name as factory_name',
            'creditors.name as creditor_name',
            'medicines.packaging',
            'medicines.unit',
            'medicines.content',
            'medicines.strip',
            'medicines.dosage',
            'medicines.preparations',
            'medicines.raw_price',
            'medicines.net_price',
            'medicines.het_price',
            'medicines.minimal_stock',
            'medicines.receipt',
            'medicines.status',
        ])
        ->orderBy('medicines.name', 'asc')
        ->orderBy('medicines.code', 'asc')
        ->get();

        $this->dataRowCount = $medicines->count();

        $rows = [
            ["APOTEK PROPHARMA — {$branchName}"],
            ["DATA MASTER OBAT & KATALOG PRODUK"],
            ["Unit: {$branchName}  |  Filter Status: {$this->statusLabel}  |  Total Data: " . number_format($this->dataRowCount, 0, ',', '.') . " Obat  |  Dicetak: " . now()->format('d/m/Y H:i') . ' WIB'],
            [''], // Row 4 Blank
            $this->headings(), // Row 5 Table Headers
        ];

        if ($this->dataRowCount === 0) {
            $rows[] = ['Tidak ada data obat yang sesuai dengan filter.'];
            return $rows;
        }

        foreach ($medicines as $index => $m) {
            $hna = (float) ($m->raw_price ?? 0);
            $hnaPpn = $hna > 0 ? (float) round($hna * 1.11) : 0.0;
            $resepText = ($m->receipt === 1 || $m->receipt === '1') ? 'Ya' : 'Tidak';
            $statusText = ($m->status === 1 || $m->status === '1') ? 'Aktif' : 'Nonaktif';

            $rows[] = [
                $index + 1,
                (string) ($m->code ?? ''),
                (string) ($m->barcode ?? ''),
                $m->name ?? '',
                $m->generic ?? '',
                $m->category_name ?? '',
                $m->composition_name ?? '',
                $m->component ?? '',
                $m->factory_name ?? '',
                $m->creditor_name ?? '',
                $m->packaging ? strtoupper($m->packaging) : '',
                $m->unit ? strtoupper($m->unit) : '',
                is_numeric($m->content) ? (float) $m->content : ($m->content ?? ''),
                is_numeric($m->strip) ? (float) $m->strip : ($m->strip ?? ''),
                $m->dosage ?? '',
                $m->preparations ?? '',
                $hna,
                $hnaPpn,
                (float) ($m->net_price ?? 0),
                (float) ($m->het_price ?? 0),
                (float) ($m->minimal_stock ?? 0),
                $resepText,
                $statusText,
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'W';
        $startRow = $this->dataStartRow; // 6
        $count = $this->dataRowCount;

        // 1. Title Block (Rows 1 to 3)
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle("A2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '059669']], // Emerald
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle("A3")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => '64748B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // Row 4 Blank Separator
        $sheet->getRowDimension(4)->setRowHeight(8);

        // 2. Table Header Styling (Row 5)
        $sheet->getRowDimension(5)->setRowHeight(30);

        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']],
            ],
        ]);

        // Section Color Highlights on Headers:
        // A-E: Identitas Utama (Dark Slate 900)
        $sheet->getStyle("A5:E5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');

        // F-J: Klasifikasi & Supplier (Navy/Indigo 900)
        $sheet->getStyle("F5:J5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');

        // K-P: Kemasan & Sediaan (Teal 800)
        $sheet->getStyle("K5:P5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');

        // Q-T: Skema Harga (Emerald 800)
        $sheet->getStyle("Q5:T5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('065F46');

        // U: Stok Minimal (Amber 800)
        $sheet->getStyle("U5:U5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B45309');

        // V-W: Regulasi & Status (Slate 700)
        $sheet->getStyle("V5:W5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('334155');

        // 3. Data Rows Styling
        if ($count > 0) {
            $endRow = $startRow + $count - 1;

            $sheet->getDefaultRowDimension()->setRowHeight(22);
            $sheet->getRowDimension(5)->setRowHeight(30);

            // General Grid Borders & Vertical Alignment
            $sheet->getStyle("A{$startRow}:{$lastCol}{$endRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Column Alignments & Typography
            // No (A)
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getFont()->setSize(9.5)->getColor()->setRGB('64748B');

            // Kode Obat (B) & Barcode (C)
            $sheet->getStyle("B{$startRow}:C{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['bold' => true, 'size' => 9.5, 'color' => ['rgb' => '1E293B']],
            ]);

            // Nama Obat (D)
            $sheet->getStyle("D{$startRow}:D{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                'font' => ['bold' => true, 'size' => 9.5, 'color' => ['rgb' => '0F172A']],
            ]);

            // Generik (E) through Supplier (J)
            $sheet->getStyle("E{$startRow}:J{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                'font' => ['size' => 9],
            ]);

            // Kemasan (K), Satuan (L), Isi (M), Strip (N), Dosis (O), Sediaan (P)
            $sheet->getStyle("K{$startRow}:P{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['size' => 9],
            ]);

            // Harga: Q (HNA), R (HNA + PPN), S (Harga Jual Netto), T (HET)
            $sheet->getStyle("Q{$startRow}:T{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['size' => 9.5, 'bold' => true, 'color' => ['rgb' => '047857']],
            ]);

            // Stok: U (Stok Minimal)
            $sheet->getStyle("U{$startRow}:U{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['size' => 9.5, 'bold' => true, 'color' => ['rgb' => '1E40AF']],
            ]);

            // Resep (V)
            $sheet->getStyle("V{$startRow}:V{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['size' => 9],
            ]);

            // Status (W)
            $sheet->getStyle("W{$startRow}:W{$endRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'font' => ['bold' => true, 'size' => 9.5],
            ]);

            // Freeze Pane & AutoFilter
            $sheet->freezePane('A6');
            $sheet->setAutoFilter("A5:{$lastCol}{$endRow}");
            $sheet->setShowGridlines(true);
        }

        return [];
    }
}
