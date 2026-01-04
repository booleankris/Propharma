<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Retur extends Model
{
    use HasFactory;
    protected $table = 'retur';
    protected $fillable = [
        'code',
        'transaction_id',
        'medicine_id',
        'qty_retur',
        'total_retur',
        'status', 
    ];
}
