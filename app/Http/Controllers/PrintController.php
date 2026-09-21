<?php

namespace App\Http\Controllers;

use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Patients;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function receipt($id)
    {
        $items = MedicineCart::with(['medicine', 'transactions'])
            ->whereHas('transactions', fn($q) => $q->where('id', $id))
            ->get();

        // Yang pecah jadi satu-satu itulahh
        $transactionCart = $items->groupBy(fn($recipe) => $recipe->recipe_number ?? 'single');

        $transaction = MedicineTransactions::with(['patients', 'doctors'])->findOrFail($id);

        $totalEmbalase = $items->sum('embalase');
        $totalFinalPrice = $items->sum('final_price') - ($transaction->discount ?? 0);
        $totalPrice = $items->sum('total_price');
        $discount = $items->sum('discount');
        $operator = $transaction->user->name;
        $pharmacy_name = $transaction->pharmacy->name;
        $pharmacy_phone = $transaction->pharmacy->phone;
        $pharmacy_address = $transaction->pharmacy->address;
        $subtotaldiscount = $transaction->discount ?? 0;
        $totaldiscount = ceil(($discount + $subtotaldiscount) / 1000) * 1000;
        $payment = $totalFinalPrice - $totaldiscount;

        return view('kasir.receipt', compact(
            'payment',
            'totalEmbalase',
            'transaction',
            'transactionCart',
            'totalFinalPrice',
            'totalPrice',
            'totaldiscount',
            'operator',
            'pharmacy_phone',
            'pharmacy_name',
            'pharmacy_address'
        ));
    }

    public function fullReceipt($id)
    {
        $items = MedicineCart::with(['medicine', 'transactions'])
            ->whereHas('transactions', fn($q) => $q->where('id', $id))
            ->get();

        // Yang pecah jadi satu-satu itulahh
        $transactionCart = $items->groupBy(fn($recipe) => $recipe->recipe_number ?? 'single');

        $transaction = MedicineTransactions::with(['patients', 'doctors'])->findOrFail($id);

        $totalEmbalase = $items->sum('embalase');
        $totalFinalPrice = $items->sum('final_price') - ($transaction->discount ?? 0);
        $totalPrice = $items->sum('total_price');
        $discount = $items->sum('discount');
        $operator = $transaction->user->name;
        $pharmacy_name = $transaction->pharmacy->name;
        $pharmacy_phone = $transaction->pharmacy->phone;
        $pharmacy_address = $transaction->pharmacy->address;
        $subtotaldiscount = $transaction->discount ?? 0;
        $totaldiscount = ceil(($discount + $subtotaldiscount) / 1000) * 1000;
        $payment = $totalFinalPrice - $totaldiscount;

        return view('kasir.allreceipt', compact(
            'payment',
            'totalEmbalase',
            'transaction',
            'transactionCart',
            'totalFinalPrice',
            'totalPrice',
            'totaldiscount',
            'operator',
            'pharmacy_phone',
            'pharmacy_name',
            'pharmacy_address'
        ));
    }

    public function kwitansi($id)
    {
        $transaction = MedicineTransactions::with(['patients', 'doctors', 'user', 'pharmacy'])->findOrFail($id);

        $items = MedicineCart::with('medicine')
            ->where('transaction_id', $id)
            ->get();

        $subtotal = $items->sum('final_price');
        $transactionDiscount = $transaction->discount ?? 0;
        $totalPrice = max(0, $subtotal - $transactionDiscount);
        $totalEmbalase = $items->sum('embalase');

        $terbilang = ucwords(terbilang((int) $totalPrice)) . ' Rupiah';

        $pharmacy = $transaction->pharmacy;
        $patient = $transaction->patients;
        $doctor = $transaction->doctors;
        $operator = $transaction->user?->name ?? 'Kasir';

        // Deskripsi Untuk Pembayaran
        if ($doctor && !empty($doctor->name)) {
            $paymentFor = 'Obat - obatan dari resep dr. ' . $doctor->name;
        } elseif (!empty($transaction->recipe_number)) {
            $paymentFor = 'Obat - obatan dari resep No. ' . $transaction->recipe_number;
        } else {
            $paymentFor = 'Pembelian Obat & Pelayanan Farmasi';
        }

        return view('sales.kwitansi', compact(
            'transaction',
            'items',
            'subtotal',
            'transactionDiscount',
            'totalPrice',
            'totalEmbalase',
            'terbilang',
            'pharmacy',
            'patient',
            'doctor',
            'operator',
            'paymentFor'
        ));
    }
}
