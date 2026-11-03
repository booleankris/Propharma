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
            ['Tanggal Terima : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        return array_merge($header, $this->buildBody());
    }

    private function buildBody(): array
    {
        $targetPharmacyIds = in_array((int) $this->pharmacyId, [1, 6, 9])
            ? [9, 1]
            : [(int) $this->pharmacyId];

        $items = $this->purchaseItems($targetPharmacyIds);

        $tableHeaders = [
            'ID', 'No Terima', 'Tgl Terima', 'Nama Kreditur', 'Kode Kreditur', 'No Faktur', 'Tgl Faktur',
            'DPP', 'PPN', 'Jatuh Tempo', 'Waktu Kredit', 'Jenis Bayar', 'Kode Obat', 'Nama Obat', 'Pabrik',
            'Qty Beli', 'Kemasan', 'Utuh', 'HNA', 'Jumlah HNA', 'Harga PPN', 'Diskon', 'Value Disc', 'Extra Diskon',
            'Value Extra Disc', '% Disc Pembelian', 'Jumlah', 'Batch', 'Expired',
        ];

        if ($items->isEmpty()) {
            $rows = [$tableHeaders];
            $emptyRow = array_fill(0, count($tableHeaders), '');
            $emptyRow[0] = 'Tidak ada data untuk periode yang dipilih.';
            $rows[] = $emptyRow;
            return $rows;
        }

        $rows = [$tableHeaders];
        $grandDpp = $grandPpn = $grandGross = $grandNomDisc = $grandNomExtraDisc = $grandTotal = 0;

        foreach ($items as $item) {
            $amounts = $this->calculateAmounts($item);
            $qty = $amounts['qty'];
            $rawPrice = $amounts['rawPrice'];
            $gross = $amounts['gross'];
            $nomDisc = $amounts['nomDisc'];
            $nomExtraDisc = $amounts['nomExtraDisc'];
            $persenDisc = $amounts['persenDisc'];
            $dpp = $amounts['dpp'];
            $ppn = $amounts['ppn'];
            $jumlah = $amounts['jumlah'];
            $hargaPpn = round($rawPrice * (1 + self::PPN));

            $tglTerima = ReceivingDateFilter::format($item->receiving_date, $item->receiving_details_created_at);
            $tglFaktur = $item->invoice_date ? Carbon::parse($item->invoice_date)->format('d/m/Y') : '-';
            $jatuhTempo = $item->invoice_due ? Carbon::parse($item->invoice_due)->format('d/m/Y') : '-';
            $expired = $item->expired_date ? Carbon::parse($item->expired_date)->format('d/m/Y') : '-';
            $batch = !empty($item->receiving_batch) ? (string) $item->receiving_batch : (!empty($item->batch_name) ? (string) $item->batch_name : '-');
            $packaging = $item->medicine_packaging ?: ($item->medicine_unit ?: '-');

            $rows[] = [
                $item->id, $item->receiving_details_code ?? '-', $tglTerima, $item->creditor_name ?? '-',
                $item->creditor_code ?? '-', $item->invoice_number ?? '-', $tglFaktur, $dpp, $ppn, $jatuhTempo,
                $item->invoice_times ?? '-', $item->invoice_payment ?? '-', $item->medicine_code ?? '-',
                $item->medicine_name ?? '-', $item->factory_name ?? '-', (int) $qty, $packaging,
                $item->order_pack ?? '-', $rawPrice, $gross, $hargaPpn, $item->discount ?? 0, round($nomDisc, 2),
                $item->extra_discount ?? 0, round($nomExtraDisc, 2), $persenDisc, $jumlah, $batch, $expired,
            ];
            $grandDpp += $dpp;
            $grandPpn += $ppn;
            $grandGross += $gross;
            $grandNomDisc += $nomDisc;
            $grandNomExtraDisc += $nomExtraDisc;
            $grandTotal += $jumlah;
        }

        $grandPersenDisc = $grandGross > 0 ? round((($grandNomDisc + $grandNomExtraDisc) / $grandGross) * 100, 2) : 0;

        $rows[] = [
            '', '', '', '', '', '', 'TOTAL',
            $grandDpp, $grandPpn, '', '', '', '', '', '',
            '', '', '', '', $grandGross, '',
            '', round($grandNomDisc, 2), '', round($grandNomExtraDisc, 2),
            $grandPersenDisc, $grandTotal, '', ''
        ];
        return $rows;
    }

    /**
     * Purchase aggregates for Kinerja Omzet, calculated from the exact item
     * query and amount rules used to build this export.
     */
    public function financeRows(array $targetPharmacyIds): array
    {
        return $this->purchaseItems($targetPharmacyIds)->map(function ($item) {
            $amounts = $this->calculateAmounts($item);
            return [
                'date' => ReceivingDateFilter::format($item->receiving_date, $item->receiving_details_created_at),
                'payment' => strtoupper(trim((string) ($item->invoice_payment ?? ''))),
                'total' => $amounts['jumlah'],
                'ppn' => $amounts['ppn'],
            ];
        })->all();
    }

    private function purchaseItems(array $targetPharmacyIds)
    {

        $query = DB::table('receiving_items')
            ->join('receiving_details', 'receiving_details.id', '=', 'receiving_items.receiving_details_id')
            ->join('receiving', 'receiving.id', '=', 'receiving_details.receiving_id')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('creditors', 'creditors.code', '=', 'order_items.creditor_code')
            ->leftJoin('batches', 'batches.id', '=', 'receiving_items.batches_id')
            ->leftJoin('factories', 'factories.id', '=', 'medicines.factory_id')
            ->whereIn('receiving.pharmacy_id', $targetPharmacyIds)
            ->whereNotNull('receiving_items.batches_id');
        ReceivingDateFilter::apply($query, $this->startDate, $this->endDate);

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

        return $query->select([
                'receiving_items.id',
                'receiving_items.qty_received',
                'receiving_items.qty',
                'receiving_items.discount',
                'receiving_items.extra_discount',
                'receiving_items.total',
                'receiving_items.raw_price as receiving_raw_price',
                'receiving_items.batch as receiving_batch',
                'batches.name as batch_name',
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
                'receiving.date as receiving_date',
                'receiving_details.created_at as receiving_details_created_at',
                'order_items.creditor_code',
                'order_items.price as order_items_price',
                'order_items.pack as order_pack',
                'medicines.code as medicine_code',
                'medicines.name as medicine_name',
                'medicines.packaging as medicine_packaging',
                'medicines.unit as medicine_unit',
                'factories.name as factory_name',
                'creditors.name as creditor_name',
            ])
            ->orderBy('receiving.updated_at', 'asc')
            ->orderBy('receiving_items.id', 'asc')
            ->get();
    }

    private function calculateAmounts(object $item): array
    {
        $qty = (float) ($item->qty_received ?? $item->qty ?? 0);
        $rawPrice = (float) ($item->receiving_raw_price ?? $item->order_items_price ?? 0);
        $gross = $qty * $rawPrice;
        $disc = (float) ($item->discount ?? 0);
        $extraDisc = (float) ($item->extra_discount ?? 0);
        $nomDisc = ($disc <= 100 && $disc > 0) ? ($gross * $disc / 100) : $disc;
        $nomExtraDisc = ($extraDisc <= 100 && $extraDisc > 0) ? ($gross * $extraDisc / 100) : $extraDisc;
        $totalDisc = $nomDisc + $nomExtraDisc;
        $persenDisc = $gross > 0 ? round(($totalDisc / $gross) * 100, 2) : 0;
        $dpp = max(0, $gross - $nomDisc - $nomExtraDisc);
        $ppn = 0;
        $ppnType = strtoupper(trim($item->invoice_ppn ?? 'TANPA'));
        if ($ppnType === 'EXCLUDE') {
            $ppn = floor($dpp * self::PPN);
        } elseif ($ppnType === 'INCLUDE') {
            $ppn = floor($dpp - ($dpp / (1 + self::PPN)));
            $dpp -= $ppn;
        }
        return compact('qty', 'rawPrice', 'gross', 'nomDisc', 'nomExtraDisc', 'totalDisc', 'persenDisc', 'dpp', 'ppn') + ['jumlah' => $dpp + $ppn];
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

        // Right-align & number format: 
        // H=DPP, I=PPN, S=HNA, T=Jumlah HNA, U=Harga PPN, V=Diskon, W=Value Disc, X=Extra Diskon, Y=Value Extra Disc, AA=Jumlah
        foreach (['H', 'I', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'AA'] as $col) {
            $sheet
                ->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet
                ->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }

        // Percentage format for Persentase Diskon (Col Z)
        $sheet
            ->getStyle("Z{$dataStartRow}:Z{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet
            ->getStyle("Z" . ($dataStartRow + 1) . ":Z{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('0.00"%"');

        // Text format for Batch (Col AB) so leading zeros or scientific-like strings are preserved
        $sheet
            ->getStyle("AB" . ($dataStartRow + 1) . ":AB{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
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
            'O' => 25,
            'P' => 10,
            'Q' => 18,
            'R' => 10,
            'S' => 15,
            'T' => 18,
            'U' => 15,
            'V' => 10,
            'W' => 15,
            'X' => 13,
            'Y' => 15,
            'Z' => 18,
            'AA' => 18,
            'AB' => 16,
            'AC' => 13,
        ];
    }
}
