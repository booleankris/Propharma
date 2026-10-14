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
            $grouped[$cat][] = $trx;

            $typeGroup = LiphExport::TYPE_MAP[$trx->transaction_type][0] ?? 'tunai';
            $paymentClass = $typeGroup === 'kredit' ? 'KREDIT' : 'CASH';
            $groupedByPaymentClass[$paymentClass][$cat][] = $trx;
        }

        // 3. Prepare master bank list
        $activeCategories = array_keys($grouped);
        $banksList = array_unique(array_merge(self::STANDARD_BANKS, $activeCategories));

        // 4. Compute summary per transaction class, then payment method/bank.
        $allBanksData = ['KREDIT' => [], 'CASH' => []];
        $activeBanksWithData = [];

        foreach ($groupedByPaymentClass as $paymentClass => $categories) {
            foreach ($banksList as $bank) {
                $bankTransactions = collect($categories[$bank] ?? []);
                $strukCount = $bankTransactions->count();
                $qtySum = 0;
                $grossSum = 0;
                $discSum = 0;
                $netSum = 0;

                foreach ($bankTransactions as $trx) {
                    // LIPH casts each monetary value to whole rupiah before summing.
                    $trxDiscount = (int) ($trx->discount ?? 0);
                    $discSum += $trxDiscount;

                    $items = $trx->transactions;
                    $originalMap = LiphExport::TYPE_MAP[$trx->transaction_type] ?? null;
                    $originalLabel = $originalMap[1] ?? '';
                    if ($items->isEmpty()) {
                        $subtotal = (int) ($trx->subtotal ?? 0);
                        $isReturn = in_array($originalLabel, ['Retur Tunai', 'Retur Kredit'], true);
                        $signedSubtotal = $isReturn ? -abs($subtotal) : $subtotal;
                        $grossSum += $signedSubtotal;
                        $netSum += ($signedSubtotal - $trxDiscount);
                        $qtySum += 1;
                    } else {
                        foreach ($items as $item) {
                            $qty = (int) ($item->quantity ?? 0);
                            $price = (float) ($item->item_price ?? 0);
                            $itemDisc = (float) ($item->discount ?? 0);
                            $final = (int) ($item->final_price ?? ($item->total_price ?? ($qty * $price - $itemDisc)));

                            $effectiveType = $item->cart_type
                                ? (LiphExport::CART_TYPE_ABBR[$item->cart_type] ?? $item->cart_type)
                                : $trx->transaction_type;
                            $effectiveMap = LiphExport::TYPE_MAP[$effectiveType] ?? $originalMap;
                            $isReturn = in_array($effectiveMap[1] ?? $originalLabel, ['Retur Tunai', 'Retur Kredit'], true);

                            $qtySum += $qty;
                            $grossSum += $isReturn ? -abs($qty * $price) : ($qty * $price);
                            $netSum += $isReturn ? -abs($final) : $final;
                        }

                        // LIPH's Netto Akhir subtracts each transaction-level
                        // discount once, after summing all eligible cart items.
                        $netSum -= $trxDiscount;
                    }
                }

                $allBanksData[$paymentClass][$bank] = [
                    'struk_count'  => $strukCount,
                    'qty_sum'      => $qtySum,
                    'gross_sum'    => $grossSum,
                    'discount_sum' => $discSum,
                    'net_sum'      => $netSum,
                ];

                // Keep one detail sheet per payment method/category.
                if ($strukCount > 0 || in_array($bank, self::STANDARD_BANKS)) {
                    $activeBanksWithData[] = $bank;
                }
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
}
