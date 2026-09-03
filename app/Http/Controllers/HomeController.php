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

        return redirect()->route('account.profile')->with('success', 'Profil Akun Berhasil Diupdat');;
    }
}
