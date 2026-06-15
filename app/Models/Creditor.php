<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Creditor extends Model
{
    use HasFactory;
    protected $table = 'creditors';

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'ppn_type',
        'numbers',
        'bank_type',
        'bank_number',
        'bank_name',
        'npwp',
        'credit_time',
        'status',
    ];
}
