<?php

namespace App\Exports\Finance;

use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\Debtors;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PiutangExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
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
        return 'Piutang Penjualan';
    }

    public function array(): array
    {
        $branchName = $this->activePharmacy ? $this->activePharmacy->name : 'Semua Cabang';

        // 1. Header Title Block
        $filterStatus = $this->filters['status'] ?? 'SEMUA';
        $filterDebtor = 'Semua Debitur';
        if (!empty($this->filters['debtor_id'])) {
            $debtor = Debtors::find($this->filters['debtor_id']);
            if ($debtor) $filterDebtor = $debtor->name;
        }
        $dueMode = $this->filters['due_mode'] ?? 'all';
        $dueModeText = 'Semua Jatuh Tempo';
        if ($dueMode === 'overdue') $dueModeText = 'Hanya Lewat Jatuh Tempo';
        elseif ($dueMode === 'due_soon') $dueModeText = 'Hanya Mendekati Jatuh Tempo (≤ 7 Hari)';

        $rows = [
            ["APOTEK PROPHARMA - {$branchName}"],
            ["LAPORAN PIUTANG USAHA (PENJUALAN TEMPO / KREDIT)"],
            ["Dicetak: " . date('d/m/Y H:i') . " | Status Tagihan: {$filterStatus} | Debitur: {$filterDebtor} | Filter Tempo: {$dueModeText}"],
            [''], // Row 4 Blank
            // Row 5 Table Headers
            [
                'No',
                'No. Transaksi',
                'Debitur / Instansi',
                'Kode Debitur',
                'Dokter',
                'Pasien',
                'Apotek Cabang',
                'Tgl. Transaksi',
                'Jatuh Tempo',
                'Status Tempo',
                'Status Pembayaran',
                'Total Penjualan (Rp)',
                'Sudah Terbayar (Rp)',
                'Sisa Piutang (Rp)',
            ],
        ];

        // 2. Query Data
        $query = MedicineTransactions::with([
            'debtors',
            'doctors',
            'patients',
            'pharmacy',
            'payments',
            'transactions.medicine',
        ])
            ->where('transaction_type', 'KREDIT')
            ->where('status', 1)
            ->whereIn('pharmacy_id', $this->targetPharmacyIds);

        if (!empty($this->filters['search'])) {
            $s = trim($this->filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('transaction_code', 'like', "%{$s}%")
                    ->orWhereHas('debtors', fn($qd) => $qd->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('patients', fn($qp) => $qp->where('name', 'like', "%{$s}%"))
                    ->orWhereHas('doctors', fn($qdc) => $qdc->where('name', 'like', "%{$s}%"));
            });
        }

        if (!empty($this->filters['debtor_id'])) {
            $query->where('debtor_id', $this->filters['debtor_id']);
        }

        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy('created_at', $sortOrder);

        $no = 1;
        $totalSemua = 0;
        $totalTerbayarSemua = 0;
        $totalSisaSemua = 0;

        foreach ($query->get() as $tx) {
            $calcSubtotal = 0;
            foreach ($tx->transactions as $cart) {
                $qty = (float) ($cart->quantity ?? 0);
                $harga = (float) ($cart->item_price ?? 0);
                $calcSubtotal += (float) ($cart->final_price ?: ($cart->total_price ?: ($qty * $harga)));
            }

            $total = (float) ($tx->subtotal > 0 ? $tx->subtotal : $calcSubtotal);
            $totalTerbayarViaFinance = (float) $tx->payments->sum('amount');
            $legacyPaid = (float) ($tx->paid ?? 0);
            $totalTerbayar = round(max($totalTerbayarViaFinance, $legacyPaid), 2);
            if ($totalTerbayar > $total) $totalTerbayar = $total;
            $sisa = round(max(0, $total - $totalTerbayar), 2);
            $isLunas = ($sisa < 0.005 && $total > 0);
            $statusLabel = $isLunas ? 'LUNAS' : ($totalTerbayar > 0 ? 'DIBAYAR SEBAGIAN' : 'BELUM BAYAR');

            if (!empty($this->filters['status'])) {
                if ($this->filters['status'] === 'LUNAS' && !$isLunas) continue;
                if ($this->filters['status'] === 'BELUM_LUNAS' && $isLunas) continue;
            }

            $createdDate = $tx->created_at ? $tx->created_at->format('d/m/Y') : '-';
            $dueDate = $tx->created_at ? $tx->created_at->copy()->addDays(30) : null;
            $dueDateStr = $dueDate ? $dueDate->format('d/m/Y') : '-';

            $dueStatus = 'Aman';
            if (!$isLunas && $dueDate) {
                if ($dueDate->isPast()) {
                    $days = (int) $dueDate->diffInDays(now());
                    $dueStatus = "Lewat {$days} Hari";
                } else {
                    $days = (int) now()->diffInDays($dueDate);
                    $dueStatus = "Sisa {$days} Hari";
                }
            } elseif ($isLunas) {
                $dueStatus = 'Lunas';
            }

            if ($dueMode === 'overdue' && ($isLunas || !$dueDate || !$dueDate->isPast())) {
                continue;
            }
            if ($dueMode === 'due_soon' && ($isLunas || !$dueDate || $dueDate->isPast() || now()->diffInDays($dueDate) > 7)) {
                continue;
            }

            $totalSemua += $total;
            $totalTerbayarSemua += $totalTerbayar;
            $totalSisaSemua += $sisa;

            $rows[] = [
                $no++,
                $tx->transaction_code ?: ('TRX-' . $tx->id),
                $tx->debtors->name ?? ($tx->patient_id ? ('Pasien: ' . ($tx->patients->name ?? 'Umum')) : 'Pelanggan Umum'),
                $tx->debtors->code ?? '-',
                $tx->doctors->name ?? '-',
                $tx->patients->name ?? '-',
                $tx->pharmacy->name ?? '-',
                $createdDate,
                $dueDateStr,
                $dueStatus,
                $statusLabel,
                $total,
                $totalTerbayar,
                $sisa,
            ];
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
                '',
                '',
                '',
                $totalSemua,
                $totalTerbayarSemua,
                $totalSisaSemua,
            ];
            $this->lastRow = count($rows);
        } else {
            $rows[] = [
                'Tidak ada data piutang yang sesuai dengan filter yang dipilih.',
                '', '', '', '', '', '', '', '', '', '', '', '', ''
            ];
            $this->lastRow = count($rows);
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Title Styling
        $sheet->mergeCells('A1:N1');
        $sheet->mergeCells('A2:N2');
        $sheet->mergeCells('A3:N3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'B45309']], // Amber-700
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']], // Slate-500
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // 2. Table Headers (Row 5)
        $sheet->getStyle('A5:N5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate 800 Dark Aesthetic
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
                    $sheet->getStyle("A{$row}:N{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F8FAFC');
                }

                // Status text coloring for quick visual glance
                $statusTempo = $sheet->getCell("J{$row}")->getValue();
                if (str_starts_with((string)$statusTempo, 'Lewat')) {
                    $sheet->getStyle("J{$row}")->getFont()->getColor()->setRGB('DC2626'); // Red-600
                    $sheet->getStyle("J{$row}")->getFont()->setBold(true);
                } elseif (str_starts_with((string)$statusTempo, 'Sisa')) {
                    $sheet->getStyle("J{$row}")->getFont()->getColor()->setRGB('D97706'); // Amber-600
                    $sheet->getStyle("J{$row}")->getFont()->setBold(true);
                } elseif ($statusTempo === 'Lunas') {
                    $sheet->getStyle("J{$row}")->getFont()->getColor()->setRGB('059669'); // Emerald-600
                }

                $statusBayar = $sheet->getCell("K{$row}")->getValue();
                if ($statusBayar === 'LUNAS') {
                    $sheet->getStyle("K{$row}")->getFont()->getColor()->setRGB('059669');
                    $sheet->getStyle("K{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'DIBAYAR SEBAGIAN') {
                    $sheet->getStyle("K{$row}")->getFont()->getColor()->setRGB('D97706');
                    $sheet->getStyle("K{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'BELUM BAYAR') {
                    $sheet->getStyle("K{$row}")->getFont()->getColor()->setRGB('DC2626');
                    $sheet->getStyle("K{$row}")->getFont()->setBold(true);
                }
            }

            // General Data Grid Borders & Alignments
            $sheet->getStyle("A{$start}:N{$end}")->applyFromArray([
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
            $sheet->getStyle("E{$start}:G{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("H{$start}:K{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Currency formatting on Numeric Columns L, M, N
            $sheet->getStyle("L{$start}:N{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            // 4. Total / Summary Row
            $totalRow = $end + 1;
            $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
            $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '78350F']],
            ]);

            $sheet->getStyle("A{$totalRow}:N{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEF3C7'], // Amber-100 highlight
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D97706']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '92400E']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FCD34D']],
                ],
            ]);

            $sheet->getStyle("L{$totalRow}:N{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);
        } else {
            // Empty state row
            $emptyRow = 6;
            $sheet->mergeCells("A{$emptyRow}:N{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:N{$emptyRow}")->applyFromArray([
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
            'B' => 18,  // No. Transaksi
            'C' => 30,  // Debitur / Instansi
            'D' => 14,  // Kode Debitur
            'E' => 22,  // Dokter
            'F' => 22,  // Pasien
            'G' => 20,  // Apotek
            'H' => 15,  // Tgl Transaksi
            'I' => 15,  // Jatuh Tempo
            'J' => 18,  // Status Tempo
            'K' => 18,  // Status Bayar
            'L' => 20,  // Total Penjualan
            'M' => 20,  // Terbayar
            'N' => 20,  // Sisa Piutang
        ];
    }
}
