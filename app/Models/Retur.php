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

    public function transaction()
    {
        return $this->belongsTo(MedicineTransactions::class, 'transaction_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }
}
