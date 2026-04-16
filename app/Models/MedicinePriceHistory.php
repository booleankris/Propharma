<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicinePriceHistory extends Model
{
    use HasFactory;

    protected $table = 'medicine_price_history';

    protected $fillable = [
        'user_id',
        'medicine_id',
        'new_price',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medicines()
    {
        return $this->belongsTo(Medicines::class);
    }
}
