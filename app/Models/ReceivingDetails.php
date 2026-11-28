<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ReceivingDetails extends Model
{
    use HasFactory;
    protected $table = 'receiving_details';
    protected $fillable = [
        'sp_code',
        'receiving_details_code',
        'receiving_id',
        'creditor_code',
        'invoice_number',
        'invoice_date',
        'invoice_times',
        'invoice_due',
        'invoice_payment',
        'invoice_ppn',
        'is_submitted',
    ];

    protected $casts = [
        'is_submitted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $detail): void {
            if (self::hasFinancePaymentsForDetail((int) $detail->id)) {
                $label = $detail->invoice_number
                    ? "{$detail->receiving_details_code} ({$detail->invoice_number})"
                    : ($detail->receiving_details_code ?: ('#' . $detail->id));

                throw new \RuntimeException(
                    "Nomor Terima / Faktur {$label} tidak dapat dihapus karena sudah memiliki catatan pembayaran di Finance."
                );
            }
        });
    }

    public static function hasFinancePaymentsForDetail(?int $detailId, bool $lock = false): bool
    {
        if (!$detailId || !Schema::hasTable('finance_payments')) {
            return false;
        }

        $query = FinancePayment::where('receiving_detail_id', $detailId);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }
    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'receiving_id');
    }
    public function receiving_items()
    {
        return $this->hasMany(ReceivingItems::class, 'receiving_details_id');
    }
    public function creditor()
    {
        return $this->belongsTo(Creditor::class, 'creditor_code', 'code');
    }

    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'receiving_detail_id');
    }

    /**
     * Faktur dianggap 100% pasti sudah diterima/masuk stok jika memiliki minimal
     * satu item dengan batches_id terisi.
     */
    public function scopePostedToStock(Builder $query): Builder
    {
        return $query->whereHas('receiving_items', fn ($ri) => $ri->whereNotNull('batches_id'));
    }
}

