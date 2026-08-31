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
    /**
     * Display the main Staff Analytics Dashboard
     */
    public function index(Request $request)
    {
        $pharmacies = Pharmacies::orderBy('name')->get();
        $initialSummary = $this->calculateSummary($request);

        return view('staff-stats', compact('pharmacies', 'initialSummary'));
    }

    /**
     * AJAX endpoint for KPI summary, podium, and chart data
     */
    public function summary(Request $request)
    {
        $summary = $this->calculateSummary($request);
        return response()->json($summary);
    }

    /**
     * AJAX endpoint for DataTables server-side feed
     */
    public function data(Request $request)
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $pharmacyId = $request->get('pharmacy_id', 'all');

        $users = User::query()
            ->leftJoin('pharmacies', 'users.pharmacy_id', '=', 'pharmacies.id')
            ->select([
                'users.id',
                'users.name',
                'users.username',
                'users.pharmacy_id',
                'pharmacies.name as pharmacy_name',

                // Filtered sales
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND status = 1
                        AND transaction_type != 'RETUR JUAL'
                        AND updated_at BETWEEN '{$startDate}' AND '{$endDate}'
                    ), 0) as filtered_sales
                "),

                // Filtered transactions
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND status = 1
                        AND transaction_type != 'RETUR JUAL'
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

        if (!empty($pharmacyId) && $pharmacyId !== 'all') {
            $users->where('users.pharmacy_id', $pharmacyId);
        }

        // Calculate total sales for share percentage
        $totalSalesPeriod = (float) DB::table('medicine_transactions')
            ->where('status', 1)
            ->where('transaction_type', '!=', 'RETUR JUAL')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->when(!empty($pharmacyId) && $pharmacyId !== 'all', function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId);
            })
            ->sum('subtotal');

        return DataTables::of($users)
            ->filter(function ($query) use ($request) {
                if ($request->has('search') && !empty($request->input('search.value'))) {
                    $searchValue = trim($request->input('search.value'));
                    $query->where(function ($q) use ($searchValue) {
                        $q->where('users.name', 'like', "%{$searchValue}%")
                          ->orWhere('users.username', 'like', "%{$searchValue}%")
                          ->orWhere('pharmacies.name', 'like', "%{$searchValue}%");
                    });
                }
            }, true)
            ->addIndexColumn()
            ->editColumn('initials', function ($row) {
                return collect(explode(' ', trim($row->name)))
                    ->filter()
                    ->map(fn($x) => strtoupper(substr($x, 0, 1)))
                    ->take(2)
                    ->implode('');
            })
            ->addColumn('aov', function ($row) {
                $txns = (int) $row->filtered_transactions;
                $sales = (float) $row->filtered_sales;
                return $txns > 0 ? ($sales / $txns) : 0;
            })
            ->addColumn('aov_rp', function ($row) {
                $txns = (int) $row->filtered_transactions;
                $sales = (float) $row->filtered_sales;
                $aov = $txns > 0 ? ($sales / $txns) : 0;
                return 'Rp ' . number_format($aov, 0, ',', '.');
            })
            ->addColumn('sales_share', function ($row) use ($totalSalesPeriod) {
                if ($totalSalesPeriod <= 0) return 0;
                return round(($row->filtered_sales / $totalSalesPeriod) * 100, 1);
            })
            ->toJson();
    }

    /**
     * Private helper to compute dashboard summary, podium, and chart
     */
    private function calculateSummary(Request $request): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($request);
        $pharmacyId = $request->get('pharmacy_id', 'all');

        $users = User::query()
            ->leftJoin('pharmacies', 'users.pharmacy_id', '=', 'pharmacies.id')
            ->select([
                'users.id',
                'users.name',
                'users.username',
                'users.pharmacy_id',
                'pharmacies.name as pharmacy_name',
                DB::raw("
                    COALESCE((
                        SELECT SUM(subtotal)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND status = 1
                        AND transaction_type != 'RETUR JUAL'
                        AND updated_at BETWEEN '{$startDate}' AND '{$endDate}'
                    ), 0) as sales
                "),
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM medicine_transactions
                        WHERE medicine_transactions.user_id = users.id
                        AND status = 1
                        AND transaction_type != 'RETUR JUAL'
                        AND updated_at BETWEEN '{$startDate}' AND '{$endDate}'
                    ) as txns
                "),
                DB::raw("
                    (
                        SELECT COUNT(*)
                        FROM shift_logs
                        WHERE shift_logs.user_id = users.id
                        AND status = 'finished'
                        AND clock_out BETWEEN '{$startDate}' AND '{$endDate}'
                    ) as shifts
                ")
            ])
            ->when(!empty($pharmacyId) && $pharmacyId !== 'all', function ($q) use ($pharmacyId) {
                $q->where('users.pharmacy_id', $pharmacyId);
            })
            ->orderByDesc('sales')
            ->get();

        $totalSales = (float) $users->sum('sales');
        $totalTxns = (int) $users->sum('txns');
        $totalShifts = (int) $users->sum('shifts');
        $aov = $totalTxns > 0 ? ($totalSales / $totalTxns) : 0;
        $activeStaffCount = $users->where('sales', '>', 0)->count();
        $totalStaffCount = $users->count();

        // Top 3 Podium
        $podium = [];
        $rankedUsers = $users->values();

        for ($i = 0; $i < 3; $i++) {
            if (isset($rankedUsers[$i]) && $rankedUsers[$i]->sales > 0) {
                $u = $rankedUsers[$i];
                $uSales = (float) $u->sales;
                $uTxns = (int) $u->txns;
                $uAov = $uTxns > 0 ? ($uSales / $uTxns) : 0;
                $share = $totalSales > 0 ? round(($uSales / $totalSales) * 100, 1) : 0;

                $initials = collect(explode(' ', trim($u->name)))
                    ->filter()
                    ->map(fn($x) => strtoupper(substr($x, 0, 1)))
                    ->take(2)
                    ->implode('');

                $podium[] = [
                    'rank'          => $i + 1,
                    'id'            => $u->id,
                    'name'          => $u->name,
                    'username'      => $u->username,
                    'pharmacy_name' => $u->pharmacy_name ?? 'Pusat / HO',
                    'initials'      => $initials,
                    'sales'         => $uSales,
                    'sales_rp'      => 'Rp ' . number_format($uSales, 0, ',', '.'),
                    'txns'          => $uTxns,
                    'shifts'        => (int) $u->shifts,
                    'aov_rp'        => 'Rp ' . number_format($uAov, 0, ',', '.'),
                    'share'         => $share,
                ];
            } else {
                $podium[] = null;
            }
        }

        // Chart Data: Top 8 Cashiers
        $chartCategories = [];
        $chartSeries = [];
        $chartBranches = [];

        foreach ($rankedUsers->take(8) as $u) {
            if ($u->sales > 0) {
                $chartCategories[] = $u->name;
                $chartSeries[] = (float) $u->sales;
                $chartBranches[] = $u->pharmacy_name ?? 'Pusat';
            }
        }

        // Top Performer
        $topPerformer = $podium[0] ?? null;

        return [
            'period_label'          => $this->getPeriodLabel($request),
            'total_sales'           => $totalSales,
            'total_sales_rp'        => 'Rp ' . number_format($totalSales, 0, ',', '.'),
            'total_transactions'    => $totalTxns,
            'total_shifts'          => $totalShifts,
            'average_order_value'   => 'Rp ' . number_format($aov, 0, ',', '.'),
            'active_staff_count'    => $activeStaffCount,
            'total_staff_count'     => $totalStaffCount,
            'top_performer'         => $topPerformer,
            'podium'                => $podium,
            'chart_data'            => [
                'categories' => $chartCategories,
                'series'     => $chartSeries,
                'branches'   => $chartBranches,
            ]
        ];
    }

    /**
     * Resolve start and end dates from request
     */
    private function resolveDateRange(Request $request): array
    {
        $period = $request->get('period', 'today');

        if ($period === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            return [
                Carbon::parse($request->start_date)->startOfDay()->toDateTimeString(),
                Carbon::parse($request->end_date)->endOfDay()->toDateTimeString(),
            ];
        }

        switch ($period) {
            case 'yesterday':
                return [
                    Carbon::yesterday()->startOfDay()->toDateTimeString(),
                    Carbon::yesterday()->endOfDay()->toDateTimeString(),
                ];

            case 'this_week':
            case 'week':
                return [
                    Carbon::now()->startOfWeek()->startOfDay()->toDateTimeString(),
                    Carbon::now()->endOfWeek()->endOfDay()->toDateTimeString(),
                ];

            case 'this_month':
            case 'month':
                return [
                    Carbon::now()->startOfMonth()->startOfDay()->toDateTimeString(),
                    Carbon::now()->endOfMonth()->endOfDay()->toDateTimeString(),
                ];

            case 'this_year':
            case 'year':
                return [
                    Carbon::now()->startOfYear()->startOfDay()->toDateTimeString(),
                    Carbon::now()->endOfYear()->endOfDay()->toDateTimeString(),
                ];

            case 'all':
                return [
                    Carbon::create(2020, 1, 1)->startOfDay()->toDateTimeString(),
                    Carbon::now()->endOfDay()->toDateTimeString(),
                ];

            case 'today':
            default:
                return [
                    Carbon::today()->startOfDay()->toDateTimeString(),
                    Carbon::today()->endOfDay()->toDateTimeString(),
                ];
        }
    }

    /**
     * Human readable period label
     */
    private function getPeriodLabel(Request $request): string
    {
        $period = $request->get('period', 'today');

        switch ($period) {
            case 'today':
                return 'Hari Ini (' . Carbon::today()->translatedFormat('d M Y') . ')';
            case 'yesterday':
                return 'Kemarin (' . Carbon::yesterday()->translatedFormat('d M Y') . ')';
            case 'this_week':
            case 'week':
                return 'Minggu Ini (' . Carbon::now()->startOfWeek()->translatedFormat('d M') . ' - ' . Carbon::now()->endOfWeek()->translatedFormat('d M Y') . ')';
            case 'this_month':
            case 'month':
                return 'Bulan Ini (' . Carbon::now()->translatedFormat('F Y') . ')';
            case 'this_year':
            case 'year':
                return 'Tahun Ini (' . Carbon::now()->format('Y') . ')';
            case 'custom':
                $start = $request->filled('start_date') ? Carbon::parse($request->start_date)->translatedFormat('d M Y') : '-';
                $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->translatedFormat('d M Y') : '-';
                return "Kustom ({$start} - {$end})";
            case 'all':
                return 'Semua Periode';
            default:
                return 'Hari Ini';
        }
    }
}
