<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected $fillable = [
        'user_id',
        'item_name',
        'item_desc',
        'item_photo',
        'item_price',
        'item_status',
    ];
    public function cart_status()
    {
        return $this->hasMany(ItemCart::class, 'items_id')
            ->where('status', '!=', 1);
    }
    public function cart()
    {
        return $this->hasMany(ItemCart::class, 'items_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
