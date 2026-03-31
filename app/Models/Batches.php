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
        'pharmacy_id',
        'name',
        'expired_date',
        'stock',
        'status',
    ];
    public function medicines()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id', 'id');
    }
    public function medicine_transfers()
    {
        return $this->hasMany(MedicineTransfers::class, 'batches_id', 'id'); 
    }
    public function receivingItems()
    {
        return $this->hasMany(ReceivingItems::class, 'batches_id', 'id');
    }
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id', 'id');
    }
}
