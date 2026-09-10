<?php

namespace App\Exports\Orders;

use App\Models\Pharmacies;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersSingleSheetExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $paymentType;
    protected $sheetTitle;

    const PPN = 0.11;

    public function __construct($pharmacyId, $startDate, $endDate, $paymentType = null, $sheetTitle = 'Semua')
    {
        $this->pharmacyId  = $pharmacyId;
        $this->startDate   = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate)->startOfDay();
        $this->endDate     = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate)->endOfDay();
        $this->paymentType = $paymentType;
        $this->sheetTitle  = $sheetTitle;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);

        $reportTitle = 'Laporan Data Pembelian';
        if ($this->paymentType !== null) {
            $reportTitle .= ' (' . strtoupper($this->sheetTitle) . ')';
        } else {
            $reportTitle .= ' (SEMUA)';
        }

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            [$reportTitle],
            ['Tanggal : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        return array_merge($header, $this->buildBody());
    }

    private function buildBody(): array
    {
        $targetPharmacyIds = in_array((int) $this->pharmacyId, [1, 6, 9])
            ? [9, 1]
            : [(int) $this->pharmacyId];

        $query = DB::table('receiving_items')
            ->join('receiving_details', 'receiving_details.id', '=', 'receiving_items.receiving_details_id')
            ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('creditors', 'creditors.code', '=', 'order_items.creditor_code')
            ->whereIn('receiving.pharmacy_id', $targetPharmacyIds)
            ->whereNotNull('receiving_items.batches_id')
            ->whereBetween('receiving_details.created_at', [$this->startDate, $this->endDate]);

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

        $items = $query->select([
                'receiving_items.id',
                'receiving_items.qty_received',
                'receiving_items.qty',
                'receiving_items.discount',
                'receiving_items.extra_discount',
                'receiving_items.total',
                'receiving_items.raw_price as receiving_raw_price',
                'receiving_items.expired_date',
                'receiving_details.receiving_id',
                'receiving_details.receiving_details_code',
                'receiving_details.invoice_number',
                'receiving_details.invoice_date',
                'receiving_details.invoice_due',
                'receiving_details.invoice_times',
                'receiving_details.invoice_payment',
                'receiving_details.invoice_ppn',
                'receiving.updated_at as receiving_updated_at',
                'receiving_details.created_at as receiving_details_created_at',
                'order_items.creditor_code',
                'order_items.price as order_items_price',
                'order_items.pack as order_pack',
                'medicines.code as medicine_code',
                'medicines.name as medicine_name',
                'medicines.packaging as medicine_packaging',
                'medicines.unit as medicine_unit',
                'creditors.name as creditor_name',
            ])
            ->orderBy('receiving.updated_at', 'asc')
            ->orderBy('receiving_items.id', 'asc')
            ->get();

        $tableHeaders = [
            'ID',
            'No Terima',
            'Tgl Terima',
            'Nama Kreditur',
            'Kode Kreditur',
            'No Faktur',
            'Tgl Faktur',
            'DPP',
            'PPN',
            'Jatuh Tempo',
            'Waktu Kredit',
            'Jenis Bayar',
            'Kode Obat',
            'Nama Obat',
            'Qty Beli',
            'Kemasan',
            'Utuh',
            'HNA',
            'Harga PPN',
            'Diskon',
            'Extra Diskon',
            'Jumlah',
            'Expired',
        ];

        if ($items->isEmpty()) {
            $rows = [$tableHeaders];
            $emptyRow = array_fill(0, count($tableHeaders), '');
            $emptyRow[0] = 'Tidak ada data untuk periode yang dipilih.';
            $rows[] = $emptyRow;
            return $rows;
        }

        $rows = [$tableHeaders];

        $grandDpp = 0;
        $grandPpn = 0;
        $grandTotal = 0;

        foreach ($items as $item) {
            $qty        = (float) ($item->qty_received ?? 0);
            $rawPrice   = (float) ($item->receiving_raw_price ?? $item->order_items_price ?? 0);
            $gross      = $qty * $rawPrice;

            $disc       = (float) ($item->discount ?? 0);
            $extraDisc  = (float) ($item->extra_discount ?? 0);

            $nomDisc      = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
            $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;

            $dpp = max(0, $gross - $nomDisc - $nomExtraDisc);

            $ppnType = strtoupper(trim($item->invoice_ppn ?? 'TANPA'));
            $ppn = 0;
            if ($ppnType === 'EXCLUDE') {
                $ppn = floor($dpp * self::PPN);
            } else if ($ppnType === 'INCLUDE') {
                $ppn = floor($dpp - ($dpp / (1 + self::PPN)));
                $dpp = $dpp - $ppn;
            }

            $jumlah = $dpp + $ppn;
            $hargaPpn = round($rawPrice * (1 + self::PPN));

            $tglTerima = $item->receiving_details_created_at
                ? Carbon::parse($item->receiving_details_created_at)->format('d/m/Y')
                : '-';
            $tglFaktur = $item->invoice_date
                ? Carbon::parse($item->invoice_date)->format('d/m/Y')
                : '-';
            $jatuhTempo = $item->invoice_due
                ? Carbon::parse($item->invoice_due)->format('d/m/Y')
                : '-';
            $expired = $item->expired_date
                ? Carbon::parse($item->expired_date)->format('d/m/Y')
                : '-';

            $packaging = $item->medicine_packaging ?: ($item->medicine_unit ?: '-');

            $rows[] = [
                $item->id,
                $item->receiving_details_code ?? '-',
                $tglTerima,
                $item->creditor_name ?? '-',
                $item->creditor_code ?? '-',
                $item->invoice_number ?? '-',
                $tglFaktur,
                $dpp,
                $ppn,
                $jatuhTempo,
                $item->invoice_times ?? '-',
                $item->invoice_payment ?? '-',
                $item->medicine_code ?? '-',
                $item->medicine_name ?? '-',
                (int) ($item->qty_received ?? 0),
                $packaging,
                $item->order_pack ?? '-',
                $rawPrice,
                $hargaPpn,
                $item->discount ?? 0,
                $item->extra_discount ?? 0,
                $jumlah,
                $expired,
            ];

            $grandDpp += $dpp;
            $grandPpn += $ppn;
            $grandTotal += $jumlah;
        }

        $rows[] = [
            '',
            '',
            '',
            '',
            '',
            '',
            'TOTAL',
            $grandDpp,
            $grandPpn,
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
            $grandTotal,
            '',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();
        $dataStartRow = 7;

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        $sheet
            ->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()
            ->setBold(true);

        $sheet
            ->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);
        }

        // Left-align everything
        $sheet
            ->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Right-align & number format: H=DPP, I=PPN, R=HNA, S=HargaPPN, T=Diskon, U=ExtraDiskon, V=Jumlah
        foreach (['H', 'I', 'R', 'S', 'T', 'U', 'V'] as $col) {
            $sheet
                ->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet
                ->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 15,
            'C' => 13,
            'D' => 25,
            'E' => 15,
            'F' => 18,
            'G' => 13,
            'H' => 18,
            'I' => 18,
            'J' => 13,
            'K' => 13,
            'L' => 14,
            'M' => 15,
            'N' => 35,
            'O' => 10,
            'P' => 18,
            'Q' => 10,
            'R' => 15,
            'S' => 15,
            'T' => 10,
            'U' => 13,
            'V' => 18,
            'W' => 13,
        ];
    }
}
