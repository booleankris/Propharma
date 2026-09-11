<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Etalases extends Model
{
    use HasFactory;
    protected $table = 'etalases';

    protected $fillable = [
        'name',
        'pharmacy_id',
        'status',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }
}
