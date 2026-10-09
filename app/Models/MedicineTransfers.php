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
        'submission_key',
        'user_id',
        'status',
        'is_request',
        'source_pharmacy_id',
        'destination_pharmacy_id',
        'request_status',
    ];
    protected $casts = ['is_request' => 'boolean'];
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

    public function sourcePharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'source_pharmacy_id');
    }

    public function destinationPharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'destination_pharmacy_id');
    }
}
