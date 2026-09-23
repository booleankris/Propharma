<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancePayment extends Model
{
    use HasFactory;

    protected $table = 'finance_payments';

    protected $fillable = [
        'receiving_id',
        'receiving_detail_id',
        'medicine_transaction_id',
        'account_id',
        'payment_type',
        'payment_date',
        'amount',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'float',
    ];

    public function account()
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'receiving_id');
    }

    public function receivingDetail()
    {
        return $this->belongsTo(ReceivingDetails::class, 'receiving_detail_id');
    }

    public function transaction()
    {
        return $this->belongsTo(MedicineTransactions::class, 'medicine_transaction_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
