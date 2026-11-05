<?php

namespace App\Exports\Finance;

use App\Models\Pharmacies;
use App\Models\Receiving;
use App\Support\ReceivingInvoiceTotals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
            $filterPbf = $this->filters['pbf'];
        }

        $rows = [
            ["APOTEK PROPHARMA - {$branchName}"],
            ['LAPORAN HUTANG DAGANG KE PBF (PEMBELIAN KREDIT)'],
            ['Dicetak: ' . date('d/m/Y H:i') . " | Status Tagihan: {$filterStatus} | Filter PBF: {$filterPbf}"],
            [''],  // Row 4 Blank
            // Row 5 Table Headers (15 Columns: A to O)
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
                'Total Tagihan Bruto (Rp)',
                'Retur Pembelian (Rp)',
                'Tagihan Bersih (Rp)',
                'Sudah Terbayar (Rp)',
                'Sisa Hutang (Rp)',
            ],
        ];

        // 2. Query Data
        $query = Receiving::with([
            'receiving_details' => function ($q) {
                $q
                    ->where('invoice_payment', 'KREDIT')
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
            $query->whereHas('receiving_details', function ($q) use ($s) {
                $q
                    ->where('invoice_number', 'like', "%{$s}%")
                    ->orWhere('receiving_details_code', 'like', "%{$s}%")
                    ->orWhereHas('creditor', function ($qc) use ($s) {
                        $qc->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
                    });
            });
        }

        if (!empty($this->filters['pbf'])) {
            $p = $this->filters['pbf'];
            $query->whereHas('receiving_details.creditor', function ($qc) use ($p) {
                $qc->where('name', 'like', "%{$p}%")->orWhere('code', 'like', "%{$p}%");
            });
        }

        $query->orderBy('date', ($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc')->orderBy('id', 'desc');

        $receivings = $query->get();

        // Kumpulkan detail IDs dan invoice numbers untuk eager query retur pembelian
        $allDetailIds = [];
        $allInvoiceNumbers = [];
        foreach ($receivings as $rcv) {
            foreach ($rcv->receiving_details as $detail) {
                $allDetailIds[] = $detail->id;
                if (!empty($detail->invoice_number)) {
                    $allInvoiceNumbers[] = trim($detail->invoice_number);
                }
            }
        }
        $allInvoiceNumbers = array_values(array_filter(array_unique($allInvoiceNumbers)));

        $returnsQuery = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where(function ($q) use ($allDetailIds, $allInvoiceNumbers) {
                if (!empty($allDetailIds)) {
                    $q->whereIn('pr.receiving_id', $allDetailIds);
                }
                if (!empty($allInvoiceNumbers)) {
                    $q->orWhereIn('pr.invoice_number', $allInvoiceNumbers);
                }
            })
            ->where('pr.status', '!=', 'dibatalkan')
            ->select('pri.id', 'pri.subtotal', 'pri.status', 'pr.receiving_id as pr_receiving_id', 'pr.invoice_number as pr_invoice_number')
            ->get();

        $returnsByDetailId = $returnsQuery->filter(fn($r) => !empty($r->pr_receiving_id))->groupBy('pr_receiving_id');
        $returnsByInvoiceNumber = $returnsQuery->filter(fn($r) => !empty($r->pr_invoice_number))->groupBy(fn($r) => strtoupper(trim((string) $r->pr_invoice_number)));

        $no = 1;
        $totalSubtotalSemua = 0;
        $totalPpnSemua = 0;
        $totalTagihanSemua = 0;
        $totalReturSemua = 0;
        $totalNetSemua = 0;
        $totalTerbayarSemua = 0;
        $totalSisaSemua = 0;

        foreach ($receivings as $rcv) {
            foreach ($rcv->receiving_details as $detail) {
                $items = $detail->receiving_items ?? collect();
                $invoiceAmounts = ReceivingInvoiceTotals::calculate(
                    $items,
                    $detail->invoice_ppn ?? $detail->creditor?->ppn_type
                );
                $subtotal = $invoiceAmounts['subtotal'];
                $ppn = $invoiceAmounts['ppn'];
                $calculatedTotal = $invoiceAmounts['total'];
                $nominal = (float) ($detail->nominal ?? $calculatedTotal);
                $finalTotal = $nominal > 0 ? $nominal : $calculatedTotal;

                // Hitung retur pembelian
                $matchedReturns = collect();
                if (isset($returnsByDetailId[$detail->id])) {
                    $matchedReturns = $matchedReturns->merge($returnsByDetailId[$detail->id]);
                }
                $invNorm = strtoupper(trim((string) $detail->invoice_number));
                if ($invNorm !== '' && isset($returnsByInvoiceNumber[$invNorm])) {
                    $matchedReturns = $matchedReturns->merge($returnsByInvoiceNumber[$invNorm]);
                }
                $matchedReturns = $matchedReturns->unique('id');

                $sudahPotongRetur = (float) $matchedReturns->where('status', 'sudah_potong')->sum('subtotal');
                $financeRetur = (float) $detail->payments->where('payment_type', 'RETUR')->sum('amount');
                $totalRetur = round(max($sudahPotongRetur, $financeRetur), 2);
                $netTotal = round(max(0, $finalTotal - $totalRetur), 2);

                $totalPaid = (float) $detail->payments->where('payment_type', '!=', 'RETUR')->sum('amount');
                if ($totalPaid > $netTotal) {
                    $totalPaid = $netTotal;
                }
                $sisa = round(max(0, $netTotal - $totalPaid), 2);
                $isLunas = ($sisa < 0.005 && $finalTotal > 0) || ((int) $rcv->status === 3 && $sisa <= 0.005);
                $sisa = $isLunas ? 0 : $sisa;

                if ($isLunas) {
                    if ($totalRetur >= $finalTotal && $finalTotal > 0) {
                        $statusLabel = 'DIRETUR PENUH';
                    } elseif ($totalRetur > 0) {
                        $statusLabel = 'LUNAS (ADA RETUR)';
                    } else {
                        $statusLabel = 'LUNAS';
                    }
                } else {
                    if ($totalPaid > 0) {
                        $statusLabel = 'DIBAYAR SEBAGIAN';
                    } elseif ($totalRetur > 0) {
                        $statusLabel = 'DIRETUR SEBAGIAN';
                    } else {
                        $statusLabel = 'BELUM LUNAS';
                    }
                }

                if (!empty($this->filters['status'])) {
                    if ($this->filters['status'] === 'LUNAS' && !$isLunas)
                        continue;
                    if ($this->filters['status'] === 'BELUM_LUNAS' && $isLunas)
                        continue;
                    if ($this->filters['status'] === 'DIAJUKAN' && (empty($detail->is_submitted) || $isLunas))
                        continue;
                    if ($this->filters['status'] === 'RETUR' && $totalRetur <= 0 && !str_contains($statusLabel, 'RETUR'))
                        continue;
                }

                $tglFaktur = $this->safeFormatDate($rcv->date);
                $tempoDate = $this->safeFormatDate($detail->invoice_due);
                $dateField = $this->filters['date_field'] ?? 'due';
                $filterDate = match ($dateField) {
                    'invoice' => $detail->invoice_date,
                    'received' => $rcv->date,
                    default => $detail->invoice_due,
                };
                $filterDate = $this->safeParseDate($filterDate)?->format('Y-m-d') ?? '';
                if (!empty($this->filters['start_date']) && (!$filterDate || $filterDate < $this->filters['start_date'])) {
                    continue;
                }
                if (!empty($this->filters['end_date']) && (!$filterDate || $filterDate > $this->filters['end_date'])) {
                    continue;
                }

                $dueMode = $this->filters['due_mode'] ?? 'all';
                $dueDate = $this->safeParseDate($detail->invoice_due);
                if ($dueMode === 'overdue' && ($isLunas || !$dueDate || $dueDate->endOfDay()->isFuture())) {
                    continue;
                }
                if ($dueMode === 'due_soon') {
                    $daysRemaining = $dueDate ? (int) Carbon::now()->startOfDay()->diffInDays($dueDate->copy()->startOfDay(), false) : null;
                    if ($isLunas || $daysRemaining === null || $daysRemaining < 0 || $daysRemaining > 7) {
                        continue;
                    }
                }

                $totalSubtotalSemua += $subtotal;
                $totalPpnSemua += $ppn;
                $totalTagihanSemua += $finalTotal;
                $totalReturSemua += $totalRetur;
                $totalNetSemua += $netTotal;
                $totalTerbayarSemua += $totalPaid;
                $totalSisaSemua += $sisa;

                $rows[] = [
                    $no++,
                    $rcv->invoice_number ?: ($detail->invoice_number ?: '-'),
                    $detail->creditor->name ?? '-',
                    $detail->receiving_details_code ?: ($rcv->code ?: '-'),
                    $rcv->pharmacy->name ?? '-',
                    $tglFaktur,
                    $tempoDate,
                    $statusLabel,
                    $subtotal,
                    $ppn,
                    $finalTotal,
                    $totalRetur,
                    $netTotal,
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
                $totalReturSemua,
                $totalNetSemua,
                $totalTerbayarSemua,
                $totalSisaSemua,
            ];
            $this->lastRow = count($rows);
        } else {
            $rows[] = [
                'Tidak ada data hutang dagang yang sesuai dengan filter yang dipilih.',
                '', '', '', '', '', '', '', '', '', '', '', '', '', ''
            ];
            $this->lastRow = count($rows);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Title Styling
        $sheet->mergeCells('A1:O1');
        $sheet->mergeCells('A2:O2');
        $sheet->mergeCells('A3:O3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1E40AF']],  // Blue-800
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],  // Slate-500
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // 2. Table Headers (Row 5)
        $sheet->getStyle('A5:O5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],  // Dark Corporate Slate
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
                    $sheet
                        ->getStyle("A{$row}:O{$row}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F8FAFC');
                }

                $statusBayar = $sheet->getCell("H{$row}")->getValue();
                if ($statusBayar === 'LUNAS' || $statusBayar === 'LUNAS (ADA RETUR)') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('059669');
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'DIRETUR PENUH') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('7C3AED');
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'DIRETUR SEBAGIAN') {
                    $sheet->getStyle("H{$row}")->getFont()->getColor()->setRGB('E11D48');
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
            $sheet->getStyle("A{$start}:O{$end}")->applyFromArray([
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

            // Currency formatting on Numeric Columns I to O
            $sheet->getStyle("I{$start}:O{$end}")->applyFromArray([
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

            $sheet->getStyle("A{$totalRow}:O{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EFF6FF'],  // Blue-50 highlight
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1E40AF']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                ],
            ]);

            $sheet->getStyle("I{$totalRow}:O{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['bold' => true, 'color' => ['rgb' => '1E40AF']],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);
        } else {
            // Empty state row
            $emptyRow = 6;
            $sheet->mergeCells("A{$emptyRow}:O{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:O{$emptyRow}")->applyFromArray([
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
            'A' => 6,  // No
            'B' => 20,  // No Faktur
            'C' => 32,  // Vendor PBF
            'D' => 16,  // Kode NT
            'E' => 20,  // Apotek
            'F' => 15,  // Tgl Faktur
            'G' => 15,  // Jatuh Tempo
            'H' => 18,  // Status
            'I' => 18,  // Subtotal
            'J' => 16,  // PPN
            'K' => 20,  // Total Tagihan Bruto
            'L' => 18,  // Retur Pembelian
            'M' => 20,  // Tagihan Bersih
            'N' => 20,  // Terbayar
            'O' => 20,  // Sisa Hutang
        ];
    }

    protected function safeFormatDate($date): string
    {
        if (empty($date))
            return '-';
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

    protected function safeParseDate($date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }
        if ($date instanceof \DateTimeInterface) {
            return Carbon::instance($date);
        }

        $value = trim((string) $date);
        foreach (['!Y-m-d', '!d/m/Y', '!Y-m-d H:i:s', '!d/m/Y H:i:s'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (\Throwable $e) {
                // Continue trying supported legacy formats.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
