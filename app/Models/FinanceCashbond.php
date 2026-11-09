<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceCashbond extends Model
{
    use HasFactory;

    protected $table = 'finance_cashbonds';

    protected $fillable = [
        'pharmacy_id',
        'account_id',
        'borrower_name',
        'amount',
        'transaction_date',
        'purpose',
        'status',
        'settlement_date',
        'settlement_notes',
        'proof_image',
        'created_by',
        'settled_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'transaction_date' => 'date',
        'settlement_date' => 'date',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    public function account()
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function settler()
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}

