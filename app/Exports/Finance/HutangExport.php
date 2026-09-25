<?php

namespace App\Exports\Finance;

use App\Models\Receiving;
use App\Models\Pharmacies;
use App\Models\Creditors;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class HutangExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected array $targetPharmacyIds;
    protected array $filters;
    protected ?Pharmacies $activePharmacy;
    protected int $lastRow = 5;
    protected int $dataStartRow = 6;
    protected int $dataRowCount = 0;

    public function __construct(array $targetPharmacyIds, array $filters = [], ?Pharmacies $activePharmacy = null)
    {
        $this->targetPharmacyIds = $targetPharmacyIds;
        $this->filters = $filters;
        $this->activePharmacy = $activePharmacy;
    }

    public function title(): string
    {
        return 'Hutang Dagang (PBF)';
    }

    public function array(): array
    {
        $branchName = $this->activePharmacy ? $this->activePharmacy->name : 'Semua Cabang';

        // 1. Header Title Block
        $filterStatus = $this->filters['status'] ?? 'SEMUA';
        $filterPbf = 'Semua PBF';
        if (!empty($this->filters['pbf'])) {
            $pbf = Creditors::where('code', $this->filters['pbf'])->orWhere('id', $this->filters['pbf'])->first();
            if ($pbf) $filterPbf = $pbf->name;
        }

        $rows = [
            ["APOTEK PROPHARMA - {$branchName}"],
            ["LAPORAN HUTANG DAGANG KE PBF (PEMBELIAN KREDIT)"],
            ["Dicetak: " . date('d/m/Y H:i') . " | Status Tagihan: {$filterStatus} | Filter PBF: {$filterPbf}"],
            [''], // Row 4 Blank
            // Row 5 Table Headers
            [
                'No',
                'No. Faktur / Penerimaan',
                'Vendor (PBF / Supplier)',
                'Kode NT',
                'Apotek Cabang',
                'Tgl. Penerimaan',
                'Jatuh Tempo',
                'Status Tagihan',
                'Subtotal (Rp)',
                'PPN 11% (Rp)',
                'Total Tagihan (Rp)',
                'Sudah Terbayar (Rp)',
                'Sisa Hutang (Rp)',
            ],
        ];

        // 2. Query Data
        $query = Receiving::with([
            'receiving_details' => function ($q) {
                $q->where('invoice_payment', 'KREDIT')
                  ->with(['creditor', 'payments', 'receiving_items.order_items.medicines']);
            },
            'pharmacy',
        ])
            ->whereIn('pharmacy_id', $this->targetPharmacyIds)
            ->whereHas('receiving_details', function ($q) {
                $q->where('invoice_payment', 'KREDIT');
            });

        if (!empty($this->filters['search'])) {
            $s = trim($this->filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                    ->orWhere('ref_number', 'like', "%{$s}%")
                    ->orWhereHas('receiving_details.creditor', function ($qc) use ($s) {
                        $qc->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
                    });
            });
        }

        if (!empty($this->filters['pbf'])) {
            $p = $this->filters['pbf'];
            $query->whereHas('receiving_details.creditor', function ($qc) use ($p) {
                $qc->where('code', $p)->orWhere('id', $p);
            });
        }

        $query->orderBy('date', 'desc')->orderBy('id', 'desc');

        $no = 1;
        $totalSubtotalSemua = 0;
        $totalPpnSemua = 0;
        $totalTagihanSemua = 0;
        $totalTerbayarSemua = 0;
        $totalSisaSemua = 0;

        foreach ($query->get() as $rcv) {
            foreach ($rcv->receiving_details as $detail) {
                $items = $detail->receiving_items ?? collect();
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += (float) ($item->total ?? 0);
                }

                $ppn = (float) ($detail->ppn ?? round($subtotal * 0.11, 2));
                $calculatedTotal = (float) ($detail->total ?? ($subtotal + $ppn));
                $nominal = (float) ($detail->nominal ?? $calculatedTotal);
                $finalTotal = $nominal > 0 ? $nominal : $calculatedTotal;

                $totalPaid = (float) $detail->payments->sum('amount');
                $sisa = round(max(0, $finalTotal - $totalPaid), 2);
                $isLunas = ($sisa < 0.005 && $finalTotal > 0);
                $statusLabel = $isLunas ? 'LUNAS' : ($totalPaid > 0 ? 'DIBAYAR SEBAGIAN' : 'BELUM LUNAS');

                if (!empty($this->filters['status'])) {
                    if ($this->filters['status'] === 'LUNAS' && !$isLunas) continue;
                    if ($this->filters['status'] === 'BELUM_LUNAS' && $isLunas) continue;
                }

                $tglFaktur = $this->safeFormatDate($rcv->date);
                $tempoDate = $this->safeFormatDate($detail->payment_due_date);

                $totalSubtotalSemua += $subtotal;
                $totalPpnSemua += $ppn;
                $totalTagihanSemua += $finalTotal;
                $totalTerbayarSemua += $totalPaid;
                $totalSisaSemua += $sisa;

                $rows[] = [
                    $no++,
                    $rcv->invoice_number ?: '-',
                    $detail->creditor->name ?? '-',
                    $rcv->ref_number ?: '-',
                    $rcv->pharmacy->name ?? '-',
                    $tglFaktur,
                    $tempoDate,
                    $statusLabel,
                    $subtotal,
                    $ppn,
                    $finalTotal,
                    $totalPaid,
                    $sisa,
                ];
            }
        }

        $this->dataRowCount = $no - 1;
        $this->lastRow = count($rows);

        // 3. Total Row if data exists
        if ($this->dataRowCount > 0) {
            $rows[] = [
                'TOTAL KESELURUHAN',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                $totalSubtotalSemua,
                $totalPpnSemua,
                $totalTagihanSemua,
                $totalTerbayarSemua,
                $totalSisaSemua,
            ];
            $this->lastRow = count($rows);
        } else {
            $rows[] = [
                'Tidak ada data hutang dagang yang sesuai dengan filter yang dipilih.',
                '', '', '', '', '', '', '', '', '', '', '', ''
            ];
            $this->lastRow = count($rows);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Title Styling
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']], // Blue-800
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']], // Slate-500
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // 2. Table Headers (Row 5)
        $sheet->getStyle('A5:M5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Dark Corporate Slate
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F172A']],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(28);

        // 3. Data Rows Styling
        if ($this->dataRowCount > 0) {
            $start = $this->dataStartRow;
            $end = $start + $this->dataRowCount - 1;

            for ($row = $start; $row <= $end; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(20);
                
                // Zebra striping for readability
                if ($row % 2 == 1) {
                    $sheet->getStyle("A{$row}:M{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }

                $statusBayar = $sheet->getCell("H{$row}")->getValue();
                if ($statusBayar === 'LUNAS') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('059669');
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'DIBAYAR SEBAGIAN') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('D97706');
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'BELUM LUNAS') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('DC2626');
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true);
                }
            }

            // General Data Grid Borders & Alignments
            $sheet->getStyle("A{$start}:M{$end}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            $sheet->getStyle("A{$start}:A{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$start}:B{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("B{$start}:B{$end}")->getFont()->setBold(true);
            $sheet->getStyle("C{$start}:C{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("D{$start}:D{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$start}:E{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("F{$start}:H{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Currency formatting on Numeric Columns I, J, K, L, M
            $sheet->getStyle("I{$start}:M{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            // 4. Total / Summary Row
            $totalRow = $end + 1;
            $sheet->mergeCells("A{$totalRow}:H{$totalRow}");
            $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1E3A8A']],
            ]);

            $sheet->getStyle("A{$totalRow}:M{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EFF6FF'], // Blue-50 highlight
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E40AF']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                ],
            ]);

            $sheet->getStyle("I{$totalRow}:M{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['bold' => true, 'color' => ['rgb' => '1E40AF']],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);
        } else {
            // Empty state row
            $emptyRow = 6;
            $sheet->mergeCells("A{$emptyRow}:M{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:M{$emptyRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
            ]);
            $sheet->getRowDimension($emptyRow)->setRowHeight(30);
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 20,  // No Faktur
            'C' => 32,  // Vendor PBF
            'D' => 16,  // Kode NT
            'E' => 20,  // Apotek
            'F' => 15,  // Tgl Faktur
            'G' => 15,  // Jatuh Tempo
            'H' => 18,  // Status
            'I' => 18,  // Subtotal
            'J' => 16,  // PPN
            'K' => 20,  // Total Tagihan
            'L' => 20,  // Terbayar
            'M' => 20,  // Sisa Hutang
        ];
    }

    protected function safeFormatDate($date): string
    {
        if (empty($date)) return '-';
        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y');
        }
        $str = trim((string) $date);
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}/', $str)) {
            return substr($str, 0, 10);
        }
        try {
            return Carbon::parse($str)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $str;
        }
    }
}
