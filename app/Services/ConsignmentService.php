<?php

namespace App\Services;

use App\Models\Receiving;
use App\Models\ReceivingItems;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsignmentService
{
    /**
     * Allocate a cumulative sold quantity to receipt lines in FIFO order.
     */
    public function allocateFifo(array $receiptQuantities, float $netSold): array
    {
        $allocations = [];
        $remainingSold = max(0, $netSold);

        foreach ($receiptQuantities as $receiptItemId => $receivedQty) {
            $receivedQty = max(0, (float) $receivedQty);
            $allocated = min($receivedQty, $remainingSold);
            $allocations[$receiptItemId] = round($allocated, 4);
            $remainingSold = max(0, $remainingSold - $allocated);
        }

        return $allocations;
    }

    /**
     * Build consignment invoices and their cumulative sold-only liability.
     */
    public function getData(array $pharmacyIds): array
    {
        $receivings = Receiving::with([
            'pharmacy',
            'receiving_details' => fn ($query) => $query->where('invoice_payment', 'KONSINYASI'),
            'receiving_details.creditor',
            'receiving_details.payments.account',
            'receiving_details.payments.creator',
            'receiving_details.receiving_items.order_items.medicines',
        ])
            ->whereIn('status', [1, 2, 3])
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereHas('receiving_details', fn ($query) => $query->where('invoice_payment', 'KONSINYASI'))
            ->latest('updated_at')
            ->get();

        $details = $receivings->flatMap->receiving_details;
        $rootBatchIds = $details->flatMap->receiving_items
            ->pluck('batches_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $allocations = $this->buildSoldAllocations($rootBatchIds);

        return $details->map(function ($detail) use ($receivings, $allocations): array {
            $receiving = $receivings->firstWhere('id', $detail->receiving_id);
            $items = [];
            $receivedTotal = 0.0;
            $soldTotal = 0.0;
            $soldSubtotal = 0.0;

            foreach ($detail->receiving_items as $item) {
                $medicine = $item->order_items?->medicines;
                $receivedQty = $this->actualReceivedQuantity($item);
                $soldQty = min($receivedQty, (float) ($allocations[$item->id] ?? 0));
                $lineTotal = (float) ($item->total ?? 0);
                $unitCost = $receivedQty > 0 ? $lineTotal / $receivedQty : 0;
                $soldAmount = round($soldQty * $unitCost, 2);

                $receivedTotal += $receivedQty;
                $soldTotal += $soldQty;
                $soldSubtotal += $soldAmount;

                $items[] = [
                    'id' => $item->id,
                    'sku' => $medicine?->code ?? ('SKU-'.$item->id),
                    'nama' => $medicine?->name ?? 'Obat',
                    'batch' => $item->batch ?: '-',
                    'expired_date' => $item->expired_date,
                    'satuan' => $medicine?->unit ?? 'Pcs',
                    'qty_received' => round($receivedQty, 4),
                    'qty_sold' => round($soldQty, 4),
                    'qty_remaining' => round(max(0, $receivedQty - $soldQty), 4),
                    'unit_cost' => round($unitCost, 2),
                    'sold_amount' => $soldAmount,
                ];
            }

            $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
            $tax = $ppnType === 'EXCLUDE' ? round($soldSubtotal * 0.11, 2) : 0.0;
            $payable = round($soldSubtotal + $tax, 2);
            $consignmentPayments = $detail->payments->where('payment_type', 'KONSINYASI');
            $paid = round((float) $consignmentPayments->sum('amount'), 2);
            $due = round(max(0, $payable - $paid), 2);
            $overpaid = round(max(0, $paid - $payable), 2);

            $status = 'Belum Ada Penjualan';
            if ($soldTotal > 0 && $due > 0 && $paid <= 0) {
                $status = 'Siap Dibayar';
            } elseif ($due > 0 && $paid > 0) {
                $status = 'Dibayar Sebagian';
            } elseif ($soldTotal > 0 && $due <= 0) {
                $status = $receivedTotal > 0 && $soldTotal >= $receivedTotal ? 'Selesai' : 'Terbayar s.d. Penjualan';
            }

            return [
                'id' => $detail->id,
                'receiving_id' => $detail->receiving_id,
                'nomor' => $detail->invoice_number ?: ($receiving?->code ?? '-'),
                'referensi' => $detail->receiving_details_code ?: ($receiving?->code ?? '-'),
                'vendor' => $detail->creditor?->name ?? ($detail->creditor_code ?: 'PBF / Vendor'),
                'tanggal' => $detail->invoice_date ? Carbon::parse($detail->invoice_date)->format('d/m/Y') : ($receiving?->date ?: '-'),
                'raw_tanggal' => $detail->invoice_date ? Carbon::parse($detail->invoice_date)->format('Y-m-d') : $receiving?->date,
                'gudang' => $receiving?->pharmacy?->name ?? 'Gudang Utama',
                'ppn_type' => $ppnType,
                'qty_received' => round($receivedTotal, 4),
                'qty_sold' => round($soldTotal, 4),
                'qty_remaining' => round(max(0, $receivedTotal - $soldTotal), 4),
                'sold_subtotal' => round($soldSubtotal, 2),
                'tax' => $tax,
                'payable' => $payable,
                'paid' => $paid,
                'due' => $due,
                'overpaid' => $overpaid,
                'status' => $status,
                'items' => $items,
                'payments' => $consignmentPayments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'tanggal' => $payment->payment_date?->format('d/m/Y') ?? '-',
                    'nominal' => (float) $payment->amount,
                    'noReferensi' => $payment->reference_number ?: '-',
                    'catatan' => $payment->notes ?: '-',
                    'akunNama' => $payment->account?->name ?? 'Kas & Bank',
                    'akunKode' => $payment->account?->code ?? '-',
                    'diprosesOleh' => $payment->creator?->name ?? $payment->creator?->username ?? 'Staf Finance',
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Allocate net sales FIFO across every receipt sharing a batch lineage.
     * Including non-consignment receipts prevents the same batch sale from
     * being counted more than once when physical batch records are merged.
     */
    private function buildSoldAllocations(Collection $rootBatchIds): array
    {
        if ($rootBatchIds->isEmpty()) {
            return [];
        }

        $adjacency = [];
        $known = $rootBatchIds->flip()->map(fn () => true)->all();
        $frontier = $rootBatchIds->all();

        while ($frontier !== []) {
            $edges = DB::table('medicine_transfer_items')
                ->whereNotNull('source_batches_id')
                ->where(function ($query) use ($frontier): void {
                    $query->whereIn('source_batches_id', $frontier)
                        ->orWhereIn('batches_id', $frontier);
                })
                ->get(['source_batches_id', 'batches_id']);

            $next = [];
            foreach ($edges as $edge) {
                $source = (int) $edge->source_batches_id;
                $destination = (int) $edge->batches_id;
                $adjacency[$source][$destination] = true;
                $adjacency[$destination][$source] = true;

                foreach ([$source, $destination] as $batchId) {
                    if (! isset($known[$batchId])) {
                        $known[$batchId] = true;
                        $next[] = $batchId;
                    }
                }
            }

            $frontier = array_values(array_unique($next));
        }

        $allocations = [];
        $processedComponents = [];
        $components = [];
        $batchToComponent = [];

        foreach ($rootBatchIds as $rootBatchId) {
            $component = $this->connectedBatchIds((int) $rootBatchId, $adjacency);
            sort($component);
            $componentKey = implode(',', $component);

            if (isset($processedComponents[$componentKey])) {
                continue;
            }
            $processedComponents[$componentKey] = true;
            $components[$componentKey] = $component;
            foreach ($component as $batchId) {
                $batchToComponent[$batchId] = $componentKey;
            }
        }

        $batchIds = array_keys($batchToComponent);
        $salesByComponent = [];
        $salesRows = DB::table('items_log')
            ->whereIn('batches_id', $batchIds)
            ->whereIn('status', [1, 3])
            ->groupBy('batches_id', 'status')
            ->get(['batches_id', 'status', DB::raw('SUM(qty) as quantity')]);

        foreach ($salesRows as $sale) {
            $componentKey = $batchToComponent[(int) $sale->batches_id];
            $direction = (int) $sale->status === 1 ? 1 : -1;
            $salesByComponent[$componentKey] = ($salesByComponent[$componentKey] ?? 0)
                + ($direction * (float) $sale->quantity);
        }

        $receiptItemsByComponent = ReceivingItems::with([
            'order_items.medicines',
            'receiving_details',
        ])
            ->whereIn('batches_id', $batchIds)
            ->get()
            ->sortBy(function ($item): string {
                $date = $item->receiving_details?->invoice_date
                    ? Carbon::parse($item->receiving_details->invoice_date)->format('Y-m-d')
                    : '9999-12-31';

                return sprintf('%s-%020d', $date, $item->id);
            })
            ->groupBy(fn ($item) => $batchToComponent[(int) $item->batches_id]);

        foreach (array_keys($components) as $componentKey) {
            $receiptQuantities = [];
            foreach ($receiptItemsByComponent->get($componentKey, collect()) as $receiptItem) {
                $receiptQuantities[$receiptItem->id] = $this->actualReceivedQuantity($receiptItem);
            }
            $allocations += $this->allocateFifo($receiptQuantities, (float) ($salesByComponent[$componentKey] ?? 0));
        }

        return $allocations;
    }

    private function connectedBatchIds(int $root, array $adjacency): array
    {
        $seen = [$root => true];
        $queue = [$root];

        while ($queue !== []) {
            $current = array_shift($queue);
            foreach (array_keys($adjacency[$current] ?? []) as $neighbor) {
                if (! isset($seen[$neighbor])) {
                    $seen[$neighbor] = true;
                    $queue[] = $neighbor;
                }
            }
        }

        return array_map('intval', array_keys($seen));
    }

    private function actualReceivedQuantity(ReceivingItems $item): float
    {
        $quantity = (float) ($item->qty_received ?? 0);
        $isPack = (int) ($item->order_items?->pack ?? 0) === 1;
        $content = $isPack ? max(1, (float) ($item->order_items?->medicines?->content ?? 1)) : 1;

        return $quantity * $content;
    }
}
