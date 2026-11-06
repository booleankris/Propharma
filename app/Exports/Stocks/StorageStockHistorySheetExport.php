<?php

namespace App\Exports\Stocks;

use App\Services\StockOpnameImportService;
use Carbon\Carbon;
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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StorageStockHistorySheetExport extends DefaultValueBinder implements FromArray, WithStyles, WithColumnWidths, WithTitle, WithCustomValueBinder
{
    protected string $sheetTitle;
    protected string $factoryName;
    protected Collection $logs;
    protected $request;
    protected $pharmacy;
    protected int $dataRowCount = 0;
    protected int $headerRow = 5;

    public function __construct(
        string $sheetTitle,
        string $factoryName,
        Collection $logs,
        $request = null,
        $pharmacy = null
    ) {
        $this->sheetTitle = $sheetTitle;
        $this->factoryName = $factoryName;
        $this->logs = $logs;
        $this->request = $request;
        $this->pharmacy = $pharmacy;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Preserve string formatting for transaction code (C) and medicine code (D)
        if (in_array($cell->getColumn(), ['C', 'D'], true) && $cell->getRow() >= $this->headerRow + 1) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function headings(): array
    {
        return [
            'NO',
            'TANGGAL',
            'NO. TRANSAKSI',
            'KODE OBAT',
            'NAMA OBAT',
            'TIPE',
            'BATCH',
            'EXPIRED DATE',
            'SALDO AWAL',
            'MUTASI',
            'SALDO AKHIR',
            'PBF / PETUGAS',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // NO
            'B' => 20,  // TANGGAL
            'C' => 18,  // NO. TRANSAKSI
            'D' => 15,  // KODE OBAT
            'E' => 40,  // NAMA OBAT
            'F' => 16,  // TIPE
            'G' => 18,  // BATCH
            'H' => 14,  // EXPIRED DATE
            'I' => 13,  // SALDO AWAL
            'J' => 13,  // MUTASI
            'K' => 13,  // SALDO AKHIR
            'L' => 24,  // PBF / PETUGAS
        ];
    }

    public function array(): array
    {
        $branchName = $this->pharmacy ? $this->pharmacy->name : 'Gudang PMI';

        $startDate = $this->request?->get('start_date');
        $endDate = $this->request?->get('end_date');
        if ($startDate && $endDate) {
            $periodLabel = Carbon::parse($startDate)->format('d/m/Y') . ' s/d ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($startDate) {
            $periodLabel = 'Dari ' . Carbon::parse($startDate)->format('d/m/Y');
        } elseif ($endDate) {
            $periodLabel = 'Sampai ' . Carbon::parse($endDate)->format('d/m/Y');
        } else {
            $periodLabel = 'Semua Waktu';
        }

        $rows = [
            ["APOTEK PROPHARMA — " . strtoupper($branchName)],
            ["RIWAYAT PERGERAKAN STOK GUDANG"],
            ["Pabrik: " . $this->factoryName . "  |  Periode: " . $periodLabel . "  |  Dicetak: " . now()->format('d/m/Y H:i') . ' WIB'],
            [''],
            $this->headings(),
        ];

        $this->dataRowCount = $this->logs->count();

        if ($this->dataRowCount === 0) {
            $rows[] = ['Tidak ada riwayat pergerakan stok untuk pabrik ini.', '', '', '', '', '', '', '', '', '', '', ''];
            return $rows;
        }

        $typeMap = [
            2 => 'Pembelian',
            4 => 'Retur Beli',
            5 => 'Stock Opname',
            6 => 'Adjustment',
            7 => 'Mutasi Stok',
            8 => 'Revisi Faktur',
            9 => 'Hapus Terima',
        ];

        $no = 1;
        foreach ($this->logs as $log) {
            $d = $log->date ? ($log->date instanceof Carbon ? $log->date : Carbon::parse($log->date)) : null;
            if ($d && $d->format('H:i:s') === '00:00:00' && $log->created_at) {
                $cAt = $log->created_at instanceof Carbon ? $log->created_at : Carbon::parse($log->created_at);
                $dateFormatted = $d->format('Y-m-d') . ' ' . $cAt->format('H:i:s');
            } else {
                $dateFormatted = $d ? $d->format('Y-m-d H:i:s') : '-';
            }

            $trxCode = $log->transaction_code ?: ($log->code ?: '-');
            $medCode = $log->medicines?->code ?: '-';
            $medName = $log->medicines?->name ?: '-';
            $typeName = $typeMap[(int) $log->status] ?? ($log->type ?: '-');

            $batch = $log->batches;
            $batchName = $batch ? ($batch->name ?: '-') : '-';
            $parsedEd = StockOpnameImportService::parseSmartExpiredDate($batch?->expired_date);
            $ed = $parsedEd ? Carbon::parse($parsedEd)->format('d/m/Y') : ($batch?->expired_date ?: '-');

            $qtyBefore = (int) $log->qty_before;
            $delta = $this->calculateDelta($log);
            $qtyAfter = (int) $log->qty_after;

            $operator = $this->resolveOperator($log);

            $rows[] = [
                $no++,
                $dateFormatted,
                $trxCode,
                $medCode,
                $medName,
                $typeName,
                $batchName,
                $ed,
                $qtyBefore,
                $delta,
                $qtyAfter,
                $operator,
            ];
        }

        return $rows;
    }

    private function calculateDelta($log): int
    {
        $status = (int) $log->status;

        if ($status === 1 || $status === 4) {
            return -abs((int) $log->qty);
        }

        if (in_array($status, [2, 3], true)) {
            return abs((int) $log->qty);
        }

        if ($status === 8) {
            return (int) $log->qty;
        }

        if ($status === 9) {
            return -abs((int) $log->qty);
        }

        if ($status === 5) {
            $totalVal = $log->total;
            if ($totalVal !== null && $totalVal !== '' && $totalVal !== '-' && is_numeric($totalVal)) {
                return (int) $totalVal;
            }
            return (int) $log->qty_after - (int) $log->qty_before;
        }

        return (int) $log->qty_after - (int) $log->qty_before;
    }

    private function resolveOperator($log): string
    {
        if (in_array((int) $log->status, [2, 4], true)) {
            try {
                $receiving = $log->relationLoaded('receiving') ? $log->getRelation('receiving') : $log->receiving;
                $pbf = $receiving?->receiving_details?->first()?->creditor?->name;
                if ($pbf) {
                    return $pbf;
                }
            } catch (\Throwable $e) {
                // Fallback to user if receiving relation cannot be resolved
            }
        }

        if (in_array((int) $log->status, [8, 9], true)) {
            if (preg_match('/REV-(?:ADD-|DEL-)?(\d+)/', (string) $log->transaction_code, $matches)) {
                try {
                    $rItem = \App\Models\ReceivingItems::with(['receiving_details.creditor', 'order_items.creditors'])->find($matches[1]);
                    $pbf = $rItem?->receiving_details?->creditor?->name ?? $rItem?->order_items?->creditors?->name;
                    if ($pbf) {
                        return $pbf;
                    }
                } catch (\Throwable $e) {
                    // Fallback to user
                }
            }
        }

        return $log->users?->name ?: '-';
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'L';
        $startRow = $this->headerRow + 1; // 6
        $count = $this->dataRowCount;

        // Title Block
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle("A2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(19);

        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getStyle("A3")->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(17);

        // Header Row 5
        $sheet->getRowDimension($this->headerRow)->setRowHeight(25);
        $sheet->getStyle("A{$this->headerRow}:{$lastCol}{$this->headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        if ($count > 0) {
            $endRow = $startRow + $count - 1;

            $sheet->getStyle("A{$startRow}:{$lastCol}{$endRow}")->applyFromArray([
                'font' => ['size' => 9, 'color' => ['rgb' => '1E293B']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Alignment per column
            $sheet->getStyle("A{$startRow}:A{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$startRow}:C{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$startRow}:D{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$startRow}:E{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("F{$startRow}:H{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$startRow}:K{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("L{$startRow}:L{$endRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Number formatting for stock numbers
            $sheet->getStyle("I{$startRow}:K{$endRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0;[Red]-#,##0;0');

            // Zebra striping
            for ($r = $startRow; $r <= $endRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                    ]);
                }
            }
        }
    }
}
