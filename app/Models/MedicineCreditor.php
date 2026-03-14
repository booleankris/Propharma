<?php

namespace App\Models;

use App\Http\Controllers\Master\CreditorsController;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineCreditor extends Model
{
    use HasFactory;
    protected $table = 'medicine_creditors';

    protected $fillable = [
        'medicine_id',
        'creditor_code',
    ];

    public function medicine()
    {
        return $this->belongsTo(Medicines::class, 'medicine_id');
    }

    public function creditor()
    {
        return $this->belongsTo(CreditorsController::class, 'creditor_id');
    }
}
