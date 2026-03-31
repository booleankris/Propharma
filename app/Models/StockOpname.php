<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    use HasFactory;
    protected $table = 'stock_opname';
    protected $fillable = [
        'users_id',
        'batches_id',
        'stock_physical',
        'stock_discrepancy',
        'stock_total',
        'date',
        'status',
    ];
    public function batches()
    {
        return $this->belongsTo(Batches::class, 'batches_id', 'id');
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
