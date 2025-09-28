<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionParameter extends Model
{
    use HasFactory;

    protected $table = "transaction_parameter";

    protected $fillable = [
        'id',
        'debtor_id',
        'receipt',
        'debtor_id',
        'debtor_id',
        'debtor_id',

    ];

    /**
     * Each parameter belongs to a debtor.
     */
    public function debtor()
    {
        return $this->belongsTo(Debtors::class, 'debtor_id', 'id');
    }
}
