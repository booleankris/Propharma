<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reject extends Model
{
    use HasFactory;
    protected $table = 'reject';
    protected $fillable = [
        'code',
        'date',
        'pharmacy_id',
        'medicine_id',
        'medicine_name', 
        'quantity',
        'total',
        'reason',
    ];
    
    public function medicines()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }
}
