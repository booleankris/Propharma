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
            'purchases' => ['pbf' => 0, 'consignment' => 0, 'return' => 0, 'cash' => 0, 'ppn' => 0],
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
        $day['purchases'][$kind] -= $gross;
        $day['purchases']['return'] += $gross;
        $day['purchases']['ppn'] -= $ppn;
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
            ])
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', array_keys(LiphExport::TYPE_MAP))
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

        $details = \App\Models\ReceivingDetails::query()
            ->with([
                'receiving:id,pharmacy_id,status,date,created_at',
                'creditor:code,ppn_type',
                'receiving_items:id,receiving_details_id,total,raw_price,qty_received,qty',
            ])
            ->whereHas('receiving', fn($query) => $query->whereIn('pharmacy_id', $purchasePharmacyIds)->whereIn('status', [1, 2, 3]))
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween(DB::raw('DATE(receiving_details.created_at)'), [$startDate, $endDate])
                    ->orWhereHas('receiving', function ($rq) use ($startDate, $endDate) {
                        $rq->whereRaw("COALESCE(STR_TO_DATE(NULLIF(date, ''), '%d/%m/%Y'), STR_TO_DATE(NULLIF(date, ''), '%Y-%m-%d'), DATE(created_at)) BETWEEN ? AND ?", [$startDate, $endDate]);
                    });
            })
            ->get();

        foreach ($details as $detail) {
            $rawDate = trim((string) ($detail->receiving?->date ?: ''));
            $date = null;
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $rawDate)) {
                $parsed = \Carbon\Carbon::createFromFormat('d/m/Y', $rawDate)->toDateString();
                if (isset($days[$parsed])) {
                    $date = $parsed;
                }
            } elseif (!empty($rawDate)) {
                try {
                    $parsed = \Carbon\Carbon::parse($rawDate)->toDateString();
                    if (isset($days[$parsed])) {
                        $date = $parsed;
                    }
                } catch (\Throwable $e) {}
            }

            if (!$date) {
                $detailDt = \Carbon\Carbon::parse($detail->created_at)->toDateString();
                if (isset($days[$detailDt])) {
                    $date = $detailDt;
                } else {
                    $rcvDt = \Carbon\Carbon::parse($detail->receiving?->created_at)->toDateString();
                    $date = isset($days[$rcvDt]) ? $rcvDt : null;
                }
            }

            if (!$date || !isset($days[$date])) {
                continue;
            }

            $amount = 0.0;
            foreach ($detail->receiving_items as $item) {
                $lineTotal = (float) ($item->total ?? 0);
                if ($lineTotal <= 0) {
                    $lineTotal = (float) ($item->raw_price ?? 0) * (float) ($item->qty_received ?? $item->qty ?? 0);
                }
                $amount += $lineTotal;
            }

            $payment = mb_strtoupper(trim((string) $detail->invoice_payment));
            $kind = match ($payment) {
                'KONSINYASI' => 'consignment',
                'TUNAI', 'CASH' => 'cash',
                default => 'pbf',
            };

            $this->addPurchase($days[$date], $kind, $amount, (string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
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
        $marginRunningTotal = 0.0;

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
            // Purchase return amounts are already subtracted from their tender category above.
            $day['purchases']['total'] = $day['purchases']['pbf'] + $day['purchases']['consignment'] + $day['purchases']['cash'];
            $day['purchases']['exVat'] = $day['purchases']['total'] - $day['purchases']['ppn'];

            $dayMargin = (float) ($day['sales']['exVat'] - $day['purchases']['exVat']);
            $day['margin'] = $dayMargin;
            $marginRunningTotal += $dayMargin;
            $day['margin_sd'] = $marginRunningTotal;

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
