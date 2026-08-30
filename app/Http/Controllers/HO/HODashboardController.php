<?php

namespace App\Http\Controllers\HO;

use App\Http\Controllers\Controller;
use App\Models\ItemsLog;
use App\Models\MedicineTransactions;
use App\Models\OrderItems;
use App\Models\Pharmacies;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HODashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:HO|administrator']);
    }

    public function index(Request $request)
    {
        $pharmacies = Pharmacies::orderBy('name', 'asc')->get();
        $analytics = $this->calculateAnalytics($request);

        return view('ho.analytics', compact('pharmacies', 'analytics'));
    }

    public function getData(Request $request)
    {
        $analytics = $this->calculateAnalytics($request);
        return response()->json($analytics);
    }

    private function calculateAnalytics(Request $request): array
    {
        $period = $request->get('period', 'this_month'); // today, this_month, this_year, custom
        $pharmacyId = $request->get('pharmacy_id', 'all'); // 'all' or specific pharmacy_id
        $startDateParam = $request->get('start_date');
        $endDateParam = $request->get('end_date');

        // Resolve Date Range
        $now = Carbon::now();
        switch ($period) {
            case 'today':
                $startDate = $now->copy()->startOfDay();
                $endDate   = $now->copy()->endOfDay();
                $periodLabel = 'Hari Ini (' . $now->translatedFormat('d F Y') . ')';
                break;
            case 'this_year':
                $startDate = $now->copy()->startOfYear();
                $endDate   = $now->copy()->endOfYear();
                $periodLabel = 'Tahun Ini (' . $now->format('Y') . ')';
                break;
            case 'custom':
                $startDate = $startDateParam ? Carbon::parse($startDateParam)->startOfDay() : $now->copy()->startOfMonth();
                $endDate   = $endDateParam ? Carbon::parse($endDateParam)->endOfDay() : $now->copy()->endOfDay();
                $periodLabel = $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y');
                break;
            case 'this_month':
            default:
                $period = 'this_month';
                $startDate = $now->copy()->startOfMonth();
                $endDate   = $now->copy()->endOfMonth();
                $periodLabel = 'Bulan Ini (' . $now->translatedFormat('F Y') . ')';
                break;
        }

        // Base Query Condition Helper
        $applyPharmacy = function ($query, $tablePrefix = '') use ($pharmacyId) {
            if ($pharmacyId !== 'all' && !empty($pharmacyId)) {
                $col = $tablePrefix ? "{$tablePrefix}.pharmacy_id" : 'pharmacy_id';
                $query->where($col, $pharmacyId);
            }
            return $query;
        };

        // 1. METRICS: PENJUALAN (SALES)
        $salesQuery = MedicineTransactions::query()
            ->where('status', 1)
            ->where('transaction_type', '!=', 'RETUR JUAL')
            ->whereBetween('updated_at', [$startDate, $endDate]);
        $salesQuery = $applyPharmacy($salesQuery);

        $totalSalesAmount = (float) $salesQuery->sum('subtotal');
        $totalSalesCount  = (int) $salesQuery->count('id');
        $totalSalesDiscount = (float) $salesQuery->sum('discount');
        $averageOrderValue = $totalSalesCount > 0 ? ($totalSalesAmount / $totalSalesCount) : 0;

        // 2. METRICS: PEMBELIAN (PURCHASES)
        $purchasesQuery = OrderItems::query()
            ->whereHas('orders', function ($q) use ($startDate, $endDate, $pharmacyId, $applyPharmacy) {
                $q->whereIn('status', [1, 2, 3])
                  ->whereBetween('created_at', [$startDate, $endDate]);
                $applyPharmacy($q);
            });

        $totalPurchaseAmount = (float) $purchasesQuery->sum('total');
        $totalPurchaseCount  = (int) $purchasesQuery->distinct('order_id')->count('order_id');

        // 3. METRICS: RETUR PENJUALAN (SALES RETURN)
        $salesReturQuery = MedicineTransactions::query()
            ->where('status', 1)
            ->where('transaction_type', 'RETUR JUAL')
            ->whereBetween('updated_at', [$startDate, $endDate]);
        $salesReturQuery = $applyPharmacy($salesReturQuery);

        $totalSalesReturAmount = (float) $salesReturQuery->sum('subtotal');
        $totalSalesReturCount  = (int) $salesReturQuery->count('id');
        $salesReturRate = $totalSalesAmount > 0 ? (($totalSalesReturAmount / $totalSalesAmount) * 100) : 0;

        // 4. METRICS: RETUR PEMBELIAN (PURCHASE RETURN)
        $purchaseReturQuery = ItemsLog::query()
            ->where('status', 4) // 4 = Retur Pembelian
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalPurchaseReturAmount = (float) $purchaseReturQuery->sum('total');
        $totalPurchaseReturCount  = (int) $purchaseReturQuery->count('id');

        // 5. BREAKDOWN JENIS TRANSAKSI (HV, UPDS, RESEP KREDIT, RESEP TUNAI)
        $txnTypesQuery = MedicineTransactions::query()
            ->where('status', 1)
            ->where('transaction_type', '!=', 'RETUR JUAL')
            ->whereBetween('updated_at', [$startDate, $endDate]);
        $txnTypesQuery = $applyPharmacy($txnTypesQuery);

        $txnTypesRaw = $txnTypesQuery->groupBy('transaction_type')
            ->select('transaction_type', DB::raw('count(*) as txn_count'), DB::raw('sum(subtotal) as total_amount'))
            ->get();

        $categoryMap = [
            'HV/OTC'      => ['label' => 'HV / Obat Bebas', 'color' => '#3b82f6', 'icon' => 'pill'],
            'UPDS'        => ['label' => 'UPDS (Swamedikasi)', 'color' => '#10b981', 'icon' => 'heart-handshake'],
            'KREDIT'      => ['label' => 'Resep Kredit', 'color' => '#8b5cf6', 'icon' => 'file-invoice'],
            'RESEP TUNAI' => ['label' => 'Resep Tunai', 'color' => '#f59e0b', 'icon' => 'receipt-2'],
        ];

        $typeBreakdown = [];
        $pieSeries = [];
        $pieLabels = [];
        $pieColors = [];

        foreach ($categoryMap as $key => $meta) {
            $found = $txnTypesRaw->firstWhere('transaction_type', $key);
            $amount = (float) ($found?->total_amount ?? 0);
            $count  = (int) ($found?->txn_count ?? 0);
            $percentage = $totalSalesAmount > 0 ? round(($amount / $totalSalesAmount) * 100, 1) : 0;

            $typeBreakdown[$key] = [
                'key'         => $key,
                'label'       => $meta['label'],
                'color'       => $meta['color'],
                'amount'      => $amount,
                'amount_rp'   => 'Rp ' . number_format($amount, 0, ',', '.'),
                'count'       => $count,
                'percentage'  => $percentage,
            ];

            $pieSeries[] = $amount;
            $pieLabels[] = $meta['label'];
            $pieColors[] = $meta['color'];
        }

        // 6. MONTHLY TREND (12 MONTHS OF SELECTED/CURRENT YEAR)
        $targetYear = $startDate->year;
        $monthlySales = [];
        $monthlyPurchases = [];
        $monthlyReturns = [];
        $monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

        // Aggregate monthly sales in single query
        $salesByMonth = MedicineTransactions::query()
            ->where('status', 1)
            ->where('transaction_type', '!=', 'RETUR JUAL')
            ->whereYear('updated_at', $targetYear)
            ->when($pharmacyId !== 'all' && !empty($pharmacyId), function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId);
            })
            ->groupBy(DB::raw('MONTH(updated_at)'))
            ->select(DB::raw('MONTH(updated_at) as month_num'), DB::raw('sum(subtotal) as total'))
            ->pluck('total', 'month_num');

        // Aggregate monthly purchases in single query
        $purchasesByMonth = OrderItems::query()
            ->whereHas('orders', function ($q) use ($targetYear, $pharmacyId) {
                $q->whereIn('status', [1, 2, 3])->whereYear('created_at', $targetYear);
                if ($pharmacyId !== 'all' && !empty($pharmacyId)) {
                    $q->where('pharmacy_id', $pharmacyId);
                }
            })
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->select(DB::raw('MONTH(created_at) as month_num'), DB::raw('sum(total) as total'))
            ->pluck('total', 'month_num');

        // Aggregate monthly returns in single query
        $returnsByMonth = MedicineTransactions::query()
            ->where('status', 1)
            ->where('transaction_type', 'RETUR JUAL')
            ->whereYear('updated_at', $targetYear)
            ->when($pharmacyId !== 'all' && !empty($pharmacyId), function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId);
            })
            ->groupBy(DB::raw('MONTH(updated_at)'))
            ->select(DB::raw('MONTH(updated_at) as month_num'), DB::raw('sum(subtotal) as total'))
            ->pluck('total', 'month_num');

        for ($m = 1; $m <= 12; $m++) {
            $monthlySales[]     = (float) ($salesByMonth[$m] ?? 0);
            $monthlyPurchases[] = (float) ($purchasesByMonth[$m] ?? 0);
            $monthlyReturns[]   = (float) ($returnsByMonth[$m] ?? 0);
        }

        // 7. DAILY TRAJECTORY (FOR SELECTED PERIOD)
        $diffDays = $startDate->diffInDays($endDate);
        $dailyDates = [];
        $dailySales = [];

        if ($diffDays <= 31) {
            $salesByDate = MedicineTransactions::query()
                ->where('status', 1)
                ->where('transaction_type', '!=', 'RETUR JUAL')
                ->whereBetween('updated_at', [$startDate, $endDate])
                ->when($pharmacyId !== 'all' && !empty($pharmacyId), function ($q) use ($pharmacyId) {
                    $q->where('pharmacy_id', $pharmacyId);
                })
                ->groupBy(DB::raw('DATE(updated_at)'))
                ->select(DB::raw('DATE(updated_at) as date_val'), DB::raw('sum(subtotal) as total'))
                ->pluck('total', 'date_val');

            $currentIter = $startDate->copy();
            while ($currentIter <= $endDate) {
                $dtStr = $currentIter->toDateString();
                $dailyDates[] = $currentIter->format('d/m');
                $dailySales[] = (float) ($salesByDate[$dtStr] ?? 0);
                $currentIter->addDay();
            }
        }

        // 8. BRANCHES PERFORMANCE LIST (FOR RIGHT SIDEBAR CARDS)
        $branchSales = MedicineTransactions::query()
            ->where('status', 1)
            ->where('transaction_type', '!=', 'RETUR JUAL')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->groupBy('pharmacy_id')
            ->select('pharmacy_id', DB::raw('sum(subtotal) as total_sales'), DB::raw('count(id) as total_txns'))
            ->get()
            ->keyBy('pharmacy_id');

        $allPharmacies = Pharmacies::orderBy('name', 'asc')->get();
        $branchList = [];

        foreach ($allPharmacies as $pharm) {
            $bData = $branchSales->get($pharm->id);
            $sales = (float) ($bData?->total_sales ?? 0);
            $txns  = (int) ($bData?->total_txns ?? 0);
            $share = $totalSalesAmount > 0 ? round(($sales / $totalSalesAmount) * 100, 1) : 0;

            $branchList[] = [
                'id'           => $pharm->id,
                'name'         => $pharm->name,
                'city'         => $pharm->city ?? 'Samarinda',
                'sales'        => $sales,
                'sales_rp'     => 'Rp ' . number_format($sales, 0, ',', '.'),
                'txns'         => $txns,
                'share'        => $share,
                'is_warehouse' => stripos($pharm->name, 'gudang') !== false,
            ];
        }

        return [
            'period'               => $period,
            'period_label'         => $periodLabel,
            'start_date'           => $startDate->toDateString(),
            'end_date'             => $endDate->toDateString(),
            'target_year'          => $targetYear,
            'pharmacy_id'          => $pharmacyId,
            'branch_list'          => $branchList,
            
            // Metrics Summary
            'total_sales'          => $totalSalesAmount,
            'total_sales_rp'       => 'Rp ' . number_format($totalSalesAmount, 0, ',', '.'),
            'total_sales_count'    => $totalSalesCount,
            'average_order_value'  => 'Rp ' . number_format($averageOrderValue, 0, ',', '.'),
            'total_discount'       => 'Rp ' . number_format($totalSalesDiscount, 0, ',', '.'),

            'total_purchases'      => $totalPurchaseAmount,
            'total_purchases_rp'   => 'Rp ' . number_format($totalPurchaseAmount, 0, ',', '.'),
            'total_purchases_count'=> $totalPurchaseCount,

            'total_sales_retur'    => $totalSalesReturAmount,
            'total_sales_retur_rp' => 'Rp ' . number_format($totalSalesReturAmount, 0, ',', '.'),
            'total_sales_retur_count' => $totalSalesReturCount,
            'sales_retur_rate'     => round($salesReturRate, 2) . '%',

            'total_purchase_retur' => $totalPurchaseReturAmount,
            'total_purchase_retur_rp' => 'Rp ' . number_format($totalPurchaseReturAmount, 0, ',', '.'),
            'total_purchase_retur_count' => $totalPurchaseReturCount,

            // Profit & Net Turnover Indicator
            'net_revenue'          => ($totalSalesAmount - $totalSalesReturAmount),
            'net_revenue_rp'       => 'Rp ' . number_format($totalSalesAmount - $totalSalesReturAmount, 0, ',', '.'),

            // Chart Breakdown
            'type_breakdown'       => $typeBreakdown,
            'pie_chart'            => [
                'series' => $pieSeries,
                'labels' => $pieLabels,
                'colors' => $pieColors,
            ],
            'monthly_chart'        => [
                'year'       => $targetYear,
                'categories' => $monthLabels,
                'sales'      => $monthlySales,
                'purchases'  => $monthlyPurchases,
                'returns'    => $monthlyReturns,
            ],
            'daily_chart'          => [
                'categories' => $dailyDates,
                'sales'      => $dailySales,
            ],
        ];
    }
}
