<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivingItems extends Model
{
    use HasFactory;
    protected $table = 'receiving_items';
    protected $fillable = [
        'raw_price',
        'receiving_details_id',
        'order_items_id',
        'qty_received',
        'qty',
        'discount',
        'extra_discount',
        'expired_date',
        'batch',
        'location',
        'etalase',
        'total',
        'status',
        'batches_id',
    ];

    public function receiving_details()
    {
        return $this->belongsTo(ReceivingDetails::class, 'receiving_details_id');
    }

    public function order_items()
    {
        return $this->belongsTo(OrderItems::class, 'order_items_id');
    }

    public function etalases()
    {
        return $this->belongsTo(Etalases::class, 'etalase');
    }
    public function locations()
    {
        return $this->belongsTo(Locations::class, 'location');
    }
    public function batches()
    {
        return $this->belongsTo(Batches::class, 'batches_id');
    }
}
