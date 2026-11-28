<?php

namespace App\Exports\Stocks;

use App\Models\Pharmacies;
use App\Services\Finance\FinanceStockReconciliationService;
use Illuminate\Support\Collection;
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
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinanceStockReconciliationSheetExport extends DefaultValueBinder implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
{
    protected int $pharmacyId;
    protected ?Collection $medicines;
    protected array $metricsMap;
    protected ?Pharmacies $pharmacy;
    protected bool $isPmi = false;
    protected int $summaryHeaderRow = 5;
    protected int $summaryEndRow = 9;
    protected int $tableHeaderRow = 11;
    protected int $dataStartRow = 12;
    protected int $dataRowCount = 0;

    public function __construct(int $pharmacyId, ?Collection $medicines = null, array $metricsMap = [])
    {
        $this->pharmacyId = $pharmacyId;
        $this->medicines = $medicines;
        $this->metricsMap = $metricsMap;
        $this->pharmacy = Pharmacies::find($this->pharmacyId);
        $this->isPmi = ($this->pharmacyId <= 0 || $this->pharmacyId === 1 || $this->pharmacyId === 9);
    }

    public function title(): string
    {
        return 'Rekonsiliasi Finance';
    }

    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'B' && $cell->getRow() >= $this->dataStartRow) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function headings(): array
    {
        $cols = [
            'NO',
            'KODE OBAT',
            'NAMA OBAT',
            'NAMA PABRIK',
            'SATUAN',
            'HNA (FINANCE)',
            'HNA + PPN 11%',
            'STOK EXCEL (TOTAL)',
            'KETERANGAN EXCEL',
            'NILAI EXCEL (NON-KONSI)',
        ];

        if ($this->isPmi) {
            $cols[] = 'FIN QTY GUDANG (>0)';
        }

        return array_merge($cols, [
            'FIN QTY ETALASE (>0)',
            'FIN TOTAL QTY (>0)',
            'NILAI STOK FINANCE (RP)',
            'SELISIH QTY (FIN - XLS)',
            'SELISIH NILAI (RP)',
            'QTY KONSI (>0)',
            'QTY MINUS (<=0)',
            'STATUS',
            'PENYEBAB SELISIH',
        ]);
    }

    public function columnWidths(): array
    {
        if ($this->isPmi) {
            return [
                'A' => 7,
                'B' => 15,
                'C' => 38,
                'D' => 24,
                'E' => 10,
                'F' => 16,
                'G' => 16,
                'H' => 15,
                'I' => 16,
                'J' => 20,
                'K' => 16,
                'L' => 16,
                'M' => 16,
                'N' => 22,
                'O' => 16,
                'P' => 20,
                'Q' => 14,
                'R' => 14,
                'S' => 15,
                'T' => 46,
            ];
        }

        return [
            'A' => 7,
            'B' => 15,
            'C' => 38,
            'D' => 24,
            'E' => 10,
            'F' => 16,
            'G' => 16,
            'H' => 15,
            'I' => 16,
            'J' => 20,
            'K' => 16,
            'L' => 16,
            'M' => 22,
            'N' => 16,
            'O' => 20,
            'P' => 14,
            'Q' => 14,
            'R' => 15,
            'S' => 46,
        ];
    }

    public function array(): array
    {
        $report = app(FinanceStockReconciliationService::class)->buildReport(
            $this->pharmacyId,
            $this->medicines,
            $this->metricsMap
        );

        $this->isPmi = (bool) $report['isPmi'];
        $summary = $report['summary'];
        $reportRows = $report['rows'];

        $branchName = $this->pharmacy ? $this->pharmacy->name : ($this->isPmi ? 'SAHABAT PMI / Gudang' : 'Apotek Cabang');

        $rows = [
            ["APOTEK PROPHARMA — {$branchName}"],
            ['REKONSILIASI VALUASI STOK EXCEL VS FINANCE (NON-KONSINYASI, QTY > 0, HNA × 1,11)'],
            ['Unit: ' . ($this->isPmi ? "Gudang Pusat + Etalase PMI ({$branchName})" : $branchName) . '  |  Dicetak: ' . now()->format('d/m/Y H:i') . ' WIB'],
            [''],
            ['RINGKASAN REKONSILIASI', 'SUMBER / PENJELASAN', 'TOTAL QTY', 'TOTAL NILAI (RP)'],
        ];

        $this->summaryHeaderRow = count($rows);

        if ($this->isPmi) {
            $rows[] = ['Finance — Etalase PMI', 'Qty > 0 non-konsinyasi, HNA × 1,11', '', (float) $summary['finance_etalase_value']];
            $rows[] = ['Finance — Gudang PMI', 'Stok > 0 non-konsinyasi, HNA × 1,11', '', (float) $summary['finance_gudang_value']];
        }

        $rows[] = [
            'Total Nilai Stok Finance',
            'Identik dengan kartu Nilai Stok di Finance Dashboard',
            (float) $summary['finance_total_qty'],
            (float) $summary['finance_total_value'],
        ];
        $rows[] = [
            'Total Nilai versi Excel Sheet 1 (Exclude Konsinyasi)',
            'TOTAL STOK × HNA × 1,11 (kecuali Keterangan = Konsinyasi)',
            (float) $summary['excel_non_cons_qty'],
            (float) $summary['excel_non_cons_value'],
        ];
        $rows[] = [
            'Total Selisih (Finance - Excel)',
            $summary['mismatch_count'] . ' item obat memiliki selisih (diurutkan paling atas di tabel bawah)',
            round((float) $summary['finance_total_qty'] - (float) $summary['excel_non_cons_qty'], 2),
            (float) $summary['total_diff_value'],
        ];

        $this->summaryEndRow = count($rows);

        $rows[] = [''];
        $rows[] = $this->headings();
        $this->tableHeaderRow = count($rows);
        $this->dataStartRow = $this->tableHeaderRow + 1;

        $this->dataRowCount = count($reportRows);
        if ($this->dataRowCount === 0) {
            $rows[] = ['Tidak ada data stok obat yang perlu direkonsiliasi.'];
            return $rows;
        }

        $sumExcelQty = 0;
        $sumExcelVal = 0.0;
        $sumFinGudangQty = 0.0;
        $sumFinEtalaseQty = 0.0;
        $sumFinQty = 0.0;
        $sumFinVal = 0.0;
        $sumDiffQty = 0.0;
        $sumDiffVal = 0.0;
        $sumConsQty = 0.0;
        $sumNegQty = 0.0;

        foreach ($reportRows as $index => $item) {
            $sumExcelQty += $item['excel_qty'];
            $sumExcelVal += $item['excel_value'];
            $sumFinGudangQty += $item['finance_gudang_qty'];
            $sumFinEtalaseQty += $item['finance_etalase_qty'];
            $sumFinQty += $item['finance_total_qty'];
            $sumFinVal += $item['finance_value'];
            $sumDiffQty += $item['diff_qty'];
            $sumDiffVal += $item['diff_value'];
            $sumConsQty += $item['consignment_pos_qty'];
            $sumNegQty += $item['negative_qty'];

            $row = [
                $index + 1,
                $item['code'],
                $item['name'],
                $item['factory_name'],
                $item['unit'],
                $item['hna_finance'],
                $item['hna_ppn'],
                $item['excel_qty'],
                $item['excel_keterangan'],
                $item['excel_value'],
            ];

            if ($this->isPmi) {
                $row[] = $item['finance_gudang_qty'];
            }

            $rows[] = array_merge($row, [
                $item['finance_etalase_qty'],
                $item['finance_total_qty'],
                $item['finance_value'],
                $item['diff_qty'],
                $item['diff_value'],
                $item['consignment_pos_qty'],
                $item['negative_qty'],
                $item['status_label'],
                $item['reason'],
            ]);
        }

        $totalRow = [
            'TOTAL KESELURUHAN',
            '',
            '',
            '',
            '',
            '',
            '',
            $sumExcelQty,
            '',
            round($sumExcelVal, 2),
        ];

        if ($this->isPmi) {
            $totalRow[] = round($sumFinGudangQty, 2);
        }

        $rows[] = array_merge($totalRow, [
            round($sumFinEtalaseQty, 2),
            round($sumFinQty, 2),
            (float) $summary['finance_total_value'],
            round($sumDiffQty, 2),
            (float) $summary['total_diff_value'],
            round($sumConsQty, 2),
            round($sumNegQty, 2),
            '',
            '',
        ]);

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $this->isPmi ? 'T' : 'S';
        $finValCol = $this->isPmi ? 'N' : 'M';
        $diffQtyCol = $this->isPmi ? 'O' : 'N';
        $diffValCol = $this->isPmi ? 'P' : 'O';
        $negQtyCol = $this->isPmi ? 'R' : 'Q';
        $statusCol = $this->isPmi ? 'S' : 'R';
        $reasonCol = $this->isPmi ? 'T' : 'S';

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '047857']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9.5, 'color' => ['rgb' => '64748B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);
        $sheet->getRowDimension(4)->setRowHeight(8);

        // Summary KPI Block
        $sh = $this->summaryHeaderRow;
        $se = $this->summaryEndRow;

        $sheet->getStyle("A{$sh}:D{$sh}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9.5, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getRowDimension($sh)->setRowHeight(24);

        $sheet->getStyle("A" . ($sh + 1) . ":D{$se}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A" . ($sh + 1) . ":B{$se}")->getFont()->setSize(9.5);
        $sheet->getStyle("C" . ($sh + 1) . ":D{$se}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            'numberFormat' => ['formatCode' => '#,##0.00'],
            'font' => ['bold' => true, 'size' => 10],
        ]);
        $sheet->getStyle("A{$se}:D{$se}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
        ]);

        // Table Header
        $th = $this->tableHeaderRow;
        $sheet->getRowDimension($th)->setRowHeight(30);
        $sheet->getStyle("A{$th}:{$lastCol}{$th}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9.5, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getStyle("A{$th}:E{$th}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F172A');
        $sheet->getStyle("{$finValCol}{$th}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('047857');
        $sheet->getStyle("{$diffQtyCol}{$th}:{$diffValCol}{$th}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B45309');
        $sheet->getStyle("{$statusCol}{$th}:{$reasonCol}{$th}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('475569');

        if ($this->dataRowCount > 0) {
            $start = $this->dataStartRow;
            $end = $start + $this->dataRowCount - 1;

            $sheet->getStyle("A{$start}:{$lastCol}{$end}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$start}:B{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$start}:E{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$start}:I{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$statusCol}{$start}:{$statusCol}{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("F{$start}:H{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0.00'],
            ]);
            $sheet->getStyle("J{$start}:{$negQtyCol}{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0.00'],
            ]);

            $sheet->getStyle("{$finValCol}{$start}:{$finValCol}{$end}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'ECFDF5']],
                'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
            ]);

            $mismatchRule = new Conditional();
            $mismatchRule->setConditionType(Conditional::CONDITION_CELLIS);
            $mismatchRule->setOperatorType(Conditional::OPERATOR_EQUAL);
            $mismatchRule->addCondition('"ADA SELISIH"');
            $mismatchRule->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB('FEF2F2');
            $mismatchRule->getStyle()->getFont()->getColor()->setRGB('B91C1C');
            $mismatchRule->getStyle()->getFont()->setBold(true);
            $sheet->getStyle("{$statusCol}{$start}:{$statusCol}{$end}")->setConditionalStyles([$mismatchRule]);

            $totalRow = $end + 1;
            $sheet->getRowDimension($totalRow)->setRowHeight(26);
            $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
            $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E3A8A']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                ],
            ]);
            $sheet->getStyle("H{$totalRow}:{$negQtyCol}{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0.00'],
            ]);

            $sheet->freezePane("A{$start}");
            $sheet->setAutoFilter("A{$th}:{$lastCol}{$end}");
        }

        $sheet->setShowGridlines(true);

        return [];
    }
}
