<?php

namespace App\Exports\Orders;

use App\Models\Pharmacies;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PurchasePaymentExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $reportType; // 'Konsinyasi', 'Tunai', 'Jatuh Tempo'
    protected $supplier;

    const PPN = 0.11;

    public function __construct($pharmacyId, $startDate, $endDate, $reportType = 'Tunai', $supplier = null)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate = Carbon::parse($startDate)->startOfDay();
        $this->endDate = Carbon::parse($endDate)->endOfDay();
        $this->reportType = $reportType;
        $this->supplier = $supplier;
    }

    private function getTargetPharmacyIds(): array
    {
        $id = (int) $this->pharmacyId;
        if (in_array($id, [1, 6, 9])) {
            return [9, 1];
        }
        return [$id];
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['LAPORAN PEMBELIAN (' . strtoupper($this->reportType) . ')'],
            ['TANGGAL : ' . $this->startDate->format('d/m/Y') . '  s/d  ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        return array_merge($header, $this->buildBody());
    }

    private function baseQuery()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        $query = DB::table('receiving_items')
            ->join('receiving_details', 'receiving_details.id', '=', 'receiving_items.receiving_details_id')
            ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('creditors', 'creditors.code', '=', 'order_items.creditor_code')
            ->whereIn('receiving.pharmacy_id', $targetPharmacyIds)
            ->whereNotNull('receiving_items.batches_id');

        // Filter by Report Type
        if ($this->reportType === 'Konsinyasi') {
            $query->where('receiving_details.invoice_payment', 'KONSINYASI')
                ->whereBetween('receiving_details.created_at', [$this->startDate, $this->endDate]);
        } elseif ($this->reportType === 'Tunai') {
            $query->where('receiving_details.invoice_payment', 'TUNAI')
                ->whereBetween('receiving_details.created_at', [$this->startDate, $this->endDate]);
        } elseif ($this->reportType === 'Jatuh Tempo') {
            // Jatuh Tempo filters by invoice_due date range
            $query->where('receiving_details.invoice_payment', '!=', 'TUNAI')
                ->where(function ($q) {
                    $q->whereBetween('receiving_details.invoice_due', [
                        $this->startDate->format('Y-m-d'),
                        $this->endDate->format('Y-m-d'),
                    ])->orWhere(function ($sub) {
                        $sub->whereNull('receiving_details.invoice_due')
                            ->whereBetween('receiving_details.created_at', [$this->startDate, $this->endDate]);
                    });
                });
        } else {
            $query->whereBetween('receiving_details.created_at', [$this->startDate, $this->endDate]);
        }

        // Filter by Creditor / PBF (if selected)
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

    private function buildBody(): array
    {
        $items = $this->baseQuery()
            ->select([
                'receiving_details.invoice_number',
                'receiving_details.invoice_date',
                'receiving_details.invoice_due',
                'receiving_details.invoice_times',
                'receiving_details.invoice_payment',
                'receiving_details.invoice_ppn',
                'receiving_details.receiving_id',
                'receiving_details.receiving_details_code',
                'receiving.updated_at as receiving_updated_at',
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

        $isJatuhTempo = ($this->reportType === 'Jatuh Tempo');

        // Column Headers
        $tableHeaders = $isJatuhTempo
            ? ['NM_KREDITUR', 'NO_FAKTUR', 'TGL_FAKTUR', 'NO_TERIMA', 'DPP', 'PPN', 'JUMLAH', 'JTH_TEMPO', 'WKT_KREDIT', 'TGL_TERIMA']
            : ['NM_KREDITUR', 'KD_KREDITUR', 'NO_FAKTUR', 'TGL_FAKTUR', 'NO_TERIMA', 'TGL_TERIMA', 'DPP', 'PPN', 'JUMLAH', 'JTH_TEMPO', 'WKT_KREDIT', 'JNS_BAYAR'];

        if ($items->isEmpty()) {
            return [
                $tableHeaders,
                ['Tidak ada data untuk periode yang dipilih.'],
            ];
        }

        $invoices = [];
        foreach ($items as $item) {
            $key = ($item->invoice_number ?? 'NONUM') . '_' . $item->receiving_id;
            if (!isset($invoices[$key])) {
                $invoices[$key] = [
                    'invoice_number' => $item->invoice_number,
                    'invoice_date' => $item->invoice_date,
                    'invoice_due' => $item->invoice_due,
                    'invoice_times' => $item->invoice_times,
                    'invoice_payment' => $item->invoice_payment,
                    'receiving_details_code' => $item->receiving_details_code,
                    'receiving_updated_at' => $item->receiving_updated_at,
                    'receiving_details_created_at' => $item->receiving_details_created_at,
                    'creditor_code' => $item->creditor_code,
                    'creditor_name' => $item->creditor_name,
                    'dpp' => 0,
                    'ppn' => 0,
                    'jumlah' => 0,
                ];
            }

            $qty = (float) ($item->qty_received ?? $item->qty ?? 0);
            $rawPrice = (float) ($item->receiving_raw_price ?? $item->order_items_price ?? 0);
            $gross = $qty * $rawPrice;
            $disc = (float) ($item->discount ?? 0);
            $extraDisc = (float) ($item->extra_discount ?? 0);
            $nomDisc = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
            $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;

            $dpp = max(0, $gross - $nomDisc - $nomExtraDisc);
            $ppnType = strtoupper(trim($item->invoice_ppn ?? 'TANPA'));
            $ppn = 0;

            if ($ppnType === 'EXCLUDE') {
                $ppn = floor($dpp * self::PPN);
            } elseif ($ppnType === 'INCLUDE') {
                $ppn = floor($dpp - ($dpp / (1 + self::PPN)));
                $dpp = $dpp - $ppn;
            }

            $jumlah = $dpp + $ppn;
            $invoices[$key]['dpp'] += $dpp;
            $invoices[$key]['ppn'] += $ppn;
            $invoices[$key]['jumlah'] += $jumlah;
        }

        $rows = [];
        $rows[] = $tableHeaders;

        $grandDpp = 0.0;
        $grandPpn = 0.0;
        $grandTotal = 0.0;

        foreach ($invoices as $inv) {
            $tglFaktur = $inv['invoice_date']
                ? Carbon::parse($inv['invoice_date'])->format('d/m/Y') : '-';
            $tglTerima = $inv['receiving_details_created_at']
                ? Carbon::parse($inv['receiving_details_created_at'])->format('d/m/Y') : '-';
            $jatuhTempo = $inv['invoice_due']
                ? Carbon::parse($inv['invoice_due'])->format('d/m/Y') : '-';

            if ($isJatuhTempo) {
                // Jatuh Tempo: NM_KREDITUR | NO_FAKTUR | TGL_FAKTUR | NO_TERIMA | DPP | PPN | JUMLAH | JTH_TEMPO | WKT_KREDIT | TGL_TERIMA
                $rows[] = [
                    $inv['creditor_name'] ?? '-',
                    $inv['invoice_number'] ?? '-',
                    $tglFaktur,
                    $inv['receiving_details_code'] ?? '-',
                    (float) $inv['dpp'],
                    (float) $inv['ppn'],
                    (float) $inv['jumlah'],
                    $jatuhTempo,
                    $inv['invoice_times'] ? (int) $inv['invoice_times'] : '-',
                    $tglTerima,
                ];
            } else {
                // Konsinyasi / Tunai: NM_KREDITUR | KD_KREDITUR | NO_FAKTUR | TGL_FAKTUR | NO_TERIMA | TGL_TERIMA | DPP | PPN | JUMLAH | JTH_TEMPO | WKT_KREDIT | JNS_BAYAR
                $rows[] = [
                    $inv['creditor_name'] ?? '-',
                    $inv['creditor_code'] ?? '-',
                    $inv['invoice_number'] ?? '-',
                    $tglFaktur,
                    $inv['receiving_details_code'] ?? '-',
                    $tglTerima,
                    (float) $inv['dpp'],
                    (float) $inv['ppn'],
                    (float) $inv['jumlah'],
                    $jatuhTempo,
                    $inv['invoice_times'] ? (int) $inv['invoice_times'] : '-',
                    $inv['invoice_payment'] ?? strtoupper($this->reportType),
                ];
            }

            $grandDpp += $inv['dpp'];
            $grandPpn += $inv['ppn'];
            $grandTotal += $inv['jumlah'];
        }

        // Total Row
        if ($isJatuhTempo) {
            $rows[] = [
                'TOTAL', '', '', '',
                (float) $grandDpp,
                (float) $grandPpn,
                (float) $grandTotal,
                '', '', '',
            ];
        } else {
            $rows[] = [
                'TOTAL', '', '', '', '', '',
                (float) $grandDpp,
                (float) $grandPpn,
                (float) $grandTotal,
                '', '', '',
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $dataStartRow = 7;

        $isJatuhTempo = ($this->reportType === 'Jatuh Tempo');
        $lastCol = $isJatuhTempo ? 'J' : 'L';
        $numericCols = $isJatuhTempo
            ? ['E', 'F', 'G']
            : ['G', 'H', 'I'];

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

        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        foreach ($numericCols as $col) {
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getNumberFormat()->setFormatCode('#,##0');
        }

        // Bold the TOTAL row
        if ($lastRow > $dataStartRow) {
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")
                ->getFont()->setBold(true);
        }
    }

    public function columnWidths(): array
    {
        if ($this->reportType === 'Jatuh Tempo') {
            return [
                'A' => 28, // NM_KREDITUR
                'B' => 20, // NO_FAKTUR
                'C' => 13, // TGL_FAKTUR
                'D' => 16, // NO_TERIMA
                'E' => 18, // DPP
                'F' => 18, // PPN
                'G' => 18, // JUMLAH
                'H' => 13, // JTH_TEMPO
                'I' => 13, // WKT_KREDIT
                'J' => 13, // TGL_TERIMA
            ];
        }

        return [
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

    public function title(): string
    {
        return preg_replace('/[^A-Za-z0-9]/', '', $this->reportType);
    }
}
