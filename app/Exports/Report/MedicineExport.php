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
        // Samakan dasar periode & status dengan LIPH: tanggal = waktu transaksi selesai (created_at).
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
            $rtAmount = (int) ($rt->subtotal ?? 0);

            $match = $candidates->first(function ($r) use ($rtTime, $rtAmount, $used, $originCodes) {
                return !isset($used[$r->id])
                    && isset($originCodes[$r->transaction_id])
                    && abs((float) $r->total_retur - (float) $rtAmount) < 1.0
                    && abs(Carbon::parse($r->created_at)->diffInSeconds($rtTime, false)) <= 10;
            });

            $date = Carbon::parse($rt->created_at ?? $rt->updated_at ?? $rt->shift_logs?->clock_in);

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
                    'total'       => (int) ($rt->subtotal ?? ($match->total_retur ?? 0)),
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
                    'total'       => (int) ($rt->subtotal ?? 0),
                    'nomor'       => 'RT#' . $rt->id,
                    'date'        => $date,
                ];
            }
        }

        return $returns;
    }

    protected function buildRecap($transactions, array $returns = []): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                if (!$medicine) {
                    $key     = 'item_' . $item->id;
                    $name    = $item->medicine_name ?? 'Obat (Tanpa Master)';
                    $factory = '-';
                    $medId   = null;
                } else {
                    $key     = $medicine->id;
                    $name    = $medicine->name ?? '-';
                    $factory = $medicine->factory?->name ?? '-';
                    $medId   = $medicine->id;
                }

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'medicine_id' => $medId,
                        'name'        => $name,
                        'factory'     => $factory,
                        'qty'         => 0,
                        'total'       => 0,
                        'qty_retur'   => 0,
                        'total_retur' => 0,
                    ];
                }

                $grouped[$key]['qty'] += (float) ($item->quantity ?? 0);
                $grouped[$key]['total'] += (int) round((float) ($item->final_price ?? 0));
            }
        }

        foreach ($returns as $ret) {
            $key = $ret['key'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'medicine_id' => $ret['medicine_id'] ?? null,
                    'name'        => $ret['name'],
                    'factory'     => $ret['factory'],
                    'qty'         => 0,
                    'total'       => 0,
                    'qty_retur'   => 0,
                    'total_retur' => 0,
                ];
            }
            $grouped[$key]['qty_retur']   += (float) $ret['qty'];
            $grouped[$key]['total_retur'] += (int) ($ret['total'] ?? 0);
        }

        // Fetch realtime stock matching Smart Order (/createorder) logic
        $medicineIds = array_values(array_filter(array_column($grouped, 'medicine_id')));
        $stockMap = $this->resolveStockMap($medicineIds);

        // Sort alphabetically by medicine name
        uasort($grouped, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $rows   = [];
        $rows[] = ['No', 'Nama Obat', 'Pabrik', 'Qty Jual', 'Nilai Jual', 'Qty Retur', 'Nilai Retur', 'Qty Netto', 'Nilai Netto', 'Sisa Stok'];

        $no = 1;
        $grand = array_fill_keys(['qty', 'total', 'qty_retur', 'total_retur'], 0);
        $grandStock = 0;

        foreach ($grouped as $data) {
            $medId = $data['medicine_id'] ?? null;
            $stock = $medId ? ($stockMap[$medId] ?? 0) : 0;
            $grandStock += $stock;

            $rows[] = [
                $no++,
                $data['name'],
                $data['factory'],
                $data['qty'],
                (int) $data['total'],
                $data['qty_retur'] ? -$data['qty_retur'] : 0,
                $data['total_retur'] ? -(int) $data['total_retur'] : 0,
                $data['qty'] - $data['qty_retur'],
                (int) ($data['total'] - $data['total_retur']),
                $stock,
            ];

            foreach ($grand as $k => $v) {
                $grand[$k] += $data[$k];
            }
        }

        $rows[] = [
            '', 'TOTAL', '',
            $grand['qty'],
            (int) $grand['total'],
            $grand['qty_retur'] ? -$grand['qty_retur'] : 0,
            $grand['total_retur'] ? -(int) $grand['total_retur'] : 0,
            $grand['qty'] - $grand['qty_retur'],
            (int) ($grand['total'] - $grand['total_retur']),
            $grandStock,
        ];

        $potonganTransaksi = (int) round($transactions->reject(function ($trx) {
            return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
        })->sum(fn ($t) => (float) ($t->discount ?? 0)));

        if ($potonganTransaksi > 0) {
            $rows[] = [
                '', 'POTONGAN TRANSAKSI (DISKON NOTA)', '',
                '', '', '', '', '',
                -$potonganTransaksi,
                '',
            ];

            $rows[] = [
                '', 'TOTAL NETTO AKHIR', '',
                '', '', '', '',
                $grand['qty'] - $grand['qty_retur'],
                (int) (($grand['total'] - $grand['total_retur']) - $potonganTransaksi),
                $grandStock,
            ];
        }

        return $rows;
    }

    protected function buildDetail($transactions, array $returns = []): array
    {
        $itemsList = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                $medName  = $medicine?->name ?? ($item->medicine_name ?? '-');

                $trxCode  = $trx->transaction_code ?? '-';
                $jnsHarga = !empty($item->cart_type) ? (string) $item->cart_type : '-';

                $qty   = (float) ($item->quantity ?? 0);
                $bruto = (int) round((float) ($item->total_price ?? ($item->raw_total ?? 0)));
                $disc  = (int) round((float) ($item->discount ?? 0));
                $netto = (int) round((float) ($item->final_price ?? 0));

                $itemsList[] = [
                    'nomor'     => $trxCode,
                    'jns_harga' => $jnsHarga,
                    'name'      => $medName,
                    'qty'       => $qty,
                    'bruto'     => $bruto,
                    'disc'      => $disc,
                    'netto'     => $netto,
                ];
            }
        }

        foreach ($returns as $ret) {
            $itemsList[] = [
                'nomor'     => $ret['nomor'],
                'jns_harga' => 'RT',
                'name'      => $ret['name'],
                'qty'       => -(float) $ret['qty'],
                'bruto'     => -(int) $ret['total'],
                'disc'      => 0,
                'netto'     => -(int) $ret['total'],
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
        $rows[] = ['No.', 'Nomor', 'JNS_HARGA', 'Nama Obat', 'Qty', 'Bruto', 'Disc', 'Netto'];

        $no = 1;
        $grandQty = 0;
        $grandBruto = 0;
        $grandDisc = 0;
        $grandNetto = 0;

        foreach ($itemsList as $row) {
            $rows[] = [
                $no++,
                $row['nomor'],
                $row['jns_harga'],
                $row['name'],
                $row['qty'],
                (int) $row['bruto'],
                (int) $row['disc'],
                (int) $row['netto'],
            ];

            $grandQty += $row['qty'];
            $grandBruto += $row['bruto'];
            $grandDisc += $row['disc'];
            $grandNetto += $row['netto'];
        }

        $rows[] = ['', 'TOTAL', '', '', $grandQty, (int) $grandBruto, (int) $grandDisc, (int) $grandNetto];

        $potonganTransaksi = (int) round($transactions->reject(function ($trx) {
            return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
        })->sum(fn ($t) => (float) ($t->discount ?? 0)));

        if ($potonganTransaksi > 0) {
            $rows[] = [
                '', 'POTONGAN TRANSAKSI (DISKON NOTA)', '', '',
                '', '', '',
                -$potonganTransaksi,
            ];

            $rows[] = [
                '', 'TOTAL NETTO AKHIR', '', '',
                $grandQty,
                '', '',
                (int) ($grandNetto - $potonganTransaksi),
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
            $trxDate = Carbon::parse($trx->created_at ?? $trx->updated_at ?? $trx->shift_logs?->clock_in);
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

        // Realtime stock calculation matching Smart Order (/createorder)
        $stockMap = $this->resolveStockMap(array_values($medicineIds));

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
            $stock = $medId ? ($stockMap[$medId] ?? 0) : 0;

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

    /**
     * Sisa stok realtime per obat menggunakan logika yang sama dengan Smart Order (/createorder).
     * Yaitu: stok batch di apotek aktif + stok transfer counter yang masih aktif.
     */
    protected function resolveStockMap(array $medicineIds): array
    {
        $medicineIds = array_values(array_filter(array_unique($medicineIds)));
        if (empty($medicineIds)) {
            return [];
        }

        $pharmacyId = $this->pharmacyId;
        $user = auth()->user();
        $isWarehouse = isWarehousePharmacy($pharmacyId) || (int) $pharmacyId === 9 || (int) $pharmacyId === 1;
        if ($user && ($user->hasRole('Gudang PMI') || $user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('Manager') || $user->hasRole('manager') || $user->hasRole('Koordinator'))) {
            if (!session()->has('ho_pharmacy_id') || in_array((int) session('ho_pharmacy_id'), [1, 9])) {
                $isWarehouse = true;
            }
        }

        $counterPharmacyId = $isWarehouse ? 1 : $pharmacyId;

        $batchStockMap = Batches::where('pharmacy_id', $pharmacyId)
            ->whereIn('medicine_id', $medicineIds)
            ->groupBy('medicine_id')
            ->select('medicine_id', DB::raw('COALESCE(SUM(stock), 0) as total'))
            ->pluck('total', 'medicine_id')
            ->toArray();

        $transferStockMap = MedicineTransferItems::join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id')
            ->where('batches.pharmacy_id', $counterPharmacyId)
            ->where('medicine_transfer_items.status', 1)
            ->where(function ($q) {
                $q->whereNull('medicine_transfer_items.source_type')
                  ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
            })
            ->whereIn('batches.medicine_id', $medicineIds)
            ->groupBy('batches.medicine_id')
            ->select('batches.medicine_id', DB::raw('COALESCE(SUM(medicine_transfer_items.qty), 0) as total'))
            ->pluck('total', 'medicine_id')
            ->toArray();

        $stockMap = [];
        foreach ($medicineIds as $id) {
            $stockMap[$id] = (int) ($batchStockMap[$id] ?? 0) + (int) ($transferStockMap[$id] ?? 0);
        }

        return $stockMap;
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
            // Align Qty & Nilai (jual, retur, netto) and Sisa Stok right (Columns D to J)
            $sheet->getStyle("D{$dataStartRow}:J{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("D" . ($dataStartRow + 1) . ":J{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Retur columns in red
            $sheet->getStyle("F" . ($dataStartRow + 1) . ":G{$lastRow}")
                ->getFont()->getColor()->setRGB('B91C1C');
        } else {
            // Align Qty, Bruto, Disc, Netto right (Columns E to H)
            $sheet->getStyle("E{$dataStartRow}:H{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Format Qty, Bruto, Disc, Netto as number
            $sheet->getStyle("E" . ($dataStartRow + 1) . ":H{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');

            // Center Nomor (Col B) and JNS_HARGA (Col C)
            $sheet->getStyle("B{$dataStartRow}:C{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Text format for Nomor (Col B) to prevent Excel from converting numbers
            $sheet->getStyle("B" . ($dataStartRow + 1) . ":B{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
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
                'J' => 14,
            ]
            : [
                'A' => 6,   // No.
                'B' => 20,  // Nomor
                'C' => 14,  // JNS_HARGA
                'D' => 38,  // Nama Obat
                'E' => 12,  // Qty
                'F' => 18,  // Bruto
                'G' => 16,  // Disc
                'H' => 20,  // Netto
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
