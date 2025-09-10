<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pharmacies extends Model
{
    use HasFactory;
    protected $table = 'pharmacies';
    protected $fillable = [
        'name',
        'address',
        'phone',
        'city',
        'npwp',
        'permit',
        'pharmacist_permit',
        'pharmacist',
        'footnote1',
        'footnote2',
        'status',
    ];
}
