<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineTransactions extends Model
{
    use HasFactory;
    protected $table = 'medicine_transactions';
    protected $fillable = [
        'pharmacy_id',
        'debtor_id',
        'transaction_type',
        'transaction_code',
        'paid',
        'changes',
        'subtotal',
        'discount',
        'status',
    ];
    public function transactions()
    {
        return $this->hasMany(MedicineCart::class);
    }
}
