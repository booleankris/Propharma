<?php

namespace App\Exports\Report;

use App\Models\Factory;
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

class FactoryExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $shift;
    protected $shiftType;
    protected $selectedType;
    protected $factoryId;

    public function __construct($pharmacyId, $startDate, $endDate, $shift = null, $shiftType = 'semua', $selectedType = 'rekap', $factoryId = null)
    {
        $this->pharmacyId   = $pharmacyId;
        $this->startDate    = Carbon::parse($startDate)->startOfDay();
        $this->endDate      = Carbon::parse($endDate)->endOfDay();
        $this->shift        = $shift;
        $this->shiftType    = $shiftType;
        $this->selectedType = $selectedType ?: 'rekap';
        $this->factoryId    = $factoryId;
    }

    public function array(): array
    {
        $query = MedicineTransactions::with([
            'transactions' => fn ($q) => $q->where('status', 1),
            'transactions.medicine.factory',
            'shift_logs',
        ])
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
        $returns      = $this->resolveReturns($transactions);

        $pharmacy = Pharmacies::find($this->pharmacyId);

        $factoryName = null;
        if (!empty($this->factoryId)) {
            $factoryObj  = Factory::find($this->factoryId);
            $factoryName = $factoryObj?->name;
        }

        $titleSuffix = $factoryName ? " - {$factoryName}" : '';
        $typeTitle   = $this->selectedType === 'detail'
            ? "Detail Penjualan Per Pabrik{$titleSuffix}"
            : "Rekap Penjualan Per Pabrik{$titleSuffix}";

        $header = [
            [$pharmacy->name ?? 'APOTEK'],
            [$pharmacy->address ?? ''],
            [''],
            ['Laporan Penjualan (' . $typeTitle . ')'],
            ['Periode : ' . $this->startDate->format('d/m/Y') . ' s/d ' . $this->endDate->format('d/m/Y')],
            [''],
        ];

        $body = $this->selectedType === 'detail'
            ? $this->buildDetail($transactions, $returns)
            : $this->buildRecap($transactions, $returns);

        return array_merge($header, $body);
    }

    private function resolveReturns($transactions): array
    {
        $headers = $transactions->filter(function ($trx) {
            return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
        })->values();

        if ($headers->isEmpty()) {
            return [];
        }

        $times = $headers->map(fn ($h) => Carbon::parse($h->created_at ?? $h->updated_at));
        $candidates = Retur::whereBetween('created_at', [
            $times->min()->copy()->subSeconds(10),
            $times->max()->copy()->addSeconds(10),
        ])->get();

        $medicines = Medicines::with('factory')
            ->whereIn('id', $candidates->pluck('medicine_id')->filter()->unique())
            ->get()->keyBy('id');
        $originCodes = MedicineTransactions::whereIn('id', $candidates->pluck('transaction_id')->filter()->unique())
            ->where('pharmacy_id', $this->pharmacyId)
            ->pluck('transaction_code', 'id');

        $used    = [];
        $returns = [];

        foreach ($headers as $rt) {
            $rtTime   = Carbon::parse($rt->created_at ?? $rt->updated_at);
            $rtAmount = (int) ($rt->subtotal ?? 0);

            $match = $candidates->first(function ($r) use ($rtTime, $rtAmount, $used, $originCodes) {
                return !isset($used[$r->id])
                    && isset($originCodes[$r->transaction_id])
                    && abs((float) $r->total_retur - (float) $rtAmount) < 1.0
                    && abs(Carbon::parse($r->created_at)->diffInSeconds($rtTime, false)) <= 10;
            });

            if ($match) {
                $used[$match->id] = true;
                $medicine = $medicines[$match->medicine_id] ?? null;
                $returns[] = [
                    'factory_id' => $medicine?->factory_id,
                    'factory'    => $medicine?->factory?->name ?? 'LAINNYA',
                    'name'       => $medicine?->name ?? 'Obat Retur (Tanpa Master)',
                    'qty'        => (float) $match->qty_retur,
                    'total'      => (int) ($rt->subtotal ?? ($match->total_retur ?? 0)),
                ];
            } else {
                $returns[] = [
                    'factory_id' => null,
                    'factory'    => '[RETUR TANPA RINCIAN OBAT]',
                    'name'       => '[RETUR TANPA RINCIAN OBAT]',
                    'qty'        => 0,
                    'total'      => (int) ($rt->subtotal ?? 0),
                ];
            }
        }

        return $returns;
    }

    private function buildRecap($transactions, array $returns = []): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                if ($this->factoryId && ($medicine?->factory_id != $this->factoryId)) {
                    continue;
                }

                $factory = $medicine?->factory?->name ?? 'LAINNYA';

                if (!isset($grouped[$factory])) {
                    $grouped[$factory] = [
                        'name'        => $factory,
                        'qty'         => 0,
                        'total'       => 0,
                        'qty_retur'   => 0,
                        'total_retur' => 0,
                    ];
                }

                $grouped[$factory]['qty']   += (float) ($item->quantity ?? 0);
                $grouped[$factory]['total'] += (int) ($item->final_price ?? 0);
            }
        }

        foreach ($returns as $ret) {
            if ($this->factoryId && ($ret['factory_id'] != $this->factoryId)) {
                continue;
            }

            $fac = $ret['factory'];
            if (!isset($grouped[$fac])) {
                $grouped[$fac] = [
                    'name'        => $fac,
                    'qty'         => 0,
                    'total'       => 0,
                    'qty_retur'   => 0,
                    'total_retur' => 0,
                ];
            }
            $grouped[$fac]['qty_retur']   += (float) $ret['qty'];
            $grouped[$fac]['total_retur'] += (int) ($ret['total'] ?? 0);
        }

        // Sort by highest sales
        uasort($grouped, fn ($a, $b) => ($b['total'] - $b['total_retur']) <=> ($a['total'] - $a['total_retur']));

        $rows   = [];
        $rows[] = ['No', 'Pabrik', 'Qty Jual', 'Nilai Jual', 'Qty Retur', 'Nilai Retur', 'Qty Netto', 'Nilai Netto'];

        $no = 1;
        $grand = array_fill_keys(['qty', 'total', 'qty_retur', 'total_retur'], 0);

        foreach ($grouped as $data) {
            $rows[] = [
                $no++,
                $data['name'],
                $data['qty'],
                (int) $data['total'],
                $data['qty_retur'] ? -$data['qty_retur'] : 0,
                $data['total_retur'] ? -(int) $data['total_retur'] : 0,
                $data['qty'] - $data['qty_retur'],
                (int) ($data['total'] - $data['total_retur']),
            ];

            foreach ($grand as $k => $v) {
                $grand[$k] += $data[$k];
            }
        }

        $rows[] = [
            '', 'TOTAL',
            $grand['qty'],
            (int) $grand['total'],
            $grand['qty_retur'] ? -$grand['qty_retur'] : 0,
            $grand['total_retur'] ? -(int) $grand['total_retur'] : 0,
            $grand['qty'] - $grand['qty_retur'],
            (int) ($grand['total'] - $grand['total_retur']),
        ];

        // Jika menampilkan seluruh pabrik (tidak difilter 1 pabrik tertentu), tampilkan rekonsiliasi diskon transaksi
        if (empty($this->factoryId)) {
            $potonganTransaksi = (int) $transactions->reject(function ($trx) {
                return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                    || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
            })->sum(fn ($t) => (int) ($t->discount ?? 0));

            if ($potonganTransaksi > 0) {
                $rows[] = [
                    '', 'POTONGAN TRANSAKSI (DISKON NOTA)', '',
                    '', '', '', '',
                    -$potonganTransaksi,
                ];

                $rows[] = [
                    '', 'TOTAL NETTO AKHIR', '',
                    '', '', '',
                    $grand['qty'] - $grand['qty_retur'],
                    (int) (($grand['total'] - $grand['total_retur']) - $potonganTransaksi),
                ];
            }
        }

        return $rows;
    }

    private function buildDetail($transactions, array $returns = []): array
    {
        $grouped = [];

        foreach ($transactions as $trx) {
            foreach ($trx->transactions ?? [] as $item) {
                $medicine = $item->medicine;
                if ($this->factoryId && ($medicine?->factory_id != $this->factoryId)) {
                    continue;
                }

                $factory = $medicine?->factory?->name ?? 'LAINNYA';
                $medName = $medicine?->name ?? ($item->medicine_name ?? '-');
                $key     = $factory . '###' . ($medicine?->id ?? ('item_' . $item->id));

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'factory'     => $factory,
                        'name'        => $medName,
                        'qty'         => 0,
                        'total'       => 0,
                        'qty_retur'   => 0,
                        'total_retur' => 0,
                    ];
                }

                $grouped[$key]['qty']   += (float) ($item->quantity ?? 0);
                $grouped[$key]['total'] += (int) ($item->final_price ?? 0);
            }
        }

        foreach ($returns as $ret) {
            if ($this->factoryId && ($ret['factory_id'] != $this->factoryId)) {
                continue;
            }

            $fac = $ret['factory'];
            $key = $fac . '###' . $ret['name'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'factory'     => $fac,
                    'name'        => $ret['name'],
                    'qty'         => 0,
                    'total'       => 0,
                    'qty_retur'   => 0,
                    'total_retur' => 0,
                ];
            }
            $grouped[$key]['qty_retur']   += (float) $ret['qty'];
            $grouped[$key]['total_retur'] += (int) ($ret['total'] ?? 0);
        }

        uasort($grouped, function ($a, $b) {
            $facCmp = strcasecmp($a['factory'], $b['factory']);
            if ($facCmp !== 0) return $facCmp;
            return strcasecmp($a['name'], $b['name']);
        });

        $rows   = [];
        $rows[] = ['No', 'Pabrik', 'Nama Obat', 'Qty Jual', 'Nilai Jual', 'Qty Retur', 'Nilai Retur', 'Qty Netto', 'Nilai Netto'];

        $no = 1;
        $grand = array_fill_keys(['qty', 'total', 'qty_retur', 'total_retur'], 0);

        foreach ($grouped as $data) {
            $rows[] = [
                $no++,
                $data['factory'],
                $data['name'],
                $data['qty'],
                (int) $data['total'],
                $data['qty_retur'] ? -$data['qty_retur'] : 0,
                $data['total_retur'] ? -(int) $data['total_retur'] : 0,
                $data['qty'] - $data['qty_retur'],
                (int) ($data['total'] - $data['total_retur']),
            ];

            foreach ($grand as $k => $v) {
                $grand[$k] += $data[$k];
            }
        }

        $rows[] = [
            '', 'TOTAL', '',
            $grand['qty'],
            (int) $grand['total'],
            $grand['qty_retur'] ? -$grand['qty_retur'] : 0,
            $grand['total_retur'] ? -(int) $grand['total_retur'] : 0,
            $grand['qty'] - $grand['qty_retur'],
            (int) ($grand['total'] - $grand['total_retur']),
        ];

        if (empty($this->factoryId)) {
            $potonganTransaksi = (int) $transactions->reject(function ($trx) {
                return in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true)
                    || strtoupper(trim((string) $trx->transaction_code)) === 'RT';
            })->sum(fn ($t) => (int) ($t->discount ?? 0));

            if ($potonganTransaksi > 0) {
                $rows[] = [
                    '', 'POTONGAN TRANSAKSI (DISKON NOTA)', '',
                    '', '', '', '', '',
                    -$potonganTransaksi,
                ];

                $rows[] = [
                    '', 'TOTAL NETTO AKHIR', '',
                    '', '', '', '',
                    $grand['qty'] - $grand['qty_retur'],
                    (int) (($grand['total'] - $grand['total_retur']) - $potonganTransaksi),
                ];
            }
        }

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

        $numStartCol = $this->selectedType === 'detail' ? 'D' : 'C';

        // Align numbers right
        $sheet->getStyle("{$numStartCol}{$dataStartRow}:{$lastCol}{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Format numbers
        $sheet->getStyle("{$numStartCol}" . ($dataStartRow + 1) . ":{$lastCol}{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode('#,##0');

        // Retur columns in red
        $returCols = $this->selectedType === 'detail' ? 'F:G' : 'E:F';
        [$c1, $c2] = explode(':', $returCols);
        $sheet->getStyle("{$c1}" . ($dataStartRow + 1) . ":{$c2}{$lastRow}")
            ->getFont()->getColor()->setRGB('B91C1C');
    }

    public function columnWidths(): array
    {
        return $this->selectedType === 'detail'
            ? [
                'A' => 6,   // No
                'B' => 25,  // Pabrik
                'C' => 38,  // Nama Obat
                'D' => 12,  // Qty Jual
                'E' => 16,  // Nilai Jual
                'F' => 12,  // Qty Retur
                'G' => 16,  // Nilai Retur
                'H' => 12,  // Qty Netto
                'I' => 18,  // Nilai Netto
            ]
            : [
                'A' => 6,   // No
                'B' => 30,  // Pabrik
                'C' => 12,  // Qty Jual
                'D' => 18,  // Nilai Jual
                'E' => 12,  // Qty Retur
                'F' => 16,  // Nilai Retur
                'G' => 12,  // Qty Netto
                'H' => 18,  // Nilai Netto
            ];
    }

    public function title(): string
    {
        return $this->selectedType === 'detail'
            ? 'Detail Pabrik Obat'
            : 'Rekap Pabrik Obat';
    }
}
