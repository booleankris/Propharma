<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceOpeningInvoice extends Model
{
    use HasFactory;

    protected $table = 'finance_opening_invoices';

    protected $fillable = [
        'pharmacy_id',
        'type',
        'creditor_code',
        'debtor_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'amount',
        'is_submitted',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'amount' => 'float',
        'is_submitted' => 'boolean',
    ];

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    public function creditor()
    {
        return $this->belongsTo(Creditor::class, 'creditor_code', 'code');
    }

    public function debtor()
    {
        return $this->belongsTo(Debtors::class, 'debtor_id');
    }

    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'opening_invoice_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function parseCompositeId($rawId): ?int
    {
        $str = trim((string) $rawId);
        if (preg_match('/^OB-(\d+)$/i', $str, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }
}
