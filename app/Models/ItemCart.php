<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCart extends Model
{
    use HasFactory;
    protected $table = 'items_cart';
    protected $fillable = [
        'user_id',
        'items_id',
        'transaction_id',
        'quantity'
    ];
    public function item() {
        return $this->belongsTo(Item::class, 'items_id');
    }
}   
