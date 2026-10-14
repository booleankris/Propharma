<?php

namespace App\Exports\Report;

use App\Models\Batches;
use App\Models\MedicineTransferItems;
use App\Models\MedicineTransactions;
use App\Models\Medicines;
use App\Models\Retur;
use App\Support\LiphTransactionScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MedicineExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;
    protected $selectedType;


    public function __construct($pharmacyId, $startDate, $endDate, $shift, $shiftType, $selectedType)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->shift        = $shift;
        $this->shiftType    = $shiftType;
        $this->selectedType = $selectedType;
    }

    public function array(): array
    {
        // Samakan dasar periode & status dengan LIPH: tanggal = mulai shift
        // (clock_in), bukan updated_at transaksi. Shift yang melewati tengah
        // malam sebelumnya terpecah ke dua tanggal sehingga total tidak klop.
        $query = MedicineTransactions::with([
            'transactions' => fn ($q) => $q->where('status', 1),
            'transactions.medicine.factory',
            'shift_logs',
        ])
            ->where('pharmacy_id', $this->pharmacyId);

        LiphTransactionScope::applySalesStatus($query);
        LiphTransactionScope::applyPeriod(
            $query,
            $this->startDate,
            $this->endDate,
            $this->shiftType,
            $this->shift
        );

        $transactions = $query->get();
        $returns      = $this->resolveReturns($transactions);

        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);

        $typeTitle = match ($this->selectedType) {
            'rekap_bulanan', 'bulanan' => 'Rekap Bulanan Obat',
            'detail'                   => 'Detail Penjualan Obat',
            default                    => 'Rekap Penjualan Obat',
        };

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan (' . $typeTitle . ')'],
            ['Periode : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = match ($this->selectedType) {
            'rekap_bulanan', 'bulanan' => $this->buildMonthlyRecap($transactions, $returns),
            'detail'                   => $this->buildDetail($transactions, $returns),
            default                    => $this->buildRecap($transactions, $returns),
        };

        return array_merge($header, $body);
    }

    /**
     * Retur penjualan disimpan sebagai header RT tanpa item keranjang, dan
     * rincian obatnya ada di tabel `retur`. Keduanya dibuat dalam request
     * yang sama (ReturController), jadi dicocokkan via waktu simpan + nominal.
     * Header RT yang tidak punya rincian (data lama) tetap dihitung sebagai
     * baris "tanpa rincian obat" agar total tetap sama dengan LIPH.
     */
    private function resolveReturns($transactions): array
    {
        $headers = $transactions->filter(function ($trx) {
            return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
        })->values();

        if ($headers->isEmpty()) {
            return [];
        }

        $times = $headers->map(fn ($h) => Carbon::parse($h->created_at ?? $h->updated_at));
        $candidates = Retur::whereBetween('created_at', [
            $times->min()->copy()->subSeconds(10),
            $times->max()->copy()->addSeconds(10),
        ])->get();

        $medicines = Medicines::with('factory')
            ->whereIn('id', $candidates->pluck('medicine_id')->filter()->unique())
            ->get()->keyBy('id');
        $originCodes = MedicineTransactions::whereIn('id', $candidates->pluck('transaction_id')->filter()->unique())
            ->where('pharmacy_id', $this->pharmacyId)
            ->pluck('transaction_code', 'id');

        $used    = [];
        $returns = [];

        foreach ($headers as $rt) {
            $rtTime   = Carbon::parse($rt->created_at ?? $rt->updated_at);
            $rtAmount = (int) round((float) $rt->subtotal);

            $match = $candidates->first(function ($r) use ($rtTime, $rtAmount, $used, $originCodes) {
                return !isset($used[$r->id])
                    && isset($originCodes[$r->transaction_id])
                    && (int) round((float) $r->total_retur) === $rtAmount
                    && abs(Carbon::parse($r->created_at)->diffInSeconds($rtTime, false)) <= 10;
            });

            $date = Carbon::parse($rt->shift_logs?->clock_in ?? $rt->created_at ?? $rt->updated_at);

            if ($match) {
                $used[$match->id] = true;
                $medicine = $medicines[$match->medicine_id] ?? null;
                $returns[] = [
                    'key'         => $medicine ? $medicine->id : 'retur_' . $rt->id,
                    'medicine_id' => $medicine?->id,
                    'code'        => $medicine?->code ?? '-',
                    'name'        => $medicine?->name ?? 'Obat Retur (Tanpa Master)',
                    'unit'        => $medicine?->unit ?? '-',
                    'factory'     => $medicine?->factory?->name ?? '-',
                    'qty'         => (float) $match->qty_retur,
                    'total'       => (float) $match->total_retur,
                    'nomor'       => 'RT/' . ($originCodes[$match->transaction_id] ?? '-'),
                    'date'        => $date,
                ];
            } else {
                $returns[] = [
                    'key'         => 'retur_' . $rt->id,
                    'medicine_id' => null,
                    'code'        => '-',
                    'name'        => '[RETUR TANPA RINCIAN OBAT]',
                    'unit'        => '-',
                    'factory'     => '-',
                    'qty'         => 0,
                    'total'       => (float) $rt->subtotal,
                    'nomor'       => 'RT#' . $rt->id,
                    'date'        => $date,
                ];
            }
        }

        return $returns;
    }

    private function buildRecap($transactions, array $returns = []): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                if (!$medicine) {
                    $key = 'item_' . $item->id;
                    $name = $item->medicine_name ?? 'Obat (Tanpa Master)';
                    $factory = '-';
                } else {
                    $key = $medicine->id;
                    $name = $medicine->name ?? '-';
                    $factory = $medicine->factory?->name ?? '-';
                }

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'name'        => $name,
                        'factory'     => $factory,
                        'qty'         => 0,
                        'total'       => 0,
                        'qty_retur'   => 0,
                        'total_retur' => 0,
                    ];
                }

                $grouped[$key]['qty'] += (float) ($item->quantity ?? 0);
                $grouped[$key]['total'] += (float) ($item->final_price ?? 0);
            }
        }

        foreach ($returns as $ret) {
            $key = $ret['key'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'name'        => $ret['name'],
                    'factory'     => $ret['factory'],
                    'qty'         => 0,
                    'total'       => 0,
                    'qty_retur'   => 0,
                    'total_retur' => 0,
                ];
            }
            $grouped[$key]['qty_retur']   += $ret['qty'];
            $grouped[$key]['total_retur'] += $ret['total'];
        }

        // Sort alphabetically by medicine name
        uasort($grouped, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $rows   = [];
        $rows[] = ['No', 'Nama Obat', 'Pabrik', 'Qty Jual', 'Nilai Jual', 'Qty Retur', 'Nilai Retur', 'Qty Netto', 'Nilai Netto'];

        $no = 1;
        $grand = array_fill_keys(['qty', 'total', 'qty_retur', 'total_retur'], 0);

        foreach ($grouped as $data) {
            $rows[] = [
                $no++,
                $data['name'],
                $data['factory'],
                $data['qty'],
                $data['total'],
                $data['qty_retur'] ? -$data['qty_retur'] : 0,
                $data['total_retur'] ? -$data['total_retur'] : 0,
                $data['qty'] - $data['qty_retur'],
                $data['total'] - $data['total_retur'],
            ];

            foreach ($grand as $k => $v) {
                $grand[$k] += $data[$k];
            }
        }

        $rows[] = [
            '', 'TOTAL', '',
            $grand['qty'],
            $grand['total'],
            $grand['qty_retur'] ? -$grand['qty_retur'] : 0,
            $grand['total_retur'] ? -$grand['total_retur'] : 0,
            $grand['qty'] - $grand['qty_retur'],
            $grand['total'] - $grand['total_retur'],
        ];

        $potonganTransaksi = (float) $transactions->reject(function ($trx) {
            return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
        })->sum(fn ($t) => is_numeric($t->discount) ? (float) $t->discount : 0);

        if ($potonganTransaksi > 0) {
            $rows[] = [
                '', 'POTONGAN TRANSAKSI (DISKON NOTA)', '',
                '', '', '', '', '',
                -$potonganTransaksi,
            ];

            $rows[] = [
                '', 'TOTAL NETTO AKHIR', '',
                '', '', '', '',
                $grand['qty'] - $grand['qty_retur'],
                ($grand['total'] - $grand['total_retur']) - $potonganTransaksi,
            ];
        }

        return $rows;
    }

    private function buildDetail($transactions, array $returns = []): array
    {
        $itemsList = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                $medName = $medicine?->name ?? ($item->medicine_name ?? '-');

                $trxCode = $trx->transaction_code ?? '-';
                $cartType = $item->cart_type ? '/' . $item->cart_type : '';
                $nomor = $trxCode . $cartType;

                $qty   = (float) ($item->quantity ?? 0);
                $bruto = (float) ($item->total_price ?? ($item->raw_total ?? 0));
                $disc  = (float) ($item->discount ?? 0);
                $netto = (float) ($item->final_price ?? 0);

                $itemsList[] = [
                    'nomor'    => $nomor,
                    'name'     => $medName,
                    'qty'      => $qty,
                    'bruto'    => $bruto,
                    'disc'     => $disc,
                    'netto'    => $netto,
                ];
            }
        }

        foreach ($returns as $ret) {
            $itemsList[] = [
                'nomor' => $ret['nomor'],
                'name'  => $ret['name'],
                'qty'   => -$ret['qty'],
                'bruto' => -$ret['total'],
                'disc'  => 0,
                'netto' => -$ret['total'],
            ];
        }

        // Sort by Nama Obat (A-Z), then by Nomor Transaksi
        usort($itemsList, function ($a, $b) {
            $cmp = strcasecmp($a['name'], $b['name']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcasecmp($a['nomor'], $b['nomor']);
        });

        $rows   = [];
        $rows[] = ['No.', 'Nomor', 'Nama Obat', 'Qty', 'Bruto', 'Disc', 'Netto'];

        $no = 1;
        $grandQty = 0;
        $grandBruto = 0;
        $grandDisc = 0;
        $grandNetto = 0;

        foreach ($itemsList as $row) {
            $rows[] = [
                $no++,
                $row['nomor'],
                $row['name'],
                $row['qty'],
                $row['bruto'],
                $row['disc'],
                $row['netto'],
            ];

            $grandQty += $row['qty'];
            $grandBruto += $row['bruto'];
            $grandDisc += $row['disc'];
            $grandNetto += $row['netto'];
        }

        $rows[] = ['', 'TOTAL', '', $grandQty, $grandBruto, $grandDisc, $grandNetto];

        $potonganTransaksi = (float) $transactions->reject(function ($trx) {
            return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
        })->sum(fn ($t) => is_numeric($t->discount) ? (float) $t->discount : 0);

        if ($potonganTransaksi > 0) {
            $rows[] = [
                '', 'POTONGAN TRANSAKSI (DISKON NOTA)', '',
                '', '', '',
                -$potonganTransaksi,
            ];

            $rows[] = [
                '', 'TOTAL NETTO AKHIR', '',
                $grandQty,
                '', '',
                $grandNetto - $potonganTransaksi,
            ];
        }

        return $rows;
    }

    public function getMonthsList(): array
    {
        $currentMonth = $this->startDate->copy()->startOfMonth();
        $endMonth     = $this->endDate->copy()->startOfMonth();
        $months       = [];

        while ($currentMonth->lte($endMonth)) {
            $key = $currentMonth->format('Y-m');
            $label = $currentMonth->locale('id')->isoFormat('MMMM Y');
            $months[$key] = $label;
            $currentMonth->addMonth();
        }

        if (empty($months)) {
            $key = $this->startDate->format('Y-m');
            $months[$key] = $this->startDate->locale('id')->isoFormat('MMMM Y');
        }

        return $months;
    }

    private function buildMonthlyRecap($transactions, array $returns = []): array
    {
        $months = $this->getMonthsList();
        $grouped = [];
        $medicineIds = [];

        foreach ($transactions as $trx) {
            $trxDate = Carbon::parse($trx->shift_logs?->clock_in ?? $trx->created_at ?? $trx->updated_at);
            $monthKey = $trxDate->format('Y-m');

            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                if (!$medicine) {
                    $key     = 'item_' . $item->id;
                    $code    = '-';
                    $name    = $item->medicine_name ?? 'Obat (Tanpa Master)';
                    $unit    = '-';
                    $factory = '-';
                    $medId   = null;
                } else {
                    $key     = $medicine->id;
                    $code    = $medicine->code ?? '-';
                    $name    = $medicine->name ?? '-';
                    $unit    = $medicine->unit ?? '-';
                    $factory = $medicine->factory?->name ?? '-';
                    $medId   = $medicine->id;
                    $medicineIds[$medId] = $medId;
                }

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'id'        => $medId,
                        'code'      => $code,
                        'name'      => $name,
                        'unit'      => $unit,
                        'factory'   => $factory,
                        'monthly'   => array_fill_keys(array_keys($months), 0),
                        'total_qty' => 0,
                    ];
                }

                $qty = (float) ($item->quantity ?? 0);
                if (isset($grouped[$key]['monthly'][$monthKey])) {
                    $grouped[$key]['monthly'][$monthKey] += $qty;
                } else {
                    $grouped[$key]['monthly'][$monthKey] = $qty;
                }
                $grouped[$key]['total_qty'] += $qty;
            }
        }

        // Kurangi qty obat yang diretur pada bulan shift retur tersebut
        foreach ($returns as $ret) {
            if (!$ret['medicine_id']) {
                continue; // retur tanpa rincian obat tidak punya qty per obat
            }
            $key = $ret['key'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id'        => $ret['medicine_id'],
                    'code'      => $ret['code'],
                    'name'      => $ret['name'],
                    'unit'      => $ret['unit'],
                    'factory'   => $ret['factory'],
                    'monthly'   => array_fill_keys(array_keys($months), 0),
                    'total_qty' => 0,
                ];
                $medicineIds[$ret['medicine_id']] = $ret['medicine_id'];
            }
            $mKey = $ret['date']->format('Y-m');
            $grouped[$key]['monthly'][$mKey] = ($grouped[$key]['monthly'][$mKey] ?? 0) - $ret['qty'];
            $grouped[$key]['total_qty'] -= $ret['qty'];
        }

        // Realtime stock calculation for involved medicines
        $storageStockMap = [];
        $counterStockMap = [];

        if (!empty($medicineIds)) {
            $canSeeWarehouse   = canAccessWarehouseStock($this->pharmacyId);
            $warehouseId       = getWarehousePharmacyId();
            $counterPharmacyId = isWarehousePharmacy($this->pharmacyId) ? 1 : $this->pharmacyId;

            if ($canSeeWarehouse) {
                $storageStockMap = Batches::where('pharmacy_id', $warehouseId)
                    ->where('stock', '>', 0)
                    ->whereIn('medicine_id', $medicineIds)
                    ->groupBy('medicine_id')
                    ->select('medicine_id', DB::raw('COALESCE(SUM(stock), 0) as total'))
                    ->pluck('total', 'medicine_id')
                    ->toArray();
            }

            $counterStockMap = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
                ->where('batches.pharmacy_id', $counterPharmacyId)
                ->where('medicine_transfer_items.status', 1)
                ->where('medicine_transfer_items.qty', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('medicine_transfer_items.source_type')
                      ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                })
                ->whereIn('batches.medicine_id', $medicineIds)
                ->groupBy('batches.medicine_id')
                ->select('batches.medicine_id', DB::raw('COALESCE(SUM(medicine_transfer_items.qty), 0) as total'))
                ->pluck('total', 'medicine_id')
                ->toArray();
        }

        // Sort alphabetically by medicine name
        uasort($grouped, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $headerCols = ['No', 'Kode Obat', 'Nama Obat', 'Satuan', 'Pabrik'];
        foreach ($months as $label) {
            $headerCols[] = $label;
        }
        $headerCols[] = 'Total Terjual (- Retur)';
        $headerCols[] = 'Sisa Stok';

        $rows = [$headerCols];

        $no = 1;
        $monthlyTotals = array_fill_keys(array_keys($months), 0);
        $grandTotalQty = 0;
        $grandTotalStock = 0;

        foreach ($grouped as $data) {
            $medId = $data['id'];
            $stock = $medId ? ((int) ($storageStockMap[$medId] ?? 0) + (int) ($counterStockMap[$medId] ?? 0)) : 0;

            $row = [
                $no++,
                $data['code'],
                $data['name'],
                $data['unit'],
                $data['factory'],
            ];

            foreach (array_keys($months) as $mK) {
                $mQty = (float) ($data['monthly'][$mK] ?? 0);
                $row[] = $mQty;
                $monthlyTotals[$mK] += $mQty;
            }

            $row[] = $data['total_qty'];
            $row[] = $stock;

            $grandTotalQty += $data['total_qty'];
            $grandTotalStock += $stock;

            $rows[] = $row;
        }

        // Summary row
        $summaryRow = ['', '', 'TOTAL', '', ''];
        foreach (array_keys($months) as $mK) {
            $summaryRow[] = $monthlyTotals[$mK];
        }
        $summaryRow[] = $grandTotalQty;
        $summaryRow[] = $grandTotalStock;

        $rows[] = $summaryRow;

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = $sheet->getHighestColumn();
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7; // header rows (1–6) + table starts at row 7

        // Merge header info cells across all columns
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        // Pharmacy name — bold, larger font
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Bold the table column header row
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        // Bold the summary rows at the bottom (TOTAL, POTONGAN TRANSAKSI, TOTAL NETTO AKHIR)
        for ($r = $lastRow; $r >= $dataStartRow; $r--) {
            $cellB = trim((string) $sheet->getCell("B{$r}")->getValue());
            if ($cellB === 'TOTAL' || str_starts_with($cellB, 'TOTAL') || str_starts_with($cellB, 'POTONGAN')) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true);
                if (str_starts_with($cellB, 'POTONGAN')) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->getColor()->setRGB('B91C1C');
                }
            } else {
                break;
            }
        }

        // Borders only on the data table (row 7 onward)
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = 7; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);
        }

        // Default alignment for data table
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Center No
        $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($this->selectedType === 'rekap_bulanan' || $this->selectedType === 'bulanan') {
            // Center Kode Obat (Col B) and Satuan (Col D)
            $sheet->getStyle("B{$dataStartRow}:B{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->getStyle("D{$dataStartRow}:D{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Numeric columns (Months, Total Terjual, Sisa Stok) start from Col F (col 6) to $lastCol
            $sheet->getStyle("F{$dataStartRow}:{$lastCol}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Format numbers as #,##0
            $sheet->getStyle("F" . ($dataStartRow + 1) . ":{$lastCol}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        } elseif ($this->selectedType === 'rekap') {
            // Align Qty & Nilai (jual, retur, netto) right
            $sheet->getStyle("D{$dataStartRow}:I{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("D" . ($dataStartRow + 1) . ":I{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Retur columns in red
            $sheet->getStyle("F" . ($dataStartRow + 1) . ":G{$lastRow}")
                ->getFont()->getColor()->setRGB('B91C1C');
        } else {
            // Align Qty, Bruto, Disc, Netto right
            $sheet->getStyle("D{$dataStartRow}:G{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Format Qty, Bruto, Disc, Netto as number
            $sheet->getStyle("D" . ($dataStartRow + 1) . ":G{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }
    }

    public function columnWidths(): array
    {
        if ($this->selectedType === 'rekap_bulanan' || $this->selectedType === 'bulanan') {
            $widths = [
                'A' => 6,   // No
                'B' => 14,  // Kode Obat
                'C' => 40,  // Nama Obat
                'D' => 10,  // Satuan
                'E' => 25,  // Pabrik
            ];

            $colIdx = 6;
            foreach ($this->getMonthsList() as $m) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
                $widths[$colLetter] = 16;
            }

            // Total Terjual
            $totalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
            $widths[$totalCol] = 16;

            // Sisa Stok
            $stockCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx++);
            $widths[$stockCol] = 15;

            return $widths;
        }

        return $this->selectedType === 'rekap'
            ? [
                'A' => 6,
                'B' => 42,
                'C' => 28,
                'D' => 12,
                'E' => 16,
                'F' => 12,
                'G' => 16,
                'H' => 12,
                'I' => 18,
            ]
            : [
                'A' => 6,
                'B' => 24,
                'C' => 38,
                'D' => 12,
                'E' => 18,
                'F' => 16,
                'G' => 20,
            ];
    }

    public function title(): string
    {
        return match ($this->selectedType) {
            'rekap_bulanan', 'bulanan' => 'Rekap Obat Per Bulan',
            'detail'                   => 'Detail Penjualan Obat',
            default                    => 'Rekap Penjualan Obat',
        };
    }
}
