<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctors extends Model
{
    use HasFactory;
    protected $table = 'doctors';

    protected $fillable = [
        'pharmacy_id',
        'code',
        'name',
        'specialist',
        'address',
        'city',
        'phone',
        'status',
    ];
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id', 'id');
    }

    public function pharmacies()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id', 'id');
    }
}
