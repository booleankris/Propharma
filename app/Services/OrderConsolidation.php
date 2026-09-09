<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\OrderItemMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderConsolidation
{
    public function create(array $selection, int $pharmacyId, int $userId, string $requestKey): Order
    {
        return DB::transaction(function () use ($selection, $pharmacyId, $userId, $requestKey) {
            $ids = array_column($selection, 'id');
            $items = OrderItems::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $orders = Order::whereIn('id', $items->pluck('order_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $existing = Order::where('consolidation_key', $requestKey)->first();
            if ($existing && (int) $existing->pharmacy_id === $pharmacyId && (int) $existing->user_id === $userId) {
                return $existing;
            }
            $fail = fn ($message) => throw ValidationException::withMessages(['items' => $message]);
            if ($items->count() !== count($selection) || $orders->count() < 2) {
                $fail('Pilih item dari minimal dua BPBA berbeda.');
            }
            if ($orders->contains(fn ($order) => (int) $order->pharmacy_id !== $pharmacyId || !in_array((int) $order->status, [1, 2]))) {
                $fail('BPBA harus masih terbuka dan memiliki tujuan penerimaan yang sama.');
            }
            if ($orders->contains(fn ($order) => !empty($order->is_consolidation))) {
                $fail('BPBA konsolidasi tidak dapat dikonsolidasi ulang.');
            }
            if ($items->pluck('creditor_code')->filter()->unique()->count() !== 1 || $items->contains(fn ($item) => !$item->creditor_code)) {
                $fail('Semua item harus berasal dari satu PBF yang sama.');
            }
            $quantities = collect($selection)->keyBy('id');
            foreach ($items as $item) {
                $receipts = $item->receivingItems()->lockForUpdate()->get();
                if ($receipts->contains(fn ($receipt) => $receipt->batches_id === null)) {
                    $fail('Item masih berada dalam draft faktur. Selesaikan atau hapus draft item terlebih dahulu.');
                }
                $qty = (float) $quantities[$item->id]['quantity'];
                if (!is_finite($qty) || $qty < 0.0001 || $qty > (float) $item->quantity - (float) $receipts->sum('qty_received')) {
                    $fail('Jumlah yang dipindahkan melebihi sisa pesanan. Muat ulang tracking.');
                }
            }

            $year = now()->format('y');
            $month = now()->format('m');
            $prefix = "KONS-{$year}{$month}";
            $lastCode = Order::where('code', 'like', "{$prefix}%")
                ->orderBy('code', 'desc')
                ->lockForUpdate()
                ->value('code');
            $nextNumber = $lastCode ? ((int) substr($lastCode, -4) + 1) : 1;
            $orderCode = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            while (Order::where('code', $orderCode)->exists()) {
                $nextNumber++;
                $orderCode = $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            }

            $order = Order::create([
                'pharmacy_id' => $pharmacyId,
                'user_id' => $userId,
                'code' => $orderCode,
                'date' => now()->format('d/m/Y'),
                'status' => 1,
                'is_consolidation' => true,
                'consolidation_key' => $requestKey,
                'note' => 'Konsolidasi untuk penerimaan—bukan pesanan ulang ke PBF',
            ]);
            foreach ($items as $item) {
                $qty = (float) $quantities[$item->id]['quantity'];
                $oldQty = (float) $item->quantity;
                $movedTotal = round((float) $item->total * $qty / $oldQty, 2);
                $target = $item->replicate(['original_quantity', 'original_total']);
                $target->order_id = $order->id;
                $target->quantity = $qty;
                $target->total = $movedTotal;
                $target->status = 0;
                $target->note = trim(($item->note ?? '') . ' | Konsolidasi dari ' . $orders[$item->order_id]->code . '; bukan pesanan ulang.');
                $target->save();
                // Keep a permanent snapshot; quantity/total remain the active obligation used by existing reports.
                $item->original_quantity ??= $item->quantity;
                $item->original_total ??= $item->total;
                $item->quantity = $oldQty - $qty;
                $item->total = round((float) $item->total - $movedTotal, 2);
                $item->save();
                OrderItemMovement::create([
                    'source_item_id' => $item->id, 'target_item_id' => $target->id,
                    'quantity' => $qty, 'user_id' => $userId,
                ]);
            }
            return $order;
        }, 3);
    }

    public function cancel(int $orderId, int $pharmacyId, int $userId): void
    {
        DB::transaction(function () use ($orderId, $pharmacyId) {
            $order = Order::with(['order_items.incomingMovement.sourceItem', 'order_items.receivingItems'])
                ->where('id', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $fail = fn ($message) => throw ValidationException::withMessages(['order' => $message]);

            if (!$order->is_consolidation) {
                $fail('Pesanan ini bukan BPBA hasil konsolidasi.');
            }

            if ((int) $order->pharmacy_id !== $pharmacyId) {
                $fail('Apotek/Gudang penerima tidak sesuai.');
            }

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
                    $sourceItem = OrderItems::where('id', $movement->source_item_id)->lockForUpdate()->first();
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
                }

                $targetItem->receivingItems()->delete();
                $targetItem->delete();
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
        }, 3);
    }
}
