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

class CashExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
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
        return 'Pembelian Tunai (Cash)';
    }

    public function array(): array
    {
        $branchName = $this->activePharmacy ? $this->activePharmacy->name : 'Semua Cabang';

        // 1. Header Title Block
        $filterPbf = 'Semua PBF';
        if (!empty($this->filters['pbf'])) {
            $pbf = Creditors::where('code', $this->filters['pbf'])->orWhere('id', $this->filters['pbf'])->first();
            if ($pbf) $filterPbf = $pbf->name;
        }

        $filterAkun = 'Semua Status Akun';
        if (!empty($this->filters['account_status'])) {
            if ($this->filters['account_status'] === 'ASSIGNED') $filterAkun = 'Sudah Pilih Akun';
            elseif ($this->filters['account_status'] === 'UNASSIGNED') $filterAkun = 'Belum Pilih Akun';
        }

        $rows = [
            ["APOTEK PROPHARMA - {$branchName}"],
            ["LAPORAN PEMBELIAN TUNAI (CASH / LANGSUNG LUNAS)"],
            ["Dicetak: " . date('d/m/Y H:i') . " | PBF: {$filterPbf} | Status Akun: {$filterAkun}"],
            [''], // Row 4 Blank
            // Row 5 Table Headers
            [
                'No',
                'No. Faktur / Penerimaan',
                'Vendor (PBF / Supplier)',
                'Kode NT / Ref',
                'Apotek Cabang',
                'Tgl. Pembelian',
                'Akun Kas / Bank',
                'Status Akun & Pembayaran',
                'Subtotal (Rp)',
                'PPN 11% (Rp)',
                'Total Pembelian (Rp)',
            ],
        ];

        // 2. Query Data
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query = Receiving::with([
            'receiving_details' => function ($q) {
                $q->where('invoice_payment', 'TUNAI')
                  ->with(['creditor', 'payments.account', 'receiving_items.order_items.medicines']);
            },
            'pharmacy',
        ])
            ->whereIn('pharmacy_id', $this->targetPharmacyIds)
            ->whereHas('receiving_details', function ($q) {
                $q->where('invoice_payment', 'TUNAI');
            })
            ->orderBy('date', $sortOrder);

        $no = 1;
        $totalSubtotalSemua = 0;
        $totalPpnSemua = 0;
        $totalPembelianSemua = 0;

        foreach ($query->get() as $rcv) {
            foreach ($rcv->receiving_details as $detail) {
                if ($detail->invoice_payment !== 'TUNAI') continue;

                $vendorName = $detail->creditor->name ?? ($detail->creditor_code ?: 'PBF / Vendor');

                if (!empty($this->filters['search'])) {
                    $s = strtolower(trim($this->filters['search']));
                    $nomor = strtolower($detail->invoice_number ?: ($rcv->invoice_number ?: $rcv->code));
                    $ref = strtolower($detail->receiving_details_code ?: ($rcv->ref_number ?: $rcv->code));
                    $vnd = strtolower($vendorName);
                    if (!str_contains($nomor, $s) && !str_contains($ref, $s) && !str_contains($vnd, $s)) {
                        continue;
                    }
                }

                if (!empty($this->filters['pbf'])) {
                    $p = $this->filters['pbf'];
                    $credCode = $detail->creditor->code ?? ($detail->creditor_code ?? '');
                    $credId = $detail->creditor->id ?? '';
                    if ($credCode != $p && $credId != $p) {
                        continue;
                    }
                }

                $payment = $detail->payments->first();
                $hasAccount = ($payment && $payment->account);

                if (!empty($this->filters['account_status']) && $this->filters['account_status'] !== 'ALL') {
                    if ($this->filters['account_status'] === 'ASSIGNED' && !$hasAccount) {
                        continue;
                    }
                    if ($this->filters['account_status'] === 'UNASSIGNED' && $hasAccount) {
                        continue;
                    }
                }

                $items = $detail->receiving_items ?? collect();
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += (float) ($item->total ?? 0);
                }

                $ppn = (float) ($detail->ppn ?? round($subtotal * 0.11, 2));
                $calculatedTotal = (float) ($detail->total ?? ($subtotal + $ppn));
                $nominal = (float) ($detail->nominal ?? $calculatedTotal);
                $finalTotal = $nominal > 0 ? $nominal : $calculatedTotal;

                if ($hasAccount) {
                    $accountName = $payment->account->name . ($payment->account->code ? " ({$payment->account->code})" : '');
                    $statusText = 'LUNAS (AKUN SIAP)';
                } else {
                    $accountName = 'BELUM DITETAPKAN';
                    $statusText = 'LUNAS (BELUM PILIH AKUN)';
                }

                $tglBeli = $this->safeFormatDate($rcv->date);

                $totalSubtotalSemua += $subtotal;
                $totalPpnSemua += $ppn;
                $totalPembelianSemua += $finalTotal;

                $rows[] = [
                    $no++,
                    $detail->invoice_number ?: ($rcv->invoice_number ?: '-'),
                    $vendorName,
                    $detail->receiving_details_code ?: ($rcv->ref_number ?: '-'),
                    $rcv->pharmacy->name ?? '-',
                    $tglBeli,
                    $accountName,
                    $statusText,
                    $subtotal,
                    $ppn,
                    $finalTotal,
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
                $totalPembelianSemua,
            ];
            $this->lastRow = count($rows);
        } else {
            $rows[] = [
                'Tidak ada data pembelian tunai yang sesuai dengan filter yang dipilih.',
                '', '', '', '', '', '', '', '', '', ''
            ];
            $this->lastRow = count($rows);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Title Styling
        $sheet->mergeCells('A1:K1');
        $sheet->mergeCells('A2:K2');
        $sheet->mergeCells('A3:K3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '047857']], // Emerald-700
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']], // Slate-500
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // 2. Table Headers (Row 5)
        $sheet->getStyle('A5:K5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Dark Slate
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
                    $sheet->getStyle("A{$row}:K{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }

                $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('059669'); // Emerald
                $sheet->getStyle("H{$row}")->getFont()->setBold(true);
            }

            // General Data Grid Borders & Alignments
            $sheet->getStyle("A{$start}:K{$end}")->applyFromArray([
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

            // Currency formatting on Numeric Columns I, J, K
            $sheet->getStyle("I{$start}:K{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            // 4. Total / Summary Row
            $totalRow = $end + 1;
            $sheet->mergeCells("A{$totalRow}:H{$totalRow}");
            $sheet->getStyle("A{$totalRow}:H{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '065F46']],
            ]);

            $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'ECFDF5'], // Emerald-50 highlight
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '10B981']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '047857']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'A7F3D0']],
                ],
            ]);

            $sheet->getStyle("I{$totalRow}:K{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['bold' => true, 'color' => ['rgb' => '047857']],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);
        } else {
            // Empty state row
            $emptyRow = 6;
            $sheet->mergeCells("A{$emptyRow}:K{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:K{$emptyRow}")->applyFromArray([
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
            'F' => 15,  // Tgl Pembelian
            'G' => 20,  // Akun Pembayaran
            'H' => 18,  // Status
            'I' => 18,  // Subtotal
            'J' => 16,  // PPN
            'K' => 22,  // Total Pembelian
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
