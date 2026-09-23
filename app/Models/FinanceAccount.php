<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceAccount extends Model
{
    use HasFactory;

    protected $table = 'finance_accounts';

    protected $fillable = [
        'code',
        'name',
        'name_en',
        'category',
        'account_number',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'float',
        'is_active' => 'boolean',
    ];

    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'account_id');
    }
}
