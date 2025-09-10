<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamPayment extends Model
{
    use HasFactory;
    protected $table = 'team_payment';

    protected $fillable = [
        'team_id',
        'type',
        'payment_code',
        'total_price',
        'status',
        'status_payment',
        'payment_url',
        'ref',
        'expired_at',
        'pay_at',
    ];
}
