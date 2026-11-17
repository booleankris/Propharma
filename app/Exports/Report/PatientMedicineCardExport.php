<?php

namespace App\Exports\Report;

use App\Models\Medicines;
use App\Models\Patients;
use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PatientMedicineCardExport implements WithMultipleSheets
{
    public function __construct(
        protected array $data,
        protected array $filters = []
    ) {}

    public function sheets(): array
    {
        return [
            new PatientMedicineCardSummarySheet($this->data, $this->filters),
            new PatientMedicineCardTimelineSheet($this->data, $this->filters),
        ];
    }
}

abstract class BasePatientMedicineCardSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected int $dataStartRow = 7;
    protected int $dataRowCount = 0;

    public function __construct(
        protected array $data,
        protected array $filters = []
    ) {}

    protected function buildHeaderBlock(string $subtitle): array
    {
        $pharmacyLabel = 'Semua Cabang';
        $pharmacyId = $this->filters['pharmacy_id'] ?? null;
        if ($pharmacyId && $pharmacyId !== 'all') {
            $ph = Pharmacies::find((int) $pharmacyId);
            if ($ph) {
                $pharmacyLabel = $ph->name;
            }
        }

        $patientLabel = 'Semua Pasien';
        $patientFilter = trim((string) ($this->filters['patient_filter'] ?? ''));
        if ($patientFilter !== '') {
            if (is_numeric($patientFilter)) {
                $p = Patients::find((int) $patientFilter);
                $patientLabel = $p ? trim(($p->code ? "[{$p->code}] " : '') . $p->name) : $patientFilter;
            } else {
                $patientLabel = $patientFilter;
            }
        }

        $medicineLabel = 'Semua Obat';
        $medicineFilter = trim((string) ($this->filters['medicine_filter'] ?? ''));
        if ($medicineFilter !== '') {
            if (is_numeric($medicineFilter)) {
                $m = Medicines::find((int) $medicineFilter);
                $medicineLabel = $m ? trim(($m->code ? "[{$m->code}] " : '') . $m->name) : $medicineFilter;
            } else {
                $medicineLabel = $medicineFilter;
            }
        }

        $period = $this->data['period'] ?? [];
        $start = !empty($period['start_date']) ? Carbon::parse($period['start_date'])->format('d/m/Y') : now()->format('d/m/Y');
        $end = !empty($period['end_date']) ? Carbon::parse($period['end_date'])->format('d/m/Y') : now()->format('d/m/Y');

        return [
            ['APOTEK PROPHARMA - ' . strtoupper($pharmacyLabel)],
            [$subtitle],
            ["Periode: {$start} s/d {$end} | Pasien: {$patientLabel} | Obat: {$medicineLabel}"],
            ['Dicetak: ' . now()->format('d/m/Y H:i')],
            [''],
        ];
    }

    protected function styleCommonHeader(Worksheet $sheet, string $lastCol): void
    {
        foreach ([1, 2, 3, 4] as $r) {
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        }

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A3:A4')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getRowDimension(2)->setRowHeight(19);
        $sheet->getRowDimension(3)->setRowHeight(17);
        $sheet->getRowDimension(4)->setRowHeight(17);

        $headerRow = 6;
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F172A']],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);
    }
}

