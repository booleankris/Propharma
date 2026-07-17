<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receiving extends Model
{
    use HasFactory;

    protected $table = 'receiving';
    protected $fillable = [
        'pharmacy_id',
        'code',
        'date',
        'status',
    ];
    public function receiving_details()
    {
        return $this->hasMany(ReceivingDetails::class, 'receiving_id');
    }
    public function receivings()
    {
        return $this->belongsTo(ReceivingDetails::class);
    }
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }
    public function receiving_items()
    {
        return $this->hasManyThrough(
            ReceivingItems::class,
            ReceivingDetails::class,
            'receiving_id',        // FK on receiving_details
            'receiving_details_id', // FK on receiving_items
            'id',                  // PK on receiving
            'id'                   // PK on receiving_details
        );
    }
}
