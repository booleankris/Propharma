<?php

namespace App\Http\Controllers;

use App\Exports\Stocks\StockNotificationExport;
use App\Models\Batches;
use App\Models\Item;
use App\Models\ItemCart;
use App\Models\MedicineTransactions;
use App\Models\OrderItems;
use App\Models\Reject;
use App\Models\Sales;
use App\Models\Shifts;
use App\Models\TicketTransaction;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

use App\Models\User;


class HomeController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        if (!function_exists('App\Http\Controllers\formatRupiah')) {
            function formatRupiah($amount)
            {
                return 'Rp ' . number_format($amount, 0, ',', '.');
            }
        }


        // Dashboard
        $pharmacyId = getActivePharmacyId();
        $startToday = Carbon::today()->startOfDay();
        $endToday = Carbon::today()->endOfDay();
        $startMonth = Carbon::now()->startOfMonth();
        $endMonth = Carbon::now()->endOfMonth();

        if (isOnlineRole()) {
            $user = auth()->user();
            $onlineRoles = array_values(array_intersect($user->getRoleNames()->toArray(), ['Online', 'Online Grab', 'Online Shopee', 'Digital']));
            if (empty($onlineRoles)) {
                $onlineRoles = ['Online', 'Online Grab', 'Online Shopee', 'Digital'];
            }

            $baseQuery = MedicineTransactions::where('status', 1)
                ->where('pharmacy_id', $pharmacyId)
                ->where(function ($query) use ($onlineRoles, $user) {
                    $query->where('user_id', $user->id)
                        ->orWhereHas('user', function ($q) use ($onlineRoles) {
                            $q->whereHas('roles', function ($rq) use ($onlineRoles) {
                                $rq->whereIn('name', $onlineRoles);
                            });
                        })
                        ->orWhereHas('transactions.user', function ($q) use ($onlineRoles) {
                            $q->whereHas('roles', function ($rq) use ($onlineRoles) {
                                $rq->whereIn('name', $onlineRoles);
                            });
                        });
                });
        } else {
            $baseQuery = MedicineTransactions::where('status', 1)
                ->where('pharmacy_id', $pharmacyId);
        }

        $isReturnSql = "UPPER(TRIM(COALESCE(transaction_type, ''))) IN ('RETUR JUAL', 'RETUR KREDIT', 'RETUR TUNAI', 'RETUR') OR UPPER(TRIM(COALESCE(transaction_code, ''))) = 'RT'";

        $calcNetSales = function ($query) use ($isReturnSql) {
            return (float) (clone $query)->selectRaw("
                COALESCE(SUM(
                    CASE 
                        WHEN {$isReturnSql} THEN -ABS(COALESCE(subtotal, 0)) 
                        ELSE COALESCE(subtotal, 0) 
                    END
                ), 0) as net_sales
            ")->value('net_sales');
        };

        $total_sales = $calcNetSales($baseQuery);
        $qty_sales   = (clone $baseQuery)
            ->whereNotIn('transaction_type', ['RETUR JUAL', 'RETUR KREDIT', 'RETUR TUNAI', 'RETUR'])
            ->where('transaction_code', '!=', 'RT')
            ->count('id');

        // Tangkap transaksi selesai baik berdasarkan updated_at (waktu checkout) maupun created_at
        $todayQuery = (clone $baseQuery)->where(function ($q) use ($startToday, $endToday) {
            $q->whereBetween('updated_at', [$startToday, $endToday])
              ->orWhereBetween('created_at', [$startToday, $endToday]);
        });

        $today_sales     = $calcNetSales($todayQuery);
        $today_qty_sales = (clone $todayQuery)
            ->whereNotIn('transaction_type', ['RETUR JUAL', 'RETUR KREDIT', 'RETUR TUNAI', 'RETUR'])
            ->where('transaction_code', '!=', 'RT')
            ->count('id');

        $monthQuery = (clone $baseQuery)->where(function ($q) use ($startMonth, $endMonth) {
            $q->whereBetween('updated_at', [$startMonth, $endMonth])
              ->orWhereBetween('created_at', [$startMonth, $endMonth]);
        });

        $month_sales = $calcNetSales($monthQuery);

        $total_orders = OrderItems::whereHas('orders', function ($query) use ($pharmacyId) {
            $targetPharmacyIds = in_array((int) $pharmacyId, [1, 6, 9]) ? [9, 1] : [(int) $pharmacyId];
            $query->where('status', 2)->whereIn('pharmacy_id', $targetPharmacyIds);
        })->sum('total');

        $total_reject = Reject::sum('total');

        $today_sales_rp = formatRupiah($today_sales);
        $month_sales_rp = formatRupiah($month_sales);
        $total_sales_rp = formatRupiah($total_sales);
        $total_orders_rp = formatRupiah($total_orders);
        $total_reject_rp = formatRupiah($total_reject);

        // 5 barang dengan ED terdekat
        $nearExpiry = $this->queryNearExpiry(null, 5);

        return view('kasir.home', compact(
            'today_sales_rp',
            'today_qty_sales',
            'month_sales_rp',
            'total_sales_rp',
            'total_orders_rp',
            'total_reject_rp',
            'nearExpiry',
            'qty_sales'
        ));
    }
    public function nearExpiry(Request $request)
    {
        $medicineId = $request->get('medicine_id');

        $items = $this->queryNearExpiry($medicineId);

        $perPage = 10;
        $page = Paginator::resolveCurrentPage() ?: 1;
        $collection = $items->forPage($page, $perPage);

        $paginator = new LengthAwarePaginator(
            $collection,
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $selectedMedicine = $medicineId
            ? \App\Models\Medicines::find($medicineId)
            : null;

        return view('kasir.near-expiry', [
            'items' => $paginator,
            'selectedMedicine' => $selectedMedicine,
        ]);
    }

    private function queryNearExpiry($medicineId = null, ?int $limit = null)
    {
        $query = Batches::query()
            ->with('medicines:id,name,code,unit')
            ->where('pharmacy_id', getActivePharmacyId())
            ->where('stock', '>', 0)
            ->whereNotNull('expired_date');

        if ($medicineId) {
            $query->where('medicine_id', $medicineId);
        }

        // expired_date is a string and historical rows can use several date formats.
        // Parse/order/limit in MariaDB for the dashboard so we do not hydrate every
        // positive-stock batch into PHP just to display five rows.
        if ($limit !== null && $limit > 0 && \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql') {
            $parsedExpiry = "COALESCE("
                . "STR_TO_DATE(batches.expired_date, '%Y-%m-%d %H:%i:%s'),"
                . "STR_TO_DATE(batches.expired_date, '%Y-%m-%d %H:%i'),"
                . "STR_TO_DATE(batches.expired_date, '%Y-%m-%d'),"
                . "STR_TO_DATE(batches.expired_date, '%d/%m/%Y %H:%i:%s'),"
                . "STR_TO_DATE(batches.expired_date, '%d/%m/%Y %H:%i'),"
                . "STR_TO_DATE(batches.expired_date, '%d/%m/%Y'),"
                . "STR_TO_DATE(batches.expired_date, '%d-%m-%Y %H:%i:%s'),"
                . "STR_TO_DATE(batches.expired_date, '%d-%m-%Y %H:%i'),"
                . "STR_TO_DATE(batches.expired_date, '%d-%m-%Y')"
                . ")";

            $query->select('batches.*')
                ->selectRaw("{$parsedExpiry} AS parsed_expiry_date")
                ->whereRaw("{$parsedExpiry} IS NOT NULL AND YEAR({$parsedExpiry}) >= 2000")
                ->orderBy('parsed_expiry_date')
                ->orderBy('batches.id')
                ->limit($limit);
        }

        $items = $query
            ->get()
            ->map(function ($batch) {
                $batch->expiry_carbon = $this->parseExpiryDate($batch->expired_date);
                $batch->expiry_formatted = $batch->expiry_carbon
                    ? $batch->expiry_carbon->format('d/m/Y')
                    : $batch->expired_date;

                if ($batch->expiry_carbon) {
                    $today = Carbon::today()->startOfDay();
                    $expiry = $batch->expiry_carbon->startOfDay();
                    $daysLeft = $today->diffInDays($expiry, false);

                    $batch->days_left = $daysLeft;

                    if ($daysLeft < 0) {
                        $batch->expiry_status = 'expired';
                    } elseif ($daysLeft <= 30) {
                        $batch->expiry_status = 'near';
                    } else {
                        $batch->expiry_status = 'safe';
                    }
                } else {
                    $batch->days_left = null;
                    $batch->expiry_status = null;
                }

                return $batch;
            })
            ->filter(fn($batch) => $batch->expiry_carbon !== null)
            ->sortBy(fn($batch) => $batch->expiry_carbon)
            ->values();

        if ($limit !== null && $limit > 0) {
            $items = $items->take($limit)->values();
        }

        return $items;
    }

    private function parseExpiryDate($value)
    {
        if (empty($value)) {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, trim($value));
                // Abaikan tanggal sentinel / placeholder (mis. 0001-01-01, 1900-01-01) untuk alkes/item non-ED.
                if ($parsed->year < 2000 || $parsed->year > 2100) {
                    return null;
                }
                return $parsed;
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    public function profile($edit = null)
    {
        $user = Auth::user()->only(['id', 'name', 'email']);

        if ($edit == null) {
            return view('account.profile', compact('user'));
        } else {
            return view('account.profile-edit', compact('user'));
        }
    }

    public function updateProfile(Request $request)
    {
        $this->validate($request, [
            'name'     => 'required|string|min:2|max:200',
            'email'    => 'required|email|min:2|max:200|unique:users,email,' . Auth::id(),
        ]);

        $data = [
            'name'  => $request->name,
            'email' => $request->email,
        ];

        if ($request->password != null) {
            $data['password'] = Hash::make($request->password);
        }

        User::where('id', Auth::id())->update($data);

        return redirect()->route('account.profile')->with('success', 'Profil Akun Berhasil Diupdat');
    }

    public function stockNotifications(Request $request)
    {
        $scope = $this->resolveStockAlertScope($request);

        $forceRefresh = $request->boolean('refresh');
        $cacheKey = 'stock_alerts_v4:' . ($scope['is_global_viewer'] ? 'global' : 'branch_' . $scope['active_pharmacy_id']) . ':' . $scope['selected_filter'];

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        $payload = Cache::remember($cacheKey, 60, function () use ($scope) {
            $daysThreshold = 90;
            $lowStock = $this->queryLowStockAlerts($scope['target_pharmacy_ids'], $scope['branch_map'], $scope['warehouse_id'], 40);
            $nearExpiry = $this->queryNearExpiryAlerts($scope['target_pharmacy_ids'], $scope['branch_map'], $scope['warehouse_id'], $daysThreshold, 40);

            $combined = collect($nearExpiry['items'])
                ->concat($lowStock['items'])
                ->sortBy([
                    ['priority_rank', 'asc'],
                    ['sort_metric', 'asc'],
                    ['name', 'asc'],
                ])
                ->take(60)
                ->values()
                ->all();

            return [
                'notifications' => $combined,
                'low_stock' => $lowStock['items'],
                'near_expiry' => $nearExpiry['items'],
                'summary' => [
                    'total_count' => $lowStock['total_count'] + $nearExpiry['total_count'],
                    'low_stock_count' => $lowStock['total_count'],
                    'out_of_stock_count' => $lowStock['critical_count'],
                    'near_expiry_count' => $nearExpiry['total_count'],
                    'expired_count' => $nearExpiry['expired_count'],
                    'critical_count' => $lowStock['critical_count'] + $nearExpiry['critical_count'],
                    'days_threshold' => $daysThreshold,
                ],
                'updated_at' => Carbon::now()->format('H:i'),
            ];
        });

        return response()->json([
            'notifications' => $payload['notifications'],
            'low_stock' => $payload['low_stock'],
            'near_expiry' => $payload['near_expiry'],
            'summary' => $payload['summary'],
            'meta' => [
                'can_filter_branch' => $scope['is_global_viewer'],
                'active_pharmacy_id' => $scope['active_pharmacy_id'],
                'active_pharmacy_name' => $scope['active_pharmacy_name'],
                'selected_filter' => $scope['selected_filter'],
                'branches' => $scope['available_branches']->values(),
                'count' => count($payload['notifications']),
                'updated_at' => $payload['updated_at'],
            ],
        ]);
    }

    public function exportStockNotifications(Request $request)
    {
        $scope = $this->resolveStockAlertScope($request);
        $rawTab = (string) $request->query('tab', 'all');
        $tab = in_array($rawTab, ['all', 'low_stock', 'near_expiry'], true) ? $rawTab : 'all';
        $daysThreshold = 90;

        $lowStockItems = ($tab === 'all' || $tab === 'low_stock')
            ? $this->queryLowStockAlerts($scope['target_pharmacy_ids'], $scope['branch_map'], $scope['warehouse_id'], null)['items']
            : [];

        $nearExpiryItems = ($tab === 'all' || $tab === 'near_expiry')
            ? $this->queryNearExpiryAlerts($scope['target_pharmacy_ids'], $scope['branch_map'], $scope['warehouse_id'], $daysThreshold, null)['items']
            : [];

        $branchLabel = $scope['selected_filter'] === 'all'
            ? 'Semua Cabang'
            : ($scope['branch_map'][$scope['selected_filter']]['name'] ?? $scope['active_pharmacy_name']);

        $tabSlug = match ($tab) {
            'low_stock' => 'Stok-Rendah',
            'near_expiry' => 'Dekat-ED',
            default => 'Stok-dan-ED',
        };
        $branchSlug = Str::slug($branchLabel, '-');
        $filename = "Notifikasi-{$tabSlug}-{$branchSlug}-" . Carbon::now()->format('Ymd-His') . '.xlsx';

        return Excel::download(
            new StockNotificationExport($lowStockItems, $nearExpiryItems, $branchLabel, $tab, $daysThreshold),
            $filename
        );
    }

    private function resolveStockAlertScope(Request $request): array
    {
        $user = auth()->user();
        $isGlobalViewer = $user && ($user->hasRole('General Manager') || $user->hasRole('administrator') || $user->hasRole('HO'));

        $availableBranches = \App\Models\Pharmacies::whereIn('id', [1, 9, 2, 3, 4, 5])
            ->orderByRaw('CASE id WHEN 1 THEN 1 WHEN 9 THEN 2 WHEN 2 THEN 3 WHEN 3 THEN 4 WHEN 4 THEN 5 WHEN 5 THEN 6 ELSE 7 END')
            ->select('id', 'name')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => (int) $p->id,
                    'name' => $p->name,
                    'short_name' => trim(str_replace(['SAHABAT ', 'SAHABAT'], '', $p->name)),
                ];
            });

        $branchMap = $availableBranches->keyBy('id')->all();

        $activePharmacyId = (int) (function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1);
        $warehouseId = (int) (function_exists('getWarehousePharmacyId') ? getWarehousePharmacyId() : 9);
        $activePharmacy = $branchMap[$activePharmacyId] ?? null;
        $activePharmacyName = $activePharmacy['name'] ?? (\App\Models\Pharmacies::whereKey($activePharmacyId)->value('name') ?: 'Cabang');

        if (!isset($branchMap[$activePharmacyId])) {
            $branchMap[$activePharmacyId] = [
                'id' => $activePharmacyId,
                'name' => $activePharmacyName,
                'short_name' => trim(str_replace(['SAHABAT ', 'SAHABAT'], '', $activePharmacyName)),
            ];
        }

        $filterPharmacy = $request->query('pharmacy_id');
        if (!$isGlobalViewer) {
            $selectedFilter = $activePharmacyId;
            $targetPharmacyIds = [$activePharmacyId];
        } elseif ($filterPharmacy && $filterPharmacy !== 'all') {
            $selectedFilter = (int) $filterPharmacy;
            $targetPharmacyIds = [$selectedFilter];
        } else {
            $selectedFilter = 'all';
            $targetPharmacyIds = $availableBranches->pluck('id')->map(fn($id) => (int) $id)->all();
            if ($targetPharmacyIds === []) {
                $targetPharmacyIds = [$activePharmacyId];
            }
        }

        return [
            'is_global_viewer' => $isGlobalViewer,
            'available_branches' => $availableBranches,
            'branch_map' => $branchMap,
            'active_pharmacy_id' => $activePharmacyId,
            'active_pharmacy_name' => $activePharmacyName,
            'warehouse_id' => $warehouseId,
            'selected_filter' => $selectedFilter,
            'target_pharmacy_ids' => $targetPharmacyIds,
        ];
    }

    /**
     * Ambil daftar obat dengan stok <= minimal_stock (hanya obat dengan minimal_stock > 0).
     */
    private function queryLowStockAlerts(array $pharmacyIds, array $branchMap, int $warehouseId, ?int $limit = 40): array
    {
        $warehouseIds = array_values(array_filter($pharmacyIds, fn($id) => (int) $id === $warehouseId));
        $outletIds = array_values(array_filter($pharmacyIds, fn($id) => (int) $id !== $warehouseId));

        $rows = collect();

        if ($warehouseIds !== []) {
            $warehouseRows = DB::table('batches')
                ->join('medicines', 'medicines.id', '=', 'batches.medicine_id')
                ->whereIn('batches.pharmacy_id', $warehouseIds)
                ->where('medicines.minimal_stock', '>', 0)
                ->groupBy(
                    'batches.pharmacy_id',
                    'batches.medicine_id',
                    'medicines.name',
                    'medicines.code',
                    'medicines.unit',
                    'medicines.minimal_stock'
                )
                ->select([
                    'batches.pharmacy_id',
                    'batches.medicine_id',
                    'medicines.name',
                    'medicines.code',
                    'medicines.unit',
                    'medicines.minimal_stock',
                    DB::raw('COALESCE(SUM(batches.stock), 0) AS current_stock'),
                    DB::raw('0 AS has_positive_transfer'),
                ])
                ->havingRaw('COALESCE(SUM(batches.stock), 0) <= medicines.minimal_stock')
                ->get();

            $rows = $rows->concat($warehouseRows);
        }

        if ($outletIds !== []) {
            $outletRows = DB::table('batches')
                ->join('medicines', 'medicines.id', '=', 'batches.medicine_id')
                ->leftJoin('medicine_transfer_items as mti', function ($join) {
                    $join->on('mti.batches_id', '=', 'batches.id')
                        ->where('mti.status', '=', 1)
                        ->where(function ($q) {
                            $q->whereNull('mti.source_type')
                                ->orWhere('mti.source_type', '!=', 'retur_gudang');
                        });
                })
                ->whereIn('batches.pharmacy_id', $outletIds)
                ->where('medicines.minimal_stock', '>', 0)
                ->groupBy(
                    'batches.pharmacy_id',
                    'batches.medicine_id',
                    'medicines.name',
                    'medicines.code',
                    'medicines.unit',
                    'medicines.minimal_stock'
                )
                ->select([
                    'batches.pharmacy_id',
                    'batches.medicine_id',
                    'medicines.name',
                    'medicines.code',
                    'medicines.unit',
                    'medicines.minimal_stock',
                    DB::raw('COALESCE(SUM(mti.qty), 0) AS current_stock'),
                    DB::raw('MAX(CASE WHEN COALESCE(mti.original_qty, 0) > 0 OR COALESCE(mti.qty, 0) > 0 THEN 1 ELSE 0 END) AS has_positive_transfer'),
                ])
                ->havingRaw('COALESCE(SUM(mti.qty), 0) <= medicines.minimal_stock')
                ->get();

            $rows = $rows->concat($outletRows);
        }

        // Filter: hanya tampilkan obat yang pernah memiliki stok (> 0) di cabang tersebut
        // (stok saat ini > 0, pernah menerima mutasi masuk > 0, atau punya riwayat items_log dengan qty_before/qty_after > 0).
        $needsHistoryCheck = $rows->filter(function ($row) {
            return (float) ($row->current_stock ?? 0) <= 0
                && (int) ($row->has_positive_transfer ?? 0) === 0;
        });

        $historicalPositiveKeys = [];
        if ($needsHistoryCheck->isNotEmpty()) {
            $checkPharmacyIds = $needsHistoryCheck->pluck('pharmacy_id')->map(fn($v) => (int) $v)->unique()->values()->all();
            $checkMedicineIds = $needsHistoryCheck->pluck('medicine_id')->map(fn($v) => (int) $v)->unique()->values()->all();

            foreach (array_chunk($checkMedicineIds, 500) as $medChunk) {
                $historyRows = DB::table('items_log')
                    ->join('batches', 'batches.id', '=', 'items_log.batches_id')
                    ->whereIn('batches.pharmacy_id', $checkPharmacyIds)
                    ->whereIn('batches.medicine_id', $medChunk)
                    ->where(function ($q) {
                        $q->where('items_log.qty_before', '>', 0)
                            ->orWhere('items_log.qty_after', '>', 0);
                    })
                    ->select('batches.pharmacy_id', 'batches.medicine_id')
                    ->distinct()
                    ->get();

                foreach ($historyRows as $hr) {
                    $historicalPositiveKeys[((int) $hr->pharmacy_id) . ':' . ((int) $hr->medicine_id)] = true;
                }
            }
        }

        $rows = $rows->filter(function ($row) use ($historicalPositiveKeys) {
            if ((float) ($row->current_stock ?? 0) > 0) {
                return true;
            }
            if ((int) ($row->has_positive_transfer ?? 0) === 1) {
                return true;
            }

            $key = ((int) $row->pharmacy_id) . ':' . ((int) $row->medicine_id);
            return isset($historicalPositiveKeys[$key]);
        })->values();

        $totalCount = $rows->count();
        $criticalCount = 0;

        $sorted = $rows->map(function ($row) use ($branchMap, &$criticalCount) {
            $currentStock = (float) ($row->current_stock ?? 0);
            $minimalStock = max(1, (int) ($row->minimal_stock ?? 0));
            $isOut = $currentStock <= 0;
            if ($isOut) {
                $criticalCount++;
            }

            $ratio = max(0.0, min(1.0, $currentStock / $minimalStock));
            $pharmacyId = (int) $row->pharmacy_id;
            $branch = $branchMap[$pharmacyId] ?? ['name' => 'Cabang', 'short_name' => 'Cabang'];

            return [
                'id' => "low-{$pharmacyId}-{$row->medicine_id}",
                'type' => 'low_stock',
                'severity' => $isOut ? 'critical' : 'warning',
                'status' => $isOut ? 'out_of_stock' : 'low_stock',
                'label' => $isOut ? 'Stok Habis' : 'Stok Rendah',
                'medicine_id' => (int) $row->medicine_id,
                'name' => $row->name ?? '-',
                'code' => $row->code ?? null,
                'unit' => $row->unit ?? '',
                'current_stock' => $this->formatAlertQty($currentStock),
                'minimal_stock' => $minimalStock,
                'stock_percent' => (int) round($ratio * 100),
                'pharmacy_id' => $pharmacyId,
                'pharmacy_name' => $branch['name'],
                'pharmacy_short' => $branch['short_name'],
                'priority_rank' => $isOut ? 1 : 3,
                'sort_metric' => $ratio,
            ];
        })
            ->sortBy([
                ['priority_rank', 'asc'],
                ['sort_metric', 'asc'],
                ['name', 'asc'],
            ]);

        if ($limit !== null) {
            $sorted = $sorted->take($limit);
        }

        $mapped = $sorted->values()->all();

        return [
            'items' => $mapped,
            'total_count' => $totalCount,
            'critical_count' => $criticalCount,
        ];
    }

    /**
     * Ambil daftar batch stok > 0 yang sudah kedaluwarsa atau mendekati ED (<= $daysThreshold hari).
     */
    private function queryNearExpiryAlerts(
        array $pharmacyIds,
        array $branchMap,
        int $warehouseId,
        int $daysThreshold = 90,
        ?int $limit = 40
    ): array {
        $warehouseIds = array_values(array_filter($pharmacyIds, fn($id) => (int) $id === $warehouseId));
        $outletIds = array_values(array_filter($pharmacyIds, fn($id) => (int) $id !== $warehouseId));

        $isMysql = DB::connection()->getDriverName() === 'mysql';
        $today = Carbon::today()->startOfDay();
        $cutoffDate = $today->copy()->addDays($daysThreshold)->toDateString();

        $parsedExpirySql = "COALESCE("
            . "STR_TO_DATE(batches.expired_date, '%Y-%m-%d %H:%i:%s'),"
            . "STR_TO_DATE(batches.expired_date, '%Y-%m-%d %H:%i'),"
            . "STR_TO_DATE(batches.expired_date, '%Y-%m-%d'),"
            . "STR_TO_DATE(batches.expired_date, '%d/%m/%Y %H:%i:%s'),"
            . "STR_TO_DATE(batches.expired_date, '%d/%m/%Y %H:%i'),"
            . "STR_TO_DATE(batches.expired_date, '%d/%m/%Y'),"
            . "STR_TO_DATE(batches.expired_date, '%d-%m-%Y %H:%i:%s'),"
            . "STR_TO_DATE(batches.expired_date, '%d-%m-%Y %H:%i'),"
            . "STR_TO_DATE(batches.expired_date, '%d-%m-%Y')"
            . ")";

        $rows = collect();

        if ($warehouseIds !== []) {
            $q = DB::table('batches')
                ->join('medicines', 'medicines.id', '=', 'batches.medicine_id')
                ->whereIn('batches.pharmacy_id', $warehouseIds)
                ->where('batches.stock', '>', 0)
                ->whereNotNull('batches.expired_date')
                ->where('batches.expired_date', '!=', '')
                ->where('batches.expired_date', '!=', '0000-00-00')
                ->select([
                    'batches.id as batch_id',
                    'batches.name as batch_name',
                    'batches.pharmacy_id',
                    'batches.medicine_id',
                    'batches.expired_date',
                    'batches.stock as current_stock',
                    'medicines.name',
                    'medicines.code',
                    'medicines.unit',
                ]);

            if ($isMysql) {
                $q->whereRaw("{$parsedExpirySql} IS NOT NULL AND YEAR({$parsedExpirySql}) >= 2000 AND {$parsedExpirySql} <= ?", [$cutoffDate]);
            }

            $rows = $rows->concat($q->get());
        }

        if ($outletIds !== []) {
            $q = DB::table('batches')
                ->join('medicines', 'medicines.id', '=', 'batches.medicine_id')
                ->join('medicine_transfer_items as mti', function ($join) {
                    $join->on('mti.batches_id', '=', 'batches.id')
                        ->where('mti.status', '=', 1)
                        ->where(function ($sub) {
                            $sub->whereNull('mti.source_type')
                                ->orWhere('mti.source_type', '!=', 'retur_gudang');
                        });
                })
                ->whereIn('batches.pharmacy_id', $outletIds)
                ->whereNotNull('batches.expired_date')
                ->where('batches.expired_date', '!=', '')
                ->where('batches.expired_date', '!=', '0000-00-00')
                ->groupBy(
                    'batches.id',
                    'batches.name',
                    'batches.pharmacy_id',
                    'batches.medicine_id',
                    'batches.expired_date',
                    'medicines.name',
                    'medicines.code',
                    'medicines.unit'
                )
                ->select([
                    'batches.id as batch_id',
                    'batches.name as batch_name',
                    'batches.pharmacy_id',
                    'batches.medicine_id',
                    'batches.expired_date',
                    DB::raw('COALESCE(SUM(mti.qty), 0) AS current_stock'),
                    'medicines.name',
                    'medicines.code',
                    'medicines.unit',
                ])
                ->havingRaw('COALESCE(SUM(mti.qty), 0) > 0');

            if ($isMysql) {
                $q->whereRaw("{$parsedExpirySql} IS NOT NULL AND YEAR({$parsedExpirySql}) >= 2000 AND {$parsedExpirySql} <= ?", [$cutoffDate]);
            }

            $rows = $rows->concat($q->get());
        }

        $expiredCount = 0;
        $criticalCount = 0;

        $filtered = $rows->map(function ($row) use ($branchMap, $today, $daysThreshold, &$expiredCount, &$criticalCount) {
            $expiry = $this->parseExpiryDate($row->expired_date);
            if (!$expiry) {
                return null;
            }

            $daysLeft = (int) $today->diffInDays($expiry->copy()->startOfDay(), false);
            if ($daysLeft > $daysThreshold) {
                return null;
            }

            $isExpired = $daysLeft < 0;
            $isCritical = $daysLeft <= 30;
            if ($isExpired) {
                $expiredCount++;
            }
            if ($isCritical) {
                $criticalCount++;
            }

            if ($isExpired) {
                $status = 'expired';
                $label = 'Sudah ED';
                $countdown = 'Lewat ' . abs($daysLeft) . ' hr';
                $priorityRank = 0;
            } elseif ($daysLeft === 0) {
                $status = 'expiring_soon';
                $label = 'ED Hari Ini';
                $countdown = 'Hari ini';
                $priorityRank = 1;
            } elseif ($daysLeft <= 30) {
                $status = 'expiring_soon';
                $label = 'ED < 30 Hari';
                $countdown = $daysLeft . ' hr lagi';
                $priorityRank = 2;
            } else {
                $status = 'near_expiry';
                $label = 'Dekat ED';
                $countdown = $daysLeft . ' hr lagi';
                $priorityRank = 4;
            }

            $pharmacyId = (int) $row->pharmacy_id;
            $branch = $branchMap[$pharmacyId] ?? ['name' => 'Cabang', 'short_name' => 'Cabang'];

            return [
                'id' => "ed-{$pharmacyId}-{$row->batch_id}",
                'type' => 'near_expiry',
                'severity' => $isCritical ? 'critical' : 'warning',
                'status' => $status,
                'label' => $label,
                'countdown' => $countdown,
                'days_left' => $daysLeft,
                'expired_date' => $expiry->format('d M Y'),
                'batch_id' => (int) $row->batch_id,
                'batch_name' => $row->batch_name ?: '-',
                'medicine_id' => (int) $row->medicine_id,
                'name' => $row->name ?? '-',
                'code' => $row->code ?? null,
                'unit' => $row->unit ?? '',
                'current_stock' => $this->formatAlertQty((float) ($row->current_stock ?? 0)),
                'pharmacy_id' => $pharmacyId,
                'pharmacy_name' => $branch['name'],
                'pharmacy_short' => $branch['short_name'],
                'priority_rank' => $priorityRank,
                'sort_metric' => $daysLeft,
            ];
        })
            ->filter()
            ->values();

        $totalCount = $filtered->count();

        $sortedCollection = $filtered
            ->sortBy([
                ['days_left', 'asc'],
                ['name', 'asc'],
            ]);

        if ($limit !== null) {
            $sortedCollection = $sortedCollection->take($limit);
        }

        $sorted = $sortedCollection->values()->all();

        return [
            'items' => $sorted,
            'total_count' => $totalCount,
            'expired_count' => $expiredCount,
            'critical_count' => $criticalCount,
        ];
    }

    private function formatAlertQty(float $qty): int|float
    {
        return floor($qty) == $qty ? (int) $qty : round($qty, 2);
    }
}
