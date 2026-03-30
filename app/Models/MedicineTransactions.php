<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineTransactions extends Model
{
    use HasFactory;
    protected $table = 'medicine_transactions';
    protected $fillable = [
        'pharmacy_id',
        'debtor_id',
        'doctor_id',
        'patient_id',
        'transaction_type',
        'transaction_code',
        'paid',
        'changes',
        'subtotal',
        'discount',
        'status',
        'user_id',
        'payment_method',
        'shift_logs_id',

    ];
    public function transactions()
    {
        return $this->hasMany(MedicineCart::class, 'transaction_id');
    }
    public function patients()
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }
}
