<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Composition extends Model
{
    protected $table = 'compositions';
    protected $fillable = [
        'code',
        'name',
        'status'
    ];
}
