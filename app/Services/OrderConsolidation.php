<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\OrderItemMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderConsolidation
{
    /**
     * Consolidate items from source orders into a selected target order.
     */
    public function create(array $selection, int $targetOrderId, int $pharmacyId, int $userId, string $requestKey): Order
    {
        return DB::transaction(function () use ($selection, $targetOrderId, $pharmacyId, $userId, $requestKey) {
            $fail = fn ($message) => throw ValidationException::withMessages(['items' => $message]);

            // Check idempotency: if movements with this requestKey already exist, return the target order
            $existingMovement = OrderItemMovement::where('consolidation_key', $requestKey)->first();
            if ($existingMovement) {
                $existingTargetItem = OrderItems::find($existingMovement->target_item_id);
                if ($existingTargetItem) {
                    $existingOrder = Order::find($existingTargetItem->order_id);
                    if ($existingOrder && (int) $existingOrder->pharmacy_id === $pharmacyId) {
                        return $existingOrder;
                    }
                }
            }

            // Lock and validate target order
            $targetOrder = Order::where('id', $targetOrderId)->lockForUpdate()->first();
            if (!$targetOrder) {
                $fail('BPBA target tidak ditemukan.');
            }
            if ((int) $targetOrder->pharmacy_id !== $pharmacyId) {
                $fail('Apotek/Gudang penerima BPBA target tidak sesuai.');
            }
            if (!in_array((int) $targetOrder->status, [0, 1, 2]) || (int) $targetOrder->status === 3) {
                $fail('BPBA target harus berstatus Dipesan atau Diterima Sebagian.');
            }
            if (!empty($targetOrder->is_consolidation)) {
                $fail('BPBA konsolidasi tidak dapat dijadikan target konsolidasi.');
            }

            // Lock and validate source items
            $ids = array_column($selection, 'id');
            $items = OrderItems::with(['medicines', 'orders'])->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($items->count() !== count($selection)) {
                $fail('Data item yang dipilih tidak lengkap atau tidak ditemukan.');
            }

            // Creditor validation: all items must belong to the same PBF
            $creditors = $items->map(fn ($i) => $i->creditor_code ?: $i->branch_creditor_code)->filter()->unique();
            if ($creditors->count() !== 1 || $items->contains(fn ($item) => !($item->creditor_code ?: $item->branch_creditor_code))) {
                $fail('Semua item harus berasal dari satu PBF yang sama.');
            }
            $sourceCreditor = $creditors->first();

            // Validate that target order belongs to the same PBF
            $targetCreditors = OrderItems::where('order_id', $targetOrder->id)->get()->map(fn ($i) => $i->creditor_code ?: $i->branch_creditor_code)->filter()->unique();
            if ($targetCreditors->isNotEmpty() && !$targetCreditors->contains($sourceCreditor)) {
                $fail('BPBA target harus berasal dari PBF yang sama dengan item yang dipindahkan.');
            }

            // Filter items that need to be moved (exclude items already in target order)
            $itemsToMove = $items->filter(fn ($item) => (int) $item->order_id !== (int) $targetOrder->id);
            if ($itemsToMove->isEmpty()) {
                $fail('Semua item yang dipilih sudah berada di dalam BPBA target. Pilih item dari BPBA lain.');
            }

            // Validate source orders
            $sourceOrderIds = $itemsToMove->pluck('order_id')->unique();
            $sourceOrders = Order::whereIn('id', $sourceOrderIds)->lockForUpdate()->get()->keyBy('id');
            foreach ($sourceOrders as $sourceOrder) {
                if ((int) $sourceOrder->pharmacy_id !== $pharmacyId || !in_array((int) $sourceOrder->status, [0, 1, 2]) || (int) $sourceOrder->status === 3) {
                    $fail('BPBA asal harus masih terbuka dan memiliki apotek/gudang yang sama.');
                }
                if (!empty($sourceOrder->is_consolidation)) {
                    $fail('Item dari BPBA konsolidasi tidak dapat dipindahkan.');
                }
            }

            // Validate quantities & draft receipts
            $quantities = collect($selection)->keyBy('id');
            foreach ($itemsToMove as $item) {
                $receipts = $item->receivingItems()->lockForUpdate()->get();
                if ($receipts->contains(fn ($receipt) => $receipt->batches_id === null)) {
                    $fail("Item '{$item->medicines->name}' masih berada dalam draft faktur/penerimaan. Hapus draft penerimaan terlebih dahulu.");
                }
                $qty = (float) $quantities[$item->id]['quantity'];
                $remaining = (float) $item->quantity - (float) $receipts->sum('qty_received');
                if (!is_finite($qty) || $qty < 0.0001 || $qty > $remaining) {
                    $fail("Jumlah yang dipindahkan untuk item '{$item->medicines->name}' melebihi sisa pesanan.");
                }
            }

            // Perform movement
            foreach ($itemsToMove as $item) {
                $qty = (float) $quantities[$item->id]['quantity'];
                $oldQty = (float) $item->quantity;
                $movedTotal = round((float) $item->total * $qty / $oldQty, 2);

                $target = $item->replicate(['original_quantity', 'original_total']);
                $target->order_id = $targetOrder->id;
                $target->quantity = $qty;
                $target->total = $movedTotal;
                $target->status = 0;
                $sourceCode = $sourceOrders[$item->order_id]->code ?? 'BPBA Asal';
                $target->note = trim(($item->note ?? '') . ' | Konsolidasi dari ' . $sourceCode . '; bukan pesanan ulang.');
                $target->save();

                // Keep a permanent snapshot; quantity/total remain the active obligation used by existing reports.
                $item->original_quantity ??= $item->quantity;
                $item->original_total ??= $item->total;
                $item->quantity = $oldQty - $qty;
                $item->total = round((float) $item->total - $movedTotal, 2);
                $item->save();

                OrderItemMovement::create([
                    'source_item_id' => $item->id,
                    'target_item_id' => $target->id,
                    'quantity' => $qty,
                    'user_id' => $userId,
                    'consolidation_key' => $requestKey,
                ]);
            }

            return $targetOrder;
        }, 3);
    }

    /**
     * Rollback a single consolidated item movement.
     */
    public function rollbackItem(int $orderItemId, int $pharmacyId, int $userId): void
    {
        DB::transaction(function () use ($orderItemId, $pharmacyId) {
            $fail = fn ($message) => throw ValidationException::withMessages(['item' => $message]);

            // Check if target item
            $incomingMovement = OrderItemMovement::where('target_item_id', $orderItemId)->lockForUpdate()->first();
            if ($incomingMovement) {
                $this->revertSingleMovement($incomingMovement, $pharmacyId, $fail);
                return;
            }

            // Check if source item
            $outgoingMovements = OrderItemMovement::where('source_item_id', $orderItemId)->lockForUpdate()->get();
            if ($outgoingMovements->isNotEmpty()) {
                foreach ($outgoingMovements as $movement) {
                    $this->revertSingleMovement($movement, $pharmacyId, $fail);
                }
                return;
            }

            $fail('Item ini tidak memiliki riwayat konsolidasi/pemindahan yang dapat dibatalkan.');
        }, 3);
    }

    /**
     * Cancel an entire consolidation (for legacy consolidation orders or all consolidated items in an order).
     */
    public function cancel(int $orderId, int $pharmacyId, int $userId): void
    {
        DB::transaction(function () use ($orderId, $pharmacyId) {
            $order = Order::with(['order_items.incomingMovement.sourceItem', 'order_items.receivingItems'])
                ->where('id', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $fail = fn ($message) => throw ValidationException::withMessages(['order' => $message]);

            if ((int) $order->pharmacy_id !== $pharmacyId) {
                $fail('Apotek/Gudang penerima tidak sesuai.');
            }

            if ($order->is_consolidation) {
                if ((int) $order->status === 3) {
                    $fail('BPBA konsolidasi sudah selesai diterima dan tidak dapat dibatalkan.');
                }
                $hasBatches = $order->order_items->flatMap->receivingItems->whereNotNull('batches_id')->isNotEmpty();
                if ($hasBatches) {
                    $fail('Sebagian atau seluruh item sudah disimpan ke stok. Batalkan penerimaan faktur terlebih dahulu sebelum membatalkan konsolidasi.');
                }
                foreach ($order->order_items as $targetItem) {
                    $movement = OrderItemMovement::where('target_item_id', $targetItem->id)->lockForUpdate()->first();
                    if ($movement) {
                        $this->revertSingleMovement($movement, $pharmacyId, $fail);
                    }
                }
                if ($order->receiving_id) {
                    $receivingId = $order->receiving_id;
                    $order->update(['receiving_id' => null]);
                    $receiving = \App\Models\Receiving::find($receivingId);
                    if ($receiving) {
                        \App\Models\ReceivingDetails::where('receiving_id', $receiving->id)->delete();
                        $receiving->delete();
                    }
                }
                $order->delete();
            } else {
                // Target order: revert all incoming movements
                $targetItemIds = $order->order_items->pluck('id');
                $movements = OrderItemMovement::whereIn('target_item_id', $targetItemIds)->lockForUpdate()->get();
                if ($movements->isEmpty()) {
                    $fail('BPBA ini tidak memiliki item hasil konsolidasi yang dapat dibatalkan.');
                }
                foreach ($movements as $movement) {
                    $this->revertSingleMovement($movement, $pharmacyId, $fail);
                }
            }
        }, 3);
    }

    protected function revertSingleMovement(OrderItemMovement $movement, int $pharmacyId, callable $fail): void
    {
        $targetItem = OrderItems::with('receivingItems')->where('id', $movement->target_item_id)->lockForUpdate()->first();
        $sourceItem = OrderItems::where('id', $movement->source_item_id)->lockForUpdate()->first();

        if ($targetItem) {
            $targetOrder = Order::where('id', $targetItem->order_id)->lockForUpdate()->first();
            if ($targetOrder && (int) $targetOrder->pharmacy_id !== $pharmacyId) {
                $fail('Apotek/Gudang tidak sesuai.');
            }

            $receivingItems = $targetItem->receivingItems;
            if ($receivingItems->contains(fn ($r) => $r->batches_id !== null)) {
                $fail('Sebagian atau seluruh item sudah disimpan ke stok. Batalkan penerimaan faktur terlebih dahulu sebelum membatalkan pemindahan.');
            }
            if ($receivingItems->contains(fn ($r) => $r->batches_id === null)) {
                $fail('Item masih berada dalam draft penerimaan (NT). Hapus item dari penerimaan terlebih dahulu secara manual sebelum membatalkan pemindahan.');
            }

            if ($sourceItem) {
                $sourceItem->quantity = (float) $sourceItem->quantity + (float) $movement->quantity;
                $sourceItem->total = round((float) $sourceItem->total + (float) $targetItem->total, 2);

                $remainingMovements = OrderItemMovement::where('source_item_id', $sourceItem->id)
                    ->where('id', '!=', $movement->id)
                    ->count();
                if ($remainingMovements === 0) {
                    $sourceItem->original_quantity = null;
                    $sourceItem->original_total = null;
                }
                $sourceItem->save();
            }

            $movement->delete();
            $targetItem->delete();
        } else {
            $movement->delete();
        }
    }
}
