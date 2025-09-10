<?php

namespace App\Http\Controllers;

use App\Models\TicketTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TransactionReportController extends Controller
{
    public function index(Request $request)
    {
        // if ($request->ajax()) {

        //     $tickets = TicketTransaction::with(['matchDay','payment']);
        //     if ($request->has('order') == false) {
        //         $tickets = $tickets->orderBy('created_at', 'ASC');
        //     }

        //     return DataTables::of($tickets)
        //         ->addIndexColumn()
        //         ->addColumn('name', fn($row) => $row->name ?? '—')


        //         ->addColumn('day', function ($data) {

        //             $matchDayId = $data->matchDay->day ?? '—'; // Safe null check
        //             $day = '<p class="btn btn-primary">' . $matchDayId . ' Ticket Pass</p>';

        //             return $day;

        //         })
        //         ->addColumn('status', function ($data) {

        //             if ($data->payment && $data->payment->status == "SETTLEMENT") {
        //                 $status = '<p class="badge badge-success">Pembayaran Berhasil <i class="fas fa-check"></i></p>';
        //             } else {
        //                 $status = '<p class="badge badge-warning">Pembayaran Pending<i style="margin-left:5px" class="fas fa-spinner"></i></p>';
        //             }
        //             return $status;
        //         })
        //         ->escapeColumns([ 'quantity'])
        //         ->toJson();
        // }
        // $transactions = TicketTransaction::with('payment')->get();

        // $transactions = $transactions->sortBy(function ($transaction) {
        //     return match ($transaction->payment->status_payment ?? '') {
        //         'settlement' => 0,
        //         'pending' => 1,
        //             'expired' => 1,
        //       -  default => 2,
        //     };
        // });
        $umkm = User::role('UMKM')
        ->with('user_transaction.payment')
        ->get()
        ->map(function ($user) {
            $transactions = $user->user_transaction;
    
            $settledPayments = $transactions->pluck('payment')
                ->filter(function ($payment) {
                    return $payment && $payment->status_payment === 'settlement';
                });
    
            $totalRevenue = $settledPayments->sum('total_price');
            $commission = ($totalRevenue * 30) / 100;
            $netRevenue = $totalRevenue - $commission;

            return [
                'name' => $user->name,
                'product_count' => $user->user_product()->count(),
                'transaction_count' => $user->user_transaction()->count(),
                'revenue' => $totalRevenue,
                'net_revenue' => $netRevenue,
            ];
        });
        return view('admin.reports.index', compact('umkm'));
    }
    public function searchreport(Request $request){
        $date = $request->get('date');
        $umkm = User::role('UMKM')
        ->with('user_transaction.payment')
        ->get()
        ->map(function ($user) use ($date) {
            $transactions = $user->user_transaction;
    
            $settledPayments = $transactions->pluck('payment')
                ->filter(function ($payment) use ($date) {
                    return $payment
                        && $payment->status_payment === 'settlement'
                        && Str::contains($payment->created_at->toDateString(), $date);
                });
    
            $totalRevenue = $settledPayments->sum('total_price');
            $commission = ($totalRevenue * 30) / 100; // Example: 10% commission
            $netRevenue = $totalRevenue - $commission; // Example: 10% commission

            return [
                'name' => $user->name,
                'product_count' => $user->user_product()->count(),
                'transaction_count' => $user->user_transaction()->count(),
                'revenue' => $totalRevenue,
                'net_revenue' => $netRevenue,
            ];
        });
        return view('admin.reports.index', compact('umkm'));
    }
}
