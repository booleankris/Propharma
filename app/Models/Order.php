<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;
    protected $table = 'orders';

    protected $fillable = [
        'pharmacy_id',
        'user_id',
        'code',
        'date',
        'status',
        'receiving_id',
        'is_consolidation',
        'consolidation_key',
        'note',
    ];
    public function order_items()
    {
        return $this->hasMany(OrderItems::class);
    }
    public function orderItems()
    {
        return $this->order_items();
    }
    public function receiving()
    {
        return $this->hasMany(Receiving::class);
    }
    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Naikkan otomatis orders berstatus 0 (PENDING) menjadi 2 (DIPESAN / dalam penerimaan)
     * jika di dalamnya sudah terdapat item penerimaan (draft maupun sudah masuk stok).
     */
    public static function syncStatusWithReceiving(?array $pharmacyIds = null, ?array $orderIds = null): int
    {
        return static::query()
            ->where('status', 0)
            ->when(!empty($pharmacyIds), fn ($q) => $q->whereIn('pharmacy_id', $pharmacyIds))
            ->when(!empty($orderIds), fn ($q) => $q->whereIn('id', $orderIds))
            ->whereHas('order_items.receivingItems')
            ->update(['status' => 2]);
    }
}
