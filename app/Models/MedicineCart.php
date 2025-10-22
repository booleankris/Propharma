<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineCart extends Model
{
    use HasFactory;
    protected $table = 'medicine_cart';

    protected $fillable = [
        'user_id',
        'medicine_id',
        'transaction_id',
        'quantity',
        'discount', 
        'embalase',
        'cart_type',
        'package',
        'dosage_r',
        'raw_total',
        'total_price',
        'final_price',
        'status',
        'recipe_status',
        'recipe_number',
    ];
    public function medicine()
    {
        return $this->belongsTo(Medicines::class);
    }
    public function transactions()
    {
        return $this->belongsTo(MedicineTransactions::class, 'transaction_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
