<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    use HasFactory;
    protected $table = 'receiving';

    protected $fillable = [
        'pharmacy_id',
        'user_id',
        'code',
        'date',
        'status',
    ];
}
