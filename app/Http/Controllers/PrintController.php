<?php

namespace App\Http\Controllers;

use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function receipt($id)
    {

        $transactionCart = MedicineCart::with(['medicine', 'transactions'])
            ->whereHas('transactions', function ($q) use ($id) {
                $q->where('id', $id);
            })
            ->get();

        $totalPrice = $transactionCart->sum('total_price');
        $totalRawTotal = $transactionCart->sum('raw_total');
        $totalFinalPrice = $transactionCart->sum('final_price');
        $discount = $transactionCart->sum('discount');
        $transaction      = $transactionCart->first()?->transactions;
        $subtotaldiscount = $transaction->discount ?? 0;
        $totaldiscount = ceil(($discount + $subtotaldiscount) / 1000) * 1000;
        $payment = $totalRawTotal - $totaldiscount;

        return view('kasir.receipt', compact('payment','transactionCart','totalRawTotal', 'totalPrice', 'totalFinalPrice', 'totaldiscount', 'subtotaldiscount', 'totaldiscount'));
    }
}
