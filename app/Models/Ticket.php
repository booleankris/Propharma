<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;
    protected $table = 'tickets';

    protected $fillable = [
        'match_id',
        'match_day_id',
        'match_name',
        'quota',
    ];
    public function matchDay(){
        return $this->belongsTo(MatchDay::class, 'match_day_id');
    }

}
