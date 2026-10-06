<?php

namespace App\Exports\Finance;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MonitoringPenjualanExport implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private array $days;
    private int $month;
    private int $year;
    private array $sectionRows = [];
    private array $gapRows = [];
    private array $toneRows = [];
    private int $lastColumn;
    private int $lastRow;

    public function __construct(array $days, int $month, int $year)
    {
        $this->days = $days;
        $this->month = $month;
        $this->year = $year;
        $this->lastColumn = 4 + count($days) * 3 + 4;
        $this->lastRow = 5 + count($this->reportRows());
    }

    public function title(): string
    {
        return 'Monitoring Penjualan';
    }

    private function reportRows(): array
    {
        return [
            ['section', 'I', 'PENJUALAN OBAT TOTAL'],
            ['data', '1', 'RESEP TUNAI', 'sales', 'cashPrescription'],
            ['data', '2', 'UPDS', 'sales', 'upds'],
            ['data', '3', 'HV', 'sales', 'hv'],
            ['data', '4', 'RETUR TUNAI', 'sales', 'returns'],
            ['data', '', 'PENJUALAN TUNAI', 'sales', 'cashTotal', 'yellow'],
            ['data', '1', 'RESEP KREDIT', 'sales', 'creditPrescription'],
            ['data', '', 'PENJUALAN KREDIT', 'sales', 'creditTotal', 'yellow'],
            ['data', '', 'TOTAL PENJUALAN OBAT', 'sales', 'total', 'green'],
            ['data', '', 'TOTAL PENJUALAN ALL', 'sales', 'total', 'blue'],
            ['gap'],
            ['section', 'I', 'PENJUALAN ONLINE'],
            ['data', '', 'WHATSAPP', 'sales', 'onlineWhatsappTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'sales', 'onlineWhatsappNonPrescription'],
            ['data', '2', 'RESEP', 'sales', 'onlineWhatsappPrescription'],
            ['data', '', 'SHOPEE', 'sales', 'onlineShopee', 'yellow'],
            ['data', '', 'TIKTOK', 'sales', 'onlineTiktok', 'yellow'],
            ['data', '', 'GRABMART', 'sales', 'onlineGrabmart', 'yellow'],
            ['data', '', 'DIGITAL / ONLINE LAINNYA', 'sales', 'onlineDigital', 'yellow'],
            ['data', '', 'TOTAL PENJUALAN ONLINE', 'sales', 'onlineTotal', 'blue'],
            ['gap'],
            ['section', 'I', 'PENJUALAN OFFLINE'],
            ['data', '', 'OFFLINE', 'sales', 'offlineTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'sales', 'offlineNonPrescription'],
            ['data', '2', 'RESEP', 'sales', 'offlinePrescription'],
            ['data', '', 'TOTAL PENJUALAN OFFLINE', 'sales', 'offlineTotal', 'blue'],
            ['data', '', 'RETUR', 'sales', 'offlineReturns'],
            ['gap'],
            ['section', 'II', 'TOTAL KUNJUNGAN (ON + OFF)'],
            ['data', '1', 'NON RESEP'],
            ['data', '2', 'RESEP'],
            ['data', '', 'TRANSAKSI TUNAI', null, null, 'yellow'],
            ['data', '', 'TOTAL KUNJUNGAN ONLINE'],
            ['data', '', 'TOTAL KUNJUNGAN OFFLINE'],
            ['data', '', 'TOTAL KUNJUNGAN ALL', null, null, 'blue'],
            ['gap'],
            ['section', 'II', 'TRANSAKSI PENJUALAN (LEMBAR)'],
            ['data', '1', 'RESEP TUNAI', 'receipts', 'cashPrescription'],
            ['data', '2', 'UPDS', 'receipts', 'upds'],
            ['data', '3', 'HV', 'receipts', 'hv'],
            ['data', '', 'TRANSAKSI TUNAI', 'receipts', 'cashTotal', 'yellow'],
            ['data', '1', 'RESEP KREDIT', 'receipts', 'creditPrescription'],
            ['data', '', 'TRANSAKSI KREDIT', 'receipts', 'creditTotal', 'yellow'],
            ['data', '', 'TOTAL TRANSAKSI ALL', 'receipts', 'total', 'green'],
            ['data', '', 'TOTAL TRANSAKSI ALL', 'receipts', 'total', 'blue'],
            ['gap'],
            ['section', 'II', 'TRANSAKSI PENJUALAN (LEMBAR) ONLINE'],
            ['data', '', 'WHATSAPP', 'receipts', 'onlineWhatsappTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'receipts', 'onlineWhatsappNonPrescription'],
            ['data', '2', 'RESEP', 'receipts', 'onlineWhatsappPrescription'],
            ['data', '', 'SHOPEE', 'receipts', 'onlineShopee', 'yellow'],
            ['data', '', 'TIKTOK', 'receipts', 'onlineTiktok', 'yellow'],
            ['data', '', 'GRABMART', 'receipts', 'onlineGrabmart', 'yellow'],
            ['data', '', 'DIGITAL / ONLINE LAINNYA', 'receipts', 'onlineDigital', 'yellow'],
            ['data', '', 'TOTAL TRANSAKSI ONLINE', 'receipts', 'onlineTotal', 'blue'],
            ['gap'],
            ['section', 'II', 'TRANSAKSI PENJUALAN (LEMBAR) OFFLINE'],
            ['data', '', 'OFFLINE', 'receipts', 'offlineTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'receipts', 'offlineNonPrescription'],
            ['data', '2', 'RESEP', 'receipts', 'offlinePrescription'],
            ['data', '', 'TOTAL TRANSAKSI OFFLINE', 'receipts', 'offlineTotal', 'blue'],
            ['gap'],
            ['section', 'III', 'TOTAL TRANSAKSI ONLINE'],
            ['data', '', 'WHATSAPP', 'receipts', 'onlineWhatsappTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'receipts', 'onlineWhatsappNonPrescription'],
            ['data', '2', 'RESEP', 'receipts', 'onlineWhatsappPrescription'],
            ['data', '', 'SHOPEE', 'receipts', 'onlineShopee', 'yellow'],
            ['data', '', 'TIKTOK', 'receipts', 'onlineTiktok', 'yellow'],
            ['data', '', 'GRABMART', 'receipts', 'onlineGrabmart', 'yellow'],
            ['data', '', 'DIGITAL / ONLINE LAINNYA', 'receipts', 'onlineDigital', 'yellow'],
            ['data', '', 'TOTAL TRANSAKSI ONLINE', 'receipts', 'onlineTotal', 'blue'],
            ['gap'],
            ['section', 'III', 'TOTAL TRANSAKSI OFFLINE'],
            ['data', '', 'OFFLINE', 'receipts', 'offlineTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'receipts', 'offlineNonPrescription'],
            ['data', '2', 'RESEP', 'receipts', 'offlinePrescription'],
            ['data', '', 'TOTAL TRANSAKSI OFFLINE', 'receipts', 'offlineTotal', 'blue'],
            ['gap'],
            ['section', 'IV', 'JUMLAH ITEM OBAT (R/)'],
            ['data', '1', 'RESEP TUNAI', 'items', 'cashPrescription'],
            ['data', '2', 'UPDS', 'items', 'upds'],
            ['data', '3', 'HV', 'items', 'hv'],
            ['data', '', 'JUMLAH ITEM TUNAI', 'items', 'cashTotal', 'yellow'],
            ['data', '1', 'RESEP KREDIT', 'items', 'creditPrescription'],
            ['data', '', 'JUMLAH ITEM KREDIT', 'items', 'creditTotal', 'yellow'],
            ['data', '', 'TOTAL JUMLAH ITEM OBAT', 'items', 'total', 'green'],
            ['data', '', 'TOTAL JUMLAH ITEM (R/) ALL', 'items', 'total', 'blue'],
            ['gap'],
            ['section', 'IV', 'JUMLAH ITEM ONLINE'],
            ['data', '', 'WHATSAPP', 'items', 'onlineWhatsappTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'items', 'onlineWhatsappNonPrescription'],
            ['data', '2', 'RESEP', 'items', 'onlineWhatsappPrescription'],
            ['data', '', 'SHOPEE', 'items', 'onlineShopee', 'yellow'],
            ['data', '', 'TIKTOK', 'items', 'onlineTiktok', 'yellow'],
            ['data', '', 'GRABMART', 'items', 'onlineGrabmart', 'yellow'],
            ['data', '', 'DIGITAL / ONLINE LAINNYA', 'items', 'onlineDigital', 'yellow'],
            ['data', '', 'TOTAL JUMLAH ITEM ONLINE', 'items', 'onlineTotal', 'blue'],
            ['gap'],
            ['section', 'IV', 'TOTAL ITEM OFFLINE'],
            ['data', '', 'OFFLINE', 'items', 'offlineTotal', 'yellow'],
            ['data', '1', 'NON RESEP', 'items', 'offlineNonPrescription'],
            ['data', '2', 'RESEP', 'items', 'offlinePrescription'],
            ['data', '', 'TOTAL JUMLAH ITEM OFFLINE', 'items', 'offlineTotal', 'blue'],
        ];
    }

    private function value(array $day, string $shift, string $area, string $key): float
    {
        return (float) ($day[$shift][$area][$key] ?? 0);
    }

    public function array(): array
    {
        $blank = array_fill(0, $this->lastColumn, '');
        $headerOne = $blank;
        $headerOne[0] = 'NO.';
        $headerOne[1] = 'URAIAN';
        $headerOne[2] = 'AP PER BULAN';
        $headerOne[3] = 'TARGET';
        foreach ($this->days as $index => $day) {
            $headerOne[4 + $index * 3] = (int) Carbon::parse($day['date'])->format('j');
        }
        $summaryStart = 4 + count($this->days) * 3;
        $headerOne[$summaryStart] = 'REALISASI S/D';
        $headerOne[$summaryStart + 2] = 'REALISASI';

        $headerTwo = $blank;
        $headerTwo[2] = 'Rp';
        $headerTwo[3] = 'HARIAN';
        foreach ($this->days as $index => $_day) {
            $column = 4 + $index * 3;
            $headerTwo[$column] = 'RP (PAGI)';
            $headerTwo[$column + 1] = 'RP (MALAM)';
            $headerTwo[$column + 2] = '%';
        }
        $headerTwo[$summaryStart] = 'PAGI';
        $headerTwo[$summaryStart + 1] = 'MALAM';
        $headerTwo[$summaryStart + 2] = 'TOTAL';
        $headerTwo[$summaryStart + 3] = '% ASE';

        $rows = [$blank, ['MONITORING TARGET PENJUALAN'], ['PERIODE '.mb_strtoupper(Carbon::createFromDate($this->year, $this->month, 1)->locale('id')->translatedFormat('F Y'))], $headerOne, $headerTwo];
        $excelRow = 6;

        foreach ($this->reportRows() as $reportRow) {
            if ($reportRow[0] === 'gap') {
                $rows[] = $blank;
                $this->gapRows[] = $excelRow++;
                continue;
            }

            if ($reportRow[0] === 'section') {
                $row = $blank;
                $row[0] = $reportRow[1];
                $row[1] = $reportRow[2];
                $rows[] = $row;
                $this->sectionRows[] = $excelRow++;
                continue;
            }

            [, $number, $label, $area, $key, $tone] = array_pad($reportRow, 6, null);
            $row = $blank;
            $row[0] = $number;
            $row[1] = $label;
            $sumMorning = 0;
            $sumEvening = 0;
            $sumOther = 0;

            if ($area && $key) {
                foreach ($this->days as $index => $day) {
                    $morning = $this->value($day, 'morning', $area, $key);
                    $evening = $this->value($day, 'evening', $area, $key);
                    $other = $this->value($day, 'otherShift', $area, $key);
                    $column = 4 + $index * 3;
                    $row[$column] = $morning;
                    $row[$column + 1] = $evening;
                    $row[$column + 2] = '';
                    $sumMorning += $morning;
                    $sumEvening += $evening;
                    $sumOther += $other;
                }
                $row[$summaryStart] = $sumMorning;
                $row[$summaryStart + 1] = $sumEvening;
                $row[$summaryStart + 2] = $sumMorning + $sumEvening + $sumOther;
                $row[$summaryStart + 3] = '';
            }

            $rows[] = $row;
            if ($tone) $this->toneRows[$excelRow] = $tone;
            $excelRow++;
        }

        return $rows;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 7, 'B' => 34, 'C' => 17, 'D' => 15];
        for ($column = 5; $column <= $this->lastColumn; $column++) {
            $widths[Coordinate::stringFromColumnIndex($column)] = 13;
        }
        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $last = Coordinate::stringFromColumnIndex($this->lastColumn);
        $sheet->getStyle("A1:{$last}{$this->lastRow}")->getFont()->setName('Arial')->setSize(9);
        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font' => ['bold' => true, 'size' => 15, 'color' => ['rgb' => '172033']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A3:{$last}3")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '475569']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A4:{$last}5")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '111827']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
        ]);
        $sheet->getStyle("A4:{$last}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C5CDD7');
        $sheet->getStyle('C4:C5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B6D3C4');
        $sheet->getStyle('D4:D5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D0D0D0');
        $summaryStart = Coordinate::stringFromColumnIndex(5 + count($this->days) * 3);
        $summaryEnd = Coordinate::stringFromColumnIndex($this->lastColumn);
        $sheet->getStyle("{$summaryStart}4:{$summaryEnd}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7D7EF');
        $sheet->getStyle(Coordinate::stringFromColumnIndex($this->lastColumn - 1).'4:'.$summaryEnd.'5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9DCCB');

        if ($this->lastRow >= 6) {
            $sheet->getStyle("A6:{$last}{$this->lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '64748B']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('C6:D'.$this->lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5F3EC');
            $sheet->getStyle('E6:'.$last.$this->lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9F0F9');
            $sheet->getStyle($summaryStart.'6:'.$summaryEnd.$this->lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B7D7EF');
            $sheet->getStyle(Coordinate::stringFromColumnIndex($this->lastColumn - 1).'6:'.$summaryEnd.$this->lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9DCCB');
            $sheet->getStyle('E6:'.$last.$this->lastRow)->getNumberFormat()->setFormatCode('#,##0;[Red](#,##0);-');
            $sheet->getStyle('A6:A'.$this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('C6:'.$last.$this->lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        foreach ($this->sectionRows as $row) {
            $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C5CDD7']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
            ]);
        }
        foreach ($this->toneRows as $row => $tone) {
            $color = match ($tone) {
                'yellow' => 'FFF200',
                'green' => '78D52E',
                'blue' => 'B7CBE8',
                default => 'FFFFFF',
            };
            $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ]);
        }
        foreach ($this->reportRows() as $index => $reportRow) {
            if (($reportRow[0] ?? null) === 'data' && ($reportRow[3] ?? null) === 'sales') {
                $excelRow = 6 + $index;
                $sheet->getStyle("E{$excelRow}:{$last}{$excelRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0;[Red]("Rp" #,##0);-');
            }
        }
        foreach ($this->gapRows as $row) $sheet->getRowDimension($row)->setRowHeight(8);

        $sheet->getRowDimension(2)->setRowHeight(25);
        $sheet->getRowDimension(4)->setRowHeight(24);
        $sheet->getRowDimension(5)->setRowHeight(23);
        return [];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $last = Coordinate::stringFromColumnIndex($this->lastColumn);
            $sheet->mergeCells("A2:{$last}2");
            $sheet->mergeCells("A3:{$last}3");
            foreach (['A', 'B'] as $column) $sheet->mergeCells("{$column}4:{$column}5");

            foreach ($this->days as $index => $_day) {
                $start = Coordinate::stringFromColumnIndex(5 + $index * 3);
                $end = Coordinate::stringFromColumnIndex(7 + $index * 3);
                $sheet->mergeCells("{$start}4:{$end}4");
            }

            $summaryStartIndex = 5 + count($this->days) * 3;
            $summaryStart = Coordinate::stringFromColumnIndex($summaryStartIndex);
            $summaryMiddle = Coordinate::stringFromColumnIndex($summaryStartIndex + 1);
            $summaryEndStart = Coordinate::stringFromColumnIndex($summaryStartIndex + 2);
            $summaryEnd = Coordinate::stringFromColumnIndex($summaryStartIndex + 3);
            $sheet->mergeCells("{$summaryStart}4:{$summaryMiddle}4");
            $sheet->mergeCells("{$summaryEndStart}4:{$summaryEnd}4");

            foreach ($this->sectionRows as $row) {
                $sheet->mergeCells("C{$row}:D{$row}");
                foreach ($this->days as $index => $_day) {
                    $start = Coordinate::stringFromColumnIndex(5 + $index * 3);
                    $end = Coordinate::stringFromColumnIndex(7 + $index * 3);
                    $sheet->mergeCells("{$start}{$row}:{$end}{$row}");
                }
                $sheet->mergeCells("{$summaryStart}{$row}:{$summaryMiddle}{$row}");
                $sheet->mergeCells("{$summaryEndStart}{$row}:{$summaryEnd}{$row}");
            }

            $sheet->freezePane('E6');
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);
            $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.25)->setRight(0.25);
        }];
    }
}
