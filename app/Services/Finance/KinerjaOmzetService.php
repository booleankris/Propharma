<?php

namespace App\Services\Finance;

use App\Models\Debtors;
use App\Models\MedicineTransactions;
use App\Models\Receiving;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KinerjaOmzetService
{
    private function blankDay(string $date): array
    {
        return [
            'date' => $date,
            'sales' => ['cash' => ['morning' => 0, 'evening' => 0], 'bank' => ['morning' => 0, 'evening' => 0], 'credit' => ['morning' => [], 'evening' => []], 'returns' => ['morning' => 0, 'evening' => 0]],
            'purchases' => ['pbf' => 0, 'consignment' => 0, 'return' => 0, 'cash' => 0, 'ppn' => 0],
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

    /** Build the workbook-style daily sales and purchase figures for one month. */
    public function build(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $days = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            $key = $date->toDateString();
            $days[$key] = $this->blankDay($key);
        }

        $debtors = Debtors::query()->orderBy('name')->get(['id', 'name']);
        $extraDebtors = [];
        $transactions = MedicineTransactions::query()->with([
            'transactions' => fn ($query) => $query->where('status', 1),
            'debtors',
        ])->whereIn('pharmacy_id', $pharmacyIds)
            ->tap(fn ($q) => LiphTransactionScope::applySalesStatus($q))
            ->tap(fn ($q) => LiphTransactionScope::applyPeriod($q, Carbon::parse($startDate), Carbon::parse($endDate)))
            ->orderBy('created_at')->get();

        // Preload metadata to match returns with their original sale
        $returnTrx = $transactions->filter(fn ($t) => in_array(mb_strtoupper(trim((string) $t->transaction_type)), ['RETUR JUAL', 'RETUR KREDIT'], true));
        $rtDates = $returnTrx->pluck('created_at')->map(fn ($c) => (string) $c)->all();
        $itemLogs = DB::table('items_log')->whereIn('date', $rtDates)->where('type', 'RT')->get(['date', 'transaction_code'])->keyBy('date');
        $trxCodes = $itemLogs->pluck('transaction_code')->filter()->unique()->all();
        $trxByCode = MedicineTransactions::whereIn('transaction_code', $trxCodes)->get()->keyBy('transaction_code');

        $patientIds = $returnTrx->pluck('patient_id')->filter()->unique()->all();
        $candsByPatient = MedicineTransactions::whereIn('patient_id', $patientIds)
            ->whereNotIn('transaction_type', ['RETUR JUAL', 'RETUR KREDIT'])
            ->orderByDesc('id')
            ->get(['id', 'patient_id', 'transaction_type', 'payment_method', 'debtor_id', 'created_at'])
            ->groupBy('patient_id');

        foreach ($transactions as $transaction) {
            $effectiveAt = $transaction->created_at ?? $transaction->updated_at;
            $date = FinanceShift::operationalDate($effectiveAt);
            if (!$date || !isset($days[$date])) continue;
            $shift = FinanceShift::key($effectiveAt);
            $type = mb_strtoupper(trim((string) $transaction->transaction_type));
            $payment = mb_strtoupper(trim((string) $transaction->payment_method));
            $items = $transaction->transactions;
            $amount = $items->sum(fn ($item) => $this->number($item->final_price ?? $item->total_price));
            if ($items->isEmpty()) $amount = max(0, $this->number($transaction->subtotal) - $this->number($transaction->discount));
            elseif (!in_array($type, ['RETUR JUAL', 'RETUR KREDIT'], true)) $amount = max(0, $amount - $this->number($transaction->discount));

            if ($type === 'RETUR KREDIT') {
                $debtorId = (int) $transaction->debtor_id;
                $name = $transaction->debtors?->name ?: 'Debitur tidak tercatat';
                if (!$transaction->debtors) $extraDebtors[$debtorId] = $name;
                $days[$date]['sales']['credit'][$shift][$debtorId] = ($days[$date]['sales']['credit'][$shift][$debtorId] ?? 0) - abs($amount);
                $days[$date]['sales']['returns'][$shift] -= abs($amount);
            } elseif ($type === 'RETUR JUAL') {
                // Determine original transaction's payment method and shift if retur is linked
                $origTrx = null;
                $log = $itemLogs->get((string) $transaction->created_at);
                if ($log && $log->transaction_code) {
                    $origTrx = $trxByCode->get($log->transaction_code);
                }
                if (!$origTrx && $transaction->patient_id) {
                    $cands = $candsByPatient->get($transaction->patient_id);
                    $origTrx = $cands ? $cands->first(fn ($c) => $c->id < $transaction->id) : null;
                }

                $targetPayment = $origTrx ? mb_strtoupper(trim((string) $origTrx->payment_method)) : $payment;
                $targetShift = $origTrx ? FinanceShift::key($origTrx->created_at) : $shift;
                $targetDate = $origTrx ? FinanceShift::operationalDate($origTrx->created_at) : $date;
                $targetDay = (isset($days[$targetDate])) ? $targetDate : $date;

                if (in_array($targetPayment, ['QRIS', 'TRANSFER', 'DEBIT', 'EDC'], true)) {
                    $days[$targetDay]['sales']['bank'][$targetShift] -= abs($amount);
                } else {
                    $days[$targetDay]['sales']['cash'][$targetShift] -= abs($amount);
                }
                $days[$targetDay]['sales']['returns'][$targetShift] -= abs($amount);
            } elseif (in_array($type, ['KREDIT', 'RESEP KREDIT'], true)) {
                $debtorId = (int) $transaction->debtor_id;
                $name = $transaction->debtors?->name ?: 'Debitur tidak tercatat';
                if (!$transaction->debtors) $extraDebtors[$debtorId] = $name;
                $days[$date]['sales']['credit'][$shift][$debtorId] = ($days[$date]['sales']['credit'][$shift][$debtorId] ?? 0) + $amount;
            } elseif (in_array($payment, ['QRIS', 'TRANSFER', 'DEBIT', 'EDC'], true)) {
                $days[$date]['sales']['bank'][$shift] += $amount;
            } else {
                $days[$date]['sales']['cash'][$shift] += $amount;
            }
        }

        $receivings = Receiving::query()->with([
            'receiving_details.receiving_items' => fn ($query) => $query->whereNotNull('batches_id'),
            'receiving_details.creditor',
        ])
            ->whereIn('pharmacy_id', $pharmacyIds)->whereIn('status', [1, 2, 3])
            ->whereDate('date', '>=', $startDate)->whereDate('date', '<=', $endDate)->get();
        foreach ($receivings as $receiving) {
            $date = \Carbon\Carbon::parse($receiving->date)->toDateString();
            if (!isset($days[$date])) continue;
            foreach ($receiving->receiving_details as $detail) {
                $amount = $detail->receiving_items->sum(fn ($item) => $this->number($item->total));
                $payment = mb_strtoupper(trim((string) $detail->invoice_payment));
                $kind = match ($payment) {
                    'KREDIT' => 'pbf', 'KONSINYASI' => 'consignment', 'TUNAI' => 'cash', default => null,
                };
                if ($kind) $this->addPurchase($days[$date], $kind, $amount, (string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
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
            ->where('il.status', 4)->whereIn('r.pharmacy_id', $pharmacyIds)->whereIn('r.status', [1, 2, 3])
            ->whereDate('il.date', '>=', $startDate)->whereDate('il.date', '<=', $endDate)
            ->selectRaw('DATE(il.date) as return_date, UPPER(TRIM(COALESCE(rd.invoice_payment, ""))) as invoice_payment, UPPER(TRIM(COALESCE(rd.invoice_ppn, "TANPA"))) as invoice_ppn, SUM(CAST(COALESCE(NULLIF(il.total, ""), "0") AS DECIMAL(15,2))) as amount')
            ->groupByRaw('DATE(il.date), UPPER(TRIM(COALESCE(rd.invoice_payment, ""))), UPPER(TRIM(COALESCE(rd.invoice_ppn, "TANPA")))')->get();
        foreach ($returns as $return) {
            $date = (string) $return->return_date;
            if (!isset($days[$date])) continue;
            $kind = match ($return->invoice_payment) {
                'KREDIT' => 'pbf', 'KONSINYASI' => 'consignment', 'TUNAI' => 'cash', default => null,
            };
            if ($kind) {
                $amount = (float) $return->amount;
                $this->subtractPurchaseReturn($days[$date], $kind, $amount, $return->invoice_ppn);
            }
        }

        $debtorRows = $debtors->map(fn ($debtor) => ['id' => (int) $debtor->id, 'name' => (string) $debtor->name])->values()->all();
        foreach ($extraDebtors as $id => $name) $debtorRows[] = ['id' => (int) $id, 'name' => $name];
        foreach ($days as &$day) {
            $day['sales']['cashBankTotal'] = $day['sales']['cash']['morning'] + $day['sales']['cash']['evening'] + $day['sales']['bank']['morning'] + $day['sales']['bank']['evening'];
            $debtorSales = array_sum($day['sales']['credit']['morning']) + array_sum($day['sales']['credit']['evening']);
            $day['sales']['creditTotal'] = $debtorSales;
            $day['sales']['cashBankExVat'] = $day['sales']['cashBankTotal'] / 1.11;
            $day['sales']['cashBankPpn'] = $day['sales']['cashBankTotal'] - $day['sales']['cashBankExVat'];
            $day['sales']['creditExVat'] = $day['sales']['creditTotal'] / 1.11;
            $day['sales']['creditPpn'] = $day['sales']['creditTotal'] - $day['sales']['creditExVat'];
            $day['sales']['total'] = $day['sales']['cashBankTotal'] + $day['sales']['creditTotal'];
            $day['sales']['exVat'] = $day['sales']['cashBankExVat'] + $day['sales']['creditExVat'];
            $day['sales']['ppn'] = $day['sales']['cashBankPpn'] + $day['sales']['creditPpn'];
            // Purchase return amounts are already subtracted from their tender category above.
            $day['purchases']['total'] = $day['purchases']['pbf'] + $day['purchases']['consignment'] + $day['purchases']['cash'];
            $day['purchases']['exVat'] = $day['purchases']['total'] - $day['purchases']['ppn'];
        }
        unset($day);

        return ['days' => array_values($days), 'debtors' => $debtorRows];
    }
}
