<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemMovement extends Model
{
    protected $guarded = ['id'];

    public function sourceItem()
    {
        return $this->belongsTo(OrderItems::class, 'source_item_id');
    }

    public function targetItem()
    {
        return $this->belongsTo(OrderItems::class, 'target_item_id');
    }
}
