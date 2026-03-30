<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfers extends Model
{
    use HasFactory;
    protected $table = 'medicine_transfers';
    protected $fillable = [
        'batches_id',
        'etalases_id',
        'stock',
        'qty',
        'status',
    ];
    public function batches()
    {
        return $this->belongsTo(Batches::class, 'batches_id', 'id');
    }
    public function etalases()
    {
        return $this->belongsTo(Etalases::class, 'etalase');
    }
}
