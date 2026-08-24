<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\ShiftLogs;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class StaffStatsController extends Controller
{
    public function index()
    {
        return view('staff-stats');
    }

    public function data(Request $request)
    {
        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)->startOfDay()
            : Carbon::today()->startOfDay();

        $endDate = $request->end_date
            ? Carbon::parse($request->end_date)->endOfDay()
            : Carbon::today()->endOfDay();

        $startMonth     = Carbon::now()->startOfMonth();
        $endMonth       = Carbon::now()->endOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd   = Carbon::now()->subMonth()->endOfMonth();

        $pharmacyid = getActivePharmacyId();

        $users = User::query()
            ->where('users.pharmacy_id', $pharmacyid)
            ->select([
                'users.id',
                'users.name',
                'users.username',

                // Filtered sales
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                        AND updated_at BETWEEN '{$startDate}' AND '{$endDate}'
                    ), 0) as filtered_sales
                "),

                // Filtered transactions
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                        AND updated_at BETWEEN '{$startDate}' AND '{$endDate}'
                    ) as filtered_transactions
                "),

                // Shift completed in period
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM shift_logs
                        WHERE shift_logs.user_id = users.id
                        AND status = 'finished'
                        AND clock_out BETWEEN '{$startDate}' AND '{$endDate}'
                    ) as shifts_completed
                "),
            ]);

        return DataTables::of($users)
            ->addIndexColumn()
            ->editColumn('initials', function ($row) {
                return collect(explode(' ', $row->name))
                    ->map(fn($x) => strtoupper(substr($x, 0, 1)))
                    ->take(2)
                    ->implode('');
            })
            ->toJson();
    }
}
