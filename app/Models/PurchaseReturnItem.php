<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_return_items';

    protected $fillable = [
        'purchase_return_id',
        'medicine_id',
        'batch_id',
        'medicine_name',
        'unit',
        'batch_number',
        'expired_date',
        'qty',
        'unit_price',
        'subtotal',
        'reason',
        'pickup_status',
        'pickup_date',
        'deduction_note',
        'notes',
    ];

    protected $casts = [
        'expired_date' => 'date',
        'pickup_date' => 'date',
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batches::class, 'batch_id');
    }
}
