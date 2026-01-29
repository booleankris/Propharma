<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineCreditor extends Model
{
    use HasFactory;
    protected $table = 'medicine_creditor';

    protected $fillable = [
        'medicine_id',
        'creditor_id',
    ];
}
