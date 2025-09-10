<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicines extends Model
{
    use HasFactory;
    protected $table = 'medicines';
    protected $fillable = [
        'code',
        'composition_id',
        'generic',
        'medicine_category_id',
        'pharmacy_id',
        'name',
        'packaging',
        'unit',
        'content',
        'dosage',
        'raw_price',
        'pharmacy_net_price',
        'net_price',
        'psychotropic',
        'minimal_stock',
        'stock',
    ];
}
