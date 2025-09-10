<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $table = 'items';

    protected $fillable = [
        'team_id',
        'name',
        'type',
        'age', 
        'jersey',
        'nik',
    ];
}
