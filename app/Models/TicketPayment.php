<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketPayment extends Model
{
    use HasFactory;
    protected $table = 'ticket_payment';

    protected $fillable = [
        'transaction_id',
        'payment_code',
        'total_price',
        'status',
        'status_payment',
        'payment_url',
        'ref',
        'expired_at',
        'pay_at',
    ];
    public function transaction()
    {
        return $this->belongsTo(TicketTransaction::class);
    }
}
