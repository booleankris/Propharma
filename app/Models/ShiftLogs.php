<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftLogs extends Model
{
    use HasFactory;

    // Allow mass assignment for these columns
    protected $fillable = [
        'user_id',
        'shift_id',
        'clock_in',
        'clock_out',
        'status',
    ];

    // Relationships
    public function shift()
    {
        return $this->belongsTo(Shifts::class, 'shift_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}