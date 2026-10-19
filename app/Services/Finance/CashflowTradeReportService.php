<?php

namespace App\Services\Finance;

use App\Models\Creditor;
use App\Models\Debtors;
use App\Models\FinancePayment;
use App\Models\MedicineTransactions;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use App\Support\ReceivingInvoiceTotals;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class CashflowTradeReportService
{
    public function receivables(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $rows = $this->blankRows(Debtors::query()->orderBy('name')->get(['id', 'name']), $startDate, $endDate);

        $transactions = MedicineTransactions::query()
            ->with([
                'debtors',
                'transactions' => fn ($query) => $query->where('status', 1),
            ])
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT', 'RETUR KREDIT'])
            ->tap(fn ($query) => LiphTransactionScope::applySalesStatus($query))
            ->tap(fn ($query) => LiphTransactionScope::applyPeriod($query, Carbon::parse($startDate), Carbon::parse($endDate)))
            ->orderBy('created_at')
            ->get();

        foreach ($transactions as $transaction) {
            $effectiveAt = $transaction->created_at ?? $transaction->updated_at;
            $date = FinanceShift::operationalDate($effectiveAt);
            if (!$date || !isset($rows['dates'][$date])) continue;

            $type = mb_strtoupper(trim((string) $transaction->transaction_type));
            $items = $transaction->transactions;
            $amount = $items->sum(fn ($item) => $this->number($item->final_price ?? $item->total_price));
            if ($items->isEmpty()) {
                $amount = max(0, $this->number($transaction->subtotal) - $this->number($transaction->discount));
            } elseif ($type !== 'RETUR KREDIT') {
                $amount = max(0, $amount - $this->number($transaction->discount));
            }
            if ($type === 'RETUR KREDIT') $amount = -abs($amount);

            $debtorId = (int) ($transaction->debtor_id ?? 0);
            $key = 'debtor:' . $debtorId;
            if (!isset($rows['entities'][$key])) {
                $name = $transaction->debtors?->name ?: 'Debitur tidak tercatat';
                $rows['entities'][$key] = $this->blankEntity($key, $name, array_keys($rows['dates']));
            }
            $rows['entities'][$key]['amounts'][$date] += $amount;
            $rows['entities'][$key]['total'] += $amount;
        }

        $opening = $this->receivableBalancesBefore($pharmacyIds, $startDate);
        $payments = $this->receivablePaymentsInPeriod($pharmacyIds, $startDate, $endDate);
        $this->attachBalanceColumns($rows, $opening, $payments);

        return $this->finalize($rows);
    }

    public function payables(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $rows = $this->blankRows(Creditor::query()->orderBy('name')->get(['code', 'name']), $startDate, $endDate, 'creditor:');
        $start = FinanceShift::start($startDate);
        $until = FinanceShift::endExclusive($endDate);

        $items = ReceivingItems::query()
            ->with(['receiving_details.creditor', 'receiving_details.receiving'])
            ->whereNotNull('batches_id')
            ->whereRaw('COALESCE(receiving_items.created_at, receiving_items.updated_at) >= ?', [$start])
            ->whereRaw('COALESCE(receiving_items.created_at, receiving_items.updated_at) < ?', [$until])
            ->whereHas('receiving_details', fn ($query) => $query->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT']))
            ->whereHas('receiving_details.receiving', fn ($query) => $query->whereIn('pharmacy_id', $pharmacyIds))
            ->orderBy('created_at')
            ->get();

        foreach ($items as $item) {
            $effectiveAt = $item->created_at ?? $item->updated_at;
            $date = FinanceShift::operationalDate($effectiveAt);
            if (!$date || !isset($rows['dates'][$date])) continue;

            $detail = $item->receiving_details;
            $creditor = $detail?->creditor;
            $code = (string) ($detail?->creditor_code ?? '');
            $key = 'creditor:' . ($code !== '' ? $code : 'unknown');
            if (!isset($rows['entities'][$key])) {
                $name = $creditor?->name ?: ($code !== '' ? "Supplier {$code}" : 'Supplier tidak tercatat');
                $rows['entities'][$key] = $this->blankEntity($key, $name, $rows['dates']);
            }

            $ppnType = mb_strtoupper(trim((string) ($detail?->invoice_ppn ?? $creditor?->ppn_type ?? 'TANPA')));
            $amount = ReceivingInvoiceTotals::calculate([$item], $ppnType)['total'];
            $rows['entities'][$key]['amounts'][$date] += $amount;
            $rows['entities'][$key]['total'] += $amount;
        }

        $opening = $this->payableBalancesBefore($pharmacyIds, $startDate);
        $payments = $this->payablePaymentsInPeriod($pharmacyIds, $startDate, $endDate);
        $this->attachBalanceColumns($rows, $opening, $payments);

        return $this->finalize($rows);
    }

    /** Saldo piutang per debitur sebelum awal periode operasional. */
    private function receivableBalancesBefore(array $pharmacyIds, string $startDate): array
    {
        $cutoff = FinanceShift::start($startDate);
        $paymentCutoff = Carbon::parse($startDate)->subDay()->toDateString();
        $transactions = MedicineTransactions::query()
            ->with(['debtors:id,name', 'transactions' => fn ($query) => $query->where('status', 1)])
            ->withSum(['payments as finance_paid' => fn ($query) => $query->where('payment_type', 'PIUTANG')->whereDate('payment_date', '<=', $paymentCutoff)], 'amount')
            ->withSum(['payments as finance_paid_total' => fn ($query) => $query->where('payment_type', 'PIUTANG')], 'amount')
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT', 'RETUR KREDIT'])
            ->where(function ($query) use ($cutoff) {
                $query->where('created_at', '<', $cutoff)
                    ->orWhere(fn ($legacy) => $legacy->whereNull('created_at')->where('updated_at', '<', $cutoff));
            })
            ->tap(fn ($query) => LiphTransactionScope::applySalesStatus($query))
            ->get(['id', 'debtor_id', 'transaction_type', 'subtotal', 'discount', 'paid']);

        $balances = [];
        $returns = [];
        foreach ($transactions as $transaction) {
            $debtorId = (int) ($transaction->debtor_id ?? 0);
            $key = 'debtor:' . $debtorId;
            if (mb_strtoupper(trim((string) $transaction->transaction_type)) === 'RETUR KREDIT') {
                $returns[$key] = ($returns[$key] ?? 0) + abs($this->number($transaction->subtotal));
                continue;
            }
            $total = $this->number($transaction->subtotal);
            if ($total <= 0) {
                $total = $transaction->transactions->sum(fn ($item) => $this->number($item->final_price ?? $item->total_price));
                $total = max(0, $total - $this->number($transaction->discount));
            }
            $legacyPaid = max(0, $this->number($transaction->paid) - $this->number($transaction->finance_paid_total));
            $paid = $this->number($transaction->finance_paid) + $legacyPaid;
            $balances[$key] = ($balances[$key] ?? 0) + max(0, $total - min($total, max(0, $paid)));
        }
        foreach ($returns as $key => $amount) {
            $balances[$key] = max(0, ($balances[$key] ?? 0) - $amount);
        }
        return $balances;
    }

    /** Pembayaran piutang Finance dalam rentang tanggal, dikelompokkan per debitur. */
    private function receivablePaymentsInPeriod(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $payments = FinancePayment::query()
            ->with('transaction:id,debtor_id')
            ->where('payment_type', 'PIUTANG')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('transaction', fn ($query) => $query->whereIn('pharmacy_id', $pharmacyIds))
            ->get(['id', 'medicine_transaction_id', 'amount', 'payment_date']);
        $totals = [];
        foreach ($payments as $payment) {
            $key = 'debtor:' . (int) ($payment->transaction?->debtor_id ?? 0);
            $date = Carbon::parse($payment->payment_date)->toDateString();
            $totals[$key][$date] = ($totals[$key][$date] ?? 0) + $this->number($payment->amount);
        }
        return $totals;
    }

    /** Saldo hutang per supplier sebelum awal periode operasional. */
    private function payableBalancesBefore(array $pharmacyIds, string $startDate): array
    {
        $cutoff = FinanceShift::start($startDate);
        $paymentCutoff = Carbon::parse($startDate)->subDay()->toDateString();
        $details = ReceivingDetails::query()
            ->with([
                'creditor:code,name',
                'receiving:id,pharmacy_id,status',
                'receiving_items' => fn ($query) => $query->whereNotNull('batches_id')->whereRaw('COALESCE(receiving_items.created_at, receiving_items.updated_at) < ?', [$cutoff]),
                'payments' => fn ($query) => $query->where('payment_type', 'KREDIT')->whereDate('payment_date', '<=', $paymentCutoff),
            ])
            ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
            ->whereHas('receiving', fn ($query) => $query->whereIn('pharmacy_id', $pharmacyIds)->whereIn('status', [1, 2, 3]))
            ->whereHas('receiving_items', fn ($query) => $query->whereNotNull('batches_id')->whereRaw('COALESCE(receiving_items.created_at, receiving_items.updated_at) < ?', [$cutoff]))
            ->get();

        $balances = [];
        foreach ($details as $detail) {
            $code = (string) ($detail->creditor_code ?? '');
            $key = 'creditor:' . ($code !== '' ? $code : 'unknown');
            $ppnType = mb_strtoupper(trim((string) ($detail->invoice_ppn ?? 'TANPA')));
            $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];
            $paid = $detail->payments->sum(fn ($payment) => $this->number($payment->amount));
            $balances[$key] = ($balances[$key] ?? 0) + max(0, $invoiceTotal - $paid);
        }
        return $balances;
    }

    /** Pembayaran hutang dagang Finance (tanpa konsinyasi) dalam periode. */
    private function payablePaymentsInPeriod(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $payments = FinancePayment::query()
            ->with('receivingDetail:id,creditor_code')
            ->where('payment_type', 'KREDIT')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->whereHas('receivingDetail.receiving', fn ($query) => $query->whereIn('pharmacy_id', $pharmacyIds))
            ->get(['id', 'receiving_detail_id', 'amount', 'payment_date']);
        $totals = [];
        foreach ($payments as $payment) {
            $code = (string) ($payment->receivingDetail?->creditor_code ?? '');
            $key = 'creditor:' . ($code !== '' ? $code : 'unknown');
            $date = Carbon::parse($payment->payment_date)->toDateString();
            $totals[$key][$date] = ($totals[$key][$date] ?? 0) + $this->number($payment->amount);
        }
        return $totals;
    }

    private function attachBalanceColumns(array &$report, array $opening, array $payments): void
    {
        $dates = array_keys($report['dates']);
        foreach ($report['entities'] as $key => &$entity) {
            $entity['openingBalance'] = (float) ($opening[$key] ?? 0);
            $entity['periodTotal'] = (float) $entity['total'];
            $entity['paymentAmounts'] = array_fill_keys($dates, 0.0);
            foreach ($payments[$key] ?? [] as $date => $amount) {
                if (array_key_exists($date, $entity['paymentAmounts'])) $entity['paymentAmounts'][$date] += (float) $amount;
            }
            $entity['periodPayments'] = array_sum($entity['paymentAmounts']);
            $entity['closingBalance'] = max(0, $entity['openingBalance'] + $entity['periodTotal'] - $entity['periodPayments']);
        }
        unset($entity);

        $additionalKeys = array_unique(array_merge(array_keys($opening), array_keys($payments)));
        foreach ($additionalKeys as $key) {
            if (isset($report['entities'][$key])) continue;
            $name = str_starts_with($key, 'debtor:')
                ? (Debtors::find((int) substr($key, 7))?->name ?: 'Debitur tidak tercatat')
                : (Creditor::where('code', substr($key, 9))->value('name') ?: 'Supplier tidak tercatat');
            $entity = $this->blankEntity($key, $name, $dates);
            $entity['openingBalance'] = (float) ($opening[$key] ?? 0);
            $entity['periodTotal'] = 0.0;
            $entity['paymentAmounts'] = array_fill_keys($dates, 0.0);
            foreach ($payments[$key] ?? [] as $date => $amount) {
                if (array_key_exists($date, $entity['paymentAmounts'])) $entity['paymentAmounts'][$date] += (float) $amount;
            }
            $entity['periodPayments'] = array_sum($entity['paymentAmounts']);
            $entity['closingBalance'] = max(0, $entity['openingBalance'] - $entity['periodPayments']);
            $report['entities'][$key] = $entity;
        }
    }

    private function blankRows($entities, string $startDate, string $endDate, string $prefix = 'debtor:'): array
    {
        $dates = [];
        foreach (CarbonPeriod::create($startDate, $endDate) as $date) $dates[$date->toDateString()] = true;
        $dateKeys = array_keys($dates);
        $entityRows = [];
        foreach ($entities as $entity) {
            $id = $prefix === 'creditor:' ? (string) $entity->code : (string) $entity->id;
            $key = $prefix . $id;
            $entityRows[$key] = $this->blankEntity($key, (string) $entity->name, $dateKeys);
        }
        return ['dates' => $dates, 'entities' => $entityRows];
    }

    private function blankEntity(string $key, string $name, array $dates): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'amounts' => array_fill_keys($dates, 0.0),
            'total' => 0.0,
        ];
    }

    private function finalize(array $report): array
    {
        $entities = array_values($report['entities']);
        usort($entities, fn ($left, $right) => strcasecmp($left['name'], $right['name']));
        $grandTotalsByDate = array_fill_keys(array_keys($report['dates']), 0.0);
        $paymentTotalsByDate = array_fill_keys(array_keys($report['dates']), 0.0);
        $grandTotal = 0.0;
        $openingBalance = 0.0;
        $periodTotal = 0.0;
        $periodPayments = 0.0;
        $closingBalance = 0.0;
        $grandDue = 0.0;
        foreach ($entities as $entity) {
            foreach ($entity['amounts'] as $date => $amount) $grandTotalsByDate[$date] += $amount;
            foreach (($entity['paymentAmounts'] ?? []) as $date => $amount) {
                if (array_key_exists($date, $paymentTotalsByDate)) $paymentTotalsByDate[$date] += $amount;
            }
            $grandTotal += $entity['total'];
            $openingBalance += (float) ($entity['openingBalance'] ?? 0);
            $periodTotal += (float) ($entity['periodTotal'] ?? $entity['total']);
            $periodPayments += (float) ($entity['periodPayments'] ?? 0);
            $closingBalance += (float) ($entity['closingBalance'] ?? 0);
            $grandDue += (float) ($entity['openingBalance'] ?? 0) + (float) ($entity['periodTotal'] ?? $entity['total']);
        }
        return [
            'dates' => array_keys($report['dates']),
            'rows' => $entities,
            'grandTotalsByDate' => $grandTotalsByDate,
            'paymentTotalsByDate' => $paymentTotalsByDate,
            'grandTotal' => $grandTotal,
            'openingBalance' => $openingBalance,
            'periodTotal' => $periodTotal,
            'grandDue' => $grandDue,
            'periodPayments' => $periodPayments,
            'closingBalance' => $closingBalance,
        ];
    }

    private function number($value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? 0));
    }
}
