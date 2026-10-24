<?php

namespace App\Exports\Orders;

use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\ReceivingDateFilter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InvoiceSingleSheetExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $selectedType;
    protected $supplier;
    protected $paymentType;
    protected $sheetTitle;

    const PPN = 0.11;

    public function __construct(
        $pharmacyId,
        $startDate,
        $endDate,
        $selectedType = 'Detail',
        $supplier = null,
        $paymentType = null,
        $sheetTitle = 'Semua'
    ) {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate)->startOfDay();
        $this->endDate      = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate)->endOfDay();
        $this->selectedType = $selectedType ?: 'detail';
        $this->supplier     = $supplier;
        $this->paymentType  = $paymentType;
        $this->sheetTitle   = $sheetTitle;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);

        $paymentSuffix = $this->paymentType !== null
            ? strtoupper($this->sheetTitle)
            : ($this->sheetTitle && strtoupper($this->sheetTitle) !== 'SEMUA' ? strtoupper($this->sheetTitle) : '');
        $reportTitle = 'LAPORAN PEMBELIAN FAKTUR' . ($paymentSuffix ? ' (' . $paymentSuffix . ')' : '');
        if (strtolower($this->selectedType) === 'rekap') {
            $reportTitle .= ' - REKAP';
        }

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            [$reportTitle],
            ['TANGGAL TERIMA : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = strtolower($this->selectedType) === 'rekap'
            ? $this->buildRekap()
            : $this->buildDetail();

        return array_merge($header, $body);
    }

    protected function baseQuery()
    {
        $targetPharmacyIds = in_array((int) $this->pharmacyId, [1, 6, 9])
            ? [9, 1]
            : [(int) $this->pharmacyId];

        $query = DB::table('receiving_items')
            ->join('receiving_details', 'receiving_details.id', '=', 'receiving_items.receiving_details_id')
            ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('creditors', 'creditors.code', '=', 'order_items.creditor_code')
            ->whereIn('receiving.pharmacy_id', $targetPharmacyIds)
            ->whereNotNull('receiving_items.batches_id');
        ReceivingDateFilter::applyToReceiptDetails($query, $this->startDate, $this->endDate);

        if ($this->paymentType !== null) {
            if ($this->paymentType === 'OTHER') {
                $query->where(function ($q) {
                    $q->whereNull('receiving_details.invoice_payment')
                      ->orWhere('receiving_details.invoice_payment', '')
                      ->orWhereNotIn(DB::raw('UPPER(TRIM(receiving_details.invoice_payment))'), ['KREDIT', 'TUNAI', 'KONSINYASI']);
                });
            } else {
                $query->where(DB::raw('UPPER(TRIM(receiving_details.invoice_payment))'), strtoupper(trim($this->paymentType)));
            }
        }

        if ($this->supplier) {
            $supplier = $this->supplier;
            $query->where(function ($sq) use ($supplier) {
                $sq->where('order_items.creditor_code', $supplier)
                    ->orWhere('creditors.code', $supplier)
                    ->orWhere('creditors.id', $supplier);
            });
        }

        return $query;
    }

    protected function buildDetail(): array
    {
        $items = $this->baseQuery()
            ->select([
                'receiving_details.id as receiving_details_id',
                'receiving_details.invoice_number',
                'receiving_details.invoice_date',
                'receiving_details.invoice_due',
                'receiving_details.invoice_times',
                'receiving_details.invoice_payment',
                'receiving_details.invoice_ppn',
                'receiving_details.receiving_id',
                'receiving_details.receiving_details_code',
                'receiving.updated_at as receiving_updated_at',
                'receiving.date as receiving_date',
                'receiving_details.created_at as receiving_details_created_at',
                'order_items.creditor_code',
                'creditors.name as creditor_name',
                'receiving_items.qty_received',
                'receiving_items.qty',
                'receiving_items.discount',
                'receiving_items.extra_discount',
                'receiving_items.raw_price as receiving_raw_price',
                'order_items.price as order_items_price',
            ])
            ->orderBy('receiving.updated_at', 'asc')
            ->orderBy('receiving_details.invoice_number', 'asc')
            ->get();

        $tableHeaders = [
            'NM_KREDITUR', 'KD_KREDITUR', 'NO_FAKTUR',
            'TGL_FAKTUR', 'NO_TERIMA', 'TGL_TERIMA',
            'DPP', 'PPN', 'JUMLAH', 'JTH_TEMPO', 'WKT_KREDIT', 'JNS_BAYAR'
        ];

        if ($items->isEmpty()) {
            return [
                $tableHeaders,
                ['Tidak ada data untuk periode yang dipilih.'],
            ];
        }

        $invoices = [];
        foreach ($items as $item) {
            // Each receipt detail owns its received items and receiving number.
            $key = (string) $item->receiving_details_id;
            if (!isset($invoices[$key])) {
                $invoices[$key] = [
                    'invoice_number'               => $item->invoice_number,
                    'invoice_date'                 => $item->invoice_date,
                    'invoice_due'                  => $item->invoice_due,
                    'invoice_times'                => $item->invoice_times,
                    'invoice_payment'              => $item->invoice_payment,
                    'receiving_details_code'       => $item->receiving_details_code,
                    'receiving_updated_at'         => $item->receiving_updated_at,
                    'receiving_date'               => $item->receiving_date,
                    'receiving_details_created_at' => $item->receiving_details_created_at,
                    'creditor_code'                => $item->creditor_code,
                    'creditor_name'                => $item->creditor_name,
                    'ppn_type'                     => strtoupper(trim($item->invoice_ppn ?? 'TANPA')),
                    'subtotal'                     => 0.0,
                    'discount'                     => 0.0,
                ];
            }

            $qty          = (float) ($item->qty_received ?? $item->qty ?? 0);
            $rawPrice     = (float) ($item->receiving_raw_price ?? $item->order_items_price ?? 0);
            $gross        = $qty * $rawPrice;
            $disc         = (float) ($item->discount ?? 0);
            $extraDisc    = (float) ($item->extra_discount ?? 0);
            $nomDisc      = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
            $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;

            $invoices[$key]['subtotal'] += $gross;
            $invoices[$key]['discount'] += ($nomDisc + $nomExtraDisc);
        }

        // Kalkulasi DPP, PPN, dan Jumlah persis seperti di cetakan faktur penerimaan (printInvoice)
        foreach ($invoices as &$inv) {
            $dpp = round(max(0, $inv['subtotal'] - $inv['discount']));
            $ppnType = $inv['ppn_type'];
            $ppn = 0;
            $jumlah = 0;

            if ($ppnType === 'EXCLUDE') {
                $ppn = floor($dpp * self::PPN);
                $jumlah = $dpp + $ppn;
            } elseif ($ppnType === 'INCLUDE') {
                $jumlah = $dpp;
                $hna = floor($dpp / (1 + self::PPN));
                $ppn = $jumlah - $hna;
                $dpp = $hna;
            } else { // TANPA
                $ppn = 0;
                $jumlah = $dpp;
            }

            $inv['dpp']    = (float) $dpp;
            $inv['ppn']    = (float) $ppn;
            $inv['jumlah'] = (float) $jumlah;
        }
        unset($inv);

        uasort($invoices, fn ($a, $b) => [
            mb_strtolower((string) ($a['creditor_name'] ?? '')),
            mb_strtolower((string) ($a['creditor_code'] ?? '')),
            mb_strtolower((string) ($a['invoice_date'] ?? '')),
            mb_strtolower((string) ($a['invoice_number'] ?? '')),
        ] <=> [
            mb_strtolower((string) ($b['creditor_name'] ?? '')),
            mb_strtolower((string) ($b['creditor_code'] ?? '')),
            mb_strtolower((string) ($b['invoice_date'] ?? '')),
            mb_strtolower((string) ($b['invoice_number'] ?? '')),
        ]);

        $rows = [$tableHeaders];

        $grandDpp           = 0.0;
        $grandPpn           = 0.0;
        $grandTotal         = 0.0;
        $activeSupplierKey  = null;
        $activeSupplierName = '';
        $supplierDpp        = 0.0;
        $supplierPpn        = 0.0;
        $supplierTotal      = 0.0;

        foreach ($invoices as $inv) {
            $supplierKey = (string) ($inv['creditor_code'] ?? '') . '|' . (string) ($inv['creditor_name'] ?? '');
            if ($activeSupplierKey !== null && $supplierKey !== $activeSupplierKey) {
                $rows[] = [
                    '',
                    'SUBTOTAL - ' . $activeSupplierName,
                    '', '', '', '',
                    (float) $supplierDpp,
                    (float) $supplierPpn,
                    (float) $supplierTotal,
                    '', '', ''
                ];
                $supplierDpp = $supplierPpn = $supplierTotal = 0.0;
            }

            $isFirstSupplierInvoice = ($supplierKey !== $activeSupplierKey);
            $activeSupplierKey  = $supplierKey;
            $activeSupplierName = $inv['creditor_name'] ?? 'PBF / Vendor';
            $supplierDpp        += $inv['dpp'];
            $supplierPpn        += $inv['ppn'];
            $supplierTotal      += $inv['jumlah'];

            $tglFaktur  = $inv['invoice_date']
                ? Carbon::parse($inv['invoice_date'])->format('d/m/Y') : '-';
            $tglTerima  = ReceivingDateFilter::format($inv['receiving_date'] ?? null, $inv['receiving_details_created_at'] ?? null);
            $jatuhTempo = $inv['invoice_due']
                ? Carbon::parse($inv['invoice_due'])->format('d/m/Y') : '-';

            $rows[] = [
                $isFirstSupplierInvoice ? ($inv['creditor_name'] ?? '-') : '',
                $isFirstSupplierInvoice ? ($inv['creditor_code'] ?? '-') : '',
                $inv['invoice_number'] ?? '-',
                $tglFaktur,
                $inv['receiving_details_code'] ?? '-',
                $tglTerima,
                (float) $inv['dpp'],
                (float) $inv['ppn'],
                (float) $inv['jumlah'],
                $jatuhTempo,
                $inv['invoice_times'] ? (int) $inv['invoice_times'] : '-',
                $inv['invoice_payment'] ?? ($this->paymentType ? strtoupper($this->paymentType) : '-'),
            ];

            $grandDpp   += $inv['dpp'];
            $grandPpn   += $inv['ppn'];
            $grandTotal += $inv['jumlah'];
        }

        if ($activeSupplierKey !== null) {
            $rows[] = [
                '',
                'SUBTOTAL - ' . $activeSupplierName,
                '', '', '', '',
                (float) $supplierDpp,
                (float) $supplierPpn,
                (float) $supplierTotal,
                '', '', ''
            ];
        }

        $rows[] = [
            'TOTAL',
            '', '', '', '', '',
            (float) $grandDpp,
            (float) $grandPpn,
            (float) $grandTotal,
            '', '', ''
        ];

        return $rows;
    }

    protected function buildRekap(): array
    {
        $items = $this->baseQuery()
            ->select([
                'receiving_details.id as receiving_details_id',
                'order_items.creditor_code',
                'creditors.name as creditor_name',
                'receiving_details.invoice_ppn',
                'receiving_items.qty_received',
                'receiving_items.qty',
                'receiving_items.discount',
                'receiving_items.extra_discount',
                'receiving_items.raw_price as receiving_raw_price',
                'order_items.price as order_items_price',
            ])
            ->orderBy('creditors.name', 'asc')
            ->get();

        $tableHeaders = ['No.', 'Kreditur', 'DPP', 'PPN', 'Jumlah'];

        if ($items->isEmpty()) {
            $rows = [$tableHeaders];
            $emptyRow = array_fill(0, count($tableHeaders), '');
            $emptyRow[0] = 'Tidak ada data untuk periode yang dipilih.';
            $rows[] = $emptyRow;
            return $rows;
        }

        // Group per faktur (receiving_details) terlebih dahulu agar perhitungan DPP & PPN
        // persis sama dengan lembar cetakan faktur penerimaan (printInvoice).
        $invoices = [];
        foreach ($items as $item) {
            $invKey = (string) $item->receiving_details_id;
            if (!isset($invoices[$invKey])) {
                $invoices[$invKey] = [
                    'creditor_code' => $item->creditor_code,
                    'creditor_name' => $item->creditor_name,
                    'ppn_type'      => strtoupper(trim($item->invoice_ppn ?? 'TANPA')),
                    'subtotal'      => 0.0,
                    'discount'      => 0.0,
                ];
            }

            $qty          = (float) ($item->qty_received ?? $item->qty ?? 0);
            $rawPrice     = (float) ($item->receiving_raw_price ?? $item->order_items_price ?? 0);
            $gross        = $qty * $rawPrice;
            $disc         = (float) ($item->discount ?? 0);
            $extraDisc    = (float) ($item->extra_discount ?? 0);
            $nomDisc      = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
            $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;

            $invoices[$invKey]['subtotal'] += $gross;
            $invoices[$invKey]['discount'] += ($nomDisc + $nomExtraDisc);
        }

        // Akumulasikan total per kreditur dari hasil faktur yang sudah selaras
        $creditorsArr = [];
        foreach ($invoices as $inv) {
            $cKey = $inv['creditor_code'] ?? 'OTHER';
            if (!isset($creditorsArr[$cKey])) {
                $creditorsArr[$cKey] = [
                    'creditor_code' => $inv['creditor_code'],
                    'creditor_name' => $inv['creditor_name'],
                    'dpp'           => 0,
                    'ppn'           => 0,
                    'jumlah'        => 0,
                ];
            }

            $dpp = round(max(0, $inv['subtotal'] - $inv['discount']));
            $ppnType = $inv['ppn_type'];
            $ppn = 0;
            $jumlah = 0;

            if ($ppnType === 'EXCLUDE') {
                $ppn = floor($dpp * self::PPN);
                $jumlah = $dpp + $ppn;
            } elseif ($ppnType === 'INCLUDE') {
                $jumlah = $dpp;
                $hna = floor($dpp / (1 + self::PPN));
                $ppn = $jumlah - $hna;
                $dpp = $hna;
            } else { // TANPA
                $ppn = 0;
                $jumlah = $dpp;
            }

            $creditorsArr[$cKey]['dpp']    += $dpp;
            $creditorsArr[$cKey]['ppn']    += $ppn;
            $creditorsArr[$cKey]['jumlah'] += $jumlah;
        }

        // Urutkan alfabetis nama kreditur
        uasort($creditorsArr, fn ($a, $b) => strcasecmp((string) ($a['creditor_name'] ?? ''), (string) ($b['creditor_name'] ?? '')));

        $rows   = [$tableHeaders];
        $no         = 1;
        $grandDpp   = 0.0;
        $grandPpn   = 0.0;
        $grandTotal = 0.0;

        foreach ($creditorsArr as $cred) {
            $rows[] = [
                (int)   $no++,
                        $cred['creditor_name'] ?? '-',
                (float) $cred['dpp'],
                (float) $cred['ppn'],
                (float) $cred['jumlah'],
            ];

            $grandDpp   += $cred['dpp'];
            $grandPpn   += $cred['ppn'];
            $grandTotal += $cred['jumlah'];
        }

        $rows[] = [
            '', 'TOTAL',
            (float) $grandDpp,
            (float) $grandPpn,
            (float) $grandTotal,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7;
        $isRekap      = strtolower($this->selectedType) === 'rekap';

        $lastCol     = $isRekap ? 'E' : 'L';
        $numericCols = $isRekap ? ['C', 'D', 'E'] : ['G', 'H', 'I'];

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);
        }

        // Left-align everything within data range
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        if (!$isRekap) {
            // Center align code, date, and type columns
            foreach (['C', 'D', 'E', 'F', 'J', 'K', 'L'] as $col) {
                $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Text format for invoice and receipt numbers
            $sheet->getStyle("C" . ($dataStartRow + 1) . ":C{$lastRow}")
                ->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
            $sheet->getStyle("E" . ($dataStartRow + 1) . ":E{$lastRow}")
                ->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

            // Vertical cell merging for Col A and Col B per creditor
            $row = $dataStartRow + 1;
            while ($row < $lastRow) {
                $supplierName = trim((string) $sheet->getCell("A{$row}")->getValue());
                $supplierCode = trim((string) $sheet->getCell("B{$row}")->getValue());

                if (stripos($supplierCode, 'SUBTOTAL') === 0) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);
                    $row++;
                    continue;
                }

                if ($supplierName === '' || stripos($supplierName, 'subtotal') === 0 || strtoupper($supplierName) === 'TOTAL') {
                    $row++;
                    continue;
                }

                $startRow = $row;
                $endRow = $row;
                for ($next = $row + 1; $next < $lastRow; $next++) {
                    $nextName = trim((string) $sheet->getCell("A{$next}")->getValue());
                    $nextCode = trim((string) $sheet->getCell("B{$next}")->getValue());
                    if ($nextName !== '' || $nextCode !== '') {
                        break;
                    }
                    $endRow = $next;
                }

                if ($endRow > $startRow) {
                    $sheet->mergeCells("A{$startRow}:A{$endRow}");
                    $sheet->mergeCells("B{$startRow}:B{$endRow}");
                    $sheet->getStyle("A{$startRow}:B{$endRow}")
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_CENTER);
                }
                $row = $endRow + 1;
            }
        }

        // Right-align & number format numeric cols
        foreach ($numericCols as $col) {
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getNumberFormat()->setFormatCode('#,##0');
        }

        // Bold the TOTAL row and double bottom border
        if ($lastRow > $dataStartRow) {
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
                ->getFont()->setBold(true);
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
                ->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_DOUBLE);
        }
    }

    public function columnWidths(): array
    {
        return strtolower($this->selectedType) === 'rekap'
            ? [
                'A' => 5,
                'B' => 30,
                'C' => 20,
                'D' => 20,
                'E' => 20,
            ]
            : [
                'A' => 28, // NM_KREDITUR
                'B' => 15, // KD_KREDITUR
                'C' => 20, // NO_FAKTUR
                'D' => 13, // TGL_FAKTUR
                'E' => 16, // NO_TERIMA
                'F' => 13, // TGL_TERIMA
                'G' => 18, // DPP
                'H' => 18, // PPN
                'I' => 18, // JUMLAH
                'J' => 13, // JTH_TEMPO
                'K' => 13, // WKT_KREDIT
                'L' => 15, // JNS_BAYAR
            ];
    }
}
