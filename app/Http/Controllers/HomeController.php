<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

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

        $total_sales = (clone $baseQuery)->sum('subtotal');
        $qty_sales = (clone $baseQuery)->count('id');

        // Tangkap transaksi selesai baik berdasarkan updated_at (waktu checkout) maupun created_at
        $today_sales = (clone $baseQuery)->where(function ($q) use ($startToday, $endToday) {
            $q->whereBetween('updated_at', [$startToday, $endToday])
              ->orWhereBetween('created_at', [$startToday, $endToday]);
        })->sum('subtotal');

        $today_qty_sales = (clone $baseQuery)->where(function ($q) use ($startToday, $endToday) {
            $q->whereBetween('updated_at', [$startToday, $endToday])
              ->orWhereBetween('created_at', [$startToday, $endToday]);
        })->count('id');

        $month_sales = (clone $baseQuery)->where(function ($q) use ($startMonth, $endMonth) {
            $q->whereBetween('updated_at', [$startMonth, $endMonth])
              ->orWhereBetween('created_at', [$startMonth, $endMonth]);
        })->sum('subtotal');

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
        $nearExpiry = $this->queryNearExpiry()->take(5);

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

    private function queryNearExpiry($medicineId = null)
    {
        $query = Batches::query()
            ->with('medicines:id,name,code,unit')
            ->where('pharmacy_id', getActivePharmacyId())
            ->where('stock', '>', 0)
            ->whereNotNull('expired_date');

        if ($medicineId) {
            $query->where('medicine_id', $medicineId);
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
                return Carbon::createFromFormat($format, trim($value));
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
        $user = auth()->user();
        $isGlobalViewer = $user && ($user->hasRole('General Manager') || $user->hasRole('administrator') || $user->hasRole('HO'));

        $availableBranches = \App\Models\Pharmacies::whereIn('id', [1, 9, 2, 3, 4, 5])
            ->orderByRaw('CASE id WHEN 1 THEN 1 WHEN 9 THEN 2 WHEN 2 THEN 3 WHEN 3 THEN 4 WHEN 4 THEN 5 WHEN 5 THEN 6 ELSE 7 END')
            ->select('id', 'name')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'short_name' => trim(str_replace(['SAHABAT ', 'SAHABAT'], '', $p->name)),
                ];
            });

        $activePharmacyId = function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1;
        $activePharmacy = \App\Models\Pharmacies::find($activePharmacyId);
        $activePharmacyName = $activePharmacy ? $activePharmacy->name : 'Cabang';

        $filterPharmacy = $request->query('pharmacy_id');

        $query = \App\Models\ItemsLog::query()
            ->join('batches', 'batches.id', '=', 'items_log.batches_id')
            ->leftJoin('pharmacies', 'pharmacies.id', '=', 'batches.pharmacy_id')
            ->select([
                'items_log.id',
                'items_log.transaction_code',
                'items_log.medicine_id',
                'items_log.status',
                'items_log.qty',
                'items_log.qty_before',
                'items_log.qty_after',
                'items_log.created_at',
                'batches.pharmacy_id',
                'pharmacies.name as pharmacy_name',
            ])
            ->with('medicines:id,name,code')
            ->orderBy('items_log.id', 'desc');

        $selectedFilter = 'all';

        if (!$isGlobalViewer) {
            $query->where('batches.pharmacy_id', $activePharmacyId);
            $selectedFilter = $activePharmacyId;
        } else {
            if ($filterPharmacy && $filterPharmacy !== 'all') {
                $query->where('batches.pharmacy_id', (int) $filterPharmacy);
                $selectedFilter = (int) $filterPharmacy;
            } else {
                $selectedFilter = 'all';
            }
        }

        $logs = $query->take(35)->get();

        $typeMap = [
            1 => ['label' => 'Penjualan', 'icon' => '↓', 'sign' => '-', 'class' => 'qty-out', 'color' => 1],
            2 => ['label' => 'Pembelian', 'icon' => '↑', 'sign' => '+', 'class' => 'qty-in', 'color' => 2],
            3 => ['label' => 'Retur Penjualan', 'icon' => '↩', 'sign' => '+', 'class' => 'qty-in', 'color' => 3],
            4 => ['label' => 'Retur Pembelian', 'icon' => '↪', 'sign' => '-', 'class' => 'qty-out', 'color' => 4],
            5 => ['label' => 'Stock Opname (+)', 'icon' => '↑', 'sign' => '+', 'class' => 'qty-neutral', 'color' => 5],
            6 => ['label' => 'Stock Opname (-)', 'icon' => '↓', 'sign' => '-', 'class' => 'qty-neutral', 'color' => 6],
            7 => ['label' => 'Mutasi Stok', 'icon' => '⇄', 'sign' => '', 'class' => 'qty-neutral', 'color' => 5],
            8 => ['label' => 'Revisi Penerimaan', 'icon' => '✎', 'sign' => '', 'class' => 'qty-neutral', 'color' => 4],
            9 => ['label' => 'Hapus Penerimaan', 'icon' => '✕', 'sign' => '-', 'class' => 'qty-out', 'color' => 1],
        ];

        $formatted = $logs->map(function ($log) use ($typeMap) {
            $info = $typeMap[$log->status] ?? [
                'label' => 'Lainnya',
                'icon' => '•',
                'sign' => '',
                'class' => 'qty-neutral',
                'color' => 0,
            ];

            // Penyesuaian khusus untuk Mutasi Stok (status 7)
            if ($log->status == 7) {
                if ($log->qty_after > $log->qty_before) {
                    $info['label'] = 'Mutasi Masuk';
                    $info['icon'] = '↑';
                    $info['sign'] = '+';
                    $info['class'] = 'qty-in';
                    $info['color'] = 2;
                } elseif ($log->qty_after < $log->qty_before) {
                    $info['label'] = 'Mutasi Keluar';
                    $info['icon'] = '↓';
                    $info['sign'] = '-';
                    $info['class'] = 'qty-out';
                    $info['color'] = 4;
                }
            }

            $pharmacyName = $log->pharmacy_name ?? '-';
            $pharmacyShort = trim(str_replace(['SAHABAT ', 'SAHABAT'], '', $pharmacyName));

            $qtyFormatted = abs($log->qty);
            if (floor($qtyFormatted) == $qtyFormatted) {
                $qtyFormatted = (int) $qtyFormatted;
            }

            $qtyAfterFormatted = null;
            if ($log->qty_after !== null) {
                $qtyAfterFormatted = floor($log->qty_after) == $log->qty_after ? (int) $log->qty_after : $log->qty_after;
            }

            return [
                'id' => $log->id,
                'name' => $log->medicines->name ?? '-',
                'code' => $log->medicines->code ?? null,
                'transaction_code' => $log->transaction_code,
                'label' => $info['label'],
                'icon' => $info['icon'],
                'sign' => $info['sign'],
                'class' => $info['class'],
                'color' => $info['color'],
                'qty' => $qtyFormatted,
                'qty_after' => $qtyAfterFormatted,
                'pharmacy_id' => $log->pharmacy_id,
                'pharmacy_name' => $pharmacyName,
                'pharmacy_short' => $pharmacyShort,
                'time' => safeDateFormat($log->created_at, 'd M Y H:i'),
                'time_relative' => $log->created_at ? $log->created_at->diffForHumans() : '',
            ];
        });

        return response()->json([
            'notifications' => $formatted,
            'meta' => [
                'can_filter_branch' => $isGlobalViewer,
                'active_pharmacy_id' => $activePharmacyId,
                'active_pharmacy_name' => $activePharmacyName,
                'selected_filter' => $selectedFilter,
                'branches' => $availableBranches,
                'count' => $formatted->count(),
            ],
        ]);
    }
}
