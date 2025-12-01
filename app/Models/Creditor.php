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
        'email',
        'status',
    ];
}
