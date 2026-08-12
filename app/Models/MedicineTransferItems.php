<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineTransferItems extends Model
{
    protected $table = 'medicine_transfer_items';
    protected $fillable = [
        'medicine_transfer_id',
        'batches_id',
        'source_batches_id',
        'etalases_id',
        'qty',
        'status',
    ];

    public function sourceBatch()
    {
        return $this->belongsTo(Batches::class, 'source_batches_id');
    }
    public function transfer()
    {
        return $this->belongsTo(MedicineTransfers::class, 'medicine_transfer_id');
    }
    public function batches()
    {
        return $this->belongsTo(Batches::class, 'batches_id');
    }
    public function etalases()
    {
        return $this->belongsTo(Etalases::class, 'etalases_id');
    }
}