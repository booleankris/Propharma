<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketDetail extends Model
{
    use HasFactory;
    protected $table = 'ticket_details';

    protected $fillable = [
        'ticket_transaction_id',
        'ticket_qr',
        'status',
    ];
    public function transaction(){
        return $this->belongsTo(TicketTransaction::class, 'ticket_transaction_id');
    }
}
