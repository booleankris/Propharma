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
     
        $transaction     = MedicineTransactions::with(['patients', 'doctors'])->findOrFail($id);

        $totalEmbalase   = $items->sum('embalase');
        $totalFinalPrice = $items->sum('final_price') - ($transaction->discount ?? 0);  
        $totalPrice      = $items->sum('total_price');
        $discount        = $items->sum('discount');
        $operator        = $transaction->user->name;
        $pharmacy_address = $transaction->pharmacy->address;
        $subtotaldiscount = $transaction->discount ?? 0;
        $totaldiscount   = ceil(($discount + $subtotaldiscount) / 1000) * 1000;
        $payment         = $totalFinalPrice - $totaldiscount;

        return view('kasir.receipt', compact(
            'payment',
            'totalEmbalase',
            'transaction',
            'transactionCart',
            'totalFinalPrice',
            'totalPrice',
            'totaldiscount',
            'operator',
            'pharmacy_address'
        ));
    }
    public function fullReceipt($id)
    {
        $items = MedicineCart::with(['medicine', 'transactions'])
            ->whereHas('transactions', function ($q) use ($id) {
                $q->where('id', $id);
            })
            ->get();

        $transactionCart = $items->groupBy(function ($recipe) {
            return $recipe->recipe_number ?? 'single';
        });

        $transaction = MedicineTransactions::with(['patients', 'doctors'])->find($id);

        $totalEmbalase = $items->sum('embalase');
        $totalPrice = $items->sum('final_price');
        $totalFinalPrice = $items->sum('final_price');
        $discount = $items->sum('discount');

        $subtotaldiscount = $transaction->discount ?? 0;
        $totaldiscount = ceil(($discount + $subtotaldiscount) / 1000) * 1000;

        $payment = $totalFinalPrice - $totaldiscount;

        return view('kasir.allreceipt', compact(
            'payment',
            'transaction',
            'transactionCart',
            'totalPrice',
            'totalFinalPrice',
            'totaldiscount',
            'subtotaldiscount'
        ));
    }
}
