<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItems extends Model
{
    use HasFactory;
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'medicine_id',
        'creditor_code',
        'pack',
        'price',
        'quantity',
        'total',
        'status'
    ];

    public function medicines()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }
    public function orders()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
    public function receivingItems()
    {
        return $this->hasMany(ReceivingItems::class, 'order_items_id');
    }
    public function receiving_items()
    {
        return $this->hasOne(ReceivingItems::class, 'order_items_id');
    }
    public function creditors()
    {
        return $this->belongsTo(Creditor::class, 'creditor_code', 'code');
    }
}
