<?php

namespace App\Exports\Report;

use App\Models\Doctors;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Support\LiphTransactionScope;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class DoctorExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;
    protected $selectedType;
    protected $doctorId;

    public function __construct($pharmacyId, $startDate, $endDate, $shift = null, $shiftType = 'semua', $selectedType = 'rekap', $doctorId = null)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->shift        = $shift;
        $this->shiftType    = $shiftType;
        $this->selectedType = $selectedType ?: 'rekap';
        $this->doctorId     = $doctorId;
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);
        $doctorName = null;
        if (!empty($this->doctorId)) {
            $doctorObj  = Doctors::find($this->doctorId);
            $doctorName = $doctorObj?->name;
        }

        $titleSuffix = $doctorName ? " - {$doctorName}" : '';
        $typeTitle   = $this->selectedType === 'detail'
            ? "Detail Penjualan Dokter{$titleSuffix}"
            : "Rekap Penjualan Dokter{$titleSuffix}";

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan (' . $typeTitle . ')'],
            ['Periode : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $query = MedicineTransactions::with([
            'transactions' => fn($q) => $q->where('status', 1)->with('medicine'),
            'doctors',
            'shift_logs',
        ])
            ->where('pharmacy_id', $this->pharmacyId)
            ->whereIn('transaction_type', ['RESEP TUNAI', 'KREDIT']);

        LiphTransactionScope::applySalesStatus($query);
        LiphTransactionScope::applyPeriod(
            $query,
            $this->startDate,
            $this->endDate,
            $this->shiftType,
            $this->shift
        );

        if (!empty($this->doctorId)) {
            $query->where('doctor_id', $this->doctorId);
        }

        $transactions = $query->get();

        $body = $this->selectedType === 'detail'
            ? $this->buildDetail($transactions)
            : $this->buildRecap($transactions);

        return array_merge($header, $body);
    }

    // REKAP — 5 Kolom: No, Nama Dokter, Nilai Resep, Lembar, Jumlah R/
    private function buildRecap($transactions): array
    {
        $grouped     = [];
        $potonganTrx = 0;

        foreach ($transactions as $trx) {
            $docName = trim($trx->doctors?->name ?? 'TANPA DOKTER');
            if ($docName === '') $docName = 'TANPA DOKTER';
            $groupKey = strtoupper($docName);

            $resepItems = $trx->transactions->filter(function ($item) use ($trx) {
                $ct = $item->cart_type ?: $trx->transaction_type;
                return in_array($ct, ['UM', 'UK', 'RESEP TUNAI', 'KREDIT'], true);
            });
            if ($resepItems->isEmpty()) continue;

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'doctor_name' => $docName,
                    'nilai_resep' => 0,
                    'lembar'      => 0,
                    'jumlah_r'    => 0,
                ];
            }

            $grouped[$groupKey]['nilai_resep'] += $resepItems->sum(fn ($i) => (int) round((float) ($i->final_price ?? 0)));
            $grouped[$groupKey]['lembar']      += 1;
            $grouped[$groupKey]['jumlah_r']    += $resepItems->count();

            if (is_numeric($trx->discount) && (float) $trx->discount > 0) {
                $potonganTrx += (int) round((float) $trx->discount);
            }
        }

        // Sort alphabetically by Doctor Name (A-Z)
        uasort($grouped, fn($a, $b) => strcasecmp($a['doctor_name'], $b['doctor_name']));

        $rows   = [];
        $rows[] = ['No.', 'Nama Dokter', 'Nilai Resep', 'Lembar', 'Jumlah R/'];

        $no           = 1;
        $grandNilai   = 0;
        $grandLembar  = 0;
        $grandJumlahR = 0;

        foreach ($grouped as $doc) {
            $rows[] = [
                $no++,
                $doc['doctor_name'],
                (int) $doc['nilai_resep'],
                $doc['lembar'],
                $doc['jumlah_r'],
            ];

            $grandNilai   += (int) $doc['nilai_resep'];
            $grandLembar  += $doc['lembar'];
            $grandJumlahR += $doc['jumlah_r'];
        }

        $rows[] = ['', 'TOTAL', (int) $grandNilai, $grandLembar, $grandJumlahR];

        if (empty($this->doctorId) && $potonganTrx > 0) {
            $rows[] = [
                '',
                'POTONGAN TRANSAKSI (DISKON NOTA)',
                -(int) $potonganTrx,
                '',
                '',
            ];

            $rows[] = [
                '',
                'TOTAL NETTO AKHIR',
                (int) ($grandNilai - $potonganTrx),
                $grandLembar,
                $grandJumlahR,
            ];
        }

        return $rows;
    }

    // DETAIL — 5 Kolom: No, Nama Dokter, Nama Obat, Qty, Jumlah
    private function buildDetail($transactions): array
    {
        $grouped     = [];
        $potonganTrx = 0;

        foreach ($transactions as $trx) {
            $docName = trim($trx->doctors?->name ?? 'TANPA DOKTER');
            if ($docName === '') $docName = 'TANPA DOKTER';
            $groupDoc = strtoupper($docName);

            $resepItems = $trx->transactions->filter(function ($item) use ($trx) {
                $ct = $item->cart_type ?: $trx->transaction_type;
                return in_array($ct, ['UM', 'UK', 'RESEP TUNAI', 'KREDIT'], true);
            });
            if ($resepItems->isEmpty()) continue;

            foreach ($resepItems as $item) {
                $med     = $item->medicine;
                $medId   = $med?->id ?? ('item_' . $item->id);
                $medName = $med?->name ?? ($item->medicine_name ?? '-');
                $key     = "{$groupDoc}_{$medId}";

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'doctor'   => $docName,
                        'medicine' => $medName,
                        'qty'      => 0,
                        'total'    => 0,
                    ];
                }

                $grouped[$key]['qty']   += (float) ($item->quantity ?? 0);
                $grouped[$key]['total'] += (int) round((float) ($item->final_price ?? 0));
            }

            if (is_numeric($trx->discount) && (float) $trx->discount > 0) {
                $potonganTrx += (int) round((float) $trx->discount);
            }
        }

        // Sort by Doctor Name (A-Z), then Medicine Name (A-Z)
        uasort($grouped, function ($a, $b) {
            $cmpDoc = strcasecmp($a['doctor'], $b['doctor']);
            return $cmpDoc !== 0 ? $cmpDoc : strcasecmp($a['medicine'], $b['medicine']);
        });

        $rows   = [];
        $rows[] = ['No.', 'Nama Dokter', 'Nama Obat', 'Qty', 'Jumlah'];

        $no         = 1;
        $grandQty   = 0;
        $grandTotal = 0;

        foreach ($grouped as $item) {
            $rows[] = [
                $no++,
                $item['doctor'],
                $item['medicine'],
                $item['qty'],
                (int) $item['total'],
            ];

            $grandQty   += $item['qty'];
            $grandTotal += (int) $item['total'];
        }

        $rows[] = ['', 'TOTAL', '', $grandQty, (int) $grandTotal];

        if (empty($this->doctorId) && $potonganTrx > 0) {
            $rows[] = [
                '',
                'POTONGAN TRANSAKSI (DISKON NOTA)',
                '',
                '',
                -(int) $potonganTrx,
            ];

            $rows[] = [
                '',
                'TOTAL NETTO AKHIR',
                '',
                $grandQty,
                (int) ($grandTotal - $potonganTrx),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol      = $sheet->getHighestColumn();
        $lastRow      = $sheet->getHighestRow();
        $dataStartRow = 7;

        // Merge header info
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A4:{$lastCol}4");
        $sheet->mergeCells("A5:{$lastCol}5");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A2')->getFont()->setSize(11);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Bold column headers
        $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$dataStartRow}")
            ->getFont()->setBold(true);

        // Bold summary rows at bottom
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

        // Borders
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

        // Center No
        $sheet->getStyle("A{$dataStartRow}:A{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        if ($this->selectedType === 'rekap') {
            // Align Nilai Resep, Lembar, Jumlah R/ right
            $sheet->getStyle("C{$dataStartRow}:E{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("C" . ($dataStartRow + 1) . ":E{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        } else {
            // Align Qty & Jumlah right
            $sheet->getStyle("D{$dataStartRow}:E{$lastRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle("D" . ($dataStartRow + 1) . ":E{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0');
        }
    }

    public function columnWidths(): array
    {
        return $this->selectedType === 'rekap'
            ? [
                'A' => 6,   // No.
                'B' => 38,  // Nama Dokter
                'C' => 20,  // Nilai Resep
                'D' => 12,  // Lembar
                'E' => 14,  // Jumlah R/
            ]
            : [
                'A' => 6,   // No.
                'B' => 32,  // Nama Dokter
                'C' => 38,  // Nama Obat
                'D' => 12,  // Qty
                'E' => 18,  // Jumlah
            ];
    }

    public function title(): string
    {
        return $this->selectedType === 'detail'
            ? 'Detail Dokter'
            : 'Rekap Dokter';
    }
}
