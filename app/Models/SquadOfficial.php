<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SquadOfficial extends Model
{
    use HasFactory;
    protected $table = 'squad_official';

    protected $fillable = [
        'team_id',
        'photo',
        'name',
        'details',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute() {
        if ($this->photo) {
            return asset('/uploads/official/'.$this->photo);
        } 
        return null;
    }
}
