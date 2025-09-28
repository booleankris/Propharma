<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Debtors extends Model
{
    use HasFactory;

    protected $table = 'debtors';

    protected $fillable = [
        'code',
        'name',
        'address',
        'city',
        'phone',
        'contact',
        'email',
        'status',
    ];

    /**
     * A debtor can have many parameters.
     */
    public function parameters()
    {
        return $this->hasMany(TransactionParameter::class, 'debtor_id', 'id');
    }
}
