<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patients extends Model
{
    use HasFactory;
    protected $table = 'patients';

    protected $fillable = [
        'code',
        'name',
        'address',
        'phone',
        'city',
        'birth',

    ];
    public function transactions()
    {
        return $this->hasMany(MedicineTransactions::class, 'patient_id');
    }
}
