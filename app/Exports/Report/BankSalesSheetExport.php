<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BankSalesSheetExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $pharmacyAddress;
    protected $shift;
    protected $shiftType;
    protected $bankName;
    protected $isRecap;
    protected $allBanksData;
    protected $transactions;

    public function __construct(
        $pharmacyId,
        $startDate,
        $endDate,
        $pharmacyName = '',
        $pharmacyAddress = '',
        $shift = null,
        $shiftType = 'semua',
        $bankName = null,
        $isRecap = false,
        $allBanksData = [],
        $transactions = []
    ) {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = Carbon::parse($startDate)->startOfDay();
        $this->endDate         = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName    = $pharmacyName ?: 'APOTEK SAHABAT';
        $this->pharmacyAddress = $pharmacyAddress ?: '';
        $this->shift           = $shift;
        $this->shiftType       = $shiftType;
        $this->bankName        = $bankName;
        $this->isRecap         = $isRecap;
        $this->allBanksData    = $allBanksData;
        $this->transactions    = $transactions;
    }

    public function title(): string
    {
        if ($this->isRecap) {
            return 'Rekap Semua Bank';
        }

        $title = $this->bankName ?: 'Lainnya';
        // Clean Excel sheet title (max 31 chars, no invalid chars)
        $cleanTitle = preg_replace('/[\\\\\\/?*\\[\\]:]/', '', $title);
        return mb_substr($cleanTitle, 0, 31);
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);
        $pharmacyName = $pharmacy->name ?? $this->pharmacyName;
        $pharmacyAddress = $pharmacy->address ?? $this->pharmacyAddress;

        $header = [
            [$pharmacyName],
            [$pharmacyAddress],
            [''],
            [$this->isRecap ? 'REKAPITULASI PENJUALAN PER METODE / BANK' : 'LAPORAN PENJUALAN : ' . strtoupper($this->bankName ?: 'METODE BAYAR')],
            ['Periode : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y') . ($this->shiftType === 'shift' && $this->shift ? ' (Shift ' . $this->shift . ')' : '')],
            [''],
        ];

        $body = $this->isRecap ? $this->buildRecapRows() : $this->buildDetailRows();

        return array_merge($header, $body);
    }

    /**
     * Build rows for the Recap Summary Sheet
     */
    private function buildRecapRows(): array
    {
        $tableHeaders = [
            'No',
            'Nama Bank / Metode',
            'Jumlah Struk',
            'Total Qty Obat',
            'Subtotal Kotor',
            'Diskon Transaksi',
            'Total Netto',
        ];

        $rows = [];
        $rows[] = $tableHeaders;

        $no = 1;
        $grandTotalStruk = 0;
        $grandTotalQty = 0;
        $grandTotalGross = 0;
        $grandTotalDiscount = 0;
        $grandTotalNet = 0;

        foreach ($this->allBanksData as $bank => $data) {
            $strukCount = $data['struk_count'] ?? 0;
            $qtySum     = $data['qty_sum'] ?? 0;
            $grossSum   = $data['gross_sum'] ?? 0;
            $discSum    = $data['discount_sum'] ?? 0;
            $netSum     = $data['net_sum'] ?? 0;

            $grandTotalStruk    += $strukCount;
            $grandTotalQty      += $qtySum;
            $grandTotalGross    += $grossSum;
            $grandTotalDiscount += $discSum;
            $grandTotalNet      += $netSum;

            $rows[] = [
                $no++,
                $bank,
                $strukCount,
                $qtySum,
                $grossSum,
                $discSum,
                $netSum,
            ];
        }

        if (count($this->allBanksData) === 0) {
            $rows[] = ['-', 'Tidak ada transaksi bank pada periode ini', 0, 0, 0, 0, 0];
        }

        // Grand Total Row
        $rows[] = [
            '',
            'TOTAL KESELURUHAN',
            $grandTotalStruk,
            $grandTotalQty,
            $grandTotalGross,
            $grandTotalDiscount,
            $grandTotalNet,
        ];

        return $rows;
    }

    /**
     * Build item-level detail rows for a specific Bank
     */
    private function buildDetailRows(): array
    {
        $tableHeaders = [
            'No',
            'Tanggal & Waktu',
            'No. Struk',
            'Jenis Transaksi',
            'Kode Obat',
            'Nama Obat',
            'Qty',
            'Harga Satuan',
            'Diskon Item',
            'Total Item',
            'Pasien / Pelanggan',
            'Dokter',
            'Kasir',
            'Shift',
        ];

        if (!empty($this->transactions)) {
            $transactions = is_array($this->transactions) ? collect($this->transactions) : $this->transactions;
        } else {
            $query = MedicineTransactions::with([
                'transactions.medicine',
                'patients',
                'doctors',
                'user',
                'shift_logs',
            ])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereBetween('created_at', [$this->startDate, $this->endDate]);

            if ($this->shiftType === 'shift' && !empty($this->shift)) {
                $query->whereHas('shift_logs', function ($q) {
                    $q->where('shift_id', $this->shift);
                });
            }

            if (empty($this->bankName) || $this->bankName === 'Lainnya' || $this->bankName === 'Transfer Lainnya') {
                $query->where(function ($q) {
                    $q->whereNull('transfer_bank_name')
                        ->orWhere('transfer_bank_name', '')
                        ->orWhere('transfer_bank_name', 'Lainnya')
                        ->orWhere('transfer_bank_name', 'Transfer Lainnya');
                });
            } else {
                $query->where('transfer_bank_name', $this->bankName);
            }

            $transactions = $query->orderBy('created_at', 'asc')->get();
        }

        $rows = [];
        $rows[] = $tableHeaders;

        $no = 1;
        $totalQty = 0;
        $totalDiscount = 0;
        $totalAmount = 0;

        foreach ($transactions as $trx) {
            $tgl = $trx->created_at ? $trx->created_at->format('d/m/Y H:i') : '-';
            $code = $trx->transaction_code ?? '-';
            $type = $trx->transaction_type ?? '-';
            $pasien = $trx->patients->name ?? '-';
            $dokter = $trx->doctors->name ?? '-';
            $kasir = $trx->user->name ?? '-';
            $shift = $trx->shift_logs->shift_name ?? ($trx->shift_logs_id ? 'Shift ' . $trx->shift_logs_id : '-');

            $items = $trx->transactions;

            if ($items->isEmpty()) {
                // If no cart items found, still show transaction total
                $subtotal = (float) ($trx->subtotal ?? 0);
                $discount = (float) ($trx->discount ?? 0);
                $netto    = $subtotal - $discount;

                $totalDiscount += $discount;
                $totalAmount   += $netto;

                $rows[] = [
                    $no++,
                    $tgl,
                    $code,
                    $type,
                    '-',
                    'Transaksi Tanpa Detail Item',
                    1,
                    $subtotal,
                    $discount,
                    $netto,
                    $pasien,
                    $dokter,
                    $kasir,
                    $shift,
                ];
                continue;
            }

            foreach ($items as $item) {
                $medicine = $item->medicine;
                $medCode  = $medicine->code ?? '-';
                $medName  = $medicine->name ?? 'Obat Tidak Ditemukan';
                $qty      = (int) ($item->quantity ?? 0);
                $price    = (float) ($item->item_price ?? 0);
                $discount = (float) ($item->discount ?? 0);
                $final    = (float) ($item->final_price ?? ($item->total_price ?? ($qty * $price - $discount)));

                $totalQty      += $qty;
                $totalDiscount += $discount;
                $totalAmount   += $final;

                $rows[] = [
                    $no++,
                    $tgl,
                    $code,
                    $type,
                    $medCode,
                    $medName,
                    $qty,
                    $price,
                    $discount,
                    $final,
                    $pasien,
                    $dokter,
                    $kasir,
                    $shift,
                ];
            }
        }

        if ($transactions->isEmpty()) {
            $rows[] = ['-', '-', '-', '-', '-', 'Tidak ada transaksi untuk bank ini', 0, 0, 0, 0, '-', '-', '-', '-'];
        }

        // Summary Total Row
        $rows[] = [
            '',
            'TOTAL ' . strtoupper($this->bankName ?: 'BANK'),
            '',
            '',
            '',
            '',
            $totalQty,
            '',
            $totalDiscount,
            $totalAmount,
            '',
            '',
            '',
            '',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        // 1. Pharmacy Title & Header Styling
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(10);
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A5')->getFont()->setItalic(true)->setSize(10);

        // 2. Table Header Row (Row 7)
        $headerRow = 7;
        $sheet->getStyle("A{$headerRow}:{$highestColumn}{$headerRow}")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        // 3. Table Data Borders & Alignment
        if ($highestRow > $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$highestColumn}{$highestRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'E2E8F0'],
                    ],
                ],
            ]);

            // Number Formats
            if ($this->isRecap) {
                // Column C..G numeric
                $sheet->getStyle("C8:C{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("D8:D{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E8:G{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("A8:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            } else {
                // Detail numeric format
                $sheet->getStyle("G8:G{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("H8:J{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("A8:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B8:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C8:C{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("E8:E{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Total Bottom Row Highlight
            $sheet->getStyle("A{$highestRow}:{$highestColumn}{$highestRow}")->applyFromArray([
                'font' => [
                    'bold' => true,
                ],
                'fill' => [
                    'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'],
                ],
                'borders' => [
                    'top' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color'       => ['rgb' => '94A3B8'],
                    ],
                    'bottom' => [
                        'borderStyle' => Border::BORDER_DOUBLE,
                        'color'       => ['rgb' => '64748B'],
                    ],
                ],
            ]);
        }

        return [];
    }

    public function columnWidths(): array
    {
        if ($this->isRecap) {
            return [
                'A' => 6,
                'B' => 28,
                'C' => 16,
                'D' => 16,
                'E' => 20,
                'F' => 20,
                'G' => 22,
            ];
        }

        return [
            'A' => 6,
            'B' => 18,
            'C' => 18,
            'D' => 15,
            'E' => 16,
            'F' => 32,
            'G' => 10,
            'H' => 16,
            'I' => 14,
            'J' => 18,
            'K' => 22,
            'L' => 20,
            'M' => 18,
            'N' => 12,
        ];
    }
}
