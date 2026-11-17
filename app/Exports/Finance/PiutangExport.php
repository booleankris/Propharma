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
                'Retur Penjualan (Rp)',
                'Sudah Terbayar (Rp)',
                'Sisa Piutang (Rp)',
            ],
        ];

        // 2. Query Data via PayablesReceivablesService (LiphTransactionScope + chunked aggregates)
        $piutangRows = app(\App\Services\Finance\PayablesReceivablesService::class)->getPiutangDataset(
            $this->targetPharmacyIds,
            $this->filters,
            false,
            null,
            1
        )['rows'];

        $cartItemsByTx = [];
        $txIds = array_values(array_unique(array_map(fn($r) => (int) $r['id'], $piutangRows)));
        foreach (array_chunk($txIds, 500) as $chunkIds) {
            $carts = \Illuminate\Support\Facades\DB::table('medicine_cart as mc')
                ->leftJoin('medicines as m', 'm.id', '=', 'mc.medicine_id')
                ->whereIn('mc.transaction_id', $chunkIds)
                ->select([
                    'mc.transaction_id',
                    'mc.quantity',
                    'mc.item_price',
                    'mc.discount',
                    'mc.total_price',
                    'mc.final_price',
                    'm.name as medicine_name',
                ])
                ->orderBy('mc.id')
                ->get();

            foreach ($carts as $cart) {
                $cartItemsByTx[(int) $cart->transaction_id][] = $cart;
            }
        }

        $no = 1;
        $totalSemua = 0;
        $totalReturSemua = 0;
        $totalTerbayarSemua = 0;
        $totalSisaSemua = 0;
        $detailRows = [];

        foreach ($piutangRows as $item) {
            $txId = (int) $item['id'];
            $total = (float) ($item['total'] ?? 0);
            $totalRetur = (float) ($item['total_retur'] ?? 0);
            $totalTerbayar = (float) ($item['terbayar'] ?? 0);
            $sisa = (float) ($item['sisa'] ?? 0);
            $isLunas = in_array($item['status'] ?? '', ['Lunas', 'Lunas (Ada Retur)', 'Diretur Penuh'], true) || $sisa <= 0.005;
            $statusLabel = strtoupper((string) ($item['status'] ?? 'BELUM BAYAR'));

            $createdDate = $item['tanggal'] ?? '-';
            $dueDateStr = $item['jatuhTempo'] ?? '-';

            if ($isLunas) {
                $dueStatus = 'Lunas';
            } elseif (!empty($item['is_overdue']) || ($item['days_overdue'] ?? 0) > 0) {
                $dueStatus = 'Lewat ' . (int) ($item['days_overdue'] ?? 0) . ' Hari';
            } elseif (!empty($item['raw_jatuh_tempo'])) {
                $dueStatus = 'Sisa ' . (int) ($item['days_remaining'] ?? 0) . ' Hari';
            } else {
                $dueStatus = 'Aman';
            }

            $totalSemua += $total;
            $totalReturSemua += $totalRetur;
            $totalTerbayarSemua += $totalTerbayar;
            $totalSisaSemua += $sisa;

            $transactionNumber = $item['nomor'] ?? ('TRX-' . $txId);
            $debtorName = $item['debtor'] ?? 'Pelanggan Umum';
            $medicineNames = [];
            $quantities = [];
            $unitPrices = [];
            foreach ($cartItemsByTx[$txId] ?? [] as $cart) {
                $medName = $cart->medicine_name ?? 'Obat';
                $qty = (float) ($cart->quantity ?? 0);
                $harga = (float) ($cart->item_price ?? 0);
                $medicineNames[] = $medName;
                $quantities[] = (string) $qty;
                $unitPrices[] = (string) $harga;
                $detailRows[] = [
                    $transactionNumber,
                    $debtorName,
                    $medName,
                    $qty,
                    $harga,
                    (float) ($cart->discount ?? 0),
                    (float) ($cart->final_price ?? $cart->total_price ?? ($qty * $harga)),
                ];
            }

            $rows[] = [
                $no++,
                $transactionNumber,
                $debtorName,
                $item['debtor_code'] ?? '-',
                $item['dokter'] ?? '-',
                $item['pasien'] ?? '-',
                implode("\n", $medicineNames) ?: '-',
                implode("\n", $quantities) ?: '0',
                implode("\n", $unitPrices) ?: '0',
                $item['apotek'] ?? '-',
                $createdDate,
                $dueDateStr,
                $dueStatus,
                $statusLabel,
                $total,
                $totalRetur,
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
                $totalReturSemua,
                $totalTerbayarSemua,
                $totalSisaSemua,
            ];
            $this->lastRow = count($rows);
        } else {
            $rows[] = [
                'Tidak ada data piutang yang sesuai dengan filter yang dipilih.',
                ...array_fill(0, 17, ''),
            ];
            $this->lastRow = count($rows);
        }

        $rows[] = [''];
        $this->detailTitleRow = count($rows) + 1;
        $rows[] = ['RINCIAN OBAT PER TRANSAKSI'];
        $this->detailHeaderRow = count($rows) + 1;
        $rows[] = ['No. Transaksi', 'Debitur / Instansi', 'Nama Obat', 'Qty', 'Harga Satuan (Rp)', 'Diskon (Rp)', 'Total Setelah Diskon (Rp)'];
        $this->detailDataStartRow = count($rows) + 1;
        foreach ($detailRows as $detailRow)
            $rows[] = $detailRow;
        $this->detailDataEndRow = count($rows);

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // 1. Title Styling
        $sheet->mergeCells('A1:R1');
        $sheet->mergeCells('A2:R2');
        $sheet->mergeCells('A3:R3');

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
        $sheet->getStyle('A5:R5')->applyFromArray([
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
                        ->getStyle("A{$row}:R{$row}")
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
                if ($statusBayar === 'LUNAS' || $statusBayar === 'LUNAS (ADA RETUR)') {
                    $sheet->getStyle("N{$row}")->getFont()->getColor()->setRGB('059669');
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'DIRETUR PENUH') {
                    $sheet->getStyle("N{$row}")->getFont()->getColor()->setRGB('7C3AED');
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'DIBAYAR SEBAGIAN' || $statusBayar === 'DIRETUR SEBAGIAN') {
                    $sheet->getStyle("N{$row}")->getFont()->getColor()->setRGB('D97706');
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                } elseif ($statusBayar === 'BELUM BAYAR') {
                    $sheet->getStyle("N{$row}")->getFont()->getColor()->setRGB('DC2626');
                    $sheet->getStyle("N{$row}")->getFont()->setBold(true);
                }
            }

            // General Data Grid Borders & Alignments
            $sheet->getStyle("A{$start}:R{$end}")->applyFromArray([
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

            // Currency formatting on Total, Retur, Paid, and Remaining columns.
            $sheet->getStyle("O{$start}:R{$end}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                'numberFormat' => ['formatCode' => '#,##0.00'],
            ]);

            // 4. Total / Summary Row
            $totalRow = $end + 1;
            $sheet->mergeCells("A{$totalRow}:N{$totalRow}");
            $sheet->getStyle("A{$totalRow}:R{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '78350F']],
            ]);

            $sheet->getStyle("A{$totalRow}:R{$totalRow}")->applyFromArray([
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

            $sheet->getStyle("O{$totalRow}:R{$totalRow}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                'numberFormat' => ['formatCode' => '#,##0.00'],
                'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(24);
        } else {
            // Empty state row
            $emptyRow = 6;
            $sheet->mergeCells("A{$emptyRow}:R{$emptyRow}");
            $sheet->getStyle("A{$emptyRow}:R{$emptyRow}")->applyFromArray([
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
                if ($row % 2 === 0)
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
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
            'P' => 18,  // Retur Penjualan
            'Q' => 20,  // Terbayar
            'R' => 20,  // Sisa Piutang
        ];
    }
}
