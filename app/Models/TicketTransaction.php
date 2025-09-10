<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketTransaction extends Model
{
    use HasFactory;
    protected $table = 'ticket_transaction';

    protected $fillable = [
        'user_id',
        'transaction_code',
        'subtotal'
    ];
    // public function tickets()
    // {
    //     return $this->hasMany(TicketDetail::class, 'ticket_transaction_id');
    // }
    public function payment()
    {
        return $this->hasOne(TicketPayment::class, 'transaction_id');
    }
//     public function matchDay(){
//         return $this->belongsTo(MatchDay::class, 'match_day_id');
//     }
}
