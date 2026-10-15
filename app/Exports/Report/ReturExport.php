<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Models\Medicines;
use App\Models\Pharmacies;
use App\Models\Retur;
use App\Support\LiphTransactionScope;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ReturExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;

    public function __construct($pharmacyId, $startDate, $endDate, $shift = null, $shiftType = 'semua')
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate  = Carbon::parse($startDate)->startOfDay();
        $this->endDate    = Carbon::parse($endDate)->endOfDay();
        $this->shift      = $shift;
        $this->shiftType  = $shiftType;
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Retur Penjualan'],
            ['Periode : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        return array_merge($header, $this->buildBody());
    }

    private function buildBody(): array
    {
        $query = MedicineTransactions::with(['shift_logs'])
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

        $headers = $transactions->filter(function ($trx) {
            return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
        })->values();

        $rows   = [];
        $rows[] = ['No.', 'Tanggal', 'No. Retur', 'No. Transaksi Asal', 'Nama Obat', 'Qty Retur', 'Nilai Retur'];

        if ($headers->isEmpty()) {
            $rows[] = ['', 'TOTAL RETUR', '', '', '', 0, 0];
            return $rows;
        }

        $times = $headers->map(fn ($h) => Carbon::parse($h->created_at ?? $h->updated_at));
        $candidates = Retur::whereBetween('created_at', [
            $times->min()->copy()->subSeconds(10),
            $times->max()->copy()->addSeconds(10),
        ])->get();

        $medicines = Medicines::whereIn('id', $candidates->pluck('medicine_id')->filter()->unique())
            ->get()->keyBy('id');
        $originCodes = MedicineTransactions::whereIn('id', $candidates->pluck('transaction_id')->filter()->unique())
            ->where('pharmacy_id', $this->pharmacyId)
            ->pluck('transaction_code', 'id');

        $used       = [];
        $no         = 1;
        $grandQty   = 0;
        $grandTotal = 0;

        foreach ($headers as $rt) {
            $rtTime   = Carbon::parse($rt->created_at ?? $rt->updated_at);
            $rtAmount = (int) ($rt->subtotal ?? 0);

            $match = $candidates->first(function ($r) use ($rtTime, $rtAmount, $used, $originCodes) {
                return !isset($used[$r->id])
                    && isset($originCodes[$r->transaction_id])
                    && abs((float) $r->total_retur - (float) $rtAmount) < 1.0
                    && abs(Carbon::parse($r->created_at)->diffInSeconds($rtTime, false)) <= 10;
            });

            $tgl = Carbon::parse($rt->shift_logs?->clock_in ?? $rt->created_at ?? $rt->updated_at)->format('d/m/Y');

            if ($match) {
                $used[$match->id] = true;
                $medicine = $medicines[$match->medicine_id] ?? null;
                $qty      = (float) $match->qty_retur;
                $total    = (int) ($rt->subtotal ?? ($match->total_retur ?? 0));
                $origin   = $originCodes[$match->transaction_id] ?? '-';
                $nomor    = 'RT/' . $origin;
                $medName  = $medicine?->name ?? 'Obat Retur (Tanpa Master)';
            } else {
                $qty     = 0;
                $total   = (int) ($rt->subtotal ?? 0);
                $origin  = '-';
                $nomor   = 'RT#' . $rt->id;
                $medName = '[RETUR TANPA RINCIAN OBAT]';
            }

            $rows[] = [
                $no++,
                $tgl,
                $nomor,
                $origin,
                $medName,
                $qty ? -$qty : 0,
                $total ? -(int) $total : 0,
            ];

            $grandQty   += $qty;
            $grandTotal += (int) $total;
        }

        $rows[] = [
            '',
            'TOTAL RETUR',
            '',
            '',
            '',
            $grandQty ? -$grandQty : 0,
            $grandTotal ? -(int) $grandTotal : 0,
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = $sheet->getHighestColumn();
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7;

        // Merge header rows
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Table header bold
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        // Bold summary rows at bottom
        for ($r = $lastRow; $r >= $dataStartRow; $r--) {
            $cellB = trim((string) $sheet->getCell("B{$r}")->getValue());
            if ($cellB === 'TOTAL RETUR' || str_starts_with($cellB, 'TOTAL')) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true);
            } else {
                break;
            }
        }

        // Borders on data area
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        for ($i = $dataStartRow; $i <= $lastRow; $i++) {
            $sheet->getRowDimension($i)->setRowHeight(22);
        }

        // Default alignment
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Center No and Dates
        $sheet->getStyle("A{$dataStartRow}:B{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Right-align and format numbers (Qty in col F, Nilai in col G)
        $sheet->getStyle("F{$dataStartRow}:G{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->getStyle("F" . ($dataStartRow + 1) . ":G{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Retur values in red
        $sheet->getStyle("F" . ($dataStartRow + 1) . ":G{$lastRow}")
            ->getFont()->getColor()->setRGB('B91C1C');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No.
            'B' => 14,  // Tanggal
            'C' => 18,  // No. Retur
            'D' => 20,  // No. Transaksi Asal
            'E' => 38,  // Nama Obat
            'F' => 14,  // Qty Retur
            'G' => 18,  // Nilai Retur
        ];
    }

    public function title(): string
    {
        return 'Retur Penjualan';
    }
}
