<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BankSalesExport implements WithMultipleSheets
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $pharmacyName;
    protected $pharmacyAddress;
    protected $shift;
    protected $shiftType;

    public const STANDARD_BANKS = ['CASH', 'BNI', 'Mandiri', 'BCA', 'BRI', 'BSI', 'BTN', 'QRIS', 'DEBIT'];

    public function __construct(
        $pharmacyId,
        $startDate,
        $endDate,
        $pharmacyName = 'APOTEK SAHABAT',
        $pharmacyAddress = '',
        $shift = null,
        $shiftType = 'semua'
    ) {
        $this->pharmacyId      = $pharmacyId;
        $this->startDate       = Carbon::parse($startDate)->startOfDay();
        $this->endDate         = Carbon::parse($endDate)->endOfDay();
        $this->pharmacyName    = $pharmacyName;
        $this->pharmacyAddress = $pharmacyAddress;
        $this->shift           = $shift;
        $this->shiftType       = $shiftType;
    }

    public static function resolveCategory($trx): string
    {
        $bank = trim($trx->transfer_bank_name ?? '');
        $method = strtoupper(trim($trx->payment_method ?? ''));

        if ($method === 'CASH') {
            return 'CASH';
        }

        if ($method === 'QRIS') {
            return !empty($bank) && !is_numeric($bank) ? "QRIS {$bank}" : 'QRIS';
        }

        if ($method === 'DEBIT') {
            return !empty($bank) && !is_numeric($bank) ? "DEBIT {$bank}" : 'DEBIT';
        }

        if ($method === 'TRANSFER') {
            return !empty($bank) && !is_numeric($bank) ? $bank : 'Transfer Lainnya';
        }

        if (!empty($bank) && !is_numeric($bank)) {
            return $bank;
        }

        return 'CASH';
    }

    public function sheets(): array
    {
        @ini_set('memory_limit', '512M');

        // 1. Fetch all transactions within date range (including CASH and Banks) with light column selection
        $query = MedicineTransactions::select(
            'id',
            'pharmacy_id',
            'status',
            'created_at',
            'transaction_code',
            'transaction_type',
            'payment_method',
            'transfer_bank_name',
            'subtotal',
            'discount',
            'paid',
            'patient_id',
            'doctor_id',
            'user_id',
            'shift_logs_id'
        )
            ->with([
                'transactions' => function ($q) {
                    $q->select('id', 'transaction_id', 'medicine_id', 'quantity', 'item_price', 'discount', 'total_price', 'final_price');
                },
                'transactions.medicine' => function ($q) {
                    $q->select('id', 'code', 'name');
                },
                'patients' => function ($q) {
                    $q->select('id', 'name');
                },
                'doctors' => function ($q) {
                    $q->select('id', 'name');
                },
                'user' => function ($q) {
                    $q->select('id', 'name');
                },
                'shift_logs' => function ($q) {
                    $q->select('id', 'shift_id');
                },
            ])
            ->where('pharmacy_id', $this->pharmacyId)
            ->where('status', 1)
            ->whereBetween('created_at', [$this->startDate, $this->endDate]);

        if ($this->shiftType === 'shift' && !empty($this->shift)) {
            $query->whereHas('shift_logs', function ($q) {
                $q->where('shift_id', $this->shift);
            });
        }

        $allTransactions = $query->orderBy('created_at', 'asc')->get();

        // 2. Classify transactions by bank/category
        $grouped = [];
        foreach ($allTransactions as $trx) {
            $cat = self::resolveCategory($trx) ?: 'Transfer Lainnya';
            $grouped[$cat][] = $trx;
        }

        // 3. Prepare master bank list
        $activeCategories = array_keys($grouped);
        $banksList = array_unique(array_merge(self::STANDARD_BANKS, $activeCategories));

        // 4. Compute summary per bank
        $allBanksData = [];
        $activeBanksWithData = [];

        foreach ($banksList as $bank) {
            $bankTransactions = collect($grouped[$bank] ?? []);
            $strukCount = $bankTransactions->count();
            $qtySum = 0;
            $grossSum = 0;
            $discSum = 0;
            $netSum = 0;

            foreach ($bankTransactions as $trx) {
                $trxDiscount = (float) ($trx->discount ?? 0);
                $discSum += $trxDiscount;

                $items = $trx->transactions;
                if ($items->isEmpty()) {
                    $subtotal = (float) ($trx->subtotal ?? 0);
                    $grossSum += $subtotal;
                    $netSum += ($subtotal - $trxDiscount);
                    $qtySum += 1;
                } else {
                    foreach ($items as $item) {
                        $qty = (int) ($item->quantity ?? 0);
                        $price = (float) ($item->item_price ?? 0);
                        $itemDisc = (float) ($item->discount ?? 0);
                        $final = (float) ($item->final_price ?? ($item->total_price ?? ($qty * $price - $itemDisc)));

                        $qtySum += $qty;
                        $grossSum += ($qty * $price);
                        $netSum += $final;
                    }
                }
            }

            $allBanksData[$bank] = [
                'struk_count'  => $strukCount,
                'qty_sum'      => $qtySum,
                'gross_sum'    => $grossSum,
                'discount_sum' => $discSum,
                'net_sum'      => $netSum,
            ];

            // Include in sheet tabs if it has transactions or is standard bank
            if ($strukCount > 0 || in_array($bank, self::STANDARD_BANKS)) {
                $activeBanksWithData[] = $bank;
            }
        }

        // 5. Build Sheets
        $sheets = [];

        // Sheet 1: Rekap Semua Bank
        $sheets[] = new BankSalesSheetExport(
            $this->pharmacyId,
            $this->startDate,
            $this->endDate,
            $this->pharmacyName,
            $this->pharmacyAddress,
            $this->shift,
            $this->shiftType,
            null,
            true, // isRecap
            $allBanksData,
            []
        );

        // Sheets 2..N: Individual Bank Sheets
        foreach ($activeBanksWithData as $bank) {
            $sheets[] = new BankSalesSheetExport(
                $this->pharmacyId,
                $this->startDate,
                $this->endDate,
                $this->pharmacyName,
                $this->pharmacyAddress,
                $this->shift,
                $this->shiftType,
                $bank,
                false, // isRecap
                [],
                $grouped[$bank] ?? []
            );
        }

        return $sheets;
    }
}
