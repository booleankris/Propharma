<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Matches extends Model
{
    use HasFactory;
    protected $table = 'matches';

    protected $fillable = [
        'match_day_id',
        'team_home',
        'team_away',
        'time'
    ];

    // In Matches.php model
    public function homeTeam() {
        return $this->belongsTo(Team::class, 'team_home');
    }

    public function awayTeam() {
        return $this->belongsTo(Team::class, 'team_away');
    }
    public function matchday() {
        return $this->belongsTo(MatchDay::class, 'match_day_id');
    }

}
