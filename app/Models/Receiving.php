<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Receiving extends Model
{
    use HasFactory;

    protected $table = 'receiving';
    protected $fillable = [
        'pharmacy_id',
        'code',
        'date',
        'status',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $receiving): void {
            if (self::hasFinancePaymentsForReceiving((int) $receiving->id)) {
                $label = $receiving->code ?: ('#' . $receiving->id);

                throw new \RuntimeException(
                    "Penerimaan {$label} tidak dapat dihapus karena sudah memiliki catatan pembayaran di Finance."
                );
            }
        });
    }

    public static function hasFinancePaymentsForReceiving(?int $receivingId, bool $lock = false): bool
    {
        if (!$receivingId || !Schema::hasTable('finance_payments')) {
            return false;
        }

        $detailIds = ReceivingDetails::where('receiving_id', $receivingId)->pluck('id');
        $query = FinancePayment::where(function ($q) use ($receivingId, $detailIds) {
            $q->where('receiving_id', $receivingId);
            if ($detailIds->isNotEmpty()) {
                $q->orWhereIn('receiving_detail_id', $detailIds);
            }
        });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }
    public function receiving_details()
    {
        return $this->hasMany(ReceivingDetails::class, 'receiving_id');
    }
    public function receivings()
    {
        return $this->belongsTo(ReceivingDetails::class);
    }
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }
    public function receiving_items()
    {
        return $this->hasManyThrough(
            ReceivingItems::class,
            ReceivingDetails::class,
            'receiving_id',        // FK on receiving_details
            'receiving_details_id', // FK on receiving_items
            'id',                  // PK on receiving
            'id'                   // PK on receiving_details
        );
    }

    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'receiving_id');
    }

    /**
     * Naikkan otomatis receiving berstatus 0 menjadi 2 jika di dalamnya sudah ada
     * item penerimaan yang 100% masuk stok (batches_id tidak null).
     */
    public static function syncPostedZeroStatus(?array $pharmacyIds = null, ?array $receivingIds = null): int
    {
        $updated = static::query()
            ->where('status', 0)
            ->when(!empty($pharmacyIds), fn ($q) => $q->whereIn('pharmacy_id', $pharmacyIds))
            ->when(!empty($receivingIds), fn ($q) => $q->whereIn('id', $receivingIds))
            ->where(function ($q) {
                $q->whereHas('receiving_details.receiving_items', fn ($ri) => $ri->whereNotNull('batches_id'))
                    ->orWhereExists(function ($sub) {
                        $sub->selectRaw('1')
                            ->from('items_log')
                            ->whereColumn('items_log.transaction_code', 'receiving.code')
                            ->where('items_log.status', 2);
                    });
            })
            ->update(['status' => 2]);

        Order::syncStatusWithReceiving($pharmacyIds);

        return $updated;
    }
}

