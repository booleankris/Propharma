<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batches extends Model
{
    use HasFactory;
    protected $table = 'batches';

    protected $fillable = [
        'medicine_id',
        'name',
        'expired_date',
        'stock',
        'status',
    ];
    public function medicines()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }
}
