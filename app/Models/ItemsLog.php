<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemsLog extends Model
{
    use HasFactory;
    protected $casts = [
        'date' => 'datetime',
    ];
    protected $table = 'items_log';
    protected $fillable = [
        'transaction_code',
        'code',
        'type',
        'medicine_id',
        'batches_id',
        'qty',
        'qty_before',
        'qty_after',
        'total',
        'date',
        'status',
        'user_id',

    ];
    protected ?ReceivingItems $cachedRevisionItem = null;
    protected bool $revisionItemResolved = false;

    public function medicines()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }
    public function transaction()
    {
        return $this->belongsTo(MedicineTransactions::class, 'transaction_code', 'transaction_code');
    }
    public function batches()
    {
        return $this->belongsTo(Batches::class, 'batches_id');
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'transaction_code', 'code');
    }

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class, 'transaction_code', 'return_no');
    }

    public function medicine_transaction()
    {
        return $this->belongsTo(MedicineTransactions::class, 'transaction_code', 'transaction_code');
    }

    /**
     * Match the specific ReceivingDetails and ReceivingItems belonging to this log
     * entry when a Receiving header contains multiple fakturs / PBFs.
     *
     * @return array{detail: ?ReceivingDetails, item: ?ReceivingItems}
     */
    protected function resolveReceivingMatch(): array
    {
        $receiving = $this->relationLoaded('receiving') ? $this->getRelation('receiving') : $this->receiving;
        if (!$receiving) {
            return ['detail' => null, 'item' => null];
        }

        $details = $receiving->relationLoaded('receiving_details')
            ? $receiving->getRelation('receiving_details')
            : $receiving->receiving_details;

        if (!$details || $details->isEmpty()) {
            return ['detail' => null, 'item' => null];
        }

        $batchId = $this->batches_id ? (int) $this->batches_id : null;
        $medId = $this->medicine_id ? (int) $this->medicine_id : null;
        $targetQty = abs((float) $this->qty);

        $bestDetail = null;
        $bestItem = null;
        $bestScore = 0;

        foreach ($details as $detail) {
            $items = $detail->relationLoaded('receiving_items')
                ? $detail->getRelation('receiving_items')
                : $detail->receiving_items;

            if (!$items) {
                continue;
            }

            foreach ($items as $ri) {
                $batchMatch = $batchId !== null && (int) $ri->batches_id === $batchId;
                $orderItem = $ri->relationLoaded('order_items') ? $ri->getRelation('order_items') : $ri->order_items;
                $medMatch = $medId !== null && (int) ($orderItem?->medicine_id) === $medId;
                $stockQty = (float) $ri->qty_received * max(1, (int) ($ri->stock_unit_factor ?? 1));
                $qtyMatch = $targetQty > 0 && abs($stockQty - $targetQty) < 0.001;

                $score = 0;
                if ($batchMatch && $medMatch && $qtyMatch) {
                    $score = 6;
                } elseif ($batchMatch && $medMatch) {
                    $score = 5;
                } elseif ($batchMatch) {
                    $score = 4;
                } elseif ($medMatch && $qtyMatch) {
                    $score = 2;
                } elseif ($medMatch) {
                    $score = 1;
                }

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestDetail = $detail;
                    $bestItem = $ri;
                }
            }
        }

        if ($bestDetail === null) {
            $bestDetail = $details->first();
        }

        return ['detail' => $bestDetail, 'item' => $bestItem];
    }

    public function resolveReceivingItem(): ?ReceivingItems
    {
        return $this->resolveReceivingMatch()['item'];
    }

    /**
     * Resolve the specific ReceivingDetails (faktur / Nomor Terima) for this log.
     */
    public function resolveReceivingDetail(): ?ReceivingDetails
    {
        return $this->resolveReceivingMatch()['detail'];
    }

    /**
     * Resolve ReceivingItems referenced by REV-* / REV-ADD-* / REV-DEL-* log codes.
     */
    public function resolveRevisionReceivingItem(): ?ReceivingItems
    {
        if ($this->revisionItemResolved) {
            return $this->cachedRevisionItem;
        }

        $this->revisionItemResolved = true;
        if (preg_match('/REV-(?:ADD-|DEL-)?(\d+)/', (string) $this->transaction_code, $matches)) {
            $this->cachedRevisionItem = ReceivingItems::with(['receiving_details.creditor', 'order_items.creditors'])
                ->find($matches[1]);
        }

        return $this->cachedRevisionItem;
    }

    /**
     * Resolve display transaction code (preferring matching NT/invoice for purchases).
     */
    public function resolvePurchaseTransactionCode(): ?string
    {
        $status = (int) $this->status;

        if (in_array($status, [2, 4], true)) {
            $detail = $this->resolveReceivingDetail();
            return $detail?->receiving_details_code ?: ($detail?->invoice_number ?: $this->transaction_code);
        }

        if (in_array($status, [8, 9], true)) {
            $rItem = $this->resolveRevisionReceivingItem();
            $inv = $rItem?->receiving_details?->invoice_number ?: $rItem?->receiving_details?->receiving_details_code;
            return $inv ? $this->transaction_code . ' (' . $inv . ')' : $this->transaction_code;
        }

        return $this->transaction_code;
    }

    /**
     * Resolve accurate PBF / Creditor name for purchase, purchase return, and revision logs.
     */
    public function resolveCreditorName(bool $allowDeletedFallback = true): ?string
    {
        $status = (int) $this->status;

        if (in_array($status, [2, 4], true)) {
            $match = $this->resolveReceivingMatch();
            $matchedItem = $match['item'];
            $detail = $match['detail'];
            $pbf = $detail?->creditor?->name ?? $matchedItem?->order_items?->creditors?->name;
            if ($pbf) {
                return $pbf;
            }

            if ($status === 4) {
                $pr = $this->relationLoaded('purchaseReturn') ? $this->getRelation('purchaseReturn') : $this->purchaseReturn;
                if ($pr?->creditor?->name) {
                    return $pr->creditor->name;
                }
            }
        }

        if (in_array($status, [8, 9], true)) {
            $rItem = $this->resolveRevisionReceivingItem();
            $pbf = $rItem?->receiving_details?->creditor?->name ?? $rItem?->order_items?->creditors?->name;
            if ($pbf) {
                return $pbf;
            }

            // Hapus Terima (REV-DEL-*) deletes the ReceivingItems row, so recover PBF
            // from the original Pembelian log sharing the same batch and medicine.
            if ($allowDeletedFallback && $status === 9 && $this->batches_id && $this->medicine_id) {
                $priorLog = static::with([
                    'receiving.receiving_details.creditor',
                    'receiving.receiving_details.receiving_items.order_items.creditors',
                ])
                    ->where('status', 2)
                    ->where('batches_id', $this->batches_id)
                    ->where('medicine_id', $this->medicine_id)
                    ->where('id', '<', $this->id)
                    ->orderByDesc('id')
                    ->first();

                if ($priorLog) {
                    return $priorLog->resolveCreditorName(false);
                }
            }
        }

        return null;
    }
}
