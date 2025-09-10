<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchDay extends Model
{
    use HasFactory;
    protected $table = 'match_day';

    protected $fillable = [
        'day',
        'place',
        'date'
    ];
    public function match() {
        return $this->hasMany(Matches::class);
    }
}
