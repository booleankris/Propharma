<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Support\LiphTransactionScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SalesDataRecapSheet implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected $pharmacyId;
    protected $dateFrom;
    protected $dateTo;
    protected $channel;
    protected $search;
    protected $pharmacyName;

    public function __construct($pharmacyId, $dateFrom = null, $dateTo = null, $channel = null, $search = null, $pharmacyName = '')
    {
        $this->pharmacyId   = $pharmacyId;
        $this->dateFrom     = $dateFrom;
        $this->dateTo       = $dateTo;
        $this->channel      = $channel;
        $this->search       = $search;
        $this->pharmacyName = $pharmacyName ?: 'APOTEK';
    }

    public function title(): string
    {
        return 'Rekap Penjualan';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No.
            'B' => 14,  // Tanggal
            'C' => 10,  // Jam
            'D' => 22,  // No. Struk
            'E' => 18,  // Jenis / Channel
            'F' => 18,  // Tipe
            'G' => 25,  // Pasien
            'H' => 18,  // Kasir / User
            'I' => 18,  // Subtotal (Rp)
            'J' => 16,  // Diskon Item (Rp)
            'K' => 16,  // Diskon Faktur (Rp)
            'L' => 18,  // Total Netto (Rp)
            'M' => 18,  // Pembayaran
        ];
    }

    private function parseDate($str)
    {
        if (!$str) return null;
        $str = trim((string) $str);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            return Carbon::parse($str);
        }
        try {
            return Carbon::createFromFormat('d/m/Y', $str);
        } catch (\Exception $e) {
            return Carbon::parse($str);
        }
    }

    public function array(): array
    {
        $pharmacy = Pharmacies::find($this->pharmacyId);
        $pharmacyName = $pharmacy?->name ?? $this->pharmacyName;

        $startDateObj = $this->parseDate($this->dateFrom);
        $endDateObj = $this->parseDate($this->dateTo);

        $periodStr = 'Semua Periode';
        if ($startDateObj && $endDateObj) {
            $periodStr = $startDateObj->format('d/m/Y') . ' s/d ' . $endDateObj->format('d/m/Y');
        } elseif ($startDateObj) {
            $periodStr = 'Mulai ' . $startDateObj->format('d/m/Y');
        } elseif ($endDateObj) {
            $periodStr = 'Sampai ' . $endDateObj->format('d/m/Y');
        }

        $channelLabel = 'Semua Jenis';
        if ($this->channel === 'kasir') $channelLabel = 'Kasir (Offline)';
        elseif ($this->channel === 'online') $channelLabel = 'Online (Chat WA)';
        elseif ($this->channel === 'shopee') $channelLabel = 'Online Shopee';
        elseif ($this->channel === 'grab') $channelLabel = 'Online Grab';
        elseif ($this->channel === 'digital') $channelLabel = 'Digital (Aplikasi Mobile)';

        $header = [
            [strtoupper($pharmacyName)],
            ['LAPORAN PENJUALAN KASIR (REKAP TRANSAKSI)'],
            ['Periode : ' . $periodStr . '  |  Jenis : ' . $channelLabel],
            [''],
        ];

        $rows = [];
        $rows[] = [
            'No.',
            'Tanggal',
            'Jam',
            'Nomor Struk',
            'Jenis Transaksi',
            'Tipe',
            'Pasien / Pelanggan',
            'Kasir / User',
            'Subtotal Kotor',
            'Diskon Item',
            'Diskon Faktur',
            'Total Netto',
            'Pembayaran',
            'Jasa / Ongkir',
            'Tipe Jasa / Ongkir',
        ];

        $query = MedicineTransactions::query()
            ->with([
                'transactions' => fn ($q) => $q->where('status', 1),
                'transactions.user.roles',
                'patients',
                'user.roles',
            ]);

        LiphTransactionScope::applySalesStatus($query);

        if ($this->pharmacyId) {
            $query->where('pharmacy_id', $this->pharmacyId);
        }

        if ($startDateObj || $endDateObj) {
            $start = $startDateObj ? $startDateObj->copy()->startOfDay()->setTime(7, 30, 0) : null;
            $end   = $endDateObj ? $endDateObj->copy()->startOfDay()->addDay()->setTime(7, 30, 0) : null;
            $query->where(function ($period) use ($start, $end) {
                $period->where(function ($main) use ($start, $end) {
                    if ($start) $main->where('created_at', '>=', $start);
                    if ($end)   $main->where('created_at', '<', $end);
                })->orWhere(function ($legacy) use ($start, $end) {
                    $legacy->whereNull('created_at');
                    if ($start) $legacy->where('updated_at', '>=', $start);
                    if ($end)   $legacy->where('updated_at', '<', $end);
                });
            });
        }

        if ($this->channel) {
            $applyChannel = function ($q, $roleName) {
                $q->where(function ($sub) use ($roleName) {
                    $sub->whereHas('user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    })->orWhereHas('transactions.user.roles', function ($r) use ($roleName) {
                        $r->where('name', $roleName);
                    });
                });
            };

            if ($this->channel === 'shopee') {
                $applyChannel($query, 'Online Shopee');
            } elseif ($this->channel === 'grab') {
                $applyChannel($query, 'Online Grab');
            } elseif ($this->channel === 'online') {
                $applyChannel($query, 'Online');
            } elseif ($this->channel === 'digital') {
                $applyChannel($query, 'Digital');
            } elseif ($this->channel === 'kasir') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('user.roles', function ($r) {
                        $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                    })->whereDoesntHave('transactions.user.roles', function ($r) {
                        $r->whereIn('name', ['Online Shopee', 'Online Grab', 'Online', 'Digital']);
                    });
                });
            }
        }

        if ($this->search) {
            $s = $this->search;
            $cleanPhone = preg_replace('/[^0-9]/', '', $s);
            $query->where(function ($q) use ($s, $cleanPhone) {
                if (preg_match('/^\d{4}$/', $s)) {
                    $q->whereYear('updated_at', $s);
                } else {
                    $q->where('transaction_code', 'like', "%{$s}%")
                        ->orWhereHas('patients', function ($p) use ($s, $cleanPhone) {
                            $p->where('name', 'like', "%{$s}%")
                                ->orWhere('phone', 'like', "%{$s}%");
                            if (!empty($cleanPhone) && strlen($cleanPhone) >= 3 && $cleanPhone !== $s) {
                                $p->orWhere('phone', 'like', "%{$cleanPhone}%");
                            }
                        })
                        ->orWhereHas('user', function ($u) use ($s) {
                            $u->where('name', 'like', "%{$s}%")
                                ->orWhere('username', 'like', "%{$s}%");
                        })
                        ->orWhereHas('transactions.user', function ($u) use ($s) {
                            $u->where('name', 'like', "%{$s}%")
                                ->orWhere('username', 'like', "%{$s}%");
                        });
                }
            });
        }

        $transactions = $query->orderBy(DB::raw('COALESCE(created_at, updated_at)'), 'ASC')->get();

        $no = 1;
        $grandSubtotal = 0;
        $grandItemDiscount = 0;
        $grandCartDiscount = 0;
        $grandNetto = 0;
        $grandShippingFee = 0;

        foreach ($transactions as $trx) {
            $date = $trx->updated_at ? $trx->updated_at->format('d/m/Y') : ($trx->created_at ? $trx->created_at->format('d/m/Y') : '-');
            $time = $trx->updated_at ? $trx->updated_at->format('H:i:s') : ($trx->created_at ? $trx->created_at->format('H:i:s') : '-');
            $code = $trx->transaction_code ?? '-';

            $cartUsers = $trx->transactions->pluck('user')->filter();
            $info = MedicineTransactions::resolveChannelInfo($trx->user, $trx->user, $cartUsers);
            $channelName = $info['label'] ?? 'Kasir';

            $user = $info['effective_user'] ?? $trx->user;
            $userName = $user ? ($user->name ?? $user->username) : '-';

            $type = $trx->transaction_type ?? '-';
            $typeLabel = $type;
            if ($type == "KREDIT") $typeLabel = "UK (Resep Kredit)";
            else if ($type == "RESEP TUNAI") $typeLabel = "UM (Resep Tunai)";
            else if ($type == "HV/OTC") $typeLabel = "HV (Obat Bebas)";
            else if ($type == "UPDS") $typeLabel = "UP (UPDS)";

            $patientName = $trx->patients?->name ?? 'Umum / Tanpa Pasien';

            $isReturn = in_array(strtoupper(trim((string) $trx->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true) || strtoupper(trim((string) ($trx->transaction_code ?? ''))) === 'RT';
            $items = $trx->transactions->where('status', 1);

            $rawSubtotal = $items->isNotEmpty()
                ? (int) round($items->sum(fn ($i) => (float) ($i->total_price ?? ($i->item_price * $i->quantity))))
                : (is_numeric($trx->subtotal) ? (int) round((float) $trx->subtotal) : 0);
            $itemDiscount = (int) round($items->sum(fn ($i) => (float) ($i->discount ?? 0)));
            $cartDiscount = is_numeric($trx->discount) ? (int) round((float) $trx->discount) : 0;

            if ($isReturn) {
                $subtotal = -abs($rawSubtotal);
                $itemDiscount = 0;
                $cartDiscount = 0;
                $netto = -abs($rawSubtotal);
            } else {
                $subtotal = $rawSubtotal;
                $netto = $items->isNotEmpty()
                    ? ((int) round($items->sum(fn ($i) => (float) ($i->final_price ?? ($i->total_price - $i->discount)))) - $cartDiscount)
                    : ($subtotal - $cartDiscount);
            }

            $payMethod = \App\Support\PaymentMethod::label(
                $trx->payment_method,
                $trx->transfer_bank_name
            );

            $shippingFee = (float) ($trx->doctor_shipping_fee ?? 0);
            $shippingMethod = '-';
            if ($shippingFee > 0 && !empty($trx->doctor_shipping_payment_method)) {
                $shippingMethod = \App\Support\PaymentMethod::label(
                    $trx->doctor_shipping_payment_method,
                    $trx->doctor_shipping_bank_name
                );
            }

            $rows[] = [
                $no++,
                $date,
                $time,
                $code,
                $channelName,
                $typeLabel,
                $patientName,
                $userName,
                $subtotal,
                $itemDiscount,
                $cartDiscount,
                $netto,
                $payMethod,
                $shippingFee > 0 ? $shippingFee : 0,
                $shippingMethod,
            ];

            $grandSubtotal += $subtotal;
            $grandItemDiscount += $itemDiscount;
            $grandCartDiscount += $cartDiscount;
            $grandNetto += $netto;
            $grandShippingFee += $shippingFee;
        }

        $rows[] = [
            '',
            'TOTAL KESELURUHAN',
            '',
            '',
            '',
            '',
            '',
            '',
            $grandSubtotal,
            $grandItemDiscount,
            $grandCartDiscount,
            $grandNetto,
            '',
            $grandShippingFee > 0 ? $grandShippingFee : 0,
            '',
        ];

        return array_merge($header, $rows);
    }

    public function styles(Worksheet $sheet)
    {
        $lastCol = 'O';
        $lastRow = $sheet->getHighestRow();
        $headerRowIdx = 5;

        // Title styling
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(10);

        // Column header styling
        $sheet->getStyle("A{$headerRowIdx}:{$lastCol}{$headerRowIdx}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRowIdx}:{$lastCol}{$headerRowIdx}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF1F5F9');

        if ($lastRow >= $headerRowIdx) {
            $sheet->getStyle("A{$headerRowIdx}:{$lastCol}{$lastRow}")->getBorders()
                ->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            for ($i = $headerRowIdx; $i <= $lastRow; $i++) {
                $sheet->getRowDimension($i)->setRowHeight(22);
            }

            // Center align No, Tanggal, Jam, Kode, Channel, Tipe, Pembayaran, Tipe Jasa
            $sheet->getStyle("A{$headerRowIdx}:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$headerRowIdx}:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$headerRowIdx}:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$headerRowIdx}:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M{$headerRowIdx}:M{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("O{$headerRowIdx}:O{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Right align Currency columns (I, J, K, L, N)
            $dataStart = $headerRowIdx + 1;
            $sheet->getStyle("I{$dataStart}:L{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("I{$dataStart}:L{$lastRow}")->getNumberFormat()->setFormatCode('#,##0;-#,##0;0');
            $sheet->getStyle("N{$dataStart}:N{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("N{$dataStart}:N{$lastRow}")->getNumberFormat()->setFormatCode('#,##0;-#,##0;0');

            // Total row bold
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFont()->setBold(true);
            $sheet->getStyle("A{$lastRow}:{$lastCol}{$lastRow}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFF8FAFC');
        }
    }
}
