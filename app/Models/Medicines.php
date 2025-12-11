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
        'generic',
        'pharmacy_id',
        'medicine_category_id',
        'composition_id',
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
        'psychotropic',
        'minimal_stock',
        'stock',
        'status',
        'preparations',
        'whole',
        'precursor',
        'receipt',

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
    public static function generateCode()
    {
        $last = self::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return '054000000';
        }

        $prefix = substr($last->code, 0, 4);
        $number = (int) substr($last->code, 4);

        if ($number >= 99999) {
            $prefix = str_pad(((int)$prefix) + 1, 4, '0', STR_PAD_LEFT);
            $number = 0;
        } else {
            $number++;
        }

        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}
