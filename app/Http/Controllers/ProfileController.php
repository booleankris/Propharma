<?php

namespace App\Http\Controllers;

use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\ShiftLogs;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index()
    {
        $today      = Carbon::today();
        $startMonth = Carbon::now()->startOfMonth();
        $lastMonth  = Carbon::now()->subMonth();
        $userid = auth()->user()->id;
        $pharmacyid = getActivePharmacyId();
        $pharmacy = Pharmacies::findOrFail($pharmacyid);

        $todaySales = MedicineTransactions::where('pharmacy_id', $pharmacyid)
            ->whereDate('updated_at', $today)->sum('subtotal');
        $yesterdaySales = MedicineTransactions::where('pharmacy_id', $pharmacyid)->whereDate('updated_at', $today->copy()->subDay())->sum('subtotal');
        $thisMonth      = MedicineTransactions::where('pharmacy_id', $pharmacyid)->whereBetween('updated_at', [$startMonth, now()])->sum('subtotal');
        $lastMonthSales = MedicineTransactions::where('pharmacy_id', $pharmacyid)->whereMonth('updated_at', $lastMonth->month)->sum('subtotal');
        $allTime        = MedicineTransactions::where('pharmacy_id', $pharmacyid)->sum('subtotal');

        $shiftsCompleted = ShiftLogs::with('shift')->where('user_id', $userid)
            ->whereMonth('clock_out', now()->month)
            ->where('status', 'finished')
            ->count();

        $totalTx = MedicineTransactions::where('pharmacy_id', $pharmacyid)
            ->whereMonth('updated_at', now()->month)
            ->count();

        $salesData = (object) [
            'today'                    => $todaySales,
            'today_change'             => $yesterdaySales > 0 ? round((($todaySales - $yesterdaySales) / $yesterdaySales) * 100) : 0,
            'this_month'               => $thisMonth,
            'month_change'             => $lastMonthSales > 0 ? round((($thisMonth - $lastMonthSales) / $lastMonthSales) * 100) : 0,
            'all_time'                 => $allTime,
            'shifts_completed'         => $shiftsCompleted,
            'shift_target'             => 25,
            'total_transactions'       => $totalTx,
            'avg_transactions_per_day' => $totalTx > 0 ? round($totalTx / now()->day, 1) : 0,
            'avg_per_transaction'      => $totalTx > 0 ? round($thisMonth / $totalTx) : 0,
            'avg_change'               => 0,
            'pharmacy'                 => $pharmacy,
        ];

        return view('profile', compact('salesData'));

    }
}
