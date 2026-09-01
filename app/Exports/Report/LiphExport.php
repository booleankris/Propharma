<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class LiphExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $pharmacyAddress;
    protected $shift;
    protected $shiftType;
    protected $onlineRole;
    protected $customTitle;

    const TYPE_MAP = [
        'KREDIT'      => ['kredit', 'Resep Kredit'],
        'HV/OTC'      => ['tunai',  'Obat Bebas'],
        'RETUR JUAL'  => ['tunai',  'Retur Tunai'],
        'RESEP TUNAI' => ['tunai',  'Resep Tunai'],
        'UPDS'        => ['tunai',  'UPDS'],
    ];

    const TUNAI_ORDER = ['Obat Bebas', 'Retur Tunai', 'Resep Tunai', 'UPDS'];

    // medicine_cart.cart_type stores abbreviations, not the full
    // transaction_type strings used as TYPE_MAP keys.
    const CART_TYPE_ABBR = [
        'UK' => 'KREDIT',
        'UM' => 'RESEP TUNAI',
        'HV' => 'HV/OTC',
        'UP' => 'UPDS',
    ];

    private function nz($value)
    {
        return is_null($value) || $value === '' ? 0 : $value;
    }

    public function __construct($pharmacyId, $startDate, $endDate, $pharmacyName = 'APOTEK SAHABAT', $pharmacyAddress = '', $shift = null, $shiftType = 'semua', $onlineRole = null, $customTitle = null)
    {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = Carbon::parse($startDate)->startOfDay();
        $this->endDate         = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName    = $pharmacyName;
        $this->pharmacyAddress = $pharmacyAddress;
        $this->shift           = $shift;
        $this->shiftType       = $shiftType;
        $this->onlineRole      = $onlineRole;
        $this->customTitle     = $customTitle;
    }

    public function array(): array
    {
        return $this->buildRows($this->buildReportData());
    }

    private function safeSum($collection, $field)
    {
        return $collection->sum(fn($item) => (int) ($item->{$field} ?? 0));
    }

    private function emptyBucket(): array
    {
        return [
            'lembar'             => 0,
            'r'                  => 0,
            'jasa'               => 0,
            'embalase'           => 0,
            'potongan'           => 0, // item-level discount only
            'potongan_transaksi' => 0, // transaction-level discount
            'netto'              => 0,
        ];
    }

    private function buildReportData(): array
    {
        if ($this->shiftType == "shift") {
            $transactions = MedicineTransactions::with(['transactions', 'shift_logs'])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereDate('updated_at', '>=', $this->startDate->toDateString())
                ->whereDate('updated_at', '<=', $this->endDate->toDateString())
                ->whereIn('transaction_type', array_keys(self::TYPE_MAP))
                ->whereHas('shift_logs', function ($shift) {
                    $shift->where('shift_id', $this->shift);
                })
                ->get();

            $grouped = $this->groupTransactions($transactions);
        } else if ($this->shiftType == 'semua') {
            $transactions = MedicineTransactions::with('transactions')
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereDate('updated_at', '>=', $this->startDate->toDateString())
                ->whereDate('updated_at', '<=', $this->endDate->toDateString())
                ->whereIn('transaction_type', array_keys(self::TYPE_MAP))
                ->get();

            $grouped = $this->groupTransactions($transactions);
        } else if ($this->shiftType == 'online') {
            $roleName = $this->onlineRole ?? ['Online', 'Online Grab', 'Online Shopee', 'Digital'];
            $transactions = MedicineTransactions::with(['transactions.user', 'user', 'shift_logs'])
                ->where('pharmacy_id', $this->pharmacyId)
                ->where('status', 1)
                ->whereDate('updated_at', '>=', $this->startDate->toDateString())
                ->whereDate('updated_at', '<=', $this->endDate->toDateString())
                ->whereIn('transaction_type', array_keys(self::TYPE_MAP))
                ->where(function ($query) use ($roleName) {
                    $applyRoleFilter = function ($q) use ($roleName) {
                        if (is_array($roleName)) {
                            $q->whereHas('roles', function ($rq) use ($roleName) {
                                $rq->whereIn('name', $roleName);
                            });
                        } elseif ($roleName === 'semua' || $roleName === 'all' || $roleName === 'Semua Online') {
                            $q->whereHas('roles', function ($rq) {
                                $rq->whereIn('name', ['Online', 'Online Grab', 'Online Shopee', 'Digital']);
                            });
                        } else {
                            $q->role($roleName);
                        }
                    };

                    $query->whereHas('user', $applyRoleFilter)
                        ->orWhereHas('transactions.user', $applyRoleFilter);
                })
                ->when(!empty($this->shift), function ($q) {
                    $q->whereHas('shift_logs', function ($shiftQuery) {
                        $shiftQuery->where('shift_id', $this->shift);
                    });
                })
                ->get();

            $grouped = $this->groupTransactions($transactions);
        }

        return $grouped;
    }

    /**
     * Groups a transaction's cart items by their EFFECTIVE type
     * (cart_type if the user switched it, otherwise the transaction's
     * own transaction_type), and allocates Lembar/R/ proportionally
     * across whichever effective types are present.
     *
     * Potongan (item discount) is summed strictly per effective type.
     * Potongan Transaksi (transaction-level discount) follows the
     * transaction's ORIGINAL type if any item still carries that type;
     * if the original type has zero items left (fully switched), it's
     * placed on the single absorbing type instead.
     */
    private function groupTransactions($transactions): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            $originalMap = self::TYPE_MAP[$trx->transaction_type] ?? null;
            if (!$originalMap) continue;

            [, $originalLabel] = $originalMap;

            $items = $trx->transactions;
            $totalItems = $items->count();

            if ($totalItems === 0) {
                // No cart items at all — count the Lembar under the
                // original type only, nothing to split.
                [$group, $label] = $originalMap;

                if (!isset($grouped[$group][$label])) {
                    $grouped[$group][$label] = $this->emptyBucket();
                }

                $grouped[$group][$label]['lembar'] += 1;
                $grouped[$group][$label]['potongan_transaksi'] += (int) ($trx->discount ?? 0);

                $trxNetto = (int) ($trx->subtotal ?? 0);
                if ($label === 'Retur Tunai') $trxNetto = -abs($trxNetto);
                $grouped[$group][$label]['netto'] += $trxNetto;

                continue;
            }

            // Bucket cart items by effective type label
            $byLabel = [];
            foreach ($items as $item) {
                if ($item->cart_type) {
                    $effectiveType = self::CART_TYPE_ABBR[$item->cart_type] ?? $item->cart_type;
                } else {
                    $effectiveType = $trx->transaction_type;
                }

                $map = self::TYPE_MAP[$effectiveType] ?? $originalMap;
                [$grp, $lbl] = $map;

                $byLabel[$lbl]['group'] = $grp;
                $byLabel[$lbl]['items'][] = $item;
            }

            // Decide where Potongan Transaksi goes
            if (isset($byLabel[$originalLabel])) {
                $potonganTransaksiTarget = $originalLabel;
            } elseif (count($byLabel) === 1) {
                $potonganTransaksiTarget = array_key_first($byLabel);
            } else {
                // Edge case: fully switched AND split across more than
                // one non-original type. No single natural home — fall
                // back to the original type so the amount isn't lost.
                $potonganTransaksiTarget = $originalLabel;
            }

            foreach ($byLabel as $label => $bucket) {
                $group = $bucket['group'];
                $groupItems = collect($bucket['items']);
                $count = $groupItems->count();

                if (!isset($grouped[$group][$label])) {
                    $grouped[$group][$label] = $this->emptyBucket();
                }

                $ref = &$grouped[$group][$label];

                $ref['r']        += $count;
                $ref['jasa']     += $this->safeSum($groupItems, 'embalase');
                $ref['embalase'] += $this->safeSum($groupItems, 'service_fee');
                $ref['potongan'] += $this->safeSum($groupItems, 'discount');

                $itemNetto = $this->safeSum($groupItems, 'final_price');
                if ($label === 'Retur Tunai') $itemNetto = -abs($itemNetto);
                $ref['netto'] += $itemNetto;

                if ($label === $potonganTransaksiTarget) {
                    $ref['lembar'] += 1;
                    $ref['potongan_transaksi'] += (int) ($trx->discount ?? 0);
                }

                unset($ref);
            }
        }

        return $grouped;
    }

    private function buildRows(array $grouped): array
    {
        $rows = [];
        $no   = 1;

        $grand = $this->emptyBucket();

        // HEADER
        $rows[] = [$this->pharmacyName];
        $rows[] = [$this->pharmacyAddress];
        $rows[] = [];
        $titleSuffix = $this->customTitle ? ' - ' . $this->customTitle : '';
        $shiftLabel = 'Seluruh';
        if (!empty($this->shift)) {
            $shiftObj = \App\Models\Shifts::find($this->shift);
            $shiftLabel = $shiftObj ? $shiftObj->name : 'Shift ' . $this->shift;
        }
        $rows[] = ['Laporan Penjualan Harian (LIPH)' . $titleSuffix];
        $rows[] = ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y') . ' (' . $shiftLabel . ')'];
        $rows[] = [];

        // TABLE HEADER
        $rows[] = ['No.', 'Pelanggan', 'Lembar', 'R/', 'Jasa', 'Embalase', 'Netto', 'Potongan', 'Potongan Transaksi', 'Netto Akhir'];

        // KREDIT
        $rows[] = ['Penjualan Kredit', '', '', '', '', '', '', '', '', ''];

        $kredit = $grouped['kredit'] ?? [];
        $sub = $this->emptyBucket();

        foreach ($kredit as $label => $d) {
            $rows[] = [
                $no++,
                $label,
                $this->nz(round($d['lembar'], 2)),
                $this->nz($d['r']),
                $this->nz($d['jasa']),
                $this->nz($d['embalase']),
                $this->nz($d['netto']),
                $this->nz($d['potongan']),
                $this->nz($d['potongan_transaksi']),
                $this->nz($d['netto'] - $d['potongan_transaksi'])
            ];

            foreach ($sub as $k => $v) $sub[$k] += $d[$k];
        }

        $rows[] = [
            '',
            'Sub Total',
            $this->nz(round($sub['lembar'], 2)),
            $this->nz($sub['r']),
            $this->nz($sub['jasa']),
            $this->nz($sub['embalase']),
            $this->nz($sub['netto']),
            $this->nz($sub['potongan']),
            $this->nz($sub['potongan_transaksi']),
            $this->nz($sub['netto'] - $sub['potongan_transaksi'])
        ];

        foreach ($grand as $k => $v) $grand[$k] += $sub[$k];

        // TUNAI
        $rows[] = ['Penjualan Tunai', '', '', '', '', '', '', '', '', ''];

        $tunai = $grouped['tunai'] ?? [];
        $sub = $this->emptyBucket();

        foreach (self::TUNAI_ORDER as $label) {

            $d = $tunai[$label] ?? $this->emptyBucket();

            if ($label === 'Retur Tunai') {
                [$d['lembar'], $d['r']] = [$d['r'], $d['lembar']];
            }

            $rows[] = [
                $no++,
                $label,
                $this->nz(round($d['lembar'], 2)),
                $this->nz($label === 'Retur Tunai' ?  -$d['r'] : $d['r']),
                $this->nz($d['jasa']),
                $this->nz($d['embalase']),
                $this->nz($d['netto']),
                $this->nz($d['potongan']),
                $this->nz($d['potongan_transaksi']),
                $this->nz($d['netto'] - $d['potongan_transaksi'])
            ];

            foreach ($sub as $k => $v) {
                if ($label === 'Retur Tunai' && $k === 'r') {
                    $sub[$k] -= $d[$k];
                } else {
                    $sub[$k] += $d[$k];
                }
            }
        }

        $rows[] = [
            '',
            'Sub Total',
            $this->nz(round($sub['lembar'], 2)),
            $this->nz($sub['r']),
            $this->nz($sub['jasa']),
            $this->nz($sub['embalase']),
            $this->nz($sub['netto']),
            $this->nz($sub['potongan']),
            $this->nz($sub['potongan_transaksi']),
            $this->nz($sub['netto'] - $sub['potongan_transaksi'])
        ];

        foreach ($grand as $k => $v) $grand[$k] += $sub[$k];

        // GRAND TOTAL
        $rows[] = [
            '',
            'Grand Total',
            $this->nz(round($grand['lembar'], 2)),
            $this->nz($grand['r']),
            $this->nz($grand['jasa']),
            $this->nz($grand['embalase']),
            $this->nz($grand['netto']),
            $this->nz($grand['potongan']),
            $this->nz($grand['potongan_transaksi']),
            $this->nz($grand['netto'] - $grand['potongan_transaksi'])
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $thin   = ['borderStyle' => Border::BORDER_THIN];
        $medium = ['borderStyle' => Border::BORDER_THIN];
        $numFmt = '#,##0';

        $lastRow = $sheet->getHighestRow();
        $lastCol = 'J'; // was H — now 10 columns (added Potongan Transaksi, Netto Akhir)

        /*
    | FONT (PDF style)
    */
        $sheet->getStyle("A1:{$lastCol}{$lastRow}")
            ->getFont()
            ->setName('Arial')
            ->setSize(10);

        /*
    | ROW HEIGHT (tight)
    */
        for ($i = 1; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(26);
        }

        /*
    | MERGE HEADER TEXT
    */
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->mergeCells("A4:{$lastCol}4");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->getStyle("A1:{$lastCol}4")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}4")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        /*
    | TABLE HEADER (ROW 5 — IMPORTANT)
    */
        $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => $thin,
            ],
        ]);

        /*
    | CONTENT
    */
        for ($row = 6; $row <= $lastRow; $row++) {

            $A = trim((string)$sheet->getCell("A{$row}")->getValue());
            $B = trim((string)$sheet->getCell("B{$row}")->getValue());

            /*
        | SECTION TITLE (NO BORDER)
        */
            if (in_array($A, ['Penjualan Kredit', 'Penjualan Tunai'])) {
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");

                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                continue;
            }

            /*
        | NORMAL ROW (THIN GRID)
        */
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'borders' => [
                    'allBorders' => $thin,
                ],
            ]);

            /*
        | ALIGNMENT (MATCH PDF)
        */
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                ->getAlignment()
                ->setVertical(Alignment::VERTICAL_CENTER);

            /*
        | NUMBER FORMAT
        */

            if ($B === 'Sub Total' || $B === 'Grand Total') {

                // Center label
                $sheet->getStyle("B{$row}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Center left side (No, Pelanggan, Lembar, R/)
                $sheet->getStyle("A{$row}:D{$row}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            for ($col = 3; $col <= 10; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, $row);

                if ($cell->getValue() === null || $cell->getValue() === '') {
                    $cell->setValueExplicit(0, DataType::TYPE_NUMERIC);
                }

                $cell->getStyle()->getNumberFormat()->setFormatCode($numFmt);
            }

            /*
        | SUB TOTAL (MEDIUM BORDER)
        */
            if ($B === 'Sub Total') {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'outline' => $medium,
                        'inside'  => $thin,
                    ],
                ]);
            }

            /*
        | GRAND TOTAL (STRONGEST)
        */
            if ($B === 'Grand Total') {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                    'font' => ['bold' => true],
                    'borders' => [
                        'outline' => $medium,
                        'inside'  => $thin,
                    ],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   // No
            'B' => 30,  // Pelanggan
            'C' => 9,   // Lembar
            'D' => 7,   // R/
            'E' => 14,  // Jasa
            'F' => 14,  // Embalase
            'G' => 14,  // Netto
            'H' => 14,  // Potongan
            'I' => 18,  // Potongan Transaksi
            'J' => 18,  // Netto Akhir
        ];
    }

    public function title(): string
    {
        return $this->customTitle ?? 'LIPH';
    }
}
