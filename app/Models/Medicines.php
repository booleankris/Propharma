<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicines extends Model
{
    use HasFactory;
    protected $table = 'medicines';
    protected $fillable = [
        'code',
        'barcode',
        'generic',
        'pharmacy_id',
        'medicine_category_id',
        'composition_id',
        'component',
        'factory_id',
        'creditors_id',
        'name',
        'packaging',
        'unit',
        'content',
        'dosage',
        'raw_price',
        'pharmacy_net_price',
        'net_price',
        'het_price',
        'psychotropic',
        'minimal_stock',
        'stock',
        'status',
        'preparations',
        'whole',
        'precursor',
        'receipt',
        'etalase',
        'location',
        'type',
        'strip'
    ];
    public function factory()
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }
    public function composition()
    {
        return $this->belongsTo(Composition::class, 'composition_id');
    }
    public function category()
    {
        return $this->belongsTo(MedicineCategory::class, 'medicine_category_id');
    }
    public function creditor()
    {
        return $this->belongsTo(Creditor::class, 'creditors_id');
    }
    public function creditors()
    {
        return $this->belongsToMany(
            Creditor::class,
            'medicine_creditors',
            'medicine_id',
            'creditor_code',
            'id',
            'code'
        )->withPivot('discount')->withTimestamps();
    }
    public function transactions()
    {
        return $this->hasMany(MedicineCreditor::class);
    }
    public function batches()
    {
        return $this->hasMany(Batches::class, 'medicine_id', 'id');
    }
    public function etalases()
    {
        return $this->belongsTo(Etalases::class, 'etalase');
    }
    public function locations()
    {
        return $this->belongsTo(Locations::class, 'location');
    }
    public function items_log()
    {
        return $this->hasMany(ItemsLog::class, 'medicine_id');
    }
    public function order_items()
    {
        return $this->hasMany(OrderItems::class, 'medicine_id');
    }
    public function history()
    {
        return $this->hasMany(MedicinePriceHistory::class, 'medicine_id');
    }
    public static function generateCode()
    {
        $last = self::whereRaw("code REGEXP '^[0-9]{9}$'")
            ->orderBy('code', 'desc')
            ->first();

        if (!$last || !$last->code) {
            $prefix = '0540';
            $number = 0;
        } else {
            $prefix = substr($last->code, 0, 4);
            $number = (int) substr($last->code, 4);
        }

        do {
            if ($number >= 99999) {
                $prefix = str_pad(((int) $prefix) + 1, 4, '0', STR_PAD_LEFT);
                $number = 1;
            } else {
                $number++;
            }
            $code = $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
        } while (self::where('code', $code)->exists());

        return $code;
    }
    public function medicine_transactions()
    {
        return $this->hasMany(MedicineTransactions::class, 'id');
    }
}
