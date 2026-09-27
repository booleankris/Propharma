<?php

namespace App\Exports\Export;

use App\Models\MedicineTransferItems;
use App\Models\Order;
use App\Models\OrderItems;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrdersExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle, WithColumnFormatting
{
    protected $id;
    protected $order;
    protected $dataRowCount = 0;
    protected $summaryRowStart = 0;

    public function __construct($id)
    {
        $this->id = $id;
        $this->order = Order::with(['pharmacy', 'user'])->find($id);
    }

    public function title(): string
    {
        return $this->order ? 'SP - ' . $this->order->code : 'Surat Pesanan';
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'C' => '#,##0',
            'E' => '#,##0',
            'F' => '#,##0',
            'I' => '#,##0',
        ];
    }

    public function array(): array
    {
        $order = $this->order;
        $pharmacy = $order?->pharmacy;
        $pharmacyName = $pharmacy ? strtoupper($pharmacy->name) : 'APOTEK PROPHARMA';
        $orderCode = $order?->code ?? '-';
        $orderDate = $order?->date ? (string) $order->date : Carbon::now()->format('d/m/Y');
        $downloadTime = Carbon::now()->translatedFormat('d F Y H:i');

        $items = OrderItems::query()
            ->select('order_items.*')
            ->leftJoin('creditors', 'creditors.code', '=', 'order_items.creditor_code')
            ->with([
                'medicines.creditors',
                'medicines',
                'creditors',
            ])
            ->where('order_id', $this->id)
            ->orderByRaw("CASE WHEN creditors.name IS NOT NULL AND creditors.name != '' THEN 0 ELSE 1 END ASC")
            ->orderBy('creditors.name', 'asc')
            ->orderBy('order_items.id', 'asc')
            ->get();

        $this->dataRowCount = $items->count();

        // 1. HEADER DOKUMEN (Baris 1 - 5)
        $rows = [
            ['SURAT PEMESANAN OBAT (ORDER PEMBELIAN)'],
            ["Apotek / Cabang : {$pharmacyName}"],
            ["No. Pesanan / SP : {$orderCode}  |  Tanggal: {$orderDate}"],
            ["Waktu Cetak    : {$downloadTime}  |  Total: {$this->dataRowCount} Item Obat"],
            [''], // Baris kosong pemisah
            // Baris 6: HEADER TABEL (9 Kolom)
            [
                'NO',
                'NAMA OBAT',
                'QTY',
                'KEMASAN',
                'HRG_HNA',
                'JUMLAH',
                'KREDITUR',
                'DISKON',
                'SISA',
            ],
        ];

        // 2. DATA ROWS
        $subtotalHna = 0;
        $no = 1;

        $targetPharmacyId = $order?->pharmacy_id ?? (function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1);

        // Ambil stok etalase per cabang dari medicine_transfer_items
        $medicineIds = $items->pluck('medicine_id')->filter()->unique()->toArray();
        $counterStocks = [];
        if (!empty($medicineIds)) {
            $counterStocks = MedicineTransferItems::join('batches', 'batches.id', '=', 'medicine_transfer_items.batches_id')
                ->where('batches.pharmacy_id', $targetPharmacyId)
                ->whereIn('batches.medicine_id', $medicineIds)
                ->where('medicine_transfer_items.status', 1)
                ->where(function ($q) {
                    $q->whereNull('medicine_transfer_items.source_type')
                      ->orWhere('medicine_transfer_items.source_type', '!=', 'retur_gudang');
                })
                ->groupBy('batches.medicine_id')
                ->select('batches.medicine_id', DB::raw('COALESCE(SUM(medicine_transfer_items.qty), 0) as total_qty'))
                ->pluck('total_qty', 'batches.medicine_id')
                ->toArray();
        }

        if ($items->isEmpty()) {
            $rows[] = ['-', 'Tidak ada item obat dalam pemesanan ini.', 0, '-', 0, 0, '-', '-', 0];
        } else {
            foreach ($items as $item) {
                // Harga satuan wajib dari medicines.raw_price (fallback ke item->price jika kosong)
                $rawPrice = (float) ($item->medicines?->raw_price ?? $item->price ?? 0);
                $qty = (float) ($item->quantity ?? 0);
                $totalRow = (float) ($qty * $rawPrice);
                $subtotalHna += $totalRow;

                $credCode = $item->creditor_code ?? optional($item->creditors)->code;
                $creditorName = $item->creditors?->name;

                if (empty($creditorName) || $creditorName === 'Belum Dipilih' || empty($credCode)) {
                    $creditorName = '-';
                    $discFormatted = '-';
                } else {
                    $medCred = $item->medicines?->creditors?->firstWhere('code', $credCode);
                    $disc = $medCred?->pivot?->discount ?? 0;
                    $discFormatted = $disc ? ($disc == (int) $disc ? (int) $disc : $disc) . '%' : '0%';
                }

                // Sisa stok riil etalase cabang dari medicine_transfer_items
                $sisaStock = isset($counterStocks[$item->medicine_id]) ? (int) $counterStocks[$item->medicine_id] : 0;

                $rows[] = [
                    $no++,
                    (string) ($item->medicines?->name ?? '-'),
                    $qty,
                    (string) ($item->medicines?->packaging ?? '-'),
                    $rawPrice,
                    $totalRow,
                    $creditorName,
                    $discFormatted,
                    $sisaStock,
                ];
            }
        }

        // 3. FOOTER RINGKASAN KEUANGAN
        $ppn = floor($subtotalHna * 0.11);
        $grandTotal = $subtotalHna + $ppn;

        $this->summaryRowStart = count($rows) + 1;

        $rows[] = ['', '', '', 'SUBTOTAL HNA', '', $subtotalHna, '', '', ''];
        $rows[] = ['', '', '', 'PPN (11%)', '', $ppn, '', '', ''];
        $rows[] = ['', '', '', 'TOTAL PEMESANAN', '', $grandTotal, '', '', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $headerRow = 6;
        $totalDataRows = max(1, $this->dataRowCount);
        $lastDataRow = $headerRow + $totalDataRows;

        // 1. Title & Header Info Styling
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');
        $sheet->mergeCells('A4:I4');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '1E3A8A'], // Navy Blue
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => '1F2937'],
            ],
        ]);

        $sheet->getStyle('A3:A4')->applyFromArray([
            'font' => [
                'size' => 9.5,
                'color' => ['rgb' => '4B5563'],
            ],
        ]);

        // 2. Table Column Header Styling (Baris 6)
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate 800 Dark
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(26);

        // 3. Data Rows Styling
        $firstDataRow = $headerRow + 1;
        $sheet->getStyle("A{$firstDataRow}:I{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'], // Slate 300
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Alignment spesifik tiap kolom data
        $sheet->getStyle("A{$firstDataRow}:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$firstDataRow}:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("C{$firstDataRow}:C{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D{$firstDataRow}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$firstDataRow}:F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$firstDataRow}:G{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("H{$firstDataRow}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("I{$firstDataRow}:I{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Zebra striping untuk baris data
        for ($r = $firstDataRow; $r <= $lastDataRow; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(20);
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:I{$r}")->getFill()->applyFromArray([
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8FAFC'], // Slate 50
                ]);
            }
        }

        // 4. Footer Ringkasan (Subtotal, PPN, Grand Total)
        $subtotalRow = $lastDataRow + 1;
        $ppnRow = $lastDataRow + 2;
        $totalRow = $lastDataRow + 3;

        // Merge D..E untuk label
        $sheet->mergeCells("D{$subtotalRow}:E{$subtotalRow}");
        $sheet->mergeCells("D{$ppnRow}:E{$ppnRow}");
        $sheet->mergeCells("D{$totalRow}:E{$totalRow}");

        $sheet->getStyle("D{$subtotalRow}:F{$totalRow}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '94A3B8'],
                ],
            ],
        ]);

        $sheet->getStyle("D{$subtotalRow}:E{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("F{$subtotalRow}:F{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Highlight baris Grand Total
        $sheet->getStyle("D{$totalRow}:F{$totalRow}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E0F2FE'], // Sky 100 soft highlight
            ],
            'font' => [
                'bold' => true,
                'size' => 10.5,
                'color' => ['rgb' => '0369A1'], // Sky 700
            ],
        ]);

        return [];
    }
}