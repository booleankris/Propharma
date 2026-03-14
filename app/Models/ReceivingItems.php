<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivingItems extends Model
{
    use HasFactory;
    protected $table = 'receiving_items';
    protected $fillable = [
        'receiving_details_id',
        'order_items_id',
        'qty_received',
        'discount',
        'extra_discount',
        'expired_date',
        'batch',
        'location',
        'etalase',
        'total',
        'status',
    ];

    public function receiving_details()
    {
        return $this->belongsTo(ReceivingDetails::class, 'receiving_details_id');
    }

    public function order_items()
    {
        return $this->belongsTo(OrderItems::class, 'order_items_id');
    }
}
