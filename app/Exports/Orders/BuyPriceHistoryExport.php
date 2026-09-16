<?php

namespace App\Exports\Orders;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BuyPriceHistoryExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    protected $items;
    protected $filters;

    public function __construct($items, array $filters = [])
    {
        $this->items = $items;
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'Riwayat Harga Beli';
    }

    public function array(): array
    {
        $now = Carbon::now()->translatedFormat('d F Y H:i');

        // Build filter subtitle
        $filterParts = [];
        if (!empty($this->filters['start_date']) && !empty($this->filters['end_date'])) {
            $filterParts[] = 'Periode: ' . Carbon::parse($this->filters['start_date'])->format('d/m/Y') . ' s/d ' . Carbon::parse($this->filters['end_date'])->format('d/m/Y');
        } elseif (!empty($this->filters['start_date'])) {
            $filterParts[] = 'Mulai: ' . Carbon::parse($this->filters['start_date'])->format('d/m/Y');
        } elseif (!empty($this->filters['end_date'])) {
            $filterParts[] = 'Sampai: ' . Carbon::parse($this->filters['end_date'])->format('d/m/Y');
        }

        if (!empty($this->filters['price_diff'])) {
            $statusMap = [
                'naik' => 'Harga Naik',
                'turun' => 'Harga Turun',
                'beda' => 'Ada Selisih Harga',
                'sama' => 'Harga Sama'
            ];
            $filterParts[] = 'Status: ' . ($statusMap[$this->filters['price_diff']] ?? $this->filters['price_diff']);
        }

        if (!empty($this->filters['creditor'])) {
            $filterParts[] = 'PBF: ' . $this->filters['creditor'];
        }

        if (!empty($this->filters['search_medicine'])) {
            $filterParts[] = 'Obat: ' . $this->filters['search_medicine'];
        }

        $filterText = count($filterParts) > 0 ? implode(' | ', $filterParts) : 'Semua Data';

        $data = [
            ['LAPORAN RIWAYAT HARGA BELI PENERIMAAN OBAT'],
            ['Filter: ' . $filterText . ' | Waktu Unduh: ' . $now],
            [''], // Empty row
            [
                'No',
                'Tgl Terima',
                'No. Terima',
                'No. Faktur',
                'PBF / Distributor',
                'Kode Obat',
                'Nama Obat',
                'Kemasan & Satuan',
                'Harga Beli Faktur (Rp)',
                'Harga Satuan HNA Faktur (Rp)',
                'Master HNA Saat Ini (Rp)',
                'Selisih (Rp)',
                'Status Perubahan',
            ],
        ];

        $getRawReceived = function ($row, $isPack, $content) {
            $raw = (float) ($row->raw_price ?? 0);
            if ($raw <= 0) {
                if (!empty($row->total) && !empty($row->qty_received) && (float) $row->qty_received > 0) {
                    $raw = (float) $row->total / (float) $row->qty_received;
                } elseif (!empty($row->order_items?->price) && (float) $row->order_items->price > 0) {
                    $oiPrice = (float) $row->order_items->price;
                    $raw = ($isPack && $content > 1) ? ($oiPrice * $content) : $oiPrice;
                }
            }
            return $raw;
        };

        $no = 1;
        foreach ($this->items as $row) {
            $med = $row->order_items?->medicines;
            $isPack = (bool) ($row->order_items?->pack == 1);
            $content = (float) ($med?->content ?? 1);
            if ($content <= 0) $content = 1;

            $pkg = trim((string) ($med?->packaging ?: 'BOX'));
            $unit = trim((string) ($med?->unit ?: 'TAB'));
            if (strcasecmp($pkg, $unit) === 0 && $content > 1) {
                $pkg = 'BOX';
            }

            $rawReceived = $getRawReceived($row, $isPack, $content);
            $unitHnaReceived = ($isPack && $content > 1) ? round($rawReceived / $content, 2) : $rawReceived;
            $masterUnitHna = (float) ($med?->raw_price ?? 0);

            // Packaging label
            $satuanLabel = ($isPack && $content > 1) 
                ? "1 {$pkg} @{$content} {$unit}" 
                : $unit;

            // Date
            $invDate = $row->receiving_details?->invoice_date;
            $tglTerima = $invDate ? Carbon::parse($invDate)->format('d/m/Y') : ($row->created_at ? $row->created_at->format('d/m/Y') : '-');

            // No terima & Faktur
            $noTerima = $row->receiving_details?->receiving_details_code 
                ?? $row->receiving_details?->receiving?->code 
                ?? '-';
            $noFaktur = $row->receiving_details?->invoice_number ?? '-';

            // Creditor
            $creditor = $row->receiving_details?->creditor?->name 
                ?? $row->order_items?->creditors?->name 
                ?? '-';

            // Diff & status
            $diff = $unitHnaReceived - $masterUnitHna;
            if ($masterUnitHna <= 0 && $unitHnaReceived <= 0) {
                $statusText = '-';
            } elseif ($masterUnitHna <= 0) {
                $statusText = 'Baru';
            } elseif (abs($diff) < 0.01) {
                $statusText = 'Sama';
            } else {
                $pct = round(abs($diff) / $masterUnitHna * 100, 1);
                $pctFmt = ($pct == (int) $pct) ? (int) $pct . '%' : number_format($pct, 1, ',', '.') . '%';
                $statusText = ($diff > 0 ? 'Naik ' : 'Turun ') . $pctFmt;
            }

            $data[] = [
                $no++,
                $tglTerima,
                $noTerima,
                $noFaktur,
                $creditor,
                $med?->code ?? '-',
                $med?->name ?? '-',
                $satuanLabel,
                $rawReceived,
                $unitHnaReceived,
                $masterUnitHna,
                $diff,
                $statusText,
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();

        // Header Title
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('64748B');

        // Table Header Row 4
        $headerRange = 'A4:M4';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'], // Teal 700
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        $sheet->getRowDimension(4)->setRowHeight(28);

        if ($highestRow >= 5) {
            $dataRange = "A5:M{$highestRow}";

            // Borders
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CBD5E1'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Alignments
            $sheet->getStyle("A5:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B5:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F5:F{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H5:H{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M5:M{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Currency formatting on column I, J, K, L
            $sheet->getStyle("I5:L{$highestRow}")->getNumberFormat()->setFormatCode('#,##0');
            $sheet->getStyle("I5:L{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Zebra striping
            for ($r = 5; $r <= $highestRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:M{$r}")->getFill()->applyFromArray([
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8FAFC'],
                    ]);
                }
            }
        }

        return [];
    }
}
