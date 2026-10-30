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
        'retur_type',
        'stock_unit_factor',
        'batch_number',
        'expired_date',
        'qty',
        'unit_price',
        'subtotal',
        'status',
        'status_date',
        'confirmed_date',
        'deduction_date',
        'stock_deducted',
        'reason',
        'pickup_status',
        'pickup_date',
        'deduction_note',
        'notes',
    ];

    protected $casts = [
        'expired_date' => 'date',
        'pickup_date' => 'date',
        'status_date' => 'date',
        'confirmed_date' => 'date',
        'deduction_date' => 'date',
        'stock_deducted' => 'boolean',
        'stock_unit_factor' => 'integer',
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
