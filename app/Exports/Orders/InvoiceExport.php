<?php

namespace App\Exports\Orders;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
 
class InvoiceExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $selectedType;
 
    const PPN = 0.11;
 
    public function __construct($pharmacyId, $startDate, $endDate, $selectedType = 'Detail')
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->selectedType = $selectedType;
    }
 
    public function array(): array
    {
        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);
 
        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Data Faktur Pembelian (' . ucfirst(strtolower($this->selectedType)) . ')'],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];
 
        $body = $this->selectedType === 'rekap'
            ? $this->buildRekap()
            : $this->buildDetail();
 
        return array_merge($header, $body);
    }
 
    // ── Base query shared by both types ──────────────────────────────────────
    private function baseQuery()
    {
        return DB::table('receiving_items')
            ->join('receiving_details', 'receiving_details.id', '=', 'receiving_items.receiving_details_id')
            ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('creditors', 'creditors.code', '=', 'order_items.creditor_code')
            ->where('receiving.pharmacy_id', $this->pharmacyId)
            ->whereBetween('receiving.updated_at', [$this->startDate, $this->endDate]);
    }
 
    // ── DETAIL: one row per invoice_number ───────────────────────────────────
    private function buildDetail(): array
    {
        $invoices = $this->baseQuery()
            ->select([
                'receiving_details.invoice_number',
                'receiving_details.invoice_date',
                'receiving_details.invoice_due',
                'receiving_details.invoice_times',
                'receiving_details.receiving_id',
                'receiving.code as receiving_code',
                'receiving.updated_at as receiving_updated_at',
                'order_items.creditor_code',
                'creditors.name as creditor_name',
                DB::raw('SUM(receiving_items.total) as dpp_raw'),
            ])
            ->groupBy([
                'receiving_details.invoice_number',
                'receiving_details.invoice_date',
                'receiving_details.invoice_due',
                'receiving_details.invoice_times',
                'receiving_details.receiving_id',
                'receiving.code',
                'receiving.updated_at',
                'order_items.creditor_code',
                'creditors.name',
            ])
            ->orderBy('receiving.updated_at', 'asc')
            ->orderBy('receiving_details.invoice_number', 'asc')
            ->get();
 
        if ($invoices->isEmpty()) {
            return [['Tidak ada data untuk periode yang dipilih.']];
        }
 
        $rows   = [];
        $rows[] = [
            'No.', 'Nama Kreditur', 'Kode Kreditur', 'No Faktur',
            'Tgl Faktur', 'No Terima', 'Tgl Terima',
            'DPP', 'PPN', 'Jumlah', 'Jatuh Tempo', 'Waktu Kredit',
        ];
 
        $no         = 1;
        $grandDpp   = 0.0;
        $grandPpn   = 0.0;
        $grandTotal = 0.0;
 
        foreach ($invoices as $inv) {
            $dpp    = (float) ($inv->dpp_raw ?? 0);
            $ppn    = round($dpp * self::PPN);
            $jumlah = round($dpp + $ppn);
 
            $tglFaktur  = $inv->invoice_date
                ? Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-';
            $tglTerima  = $inv->receiving_updated_at
                ? Carbon::parse($inv->receiving_updated_at)->format('d/m/Y') : '-';
            $jatuhTempo = $inv->invoice_due
                ? Carbon::parse($inv->invoice_due)->format('d/m/Y') : '-';
 
            $rows[] = [
                (int)   $no++,
                        $inv->creditor_name  ?? '-',
                        $inv->creditor_code  ?? '-',
                        $inv->invoice_number ?? '-',
                        $tglFaktur,
                        $inv->receiving_code ?? '-',
                        $tglTerima,
                (float) $dpp,
                (float) $ppn,
                (float) $jumlah,
                        $jatuhTempo,
                        $inv->invoice_times  ?? '-',
            ];
 
            $grandDpp   += $dpp;
            $grandPpn   += $ppn;
            $grandTotal += $jumlah;
        }
 
        $rows[] = [
            '', '', '', '', '', '', 'TOTAL',
            (float) $grandDpp,
            (float) $grandPpn,
            (float) $grandTotal,
            '', '',
        ];
 
        return $rows;
    }
 
    // ── REKAP: one row per creditor ───────────────────────────────────────────
    private function buildRekap(): array
    {
        $creditors = $this->baseQuery()
            ->select([
                'order_items.creditor_code',
                'creditors.name as creditor_name',
                DB::raw('SUM(receiving_items.total) as dpp_raw'),
            ])
            ->groupBy([
                'order_items.creditor_code',
                'creditors.name',
            ])
            ->orderBy('creditors.name', 'asc')
            ->get();
 
        if ($creditors->isEmpty()) {
            return [['Tidak ada data untuk periode yang dipilih.']];
        }
 
        $rows   = [];
        $rows[] = ['No.', 'Kreditur', 'DPP', 'PPN', 'Jumlah'];
 
        $no         = 1;
        $grandDpp   = 0.0;
        $grandPpn   = 0.0;
        $grandTotal = 0.0;
 
        foreach ($creditors as $cred) {
            $dpp    = (float) ($cred->dpp_raw ?? 0);
            $ppn    = round($dpp * self::PPN);
            $jumlah = round($dpp + $ppn);
 
            $rows[] = [
                (int)   $no++,
                        $cred->creditor_name ?? '-',
                (float) $dpp,
                (float) $ppn,
                (float) $jumlah,
            ];
 
            $grandDpp   += $dpp;
            $grandPpn   += $ppn;
            $grandTotal += $jumlah;
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
 
        // Hardcode lastCol per type to avoid extra borders from ghost columns
        $lastCol     = $this->selectedType === 'rekap' ? 'E' : 'L';
        $numericCols = $this->selectedType === 'rekap'
            ? ['C', 'D', 'E']
            : ['H', 'I', 'J'];
 
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
 
        // Left-align everything within the data range
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
 
        // Right-align & number format numeric cols
        foreach ($numericCols as $col) {
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getNumberFormat()->setFormatCode('#,##0');
        }
    }
 
    public function columnWidths(): array
    {
        return $this->selectedType === 'rekap'
            ? [
                'A' => 5,
                'B' => 30,
                'C' => 20,
                'D' => 20,
                'E' => 20,
            ]
            : [
                'A' => 5,
                'B' => 25,
                'C' => 15,
                'D' => 20,
                'E' => 13,
                'F' => 15,
                'G' => 13,
                'H' => 18,
                'I' => 18,
                'J' => 18,
                'K' => 13,
                'L' => 13,
            ];
    }
 
    public function title(): string
    {
        return 'DataFaktur';
    }
}