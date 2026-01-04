<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionParameter extends Model
{
    use HasFactory;

    protected $table = "transaction_parameter";

    protected $fillable = [
        'debtor_id',
        'receipt',
        'pdu',
        'otc',
        'credit',
        'embalas',
        'service',
        'rounding',
        'status'
    ];


    /**
     * Each parameter belongs to a debtor.
     */
    public function debtor()
    {
        return $this->belongsTo(Debtors::class, 'debtor_id', 'id');
    }
}