class PatientMedicineCardSummarySheet extends BasePatientMedicineCardSheet
{
    public function title(): string
    {
        return 'Rekap Frekuensi Obat';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 12,
            'C' => 26,
            'D' => 15,
            'E' => 13,
            'F' => 32,
            'G' => 11,
            'H' => 13,
            'I' => 13,
            'J' => 13,
            'K' => 13,
            'L' => 17,
            'M' => 17,
            'N' => 17,
            'O' => 18,
            'P' => 18,
        ];
    }

    public function array(): array
    {
        $rows = $this->buildHeaderBlock('KARTU OBAT PASIEN - REKAP FREKUENSI & QTY PER OBAT');
        $rows[] = [
            'No',
            'Kode Pasien',
            'Nama Pasien',
            'No. Telp',
            'Kode Obat',
            'Nama Obat',
            'Satuan',
            'Frekuensi Beli',
            'Qty Beli',
            'Qty Retur',
            'Qty Bersih',
            'Nilai Beli (Rp)',
            'Nilai Retur (Rp)',
            'Nilai Bersih (Rp)',
            'Pertama Beli',
            'Terakhir Beli',
        ];

        $summary = $this->data['summary'] ?? [];
        $this->dataRowCount = count($summary);

        $no = 1;
        $totFreq = 0;
        $totQtyBeli = 0.0;
        $totQtyRetur = 0.0;
        $totQtyNet = 0.0;
        $totNomBeli = 0.0;
        $totNomRetur = 0.0;
        $totNomNet = 0.0;

        foreach ($summary as $item) {
            $totFreq += (int) $item['frekuensi_beli'];
            $totQtyBeli += (float) $item['qty_beli'];
            $totQtyRetur += (float) $item['qty_retur'];
            $totQtyNet += (float) $item['qty_net'];
            $totNomBeli += (float) $item['nominal_beli'];
            $totNomRetur += (float) $item['nominal_retur'];
            $totNomNet += (float) $item['nominal_net'];

            $rows[] = [
                $no++,
                $item['patient_code'],
                $item['patient_name'],
                $item['patient_phone'],
                $item['medicine_code'],
                $item['medicine_name'],
                $item['medicine_unit'],
                (int) $item['frekuensi_beli'],
                (float) $item['qty_beli'],
                (float) $item['qty_retur'],
                (float) $item['qty_net'],
                (float) $item['nominal_beli'],
                (float) $item['nominal_retur'],
                (float) $item['nominal_net'],
                $item['first_buy_at'] ?? '-',
                $item['last_buy_at'] ?? '-',
            ];
        }

        if ($this->dataRowCount > 0) {
            $rows[] = [
                'TOTAL KESELURUHAN',
                '', '', '', '', '', '',
                $totFreq,
                round($totQtyBeli, 2),
                round($totQtyRetur, 2),
                round($totQtyNet, 2),
                round($totNomBeli, 2),
                round($totNomRetur, 2),
                round($totNomNet, 2),
                '', '',
            ];
        } else {
            $rows[] = ['Tidak ada data transaksi obat pasien pada filter yang dipilih.', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $this->styleCommonHeader($sheet, 'P');

        if ($this->dataRowCount > 0) {
            $start = $this->dataStartRow;
            $end = $start + $this->dataRowCount - 1;

            $sheet->getStyle("A{$start}:P{$end}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            for ($r = $start; $r <= $end; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(20);
                if ($r % 2 === 1) {
                    $sheet->getStyle("A{$r}:P{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
            }

            $sheet->getStyle("A{$start}:B{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$start}:E{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$start}:G{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$start}:N{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$start}:K{$end}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sheet->getStyle("L{$start}:N{$end}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("O{$start}:P{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $totalRow = $end + 1;
            $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
            $sheet->getStyle("A{$totalRow}:G{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$totalRow}:P{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1E3A8A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E40AF']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                ],
            ]);
            $sheet->getStyle("H{$totalRow}:N{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$totalRow}:K{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sheet->getStyle("L{$totalRow}:N{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getRowDimension($totalRow)->setRowHeight(23);
        } else {
            $emptyRow = $this->dataStartRow;
            $sheet->mergeCells("A{$emptyRow}:P{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:P{$emptyRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']],
            ]);
            $sheet->getRowDimension($emptyRow)->setRowHeight(28);
        }
    }
}

class PatientMedicineCardTimelineSheet extends BasePatientMedicineCardSheet
{
    public function title(): string
    {
        return 'Riwayat Kronologis';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,
            'B' => 13,
            'C' => 10,
            'D' => 22,
            'E' => 25,
            'F' => 14,
            'G' => 30,
            'H' => 11,
            'I' => 15,
            'J' => 13,
            'K' => 12,
            'L' => 14,
            'M' => 16,
            'N' => 20,
        ];
    }

    public function array(): array
    {
        $rows = $this->buildHeaderBlock('KARTU OBAT PASIEN - RIWAYAT KRONOLOGIS PEMBELIAN & RETUR');
        $rows[] = [
            'No',
            'Tanggal',
            'Jam',
            'Kode Transaksi / Retur',
            'Nama Pasien',
            'Kode Obat',
            'Nama Obat',
            'Jenis',
            'Tipe Layanan',
            'Frekuensi Ke-',
            'Qty',
            'Akumulasi Qty',
            'Subtotal (Rp)',
            'Kasir / Cabang',
        ];

        $timeline = $this->data['timeline'] ?? [];
        $this->dataRowCount = count($timeline);

        $no = 1;
        $totSignedQty = 0.0;
        $totSignedSubtotal = 0.0;

        foreach ($timeline as $item) {
            $kode = $item['transaction_code'];
            if (!empty($item['reference_code'])) {
                $kode .= ' (Ref: ' . $item['reference_code'] . ')';
            }

            $totSignedQty += (float) $item['signed_qty'];
            $totSignedSubtotal += (float) $item['signed_subtotal'];

            $rows[] = [
                $no++,
                $item['date'],
                $item['time'],
                $kode,
                $item['patient_name'],
                $item['medicine_code'],
                $item['medicine_name'],
                $item['event_type'],
                $item['transaction_type'],
                $item['frekuensi_ke'] ? 'Ke-' . $item['frekuensi_ke'] : 'Retur',
                (float) $item['signed_qty'],
                (float) $item['akumulasi_qty'],
                (float) $item['signed_subtotal'],
                $item['operator'] . ' / ' . $item['pharmacy_name'],
            ];
        }

        if ($this->dataRowCount > 0) {
            $rows[] = [
                'TOTAL BERSIH',
                '', '', '', '', '', '', '', '', '',
                round($totSignedQty, 2),
                '',
                round($totSignedSubtotal, 2),
                '',
            ];
        } else {
            $rows[] = ['Tidak ada riwayat pembelian atau retur obat pasien pada filter yang dipilih.', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $this->styleCommonHeader($sheet, 'N');

        if ($this->dataRowCount > 0) {
            $start = $this->dataStartRow;
            $end = $start + $this->dataRowCount - 1;

            $sheet->getStyle("A{$start}:N{$end}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            for ($r = $start; $r <= $end; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(20);
                if ($r % 2 === 1) {
                    $sheet->getStyle("A{$r}:N{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
                }
                $jenis = $sheet->getCell("H{$r}")->getValue();
                if ($jenis === 'BELI') {
                    $sheet->getStyle("H{$r}")->getFont()->getColor()->setRGB('059669');
                    $sheet->getStyle("H{$r}")->getFont()->setBold(true);
                } elseif ($jenis === 'RETUR') {
                    $sheet->getStyle("H{$r}")->getFont()->getColor()->setRGB('E11D48');
                    $sheet->getStyle("H{$r}")->getFont()->setBold(true);
                }
            }

            $sheet->getStyle("A{$start}:C{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$start}:F{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$start}:J{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("K{$start}:M{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$start}:L{$end}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sheet->getStyle("M{$start}:M{$end}")->getNumberFormat()->setFormatCode('#,##0');

            $totalRow = $end + 1;
            $sheet->mergeCells("A{$totalRow}:J{$totalRow}");
            $sheet->getStyle("A{$totalRow}:J{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$totalRow}:N{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1E3A8A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E40AF']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                ],
            ]);
            $sheet->getStyle("K{$totalRow}:M{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$totalRow}:K{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.##');
            $sheet->getStyle("M{$totalRow}:M{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getRowDimension($totalRow)->setRowHeight(23);
        } else {
            $emptyRow = $this->dataStartRow;
            $sheet->mergeCells("A{$emptyRow}:N{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:N{$emptyRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']],
            ]);
            $sheet->getRowDimension($emptyRow)->setRowHeight(28);
        }
    }
}
