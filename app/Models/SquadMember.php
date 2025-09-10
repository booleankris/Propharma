<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SquadMember extends Model
{
    use HasFactory;
    protected $table = 'squad_member';

    protected $fillable = [
        'team_id',
        'name',
        'photo',
        'number', 
        'position',
        'dateofbirth',
        'identity_card'
    ];

    protected $appends = ['image_url', 'identity_url'];

    public function getImageUrlAttribute() {
        if ($this->photo) {
            return asset('/uploads/squad/'.$this->photo);
        } 
        return null;
    }

    public function getIdentityUrlAttribute() {
        if ($this->identity_card) {
            return asset('/uploads/squad/identitycard/'.$this->identity_card);
        } 
        return null;
    }
}
