<?php

namespace App\Services\Finance;

use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\MedicineTransactions;
use App\Models\ReceivingDetails;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use App\Support\ReceivingInvoiceTotals;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NeracaReportService
{
    /**
     * Build Neraca (Balance Sheet) data comparing beginning and ending of period.
     */
    public function build(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $pharmacyId = (int) ($pharmacyIds[0] ?? 1);
        $dayBeforeStart = Carbon::parse($startDate)->subDay()->toDateString();
        $todayOperationalDate = FinanceShift::operationalDate(now());
        $periodEnd = max($startDate, min($endDate, $todayOperationalDate));

        // 1. Kas & Bank accounts of the pharmacy
        $accounts = FinanceAccount::where('category', 'Kas & Bank')
            ->where('pharmacy_id', $pharmacyId)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('balance', '!=', 0))
            ->orderByRaw("CASE WHEN account_type = 'kas_apotek' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'account_type', 'balance']);

        // Default standard bank names to present if not already present
        $standardBanks = ['BANK BPD', 'BANK BNI', 'BANK MANDIRI', 'BANK BRI', 'BANK BCA'];

        $kasRows = [];
        $bankRows = [];
        $existingBankNames = [];

        foreach ($accounts as $account) {
            $opening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $account->id)->first();
            $openingDate = $opening?->payment_date?->toDateString();

            $openingBalance = 0.0;
            if ($opening && $openingDate <= $startDate) {
                $openingBalance = $openingDate === $startDate
                    ? (float) $opening->amount
                    : $this->calculateAccountBalance($account->id, $opening, $dayBeforeStart);
            }

            $closingBalance = 0.0;
            if ($opening && $openingDate <= $periodEnd) {
                $closingBalance = $this->calculateAccountBalance($account->id, $opening, $periodEnd);
            } elseif ($periodEnd === now()->toDateString()) {
                $closingBalance = (float) $account->balance;
            }

            $name = strtoupper(trim((string) $account->name));
            if ($account->account_type === 'kas_apotek') {
                $kasRows[] = [
                    'label' => 'KAS',
                    'awal' => $openingBalance,
                    'akhir' => $closingBalance,
                    'selisih' => $closingBalance - $openingBalance,
                ];
            } else {
                // Normalize label (e.g. Rekening BCA -> BANK BCA)
                $label = str_starts_with($name, 'REKENING ') ? 'BANK ' . substr($name, 9) : (str_starts_with($name, 'BANK ') ? $name : 'BANK ' . $name);
                $existingBankNames[] = $label;
                $bankRows[] = [
                    'label' => $label,
                    'awal' => $openingBalance,
                    'akhir' => $closingBalance,
                    'selisih' => $closingBalance - $openingBalance,
                ];
            }
        }

        // If KAS was not in accounts, ensure a KAS row exists
        if (empty($kasRows)) {
            $kasRows[] = ['label' => 'KAS', 'awal' => 0.0, 'akhir' => 0.0, 'selisih' => 0.0];
        }

        // Ensure standard banks from template (BPD, BNI, Mandiri, BRI, BCA) are present
        foreach ($standardBanks as $stdBank) {
            if (!in_array($stdBank, $existingBankNames, true)) {
                $bankRows[] = [
                    'label' => $stdBank,
                    'awal' => 0.0,
                    'akhir' => 0.0,
                    'selisih' => 0.0,
                ];
            }
        }

        // Sort banks to match template order: BPD, BNI, MANDIRI, BRI, BCA, others
        usort($bankRows, function ($a, $b) use ($standardBanks) {
            $posA = array_search($a['label'], $standardBanks, true);
            $posB = array_search($b['label'], $standardBanks, true);
            $posA = $posA === false ? 999 : $posA;
            $posB = $posB === false ? 999 : $posB;
            return $posA <=> $posB;
        });

        // 2. Piutang Dagang (Penjualan Kredit yang belum lunas)
        $piutangAwal = $this->receivableBalanceAtDate($pharmacyIds, $dayBeforeStart);
        $piutangAkhir = $this->receivableBalanceAtDate($pharmacyIds, $periodEnd);
        $piutangDagangRow = [
            'label' => 'PIUTANG DAGANG',
            'awal' => $piutangAwal,
            'akhir' => $piutangAkhir,
            'selisih' => $piutangAkhir - $piutangAwal,
        ];

        // 3. Piutang Usaha (0 sesuai konfirmasi)
        $piutangUsahaRow = [
            'label' => 'PIUTANG USAHA',
            'awal' => 0.0,
            'akhir' => 0.0,
            'selisih' => 0.0,
        ];

        // 4. Hutang Dagang (Pembelian Kredit yang belum lunas)
        $hutangAwal = $this->payableBalanceAtDate($pharmacyIds, $dayBeforeStart);
        $hutangAkhir = $this->payableBalanceAtDate($pharmacyIds, $periodEnd);
        // Note: For liability, SELISIH in template is AWAL - AKHIR (or -(AKHIR - AWAL))
        // because increasing liability decreases net worth/profit.
        $hutangDagangRow = [
            'label' => 'HUTANG DAGANG',
            'awal' => $hutangAwal,
            'akhir' => $hutangAkhir,
            'selisih' => $hutangAwal - $hutangAkhir,
        ];

        // 5. Laba Tanpa Stok: (Kas + Bank + Piutang) - Hutang
        $totalAsetLancarAwal = array_sum(array_column($kasRows, 'awal'))
            + array_sum(array_column($bankRows, 'awal'))
            + $piutangAwal;
        $totalAsetLancarAkhir = array_sum(array_column($kasRows, 'akhir'))
            + array_sum(array_column($bankRows, 'akhir'))
            + $piutangAkhir;

        $labaTanpaStokAwal = $totalAsetLancarAwal - $hutangAwal;
        $labaTanpaStokAkhir = $totalAsetLancarAkhir - $hutangAkhir;
        $labaTanpaStok = [
            'label' => 'LABA TANPA STOK',
            'awal' => $labaTanpaStokAwal,
            'akhir' => $labaTanpaStokAkhir,
            'selisih' => $labaTanpaStokAkhir - $labaTanpaStokAwal,
        ];

        // 6. Persediaan: Nilai total stok (Stok * raw_price)
        $persediaanAwal = $this->inventoryValuationAtDate($pharmacyIds, $dayBeforeStart);
        $persediaanAkhir = $this->inventoryValuationAtDate($pharmacyIds, $periodEnd);
        $persediaanRow = [
            'label' => 'PERSEDIAAN',
            'awal' => $persediaanAwal,
            'akhir' => $persediaanAkhir,
            'selisih' => $persediaanAkhir - $persediaanAwal,
        ];

        // 7. Laba Dengan Stok: Laba Tanpa Stok + Persediaan
        $labaDenganStokAwal = $labaTanpaStokAwal + $persediaanAwal;
        $labaDenganStokAkhir = $labaTanpaStokAkhir + $persediaanAkhir;
        $labaDenganStok = [
            'label' => 'LABA DENGAN STOK',
            'awal' => $labaDenganStokAwal,
            'akhir' => $labaDenganStokAkhir,
            'selisih' => $labaDenganStokAkhir - $labaDenganStokAwal,
        ];

        // 8. Harta (Wealth / Total Aktiva): Kas + Bank + Piutang + Persediaan
        $hartaAwal = $totalAsetLancarAwal + $persediaanAwal;
        $hartaAkhir = $totalAsetLancarAkhir + $persediaanAkhir;
        $harta = [
            'label' => 'HARTA',
            'awal' => $hartaAwal,
            'akhir' => $hartaAkhir,
            'selisih' => $hartaAkhir - $hartaAwal,
        ];

        // 9. Kewajiban (Total Pasiva / Liabilitas): Hutang Dagang
        $kewajiban = [
            'label' => 'KEWAJIBAN',
            'awal' => $hutangAwal,
            'akhir' => $hutangAkhir,
            'selisih' => $hutangAkhir - $hutangAwal,
        ];

        // 10. Saldo (Ekuitas): Harta - Kewajiban (= Laba Dengan Stok)
        $saldoAwal = $hartaAwal - $hutangAwal;
        $saldoAkhir = $hartaAkhir - $hutangAkhir;
        $saldo = [
            'label' => 'SALDO',
            'awal' => $saldoAwal,
            'akhir' => $saldoAkhir,
            'selisih' => $saldoAkhir - $saldoAwal,
        ];

        // Build items list with numbers 1..10 exactly as in screenshot
        $numberedItems = [];
        $no = 1;

        foreach ($kasRows as $row) {
            $numberedItems[] = array_merge(['no' => $no++], $row);
        }
        foreach ($bankRows as $row) {
            $numberedItems[] = array_merge(['no' => $no++], $row);
        }
        $numberedItems[] = array_merge(['no' => $no++], $piutangDagangRow);
        $numberedItems[] = array_merge(['no' => $no++], $piutangUsahaRow);
        $numberedItems[] = array_merge(['no' => $no++], $hutangDagangRow);

        $persediaanItem = array_merge(['no' => $no++], $persediaanRow);

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dayBeforeStart' => $dayBeforeStart,
            'periodEnd' => $periodEnd,
            'items' => $numberedItems,
            'laba_tanpa_stok' => $labaTanpaStok,
            'persediaan' => $persediaanItem,
            'laba_dengan_stok' => $labaDenganStok,
            'harta' => $harta,
            'kewajiban' => $kewajiban,
            'saldo' => $saldo,
        ];
    }

    private function calculateAccountBalance(int $accountId, FinancePayment $opening, string $asOfDate): float
    {
        $openingDate = $opening->payment_date?->format('Y-m-d');
        if (!$openingDate || $openingDate > $asOfDate) return 0;

        $payments = FinancePayment::where('account_id', $accountId)
            ->where('id', '!=', $opening->id)
            ->whereDate('payment_date', '>=', $openingDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->get(['payment_type', 'amount', 'transfer_account_id']);

        $balance = (float) $opening->amount;
        foreach ($payments as $payment) {
            if (in_array($payment->payment_type, ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'], true)) {
                $balance += (float) $payment->amount;
            } elseif (in_array($payment->payment_type, ['KREDIT', 'CASH', 'KONSINYASI', 'BIAYA', 'SETOR'], true)) {
                $balance -= (float) $payment->amount;
            }
        }
        $balance += (float) FinancePayment::where('payment_type', 'SETOR')
            ->where('transfer_account_id', $accountId)
            ->whereDate('payment_date', '>=', $openingDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->sum('amount');

        return $balance;
    }

    private function receivableBalanceAtDate(array $pharmacyIds, string $asOfDate): float
    {
        $transactions = MedicineTransactions::query()
            ->with(['transactions' => fn ($query) => $query->where('status', 1)])
            ->withSum(['payments as finance_paid' => fn ($query) => $query
                ->where('payment_type', 'PIUTANG')
                ->whereDate('payment_date', '<=', $asOfDate)], 'amount')
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT', 'RETUR KREDIT'])
            ->where(function ($period) use ($asOfDate) {
                $period->where('created_at', '<', FinanceShift::endExclusive($asOfDate))
                    ->orWhere(function ($legacy) use ($asOfDate) {
                        $legacy->whereNull('created_at')->where('updated_at', '<', FinanceShift::endExclusive($asOfDate));
                    });
            })
            ->tap(fn ($query) => LiphTransactionScope::applySalesStatus($query))
            ->get(['id', 'debtor_id', 'transaction_type', 'subtotal', 'paid']);

        $balancesByDebtor = [];
        $returnsByDebtor = [];

        foreach ($transactions as $transaction) {
            $debtorKey = (string) ((int) ($transaction->debtor_id ?? 0));

            if ($transaction->transaction_type === 'RETUR KREDIT') {
                $returnsByDebtor[$debtorKey] = ($returnsByDebtor[$debtorKey] ?? 0)
                    + abs((float) ($transaction->subtotal ?? 0));
                continue;
            }

            $total = (float) ($transaction->subtotal ?? 0);
            if ($total <= 0) {
                $total = (float) $transaction->transactions->sum(
                    fn ($item) => $item->final_price ?: $item->total_price
                );
            }

            $total = max(0, $total);
            $paid = min($total, max(
                (float) ($transaction->finance_paid ?? 0),
                (float) ($transaction->paid ?? 0)
            ));
            $balancesByDebtor[$debtorKey] = ($balancesByDebtor[$debtorKey] ?? 0) + max(0, $total - $paid);
        }

        foreach ($returnsByDebtor as $debtorKey => $returnAmount) {
            if (isset($balancesByDebtor[$debtorKey])) {
                $balancesByDebtor[$debtorKey] = max(0, $balancesByDebtor[$debtorKey] - $returnAmount);
            }
        }

        return (float) array_sum($balancesByDebtor);
    }

    private function payableBalanceAtDate(array $pharmacyIds, string $asOfDate): float
    {
        // PMI (Apotek ID 1): Pemesanan dan pembelian dari supplier dicatat di Gudang PMI (ID 9).
        if (in_array(1, $pharmacyIds, true) && !in_array(9, $pharmacyIds, true)) {
            $pharmacyIds[] = 9;
        }

        $details = ReceivingDetails::query()
            ->with([
                'receiving:id,pharmacy_id,status,date,created_at',
                'creditor:code,ppn_type',
                'receiving_items:id,receiving_details_id,total,raw_price,qty_received',
                'payments' => fn ($query) => $query->whereDate('payment_date', '<=', $asOfDate),
            ])
            ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
            ->whereHas('receiving', function ($query) use ($pharmacyIds, $asOfDate) {
                $query->whereIn('pharmacy_id', $pharmacyIds)
                    ->whereIn('status', [1, 2, 3])
                    ->where(function ($date) use ($asOfDate) {
                        $date->whereRaw("COALESCE(STR_TO_DATE(NULLIF(date, ''), '%d/%m/%Y'), STR_TO_DATE(NULLIF(date, ''), '%Y-%m-%d'), DATE(created_at)) <= ?", [$asOfDate]);
                    });
            })
            ->get();

        $balance = 0.0;
        foreach ($details as $detail) {
            $subtotal = 0.0;
            foreach ($detail->receiving_items as $item) {
                $lineTotal = (float) ($item->total ?? 0);
                if ($lineTotal <= 0) {
                    $lineTotal = (float) ($item->raw_price ?? 0) * (float) ($item->qty_received ?? 0);
                }
                $subtotal += $lineTotal;
            }

            $ppnType = strtoupper(trim((string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA')));
            $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];
            $paid = (float) $detail->payments->sum('amount');
            $balance += max(0, $invoiceTotal - $paid);
        }

        return $balance;
    }

    /**
     * Calculate automatic inventory valuation for the target pharmacy as of a date.
     */
    private function inventoryValuationAtDate(array $pharmacyIds, string $asOfDate): float
    {
        $targetPharmacyId = (int) ($pharmacyIds[0] ?? 1);
        $warehouseId = function_exists('getWarehousePharmacyId') ? getWarehousePharmacyId() : 9;
        $canSeeWarehouse = function_exists('canAccessWarehouseStock') ? canAccessWarehouseStock($targetPharmacyId) : in_array($targetPharmacyId, [1, 9, 6], true);
        $counterPharmacyId = (function_exists('isWarehousePharmacy') && isWarehousePharmacy($targetPharmacyId)) ? 1 : $targetPharmacyId;

        // Current counter stock valuation
        $counterValuation = (float) DB::table('medicine_transfer_items as mti')
            ->join('batches as b', 'b.id', '=', 'mti.batches_id')
            ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
            ->where('b.pharmacy_id', $counterPharmacyId)
            ->where('mti.status', 1)
            ->where(function ($q) {
                $q->whereNull('mti.source_type')->orWhere('mti.source_type', '!=', 'retur_gudang');
            })
            ->selectRaw('SUM(mti.qty * COALESCE(NULLIF(m.raw_price, 0), 0)) as total_val')
            ->value('total_val');

        // Storage / warehouse valuation if applicable
        $storageValuation = 0.0;
        if ($canSeeWarehouse) {
            $storageValuation = (float) DB::table('batches as b')
                ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                ->where('b.pharmacy_id', $warehouseId)
                ->where('b.stock', '>', 0)
                ->selectRaw('SUM(b.stock * COALESCE(NULLIF(m.raw_price, 0), 0)) as total_val')
                ->value('total_val');
        }

        $currentTotalValuation = $counterValuation + $storageValuation;

        // Reconcile backwards from today to $asOfDate using items_log movements
        $trackedPharmacies = $canSeeWarehouse ? array_unique([$counterPharmacyId, $warehouseId]) : [$counterPharmacyId];
        $cutoff = Carbon::parse($asOfDate)->endOfDay()->toDateTimeString();

        $deltaSinceCutoff = (float) DB::table('items_log as il')
            ->join('batches as b', 'b.id', '=', 'il.batches_id')
            ->join('medicines as m', 'm.id', '=', 'il.medicine_id')
            ->whereIn('b.pharmacy_id', $trackedPharmacies)
            ->where('il.date', '>', $cutoff)
            ->selectRaw('SUM(
                CASE 
                    WHEN il.status IN (1, 4) THEN -il.qty * COALESCE(NULLIF(m.raw_price, 0), 0)
                    WHEN il.status IN (2, 3) THEN il.qty * COALESCE(NULLIF(m.raw_price, 0), 0)
                    WHEN il.status = 5 THEN 
                        CASE 
                            WHEN il.total IS NOT NULL AND TRIM(il.total) REGEXP "^[+-]?[0-9]+([.][0-9]+)?$" 
                            THEN CAST(il.total AS DECIMAL(15,2)) * COALESCE(NULLIF(m.raw_price, 0), 0)
                            ELSE (CAST(il.qty_after AS DECIMAL(15,2)) - CAST(il.qty_before AS DECIMAL(15,2))) * COALESCE(NULLIF(m.raw_price, 0), 0)
                        END
                    WHEN il.status IN (6, 7) THEN 
                        (CAST(il.qty_after AS DECIMAL(15,2)) - CAST(il.qty_before AS DECIMAL(15,2))) * COALESCE(NULLIF(m.raw_price, 0), 0)
                    WHEN il.status = 8 THEN il.qty * COALESCE(NULLIF(m.raw_price, 0), 0)
                    WHEN il.status = 9 THEN -il.qty * COALESCE(NULLIF(m.raw_price, 0), 0)
                    ELSE 0
                END
            ) as delta_val')
            ->value('delta_val');

        return max(0, $currentTotalValuation - $deltaSinceCutoff);
    }
}

