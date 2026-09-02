<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $table = 'orders';

    protected $fillable = [
        'pharmacy_id',
        'user_id',
        'code',
        'date',
        'status',
        'receiving_id',
    ];
    public function order_items()
    {
        return $this->hasMany(OrderItems::class);
    }
    public function receiving()
    {
        return $this->hasMany(Receiving::class);
    }
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
