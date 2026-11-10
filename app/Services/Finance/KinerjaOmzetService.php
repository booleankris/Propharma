<?php

namespace App\Services\Finance;

use App\Exports\Report\BankSalesExport;
use App\Exports\Report\LiphExport;
use App\Models\Debtors;
use App\Models\MedicineTransactions;
use App\Models\Receiving;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class KinerjaOmzetService
{
    private function blankDay(string $date): array
    {
        return [
            'date' => $date,
            'sales' => [
                'cash' => ['morning' => 0, 'evening' => 0],
                'bank' => ['morning' => 0, 'evening' => 0],
                'credit' => ['morning' => [], 'evening' => []],
                'returns' => ['morning' => 0, 'evening' => 0],
                'cashBankTotal' => 0.0,
                'creditTotal' => 0.0,
                'total' => 0.0,
                'total_sd' => 0.0,
            ],
            'purchases' => ['pbf' => 0, 'consignment' => 0, 'return' => 0, 'cash' => 0, 'discountOff' => 0, 'discountOffSd' => 0, 'ppn' => 0, 'total' => 0, 'sd' => 0, 'exVat' => 0],
            'other_income' => 0.0,
            'other_income_sd' => 0.0,
            'expense' => 0.0,
            'saving' => 0.0,
            'expense_sd' => 0.0,
            'margin' => 0.0,
            'margin_sd' => 0.0,
            'profit_loss_sd' => 0.0,
        ];
    }

    private function number($value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? 0));
    }

    private function addPurchase(array &$day, string $kind, float $amount, string $ppnType): void
    {
        $type = mb_strtoupper(trim($ppnType));
        $sign = $amount < 0 ? -1 : 1;
        $absolute = abs($amount);
        $dpp = match ($type) {
            'EXCLUDE', 'INCLUDE' => $sign * floor($absolute / 1.11),
            default => $amount,
        };
        $ppn = in_array($type, ['EXCLUDE', 'INCLUDE'], true) ? $amount - $dpp : 0;
        // receiving_items.total is already saved with invoice tax included when applicable.
        $day['purchases'][$kind] += $amount;
        $day['purchases']['ppn'] += $ppn;
    }

    private function subtractPurchaseReturn(array &$day, string $kind, float $amount, string $ppnType): void
    {
        $type = mb_strtoupper(trim($ppnType));
        $sign = $amount < 0 ? -1 : 1;
        $absolute = abs($amount);
        if ($type === 'EXCLUDE') {
            // items_log.total stores the returned line's base value for EXCLUDE invoices.
            $ppn = $sign * floor($absolute * 0.11);
            $gross = $amount + $ppn;
        } elseif ($type === 'INCLUDE') {
            $dpp = $sign * floor($absolute / 1.11);
            $ppn = $amount - $dpp;
            $gross = $amount;
        } else {
            $ppn = 0;
            $gross = $amount;
        }
        $day['purchases']['return'] += $gross;
    }

    /**
     * Build the workbook-style daily sales and purchase figures for one month.
     */
    public function build(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $days = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $key = $date->toDateString();
            $days[$key] = $this->blankDay($key);
        }

        $debtors = Debtors::query()->orderBy('name')->get(['id', 'name']);
        $extraDebtors = [];
        $transactions = MedicineTransactions::query()
            ->with([
                'transactions' => fn($query) => $query->where('status', 1),
                'debtors',
                'service',
            ])
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', array_merge(array_keys(LiphExport::TYPE_MAP), ['JASA']))
            ->tap(fn($q) => LiphTransactionScope::applySalesStatus($q))
            ->tap(fn($q) => LiphTransactionScope::applyPeriod($q, Carbon::parse($startDate), Carbon::parse($endDate)))
            ->orderBy('created_at')
            ->get();

        foreach ($transactions as $transaction) {
            $effectiveAt = $transaction->created_at ? Carbon::parse($transaction->created_at) : ($transaction->updated_at ? Carbon::parse($transaction->updated_at) : null);
            $date = FinanceShift::operationalDate($effectiveAt);
            if (!$date || !isset($days[$date])) {
                continue;
            }
            $shift = FinanceShift::key($effectiveAt);
            $amount = BankSalesExport::calculateTransactionAmount($transaction);

            $typeGroup = LiphExport::TYPE_MAP[$transaction->transaction_type][0] ?? 'tunai';

            if ($typeGroup === 'kredit') {
                $debtorId = (int) ($transaction->debtor_id ?? 0);
                $name = $transaction->debtors?->name ?: 'Debitur tidak tercatat';
                if (!$transaction->debtors && $debtorId > 0) {
                    $extraDebtors[$debtorId] = $name;
                }
                $days[$date]['sales']['credit'][$shift][$debtorId] = ($days[$date]['sales']['credit'][$shift][$debtorId] ?? 0) + $amount;
                if ($amount < 0) {
                    $days[$date]['sales']['returns'][$shift] += $amount;
                }
            } else {
                $category = BankSalesExport::resolveCategory($transaction);
                $catUpper = strtoupper(trim((string) $category));

                if (in_array($catUpper, ['CASH', 'TUNAI'], true)) {
                    $days[$date]['sales']['cash'][$shift] += $amount;
                } elseif ($catUpper !== 'BELUM BAYAR' && $catUpper !== 'KREDIT') {
                    $days[$date]['sales']['bank'][$shift] += $amount;
                }
                if ($amount < 0) {
                    $days[$date]['sales']['returns'][$shift] += $amount;
                }
            }
        }

        $purchasePharmacyIds = $pharmacyIds;
        if (in_array(1, $purchasePharmacyIds, true) && !in_array(9, $purchasePharmacyIds, true)) {
            $purchasePharmacyIds[] = 9;
        }

        $dailyInvoices = \App\Exports\Orders\InvoiceSingleSheetExport::queryDailyInvoices($purchasePharmacyIds, $startDate, $endDate);
        foreach ($dailyInvoices as $date => $invSummary) {
            if (isset($days[$date])) {
                $days[$date]['purchases']['pbf'] = (float) $invSummary['pbf'];
                $days[$date]['purchases']['consignment'] = (float) $invSummary['consignment'];
                $days[$date]['purchases']['cash'] = (float) $invSummary['cash'];
                $days[$date]['purchases']['ppn'] = (float) $invSummary['ppn'];
                $days[$date]['purchases']['total'] = (float) $invSummary['total'];
                $days[$date]['purchases']['exVat'] = (float) $invSummary['dpp'];
            }
        }

        $returnInvoiceMeta = DB::table('receiving_items as ri')
            ->join('receiving_details as rd', 'rd.id', '=', 'ri.receiving_details_id')
            ->leftJoin('creditors as c', 'c.code', '=', 'rd.creditor_code')
            ->whereNotNull('ri.batches_id')
            ->select('rd.receiving_id', 'ri.batches_id')
            ->selectRaw('MAX(rd.invoice_payment) as invoice_payment, MAX(COALESCE(rd.invoice_ppn, c.ppn_type, "TANPA")) as invoice_ppn')
            ->groupBy('rd.receiving_id', 'ri.batches_id');
        $returns = DB::table('items_log as il')
            ->join('receiving as r', 'r.code', '=', 'il.transaction_code')
            ->joinSub($returnInvoiceMeta, 'rd', function ($join) {
                $join->on('rd.receiving_id', '=', 'r.id')->on('rd.batches_id', '=', 'il.batches_id');
            })
            ->where('il.status', 4)
            ->whereIn('r.pharmacy_id', $purchasePharmacyIds)
            ->whereIn('r.status', [1, 2, 3])
            ->whereRaw("COALESCE(STR_TO_DATE(NULLIF(il.date, ''), '%Y-%m-%d'), STR_TO_DATE(NULLIF(il.date, ''), '%d/%m/%Y'), DATE(il.created_at)) BETWEEN ? AND ?", [$startDate, $endDate])
            ->selectRaw('DATE(COALESCE(STR_TO_DATE(NULLIF(il.date, ""), "%Y-%m-%d"), STR_TO_DATE(NULLIF(il.date, ""), "%d/%m/%Y"), il.created_at)) as return_date, UPPER(TRIM(COALESCE(rd.invoice_payment, ""))) as invoice_payment, UPPER(TRIM(COALESCE(rd.invoice_ppn, "TANPA"))) as invoice_ppn, SUM(CAST(COALESCE(NULLIF(il.total, ""), "0") AS DECIMAL(15,2))) as amount')
            ->groupByRaw('return_date, UPPER(TRIM(COALESCE(rd.invoice_payment, ""))), UPPER(TRIM(COALESCE(rd.invoice_ppn, "TANPA")))')
            ->get();
        foreach ($returns as $return) {
            $date = (string) $return->return_date;
            if (!isset($days[$date]))
                continue;
            $kind = match ($return->invoice_payment) {
                'KREDIT' => 'pbf',
                'KONSINYASI' => 'consignment',
                'TUNAI' => 'cash',
                default => null,
            };
            if ($kind) {
                $amount = (float) $return->amount;
                $this->subtractPurchaseReturn($days[$date], $kind, $amount, $return->invoice_ppn);
            }
        }

        $discounts = DB::table('finance_payments')
            ->where('payment_type', 'DISKON')
            ->where(function ($q) use ($pharmacyIds) {
                $q->whereIn('pharmacy_id', $pharmacyIds);
                if (in_array(1, $pharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as payment_dt, SUM(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->get();
        foreach ($discounts as $disc) {
            $date = (string) $disc->payment_dt;
            if (isset($days[$date])) {
                $days[$date]['purchases']['discountOff'] += (float) $disc->amount;
            }
        }

        $otherIncomes = DB::table('finance_payments')
            ->where('payment_type', 'PENDAPATAN')
            ->where(function ($q) use ($pharmacyIds) {
                $q->whereIn('pharmacy_id', $pharmacyIds);
                if (in_array(1, $pharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as payment_dt, SUM(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->get();
        foreach ($otherIncomes as $inc) {
            $date = (string) $inc->payment_dt;
            if (isset($days[$date])) {
                $days[$date]['other_income'] += (float) $inc->amount;
            }
        }

        $expenses = DB::table('finance_payments')
            ->where('payment_type', 'BIAYA')
            ->where(function ($q) use ($pharmacyIds) {
                $q->whereIn('pharmacy_id', $pharmacyIds);
                if (in_array(1, $pharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            })
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->selectRaw('DATE(payment_date) as payment_dt, SUM(amount) as amount')
            ->groupByRaw('DATE(payment_date)')
            ->get();
        foreach ($expenses as $exp) {
            $date = (string) $exp->payment_dt;
            if (isset($days[$date])) {
                $days[$date]['expense'] += (float) $exp->amount;
            }
        }

        $debtorRows = $debtors->map(fn($debtor) => ['id' => (int) $debtor->id, 'name' => (string) $debtor->name])->values()->all();
        foreach ($extraDebtors as $id => $name)
            $debtorRows[] = ['id' => (int) $id, 'name' => $name];
        $omzetRunningTotal = 0.0;
        $incomeRunningTotal = 0.0;
        $expenseRunningTotal = 0.0;
        $purchaseRunningTotal = 0.0;
        $discountOffRunningTotal = 0.0;

        foreach ($days as &$day) {
            $day['sales']['cashBankTotal'] = $day['sales']['cash']['morning'] + $day['sales']['cash']['evening'] + $day['sales']['bank']['morning'] + $day['sales']['bank']['evening'];
            $debtorSales = array_sum($day['sales']['credit']['morning']) + array_sum($day['sales']['credit']['evening']);
            $day['sales']['creditTotal'] = $debtorSales;
            $day['sales']['cashBankExVat'] = $day['sales']['cashBankTotal'] / 1.11;
            $day['sales']['cashBankPpn'] = $day['sales']['cashBankTotal'] - $day['sales']['cashBankExVat'];
            $day['sales']['creditExVat'] = $day['sales']['creditTotal'] / 1.11;
            $day['sales']['creditPpn'] = $day['sales']['creditTotal'] - $day['sales']['creditExVat'];
            $day['sales']['total'] = $day['sales']['cashBankTotal'] + $day['sales']['creditTotal'];

            $omzetRunningTotal += (float) $day['sales']['total'];
            $day['sales']['total_sd'] = $omzetRunningTotal;
            $day['sales']['exVat'] = $day['sales']['cashBankExVat'] + $day['sales']['creditExVat'];
            $day['sales']['ppn'] = $day['sales']['cashBankPpn'] + $day['sales']['creditPpn'];
            // Total Pembelian = Kredit (PBF) + Tunai (CASH) + Konsinyasi - Retur - Diskon Off
            $day['purchases']['total'] = $day['purchases']['pbf'] + $day['purchases']['consignment'] + $day['purchases']['cash'] - $day['purchases']['return'] - $day['purchases']['discountOff'];
            $purchaseRunningTotal += (float) $day['purchases']['total'];
            $day['purchases']['sd'] = $purchaseRunningTotal;
            $discountOffRunningTotal += (float) $day['purchases']['discountOff'];
            $day['purchases']['discountOffSd'] = $discountOffRunningTotal;
            $day['purchases']['exVat'] = $day['purchases']['total'] - $day['purchases']['ppn'];

            // Margin = Total Omzet S/D - Total Pembelian S/D + Diskon Off (harian)
            $day['margin'] = (float) $day['sales']['total_sd'] - (float) $day['purchases']['sd'] + (float) $day['purchases']['discountOff'];
            $day['margin_sd'] = $day['margin'];

            $incomeRunningTotal += (float) ($day['other_income'] ?? 0);
            $day['other_income_sd'] = $incomeRunningTotal;

            $expenseRunningTotal += (float) ($day['expense'] ?? 0);
            $day['expense_sd'] = $expenseRunningTotal;

            // Rugi Laba S/D = Margin S/D + Pendapatan Lain-lain S/D - Biaya S/D
            $day['profit_loss_sd'] = $day['margin_sd'] + $day['other_income_sd'] - $day['expense_sd'];
        }
        unset($day);

        return ['days' => array_values($days), 'debtors' => $debtorRows];
    }
}
