<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceivingDetails extends Model
{
    use HasFactory;
    protected $table = 'receiving_details';
    protected $fillable = [
        'sp_code',
        'receiving_details_code',
        'receiving_id',
        'creditor_code',
        'invoice_number',
        'invoice_date',
        'invoice_times',
        'invoice_due',
        'invoice_payment',
        'invoice_ppn',
    ];
    public function receiving()
    {
        return $this->belongsTo(Receiving::class, 'receiving_id');
    }
    public function receiving_items()
    {
        return $this->hasMany(ReceivingItems::class);
    }
    public function creditor()
    {
        return $this->belongsTo(Creditor::class, 'creditor_code', 'code');
    }
}
