<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceBalanceAdjustment extends Model
{
    use HasFactory;

    protected $table = 'finance_balance_adjustments';

    protected $fillable = [
        'pharmacy_id',
        'account_id',
        'system_balance',
        'real_balance',
        'difference',
        'adjustment_date',
        'adjusted_at',
        'notes',
        'finance_payment_id',
        'created_by',
    ];

    protected $casts = [
        'system_balance' => 'float',
        'real_balance' => 'float',
        'difference' => 'float',
        'adjustment_date' => 'date',
        'adjusted_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    public function payment()
    {
        return $this->belongsTo(FinancePayment::class, 'finance_payment_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

