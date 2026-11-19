<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockRevision extends Model
{
    use HasFactory;

    protected $table = 'stock_revisions';

    protected $fillable = [
        'revision_code',
        'pharmacy_id',
        'target_mode',
        'medicine_id',
        'user_id',
        'reason',
        'notes',
        'db_stock_before',
        'db_stock_after',
        'ledger_balance_before',
        'ledger_balance_after',
        'ledger_delta',
        'batch_details',
        'items_log_id',
    ];

    protected $casts = [
        'batch_details' => 'array',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function itemsLog()
    {
        return $this->belongsTo(ItemsLog::class, 'items_log_id');
    }
}
