<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineCategory extends Model
{
    use HasFactory;
    protected $table = 'medicine_categories';

    protected $fillable = [
        'team_id',
        'name',
        'type',
        'age', 
        'jersey',
        'nik',
    ];
}
