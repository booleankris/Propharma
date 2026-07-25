<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemsLog extends Model
{
    use HasFactory;
    protected $casts = [
        'date' => 'datetime',
    ];
    protected $table = 'items_log';
    protected $fillable = [
        'transaction_code',
        'code',
        'type',
        'medicine_id',
        'batches_id',
        'qty',
        'qty_before',
        'qty_after',
        'total',
        'date',
        'status',
        'user_id',

    ];
    public function medicines()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }
    public function transaction()
    {
        return $this->belongsTo(MedicineTransactions::class, 'transaction_code', 'transaction_code');
    }
    public function batches()
    {
        return $this->belongsTo(Batches::class, 'batches_id');
    }
    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'transaction_code', 'code');
    }

    public function medicine_transaction()
    {
        return $this->belongsTo(MedicineTransactions::class, 'transaction_code', 'transaction_code');
    }
}
