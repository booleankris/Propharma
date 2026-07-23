<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCart;
use App\Models\MedicineTransactions;
use App\Models\OrderItems;
use App\Models\Reject;
use App\Models\Sales;
use App\Models\Shifts;
use App\Models\TicketTransaction;
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
        function formatRupiah($amount)
        {
            return 'Rp ' . number_format($amount, 0, ',', '.');
        }


        // Dashboard 
        $total_sales = MedicineTransactions::where('status', 1)->sum('subtotal');
        $qty_sales = MedicineTransactions::where('status', 1)->count('id');

        $total_orders = OrderItems::whereHas('orders', function ($query) {
            $query->where('status', 2);
        })->sum('total');
       
        $total_reject = Reject::sum('total');

        $total_sales_rp = formatRupiah($total_sales);
        $total_orders_rp = formatRupiah($total_orders);
        $total_reject_rp = formatRupiah($total_reject);
        
        // Reports

        return view('kasir.home', compact('total_sales_rp', 'total_orders_rp', 'total_reject_rp'));
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
