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
        'city',
        'phone',
        'fax',
        'ppn_type',
        'numbers',
        'bank_type',
        'bank_number',
        'bank_name',
        'npwp',
        'status',
    ];
}
