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

        $pharmacyid = auth()->user()->pharmacy_id;

        $users = User::query()
            ->where('users.pharmacy_id', $pharmacyid)
            ->select([
                'users.id',
                'users.name',
                'users.username',

                // Today sales
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                        AND DATE(updated_at) = CURDATE()
                    ), 0) as today_sales
                "),

                // Yesterday sales
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                        AND DATE(updated_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                    ), 0) as yesterday_sales
                "),

                // Month sales
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                        AND updated_at BETWEEN '{$startMonth}' AND '{$endMonth}'
                    ), 0) as month_sales
                "),

                // Last month sales
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                        AND updated_at BETWEEN '{$lastMonthStart}' AND '{$lastMonthEnd}'
                    ), 0) as last_month_sales
                "),

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

                // All time sales
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                    ), 0) as all_time_sales
                "),

                // Today transactions
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                        AND DATE(updated_at) = CURDATE()
                    ) as today_transactions
                "),

                // Total transactions
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND medicine_transactions.pharmacy_id = {$pharmacyid}
                    ) as total_transactions
                "),

                // Shift completed
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM shift_logs
                        WHERE shift_logs.user_id = users.id
                        AND status = 'finished'
                        AND MONTH(clock_out) = MONTH(CURRENT_DATE())
                        AND YEAR(clock_out) = YEAR(CURRENT_DATE())
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
            ->addColumn('today_change', function ($row) {
                if ($row->yesterday_sales <= 0) return 0;
                return round((($row->today_sales - $row->yesterday_sales) / $row->yesterday_sales) * 100);
            })
            ->addColumn('month_change', function ($row) {
                if ($row->last_month_sales <= 0) return 0;
                return round((($row->month_sales - $row->last_month_sales) / $row->last_month_sales) * 100);
            })
            ->toJson();
    }
}
