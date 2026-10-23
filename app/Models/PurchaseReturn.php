<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasFactory;

    protected $table = 'purchase_returns';

    protected $fillable = [
        'return_no',
        'invoice_number',
        'invoice_date',
        'received_date',
        'creditor_code',
        'pharmacy_id',
        'user_id',
        'receiving_id',
        'total_amount',
        'status',
        'pickup_date',
        'deduction_status',
        'deduction_date',
        'reason',
        'notes',
        'is_manual',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'received_date' => 'date',
        'pickup_date' => 'date',
        'deduction_date' => 'date',
        'total_amount' => 'decimal:2',
        'is_manual' => 'boolean',
    ];

    public function creditor()
    {
        return $this->belongsTo(Creditor::class, 'creditor_code', 'code');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'receiving_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }
}
