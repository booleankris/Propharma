<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

