<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnPolicy extends Model
{
    use HasFactory;

    protected $table = 'return_policies';

    protected $fillable = [
        'factory_id',
        'factory_name',
        'creditor_code',
        'is_returnable',
        'months_before_ed',
        'months_after_ed',
        'allow_on_ed_month',
        'raw_rule',
        'notes',
    ];

    protected $casts = [
        'is_returnable' => 'boolean',
        'allow_on_ed_month' => 'boolean',
        'months_before_ed' => 'integer',
        'months_after_ed' => 'integer',
    ];

    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function creditor()
    {
        return $this->belongsTo(Creditor::class, 'creditor_code', 'code');
    }
}
