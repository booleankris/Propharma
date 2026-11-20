<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferLocation extends Model
{
    use HasFactory;

    protected $table = 'transfer_locations';

    protected $fillable = [
        'name',
        'pharmacy_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getUpdatedByNameAttribute(): ?string
    {
        $user = $this->updater ?: $this->creator;

        return $user ? ($user->name ?: ($user->username ?? null)) : null;
    }
}
