<?php

namespace App\Exports\Report;

use App\Models\MedicineTransactions;
use App\Support\LiphTransactionScope;
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

    public const STANDARD_BANKS = ['CASH', 'BELUM BAYAR',
        'TRANSFER MANDIRI', 'TRANSFER BNI', 'TRANSFER BCA', 'TRANSFER BRI', 'TRANSFER BTN',
        'DEBIT MANDIRI', 'DEBIT BNI', 'DEBIT BCA', 'DEBIT BRI', 'DEBIT BTN',
        'QRIS MANDIRI', 'QRIS BNI', 'QRIS BCA', 'QRIS BRI', 'QRIS BTN'];

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
        return \App\Support\PaymentMethod::label($trx->payment_method ?? null, $trx->transfer_bank_name ?? null);
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
            'updated_at',
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
                    $q->select('id', 'transaction_id', 'medicine_id', 'quantity', 'item_price', 'discount', 'total_price', 'final_price', 'status', 'cart_type')
                        ->where('status', 1);
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
            ->whereIn('transaction_type', array_keys(LiphExport::TYPE_MAP));

        LiphTransactionScope::applySalesStatus($query);
        LiphTransactionScope::applyPeriod(
            $query,
            $this->startDate,
            $this->endDate,
            $this->shiftType,
            $this->shift
        );

        $allTransactions = $query->orderBy('updated_at', 'asc')->get();

        // 2. Classify transactions by bank/category
        $grouped = [];
        $groupedByPaymentClass = ['KREDIT' => [], 'CASH' => []];
        foreach ($allTransactions as $trx) {
            $cat = self::resolveCategory($trx) ?: 'Transfer Lainnya';
            $typeGroup = LiphExport::TYPE_MAP[$trx->transaction_type][0] ?? 'tunai';
            $paymentClass = $typeGroup === 'kredit' ? 'KREDIT' : 'CASH';
            $groupedByPaymentClass[$paymentClass][$cat][] = $trx;

            // Kredit is a transaction type, not a selected payment method.
            // Keep those transactions out of the bank/method detail sheets.
            if ($paymentClass !== 'KREDIT') {
                $grouped[$cat][] = $trx;
            }
        }

        // 3. Prepare master bank list
        $activeCategories = array_keys($grouped);
        $banksList = array_values(array_unique(array_merge(self::STANDARD_BANKS, $activeCategories)));
        // Put the aggregate Kredit row first, preserving the existing order
        // of Cash and every payment method that follows it.
        array_unshift($banksList, 'KREDIT');

        // 4. Build one flat recap: Cash, Kredit, then each payment method once.
        $allBanksData = [];
        $activeBanksWithData = [];

        foreach ($banksList as $bank) {
            $bankTransactions = $bank === 'KREDIT'
                ? collect($groupedByPaymentClass['KREDIT'])->flatten(1)
                : collect($grouped[$bank] ?? []);
            $allBanksData[$bank] = self::summarizeTransactions($bankTransactions);

            if ($bank !== 'KREDIT' && ($bankTransactions->isNotEmpty() || in_array($bank, self::STANDARD_BANKS, true))) {
                $activeBanksWithData[] = $bank;
            }
        }
        $activeBanksWithData = array_values(array_unique($activeBanksWithData));

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

    private static function summarizeTransactions($transactions): array
    {
        $summary = ['struk_count' => $transactions->count(), 'qty_sum' => 0, 'gross_sum' => 0, 'discount_sum' => 0, 'net_sum' => 0];

        foreach ($transactions as $trx) {
            $trxDiscount = (int) ($trx->discount ?? 0);
            $summary['discount_sum'] += $trxDiscount;
            $items = $trx->transactions;
            $originalMap = LiphExport::TYPE_MAP[$trx->transaction_type] ?? null;
            $originalLabel = $originalMap[1] ?? '';

            if ($items->isEmpty()) {
                $subtotal = (int) ($trx->subtotal ?? 0);
                $isReturn = in_array($originalLabel, ['Retur Tunai', 'Retur Kredit'], true);
                $signedSubtotal = $isReturn ? -abs($subtotal) : $subtotal;
                $summary['gross_sum'] += $signedSubtotal;
                $summary['net_sum'] += $signedSubtotal - $trxDiscount;
                $summary['qty_sum'] += ($isReturn ? -1 : 1);
                continue;
            }

            $trxGross = 0;
            foreach ($items as $item) {
                $qty = (float) ($item->quantity ?? 0);
                $price = (float) ($item->item_price ?? 0);
                $itemDisc = (float) ($item->discount ?? 0);
                $final = (float) ($item->final_price ?? ($item->total_price ?? ($qty * $price - $itemDisc)));
                $effectiveType = $item->cart_type
                    ? (LiphExport::CART_TYPE_ABBR[$item->cart_type] ?? $item->cart_type)
                    : $trx->transaction_type;
                $effectiveMap = LiphExport::TYPE_MAP[$effectiveType] ?? $originalMap;
                $isReturn = in_array($effectiveMap[1] ?? $originalLabel, ['Retur Tunai', 'Retur Kredit'], true);

                $signedQty = $isReturn ? -abs($qty) : $qty;
                $signedFinal = $isReturn ? -abs($final) : $final;

                $summary['qty_sum'] += $signedQty;
                $trxGross += $signedFinal;
            }

            $roundedGross = (int) round($trxGross);
            $summary['gross_sum'] += $roundedGross;
            $summary['net_sum'] += ($roundedGross - $trxDiscount);
        }

        return $summary;
    }
}
