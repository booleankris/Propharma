<?php

namespace App\Exports\Export;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// ══════════════════════════════════════════════════════════
//  Main export — just delegates to the two sheet exports
// ══════════════════════════════════════════════════════════
class ParetoExport implements WithMultipleSheets
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $searchMedicine;

    public function __construct($pharmacyId, $startDate = null, $endDate = null, $searchMedicine = null)
    {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = $startDate;
        $this->endDate         = $endDate;
        $this->searchMedicine  = $searchMedicine;
    }

    public function sheets(): array
    {
        return [
            new ParetoSalesSheet($this->pharmacyId, $this->startDate, $this->endDate, $this->searchMedicine),
            new ParetoOrdersSheet($this->pharmacyId, $this->startDate, $this->endDate, $this->searchMedicine),
        ];
    }
}

// ══════════════════════════════════════════════════════════
//  Shared base — both sheets extend this
// ══════════════════════════════════════════════════════════
abstract class ParetoBaseSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $searchMedicine;

    // Accent color (hex RGB) — overridden per sheet
    protected string $accentColor   = '2563EB';   // blue  → sales
    protected string $totalBgColor  = 'DBEAFE';   // light blue

    public function __construct($pharmacyId, $startDate = null, $endDate = null, $searchMedicine = null)
    {
        $this->pharmacyId      = $pharmacyId;
        $this->searchMedicine  = $searchMedicine;
        $this->startDate       = !empty($startDate)
            ? Carbon::parse($startDate)->startOfDay()
            : null;
        $this->endDate         = !empty($endDate)
            ? Carbon::parse($endDate)->endOfDay()
            : null;
    }

    // ── Subclasses supply the report label and the data rows ──
    abstract protected function reportTitle(): string;
    abstract protected function fetchItems(): \Illuminate\Support\Collection;

    // ── Array output ─────────────────────────────────────────
    public function array(): array
    {
        $pharmacy = \App\Models\Pharmacies::find($this->pharmacyId);

        $periodeText = ($this->startDate && $this->endDate)
            ? 'Periode : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')
            : ($this->startDate
                ? 'Periode : Mulai ' . $this->startDate->format('d/m/Y')
                : ($this->endDate
                    ? 'Periode : Sampai ' . $this->endDate->format('d/m/Y')
                    : 'Periode : Semua Transaksi'));

        $header = [
            [$pharmacy->name    ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            [$this->reportTitle()],
            [$periodeText],
            [''],
        ];

        return array_merge($header, $this->buildBody());
    }

    // ── Body rows ─────────────────────────────────────────────
    private function buildBody(): array
    {
        $items = $this->fetchItems();

        $columnHeader = ['No', 'Kode Obat', 'Nama Obat', 'Qty', 'Jumlah', 'Persen(%)', 'Kumulatif(%)', 'Freq R/', 'Satuan'];

        if ($items->isEmpty()) {
            return [
                $columnHeader,
                ['Tidak ada data untuk periode yang dipilih.'],
            ];
        }

        $rows        = [$columnHeader];
        $grandQty    = 0.0;
        $grandJumlah = 0.0;
        $sumPersen   = 0.0;

        foreach ($items as $item) {
            $jumlah     = (float) ($item->total_jumlah ?? 0);
            $qty        = (float) ($item->total_qty    ?? 0);
            $freq       = (int)   ($item->freq         ?? 0);
            $persen     = (float) ($item->persen       ?? 0.0);
            $cumulative = (float) ($item->kumulatif    ?? 0.0);

            $rows[] = [
                $item->rank ?? '-',
                $item->medicine_code ?? '-',
                $item->medicine_name ?? '-',
                $qty,
                $jumlah,
                $persen,
                $cumulative,
                $freq,
                $item->medicine_unit ?? '-',
            ];

            $grandQty    += $qty;
            $grandJumlah += $jumlah;
            $sumPersen   += $persen;
        }

        $label = !empty($this->searchMedicine) ? 'TOTAL (TERFILTER)' : 'TOTAL';
        $persenDisplay = !empty($this->searchMedicine) ? round($sumPersen, 2) : 100.0;
        $cumDisplay = !empty($this->searchMedicine) ? '' : 100.0;

        $rows[] = ['', '', $label, $grandQty, $grandJumlah, $persenDisplay, $cumDisplay, '', ''];

        return $rows;
    }

    // ── Styles (shared) ───────────────────────────────────────
    public function styles(Worksheet $sheet)
    {
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7;
        $dataEndRow   = $lastRow - 1;
        $totalRow     = $lastRow;
        $lastCol      = 'I';

        // Merge info rows
        foreach ([1, 2, 4, 5] as $r) {
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        }

        // Pharmacy name
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Report title
        $sheet->getStyle('A4')->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Period
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Column header row
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($this->accentColor);
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Borders
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // Alternating row shading
        for ($i = $dataStartRow + 1; $i <= $dataEndRow; $i++) {
            if ($i % 2 === 0) {
                $sheet->getStyle("A{$i}:{$lastCol}{$i}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F8FAFC');
            }
        }

        // Total row
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")
            ->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($this->totalBgColor);

        // Row heights
        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);
        }

        // Default alignment
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Right-align numeric columns
        foreach (['D', 'E', 'F', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}{$dataStartRow}:{$col}{$lastRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $dataRow = $dataStartRow + 1;

        // D = Qty
        $sheet->getStyle("D{$dataRow}:D{$lastRow}")
            ->getNumberFormat()->setFormatCode('#,##0');

        // E = Jumlah (Rp)
        $sheet->getStyle("E{$dataRow}:E{$lastRow}")
            ->getNumberFormat()->setFormatCode('"Rp "#,##0');

        // F = Persen, G = Kumulatif
        foreach (['F', 'G'] as $col) {
            $sheet->getStyle("{$col}{$dataRow}:{$col}{$lastRow}")
                ->getNumberFormat()->setFormatCode('0.00');
        }

        // H = Freq
        $sheet->getStyle("H{$dataRow}:H{$lastRow}")
            ->getNumberFormat()->setFormatCode('#,##0');

        // Center: No & Satuan columns
        foreach (['A', 'I'] as $col) {
            $sheet->getStyle("{$col}{$dataRow}:{$col}{$lastRow}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 15,
            'C' => 45,
            'D' => 12,
            'E' => 20,
            'F' => 12,
            'G' => 14,
            'H' => 10,
            'I' => 12,
        ];
    }
}

// ══════════════════════════════════════════════════════════
//  Sheet 1 — Pareto Penjualan  (blue accent)
// ══════════════════════════════════════════════════════════
class ParetoSalesSheet extends ParetoBaseSheet
{
    protected string $accentColor  = '2563EB';
    protected string $totalBgColor = 'DBEAFE';

    protected function reportTitle(): string
    {
        return 'Laporan Pareto Penjualan';
    }

    public function title(): string
    {
        return 'Pareto Penjualan';
    }

    protected function fetchItems(): \Illuminate\Support\Collection
    {
        $baseQuery = DB::table('medicine_cart')
            ->join('medicine_transactions', 'medicine_transactions.id', '=', 'medicine_cart.transaction_id')
            ->join('medicines', 'medicines.id', '=', 'medicine_cart.medicine_id')
            ->where('medicine_transactions.pharmacy_id', $this->pharmacyId)
            ->where('medicine_cart.status', 1)
            ->where(function ($q) {
                $q->where('medicine_transactions.status', 1)
                    ->orWhere(function ($unpaid) {
                        $unpaid->whereNotNull('medicine_transactions.transaction_code')
                            ->where(function ($unpaidType) {
                                $unpaidType->whereRaw("UPPER(TRIM(COALESCE(medicine_transactions.payment_method, ''))) = 'BELUM BAYAR'")
                                    ->orWhereIn('medicine_transactions.transaction_type', ['KREDIT', 'RESEP KREDIT']);
                            });
                    });
            });

        if ($this->startDate) {
            $baseQuery->where('medicine_transactions.created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $baseQuery->where('medicine_transactions.created_at', '<=', $this->endDate);
        }

        $allRows = (clone $baseQuery)
            ->select([
                'medicines.id   as medicine_id',
                'medicines.code as medicine_code',
                'medicines.name as medicine_name',
                'medicines.unit as medicine_unit',
                DB::raw('SUM(CAST(COALESCE(medicine_cart.quantity, 0) AS DECIMAL(12,2)))    as total_qty'),
                DB::raw('SUM(CAST(COALESCE(medicine_cart.final_price, 0) AS DECIMAL(16,2))) as total_jumlah'),
                DB::raw('COUNT(DISTINCT medicine_cart.transaction_id) as freq'),
            ])
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.unit')
            ->orderByDesc('total_jumlah')
            ->orderByDesc('total_qty')
            ->orderBy('medicines.name')
            ->get();

        $overallGrandTotal = (float) $allRows->sum('total_jumlah');
        $runningSum        = 0.0;
        $rank              = 1;

        $decorated = $allRows->map(function ($item) use ($overallGrandTotal, &$runningSum, &$rank) {
            $jumlah     = (float) $item->total_jumlah;
            $persen     = $overallGrandTotal > 0 ? round(($jumlah / $overallGrandTotal) * 100, 2) : 0.0;
            $runningSum += $jumlah;
            $cumulative = $overallGrandTotal > 0 ? min(100.0, round(($runningSum / $overallGrandTotal) * 100, 2)) : 0.0;

            $item->rank      = $rank++;
            $item->persen    = $persen;
            $item->kumulatif = $cumulative;

            return $item;
        });

        if (!empty($this->searchMedicine)) {
            $kw = mb_strtolower(trim($this->searchMedicine));
            $decorated = $decorated->filter(function ($item) use ($kw) {
                return str_contains(mb_strtolower($item->medicine_name ?? ''), $kw)
                    || str_contains(mb_strtolower($item->medicine_code ?? ''), $kw);
            })->values();
        }

        return $decorated;
    }
}

// ══════════════════════════════════════════════════════════
//  Sheet 2 — Pareto Pembelian  (green accent)
// ══════════════════════════════════════════════════════════
class ParetoOrdersSheet extends ParetoBaseSheet
{
    protected string $accentColor  = '16A34A';
    protected string $totalBgColor = 'DCFCE7';

    protected function reportTitle(): string
    {
        return 'Laporan Pareto Pembelian';
    }

    public function title(): string
    {
        return 'Pareto Pembelian';
    }

    protected function fetchItems(): \Illuminate\Support\Collection
    {
        $targetPharmacyIds = in_array((int) $this->pharmacyId, [1, 6, 9])
            ? [9, 1]
            : [(int) $this->pharmacyId];

        $baseQuery = DB::table('receiving_items')
            ->join('order_items', 'order_items.id', '=', 'receiving_items.order_items_id')
            ->join('medicines', 'medicines.id', '=', 'order_items.medicine_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.pharmacy_id', $targetPharmacyIds);

        if ($this->startDate) {
            $baseQuery->where('receiving_items.created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $baseQuery->where('receiving_items.created_at', '<=', $this->endDate);
        }

        $allRows = (clone $baseQuery)
            ->select([
                'medicines.id   as medicine_id',
                'medicines.code as medicine_code',
                'medicines.name as medicine_name',
                'medicines.unit as medicine_unit',
                DB::raw('SUM(CAST(COALESCE(receiving_items.qty, 0) AS DECIMAL(12,2)))   as total_qty'),
                DB::raw('SUM(CAST(COALESCE(receiving_items.total, 0) AS DECIMAL(16,2))) as total_jumlah'),
                DB::raw('COUNT(DISTINCT receiving_items.receiving_details_id) as freq'),
            ])
            ->groupBy('medicines.id', 'medicines.code', 'medicines.name', 'medicines.unit')
            ->orderByDesc('total_jumlah')
            ->orderByDesc('total_qty')
            ->orderBy('medicines.name')
            ->get();

        $overallGrandTotal = (float) $allRows->sum('total_jumlah');
        $runningSum        = 0.0;
        $rank              = 1;

        $decorated = $allRows->map(function ($item) use ($overallGrandTotal, &$runningSum, &$rank) {
            $jumlah     = (float) $item->total_jumlah;
            $persen     = $overallGrandTotal > 0 ? round(($jumlah / $overallGrandTotal) * 100, 2) : 0.0;
            $runningSum += $jumlah;
            $cumulative = $overallGrandTotal > 0 ? min(100.0, round(($runningSum / $overallGrandTotal) * 100, 2)) : 0.0;

            $item->rank      = $rank++;
            $item->persen    = $persen;
            $item->kumulatif = $cumulative;

            return $item;
        });

        if (!empty($this->searchMedicine)) {
            $kw = mb_strtolower(trim($this->searchMedicine));
            $decorated = $decorated->filter(function ($item) use ($kw) {
                return str_contains(mb_strtolower($item->medicine_name ?? ''), $kw)
                    || str_contains(mb_strtolower($item->medicine_code ?? ''), $kw);
            })->values();
        }

        return $decorated;
    }
}