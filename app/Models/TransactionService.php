<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jasa yang dipilih pada transaksi penjualan tipe JASA.
 * Satu transaksi hanya memiliki satu jasa (unique medicine_transaction_id).
 */
class TransactionService extends Model
{
    use HasFactory;

    protected $table = 'transaction_services';

    protected $fillable = [
        'medicine_transaction_id',
        'doctor_service_id',
        'service_name',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function transaction()
    {
        return $this->belongsTo(MedicineTransactions::class, 'medicine_transaction_id');
    }

    public function doctorService()
    {
        return $this->belongsTo(DoctorService::class, 'doctor_service_id');
    }
}
