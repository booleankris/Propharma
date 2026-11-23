<?php

namespace App\Http\Controllers;

use App\Models\Pharmacies;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class StaffStatsController extends Controller
{
    private const RETURN_TYPES = ['RETUR JUAL', 'RETUR KREDIT'];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !hasFeatureAccess('reports.staff_stats', ['General Manager', 'administrator'], $user)) {
                abort(403, 'Akses ditolak: Anda tidak memiliki akses ke Statistik Kasir.');
            }
            return $next($request);
        });
    }

    /**
     * Display the Staff Performance page with initial server-rendered data.
     */
    public function index(Request $request)
    {
        $pharmacies = Pharmacies::orderBy('name')->get(['id', 'name']);
        $initialSummary = $this->calculateSummary($request);

        return view('staff-stats', compact('pharmacies', 'initialSummary'));
    }

    /**
     * AJAX endpoint returning KPI totals, period comparison, and full ranked staff list.
     */
    public function summary(Request $request)
    {
        return response()->json($this->calculateSummary($request));
    }

    /**
     * Backward-compatible DataTables JSON endpoint using optimized parameterized subqueries.
     */
    public function data(Request $request)
    {
        [$startCarbon, $endCarbon] = $this->resolveDateRange($request);
        $pharmacyId = $this->resolvePharmacyId($request);

        $query = $this->buildStaffMetricsQuery($startCarbon, $endCarbon, $pharmacyId);

        $totalSalesPeriod = (float) DB::table('medicine_transactions')
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('transaction_type')
                    ->orWhereNotIn('transaction_type', self::RETURN_TYPES);
            })
            ->whereRaw('COALESCE(created_at, updated_at) BETWEEN ? AND ?', [
                $startCarbon->toDateTimeString(),
                $endCarbon->toDateTimeString(),
            ])
            ->when($pharmacyId !== null, fn ($q) => $q->where('pharmacy_id', $pharmacyId))
            ->sum(DB::raw('GREATEST(0, COALESCE(subtotal, 0) - COALESCE(discount, 0))'));

        return DataTables::of($query)
            ->filter(function ($query) use ($request) {
                $searchValue = trim((string) $request->input('search.value', ''));
                if ($searchValue !== '') {
                    $query->where(function ($q) use ($searchValue) {
                        $q->where('users.name', 'like', "%{$searchValue}%")
                            ->orWhere('users.username', 'like', "%{$searchValue}%")
                            ->orWhere('pharmacies.name', 'like', "%{$searchValue}%");
                    });
                }
            }, true)
            ->addIndexColumn()
            ->editColumn('initials', fn ($row) => $this->makeInitials((string) $row->name))
            ->addColumn('aov', function ($row) {
                $txns = (int) $row->filtered_transactions;
                return $txns > 0 ? round(((float) $row->filtered_sales) / $txns) : 0;
            })
            ->addColumn('aov_rp', function ($row) {
                $txns = (int) $row->filtered_transactions;
                $aov = $txns > 0 ? (((float) $row->filtered_sales) / $txns) : 0;
                return 'Rp ' . number_format($aov, 0, ',', '.');
            })
            ->addColumn('sales_share', function ($row) use ($totalSalesPeriod) {
                if ($totalSalesPeriod <= 0) {
                    return 0;
                }
                return round((((float) $row->filtered_sales) / $totalSalesPeriod) * 100, 1);
            })
            ->toJson();
    }

    /**
     * Build single-pass aggregated query joining sales, returns, shifts, and roles per user.
     */
    private function buildStaffMetricsQuery(Carbon $start, Carbon $end, ?int $pharmacyId): Builder
    {
        $startStr = $start->toDateTimeString();
        $endStr = $end->toDateTimeString();

        $salesAgg = DB::table('medicine_transactions')
            ->select([
                'user_id',
                DB::raw("COALESCE(SUM(CASE WHEN status = 1 AND (transaction_type IS NULL OR transaction_type NOT IN ('RETUR JUAL', 'RETUR KREDIT')) THEN GREATEST(0, COALESCE(subtotal, 0) - COALESCE(discount, 0)) ELSE 0 END), 0) AS filtered_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN status = 1 AND (transaction_type IS NULL OR transaction_type NOT IN ('RETUR JUAL', 'RETUR KREDIT')) THEN 1 ELSE 0 END), 0) AS filtered_transactions"),
                DB::raw("COALESCE(SUM(CASE WHEN transaction_type IN ('RETUR JUAL', 'RETUR KREDIT') AND (status = 1 OR transaction_code = 'RT') THEN 1 ELSE 0 END), 0) AS retur_count"),
                DB::raw("COALESCE(SUM(CASE WHEN transaction_type IN ('RETUR JUAL', 'RETUR KREDIT') AND (status = 1 OR transaction_code = 'RT') THEN ABS(COALESCE(subtotal, 0)) ELSE 0 END), 0) AS retur_total"),
            ])
            ->whereNotNull('user_id')
            ->whereRaw('COALESCE(created_at, updated_at) BETWEEN ? AND ?', [$startStr, $endStr])
            ->when($pharmacyId !== null, fn ($q) => $q->where('pharmacy_id', $pharmacyId))
            ->groupBy('user_id');

        $shiftAgg = DB::table('shift_logs')
            ->select([
                'user_id',
                DB::raw('COUNT(*) AS shifts_completed'),
            ])
            ->where('status', 'finished')
            ->whereRaw('COALESCE(clock_out, created_at) BETWEEN ? AND ?', [$startStr, $endStr])
            ->groupBy('user_id');

        $rolesAgg = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->select([
                'model_has_roles.model_id AS user_id',
                DB::raw("GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ', ') AS role_name"),
            ])
            ->groupBy('model_has_roles.model_id');

        $query = User::query()
            ->leftJoin('pharmacies', 'users.pharmacy_id', '=', 'pharmacies.id')
            ->leftJoinSub($salesAgg, 'tx_agg', 'tx_agg.user_id', '=', 'users.id')
            ->leftJoinSub($shiftAgg, 'shift_agg', 'shift_agg.user_id', '=', 'users.id')
            ->leftJoinSub($rolesAgg, 'role_agg', 'role_agg.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                'users.username',
                'users.pharmacy_id',
                'pharmacies.name as pharmacy_name',
                DB::raw('COALESCE(role_agg.role_name, "") as role_name'),
                DB::raw('COALESCE(tx_agg.filtered_sales, 0) as filtered_sales'),
                DB::raw('COALESCE(tx_agg.filtered_transactions, 0) as filtered_transactions'),
                DB::raw('COALESCE(tx_agg.retur_count, 0) as retur_count'),
                DB::raw('COALESCE(tx_agg.retur_total, 0) as retur_total'),
                DB::raw('COALESCE(shift_agg.shifts_completed, 0) as shifts_completed'),
            ]);

        if ($pharmacyId !== null) {
            $query->where(function ($q) use ($pharmacyId) {
                $q->where('users.pharmacy_id', $pharmacyId)
                    ->orWhere('tx_agg.filtered_transactions', '>', 0)
                    ->orWhere('tx_agg.retur_count', '>', 0);
            });
        }

        return $query;
    }

    /**
     * Compute complete dashboard summary, comparison deltas, and ranked staff list in 2 queries.
     */
    private function calculateSummary(Request $request): array
    {
        $period = $this->resolvePeriodKey($request);
        [$startCarbon, $endCarbon] = $this->resolveDateRange($request);
        $pharmacyId = $this->resolvePharmacyId($request);

        $rows = $this->buildStaffMetricsQuery($startCarbon, $endCarbon, $pharmacyId)
            ->orderByDesc('filtered_sales')
            ->orderByDesc('filtered_transactions')
            ->orderBy('users.name')
            ->get();

        $totalSales = (float) $rows->sum('filtered_sales');
        $totalTxns = (int) $rows->sum('filtered_transactions');
        $totalShifts = (int) $rows->sum('shifts_completed');
        $totalReturCount = (int) $rows->sum('retur_count');
        $totalReturAmount = (float) $rows->sum('retur_total');
        $overallAov = $totalTxns > 0 ? round($totalSales / $totalTxns) : 0;
        $overallSalesPerShift = $totalShifts > 0 ? round($totalSales / $totalShifts) : 0;
        $activeStaffCount = $rows->filter(fn ($u) => ((float) $u->filtered_sales) > 0 || ((int) $u->filtered_transactions) > 0)->count();
        $totalStaffCount = $rows->count();

        $staff = [];
        foreach ($rows->values() as $index => $u) {
            $sales = (float) $u->filtered_sales;
            $txns = (int) $u->filtered_transactions;
            $shifts = (int) $u->shifts_completed;
            $returCount = (int) $u->retur_count;
            $returTotal = (float) $u->retur_total;
            $aov = $txns > 0 ? round($sales / $txns) : 0;
            $salesPerShift = $shifts > 0 ? round($sales / $shifts) : 0;
            $share = $totalSales > 0 ? round(($sales / $totalSales) * 100, 1) : 0.0;

            $staff[] = [
                'rank' => $index + 1,
                'id' => (int) $u->id,
                'name' => (string) $u->name,
                'initials' => $this->makeInitials((string) $u->name),
                'username' => (string) ($u->username ?? ''),
                'pharmacy_id' => $u->pharmacy_id ? (int) $u->pharmacy_id : null,
                'pharmacy_name' => $u->pharmacy_name ?: 'Pusat / HO',
                'role_name' => (string) ($u->role_name ?? ''),
                'sales' => $sales,
                'txns' => $txns,
                'aov' => $aov,
                'shifts' => $shifts,
                'sales_per_shift' => $salesPerShift,
                'sales_share' => $share,
                'retur_count' => $returCount,
                'retur_total' => $returTotal,
            ];
        }

        // Group branch summary directly from the already-fetched rows (zero extra queries)
        $branchMap = [];
        foreach ($staff as $item) {
            $bKey = (string) ($item['pharmacy_id'] ?? 'ho');
            if (!isset($branchMap[$bKey])) {
                $branchMap[$bKey] = [
                    'pharmacy_id' => $item['pharmacy_id'],
                    'name' => $item['pharmacy_name'],
                    'sales' => 0.0,
                    'txns' => 0,
                    'shifts' => 0,
                    'active_staff' => 0,
                    'total_staff' => 0,
                ];
            }
            $branchMap[$bKey]['sales'] += $item['sales'];
            $branchMap[$bKey]['txns'] += $item['txns'];
            $branchMap[$bKey]['shifts'] += $item['shifts'];
            $branchMap[$bKey]['total_staff'] += 1;
            if ($item['sales'] > 0 || $item['txns'] > 0) {
                $branchMap[$bKey]['active_staff'] += 1;
            }
        }

        $branches = array_values($branchMap);
        usort($branches, fn ($a, $b) => $b['sales'] <=> $a['sales'] ?: $b['txns'] <=> $a['txns'] ?: strcmp($a['name'], $b['name']));
        foreach ($branches as &$branch) {
            $branch['share'] = $totalSales > 0 ? round(($branch['sales'] / $totalSales) * 100, 1) : 0.0;
        }
        unset($branch);

        $topPerformer = (!empty($staff) && $staff[0]['sales'] > 0) ? $staff[0] : null;
        $comparison = $this->calculatePreviousPeriodComparison($period, $startCarbon, $endCarbon, $pharmacyId, $totalSales, $totalTxns);

        $pharmacyLabel = 'Semua Cabang';
        if ($pharmacyId !== null) {
            $pharmacyLabel = (string) (Pharmacies::where('id', $pharmacyId)->value('name') ?? 'Cabang #' . $pharmacyId);
        }

        return [
            'period' => $period,
            'period_label' => $this->getPeriodLabel($period, $startCarbon, $endCarbon),
            'start_date' => $startCarbon->toDateString(),
            'end_date' => $endCarbon->toDateString(),
            'pharmacy_id' => $pharmacyId ? (string) $pharmacyId : 'all',
            'pharmacy_label' => $pharmacyLabel,
            'totals' => [
                'sales' => $totalSales,
                'transactions' => $totalTxns,
                'shifts' => $totalShifts,
                'aov' => $overallAov,
                'sales_per_shift' => $overallSalesPerShift,
                'active_staff' => $activeStaffCount,
                'total_staff' => $totalStaffCount,
                'retur_count' => $totalReturCount,
                'retur_total' => $totalReturAmount,
            ],
            'comparison' => $comparison,
            'top_performer' => $topPerformer,
            'branches' => $branches,
            'staff' => $staff,

            // Backward-compatible top-level keys
            'total_sales' => $totalSales,
            'total_sales_rp' => 'Rp ' . number_format($totalSales, 0, ',', '.'),
            'total_transactions' => $totalTxns,
            'total_shifts' => $totalShifts,
            'average_order_value' => 'Rp ' . number_format($overallAov, 0, ',', '.'),
            'active_staff_count' => $activeStaffCount,
            'total_staff_count' => $totalStaffCount,
        ];
    }

    /**
     * Compare total net sales and transaction count with the immediately preceding period.
     */
    private function calculatePreviousPeriodComparison(
        string $period,
        Carbon $start,
        Carbon $end,
        ?int $pharmacyId,
        float $currentSales,
        int $currentTxns
    ): ?array {
        if ($period === 'all') {
            return null;
        }

        switch ($period) {
            case 'today':
                $prevStart = $start->copy()->subDay()->startOfDay();
                $prevEnd = $end->copy()->subDay()->endOfDay();
                $label = 'vs kemarin';
                break;

            case 'yesterday':
                $prevStart = $start->copy()->subDay()->startOfDay();
                $prevEnd = $end->copy()->subDay()->endOfDay();
                $label = 'vs 2 hari lalu';
                break;

            case 'this_week':
                $prevStart = $start->copy()->subWeek()->startOfDay();
                $prevEnd = $end->copy()->subWeek()->endOfDay();
                $label = 'vs minggu lalu';
                break;

            case 'this_month':
                $prevStart = $start->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
                $prevEnd = $prevStart->copy()->endOfMonth()->endOfDay();
                $label = 'vs bulan lalu';
                break;

            case 'this_year':
                $prevStart = $start->copy()->subYear()->startOfYear()->startOfDay();
                $prevEnd = $prevStart->copy()->endOfYear()->endOfDay();
                $label = 'vs tahun lalu';
                break;

            case 'custom':
            default:
                $days = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
                $prevEnd = $start->copy()->subDay()->endOfDay();
                $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();
                $label = "vs {$days} hari sebelumnya";
                break;
        }

        $prev = DB::table('medicine_transactions')
            ->select([
                DB::raw('COALESCE(SUM(GREATEST(0, COALESCE(subtotal, 0) - COALESCE(discount, 0))), 0) AS prev_sales'),
                DB::raw('COUNT(*) AS prev_txns'),
            ])
            ->whereNotNull('user_id')
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('transaction_type')
                    ->orWhereNotIn('transaction_type', self::RETURN_TYPES);
            })
            ->whereRaw('COALESCE(created_at, updated_at) BETWEEN ? AND ?', [
                $prevStart->toDateTimeString(),
                $prevEnd->toDateTimeString(),
            ])
            ->when($pharmacyId !== null, fn ($q) => $q->where('pharmacy_id', $pharmacyId))
            ->first();

        $prevSales = (float) ($prev->prev_sales ?? 0);
        $prevTxns = (int) ($prev->prev_txns ?? 0);

        return [
            'label' => $label,
            'prev_sales' => $prevSales,
            'sales_delta_pct' => $prevSales > 0 ? round((($currentSales - $prevSales) / $prevSales) * 100, 1) : null,
            'prev_txns' => $prevTxns,
            'txns_delta_pct' => $prevTxns > 0 ? round((($currentTxns - $prevTxns) / $prevTxns) * 100, 1) : null,
        ];
    }

    private function resolvePeriodKey(Request $request): string
    {
        $period = (string) $request->get('period', 'today');

        return match ($period) {
            'yesterday' => 'yesterday',
            'this_week', 'week' => 'this_week',
            'this_month', 'month' => 'this_month',
            'this_year', 'year' => 'this_year',
            'custom' => 'custom',
            'all' => 'all',
            default => 'today',
        };
    }

    private function resolvePharmacyId(Request $request): ?int
    {
        $raw = $request->get('pharmacy_id', 'all');
        if ($raw === null || $raw === '' || $raw === 'all') {
            return null;
        }

        $id = filter_var($raw, FILTER_VALIDATE_INT);
        return ($id !== false && $id > 0) ? (int) $id : null;
    }

    /**
     * Resolve start and end Carbon boundaries from request.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(Request $request): array
    {
        $period = $this->resolvePeriodKey($request);

        if ($period === 'custom' && $request->filled('start_date') && $request->filled('end_date')) {
            try {
                $start = Carbon::parse($request->get('start_date'))->startOfDay();
                $end = Carbon::parse($request->get('end_date'))->endOfDay();

                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }

                return [$start, $end];
            } catch (\Throwable $e) {
                // Fall back to today if invalid date string is provided
            }
        }

        return match ($period) {
            'yesterday' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'this_week' => [Carbon::now()->startOfWeek()->startOfDay(), Carbon::now()->endOfWeek()->endOfDay()],
            'this_month' => [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfMonth()->endOfDay()],
            'this_year' => [Carbon::now()->startOfYear()->startOfDay(), Carbon::now()->endOfYear()->endOfDay()],
            'all' => [Carbon::create(2020, 1, 1)->startOfDay(), Carbon::now()->endOfDay()],
            default => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
        };
    }

    private function getPeriodLabel(string $period, Carbon $start, Carbon $end): string
    {
        return match ($period) {
            'today' => 'Hari Ini (' . $start->translatedFormat('d M Y') . ')',
            'yesterday' => 'Kemarin (' . $start->translatedFormat('d M Y') . ')',
            'this_week' => 'Minggu Ini (' . $start->translatedFormat('d M') . ' - ' . $end->translatedFormat('d M Y') . ')',
            'this_month' => 'Bulan Ini (' . $start->translatedFormat('F Y') . ')',
            'this_year' => 'Tahun Ini (' . $start->format('Y') . ')',
            'custom' => $start->translatedFormat('d M Y') . ' - ' . $end->translatedFormat('d M Y'),
            'all' => 'Semua Periode',
            default => 'Hari Ini (' . $start->translatedFormat('d M Y') . ')',
        };
    }

    private function makeInitials(string $name): string
    {
        return collect(explode(' ', trim($name)))
            ->filter()
            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
