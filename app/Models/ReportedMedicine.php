<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportedMedicine extends Model
{
    use HasFactory;

    protected $table = 'reported_medicines';

    protected $fillable = [
        'pharmacy_id',
        'medicine_id',
        'user_id',
        'notes',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    public function medicine()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
