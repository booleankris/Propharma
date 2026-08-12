<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineTransfers extends Model
{
    use HasFactory;
    protected $table = 'medicine_transfers';
    protected $fillable = [
        'code',
        'user_id',
        'status',
    ];
    public function batches()
    {
        return $this->belongsTo(Batches::class, 'batches_id', 'id');
    }
    public function etalases()
    {
        return $this->belongsTo(Etalases::class, 'etalases_id', 'id');
    }
    public function items()
    {
        return $this->hasMany(MedicineTransferItems::class, 'medicine_transfer_id');
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
