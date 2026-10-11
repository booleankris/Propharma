<?php

namespace App\Exports\Finance;

use App\Models\Debtors;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
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
    protected int $detailTitleRow = 0;
    protected int $detailHeaderRow = 0;
    protected int $detailDataStartRow = 0;
    protected int $detailDataEndRow = 0;
    protected array $dataRowHeights = [];

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
            if ($debtor)
                $filterDebtor = $debtor->name;
        }
        $dueMode = $this->filters['due_mode'] ?? 'all';
        $dueModeText = 'Semua Jatuh Tempo';
        if ($dueMode === 'overdue')
            $dueModeText = 'Hanya Lewat Jatuh Tempo';
        elseif ($dueMode === 'due_soon')
            $dueModeText = 'Hanya Mendekati Jatuh Tempo (≤ 7 Hari)';

        $rows = [
            ["APOTEK PROPHARMA - {$branchName}"],
            ['LAPORAN PIUTANG USAHA (PENJUALAN TEMPO / KREDIT)'],
            ['Dicetak: ' . date('d/m/Y H:i') . " | Status Tagihan: {$filterStatus} | Debitur: {$filterDebtor} | Filter Tempo: {$dueModeText}"],
            [''],  // Row 4 Blank
            // Row 5 Table Headers
            [
                'No',
                'No. Transaksi',
                'Debitur / Instansi',
                'Kode Debitur',
                'Dokter',
                'Pasien',
                'Barang Dibeli',
                'Qty',
                'Harga Satuan (Rp)',
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
                $q
                    ->where('transaction_code', 'like', "%{$s}%")
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
        $detailRows = [];

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
            if ($totalTerbayar > $total)
                $totalTerbayar = $total;
            $sisa = round(max(0, $total - $totalTerbayar), 2);
            $isLunas = ($sisa < 0.005 && $total > 0);
            $statusLabel = $isLunas ? 'LUNAS' : ($totalTerbayar > 0 ? 'DIBAYAR SEBAGIAN' : 'BELUM BAYAR');

            if (!empty($this->filters['status'])) {
                if ($this->filters['status'] === 'LUNAS' && !$isLunas)
                    continue;
                if ($this->filters['status'] === 'BELUM_LUNAS' && $isLunas)
                    continue;
                if ($this->filters['status'] === 'DIAJUKAN' && (empty($tx->is_submitted) || $isLunas))
                    continue;
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

            $transactionNumber = $tx->transaction_code ?: ('TRX-' . $tx->id);
            $debtorName = $tx->debtors->name ?? ($tx->patient_id ? ('Pasien: ' . ($tx->patients->name ?? 'Umum')) : 'Pelanggan Umum');
            $medicineNames = [];
            $quantities = [];
            $unitPrices = [];
            foreach ($tx->transactions as $cart) {
                $medicineNames[] = $cart->medicine->name ?? 'Obat';
                $quantities[] = (string) (float) ($cart->quantity ?? 0);
                $unitPrices[] = (string) (float) ($cart->item_price ?? 0);
                $detailRows[] = [
                    $transactionNumber,
                    $debtorName,
                    $cart->medicine->name ?? 'Obat',
                    (float) ($cart->quantity ?? 0),
                    (float) ($cart->item_price ?? 0),
                    (float) ($cart->discount ?? 0),
                    (float) ($cart->final_price ?? $cart->total_price ?? 0),
                ];
            }

            $rows[] = [
                $no++,
                $transactionNumber,
                $debtorName,
                $tx->debtors->code ?? '-',
                $tx->doctors->name ?? '-',
                $tx->patients->name ?? '-',
                implode("\n", $medicineNames) ?: '-',
                implode("\n", $quantities) ?: '0',
                implode("\n", $unitPrices) ?: '0',
                $tx->pharmacy->name ?? '-',
                $createdDate,
                $dueDateStr,
                $dueStatus,
                $statusLabel,
                $total,
                $totalTerbayar,
                $sisa,
            ];
            $this->dataRowHeights[] = max(24, count($medicineNames) * 18);
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
                ...array_fill(0, 16, ''),
            ];
            $this->lastRow = count($rows);
        }

        $rows[] = [''];
        $this->detailTitleRow = count($rows) + 1;
        $rows[] = ['RINCIAN OBAT PER TRANSAKSI'];
        $this->detailHeaderRow = count($rows) + 1;
        $rows[] = ['No. Transaksi', 'Debitur / Instansi', 'Nama Obat', 'Qty', 'Harga Satuan (Rp)', 'Diskon (Rp)', 'Total Setelah Diskon (Rp)'];
        $this->detailDataStartRow = count($rows) + 1;
        foreach ($detailRows as $detailRow) $rows[] = $detailRow;
        $this->detailDataEndRow = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Title Styling
        $sheet->mergeCells('A1:Q1');
        $sheet->mergeCells('A2:Q2');
        $sheet->mergeCells('A3:Q3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'B45309']],  // Amber-700
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],  // Slate-500
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // 2. Table Headers (Row 5)
        $sheet->getStyle('A5:Q5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],  // Slate 800 Dark Aesthetic
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
                $sheet->getRowDimension($row)->setRowHeight($this->dataRowHeights[$row - $start] ?? 24);

                // Zebra striping for readability
                if ($row % 2 == 1) {
                    $sheet
                        ->getStyle("A{$row}:Q{$row}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F8FAFC');
                }

                // Status text coloring for quick visual glance
                $statusTempo = $sheet->getCell("M{$row}")->getValue();
                if (str_starts_with((string) $statusTempo, 'Lewat')) {
                    $sheet->getStyle("M{$row}")->getFont()->getColor()->setRGB('DC2626');  // Red-600
                    $sheet->getStyle("M{$row}")->getFont()->setBold(true);
                } elseif (str_starts_with((string) $statusTempo, 'Sisa')) {
                    $sheet->getStyle("M{$row}")->getFont()->getColor()->setRGB('D97706');  // Amber-600
                    $sheet->getStyle("M{$row}")->getFont()->setBold(true);
                } elseif ($statusTempo === 'Lunas') {
                    $sheet->getStyle("M{$row}")->getFont()->getColor()->setRGB('059669');  // Emerald-600
                }

                $statusBayar = $sheet->getCell("N{$row}")->getValue();
                if ($statusBayar === 'LUNAS') {
                    $sheet->getStyle("N{$row}")->getFont()->getColor()->setRGB('059669');
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'DIBAYAR SEBAGIAN') {
                    $sheet->getStyle("N{$row}")->getFont()->getColor()->setRGB('D97706');
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'BELUM BAYAR') {
                    $sheet->getStyle("N{$row}")->getFont()->getColor()->setRGB('DC2626');
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                }
            }

            // General Data Grid Borders & Alignments
            $sheet->getStyle("A{$start}:Q{$end}")->applyFromArray([
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
            $sheet->getStyle("H{$start}:I{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G{$start}:I{$end}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("J{$start}:N{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Currency formatting on Total, Paid, and Remaining columns.
            $sheet->getStyle("O{$start}:Q{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0'],
            ]);

            // 4. Total / Summary Row
            $totalRow = $end + 1;
            $sheet->mergeCells("A{$totalRow}:N{$totalRow}");
            $sheet->getStyle("A{$totalRow}:Q{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '78350F']],
            ]);

            $sheet->getStyle("A{$totalRow}:Q{$totalRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'FEF3C7'],  // Amber-100 highlight
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D97706']],
                    'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '92400E']],
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FCD34D']],
                ],
            ]);

            $sheet->getStyle("O{$totalRow}:Q{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0'],
                'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);
        } else {
            // Empty state row
            $emptyRow = 6;
            $sheet->mergeCells("A{$emptyRow}:Q{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:Q{$emptyRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['italic' => true, 'color' => ['rgb' => '94A3B8']],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
            ]);
            $sheet->getRowDimension($emptyRow)->setRowHeight(30);
        }

        $titleRow = $this->detailTitleRow;
        $headerRow = $this->detailHeaderRow;
        $sheet->mergeCells("A{$titleRow}:G{$titleRow}");
        $sheet->getStyle("A{$titleRow}:G{$titleRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'B45309']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBEB']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($titleRow)->setRowHeight(22);
        $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F172A']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);
        if ($this->detailDataEndRow >= $this->detailDataStartRow) {
            $start = $this->detailDataStartRow;
            $end = $this->detailDataEndRow;
            $sheet->getStyle("A{$start}:G{$end}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("D{$start}:D{$end}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$start}:G{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0.00'],
            ]);
            for ($row = $start; $row <= $end; $row++) {
                if ($row % 2 === 0) $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  // No
            'B' => 18,  // No. Transaksi
            'C' => 30,  // Debitur / Instansi
            'D' => 14,  // Kode Debitur
            'E' => 22,  // Dokter
            'F' => 22,  // Pasien
            'G' => 34,  // Barang Dibeli
            'H' => 10,  // Qty
            'I' => 18,  // Harga Satuan
            'J' => 20,  // Apotek
            'K' => 15,  // Tgl Transaksi
            'L' => 15,  // Jatuh Tempo
            'M' => 18,  // Status Tempo
            'N' => 18,  // Status Bayar
            'O' => 20,  // Total Penjualan
            'P' => 20,  // Terbayar
            'Q' => 20,  // Sisa Piutang
        ];
    }
}
