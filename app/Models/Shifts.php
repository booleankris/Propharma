<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shifts extends Model
{
    use HasFactory;
    protected $table = 'shift';
    protected $fillable = [
        'code',
        'name',
        'shift_in',
        'shift_out',
        'total_retur',
        'status', 
    ];
}
