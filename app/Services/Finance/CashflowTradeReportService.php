<?php

namespace App\Services\Finance;

use App\Models\Creditor;
use App\Models\Debtors;
use App\Models\MedicineTransactions;
use App\Models\ReceivingItems;
use Carbon\CarbonPeriod;

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
            ->where('status', 1)
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT', 'RETUR KREDIT'])
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->orderBy('created_at')
            ->get();

        foreach ($transactions as $transaction) {
            $date = $transaction->created_at?->toDateString();
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

        return $this->finalize($rows);
    }

    public function payables(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $rows = $this->blankRows(Creditor::query()->orderBy('name')->get(['code', 'name']), $startDate, $endDate, 'creditor:');

        $items = ReceivingItems::query()
            ->with(['receiving_details.creditor', 'receiving_details.receiving'])
            ->whereNotNull('batches_id')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->whereHas('receiving_details', fn ($query) => $query->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT']))
            ->whereHas('receiving_details.receiving', fn ($query) => $query->whereIn('pharmacy_id', $pharmacyIds))
            ->orderBy('created_at')
            ->get();

        foreach ($items as $item) {
            $date = $item->created_at?->toDateString();
            if (!$date || !isset($rows['dates'][$date])) continue;

            $detail = $item->receiving_details;
            $creditor = $detail?->creditor;
            $code = (string) ($detail?->creditor_code ?? '');
            $key = 'creditor:' . ($code !== '' ? $code : 'unknown');
            if (!isset($rows['entities'][$key])) {
                $name = $creditor?->name ?: ($code !== '' ? "Supplier {$code}" : 'Supplier tidak tercatat');
                $rows['entities'][$key] = $this->blankEntity($key, $name, $rows['dates']);
            }

            $amount = $this->number($item->total);
            if ($amount <= 0) {
                $amount = $this->number($item->raw_price) * $this->number($item->qty_received);
            }
            $rows['entities'][$key]['amounts'][$date] += $amount;
            $rows['entities'][$key]['total'] += $amount;
        }

        return $this->finalize($rows);
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
        $grandTotal = 0.0;
        foreach ($entities as $entity) {
            foreach ($entity['amounts'] as $date => $amount) $grandTotalsByDate[$date] += $amount;
            $grandTotal += $entity['total'];
        }
        return [
            'dates' => array_keys($report['dates']),
            'rows' => $entities,
            'grandTotalsByDate' => $grandTotalsByDate,
            'grandTotal' => $grandTotal,
        ];
    }

    private function number($value): float
    {
        return (float) str_replace(',', '.', (string) ($value ?? 0));
    }
}
