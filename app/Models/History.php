<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    use HasFactory;
    protected $table = 'history';

    protected $fillable = [
        'code',
        'type',
        'medicine_id',
        'qty',
        'total',
        'date',
        'status'
    ];
}
