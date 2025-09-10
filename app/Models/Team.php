<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;
    protected $table = 'teams';

    protected $fillable = [
        'name',
        'slug',
        'code',
        'logo', 
        'status',
        'manager_name',
        'manager_email',
        'manager_phone',
        'statement_letter',
    ];

    protected $appends = ['logo_url','file_url'];

    public function getLogoUrlAttribute() {
        if ($this->logo) {
            return asset('/uploads/teams/'.$this->logo);
        } 
        return null;
    }

    public function getFileUrlAttribute() {
        if ($this->statement_letter) {
            return asset('/uploads/surat-pernyataan/'.$this->statement_letter);
        } 
        return null;
    }

    public function repayment()
    {
        return $this->hasOne(TeamPayment::class)->where('type', 'FULL');
    }

    public function downpayment()
    {
        return $this->hasOne(TeamPayment::class)->where('type', 'DP');
    }

    public function officiallist()
    {
        return $this->hasMany(SquadOfficial::class);
    }

    public function squadlist()
    {
        return $this->hasMany(SquadMember::class);
    }
}
