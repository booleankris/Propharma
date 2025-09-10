<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCart;
use App\Models\TicketTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $totalproduct = Item::count('id');
        $cart_total = ItemCart::where('status', '0')->where('user_id', Auth()->id())->count('id');
        $get_subtotal = ItemCart::with('item')->where('user_id', Auth()->id())->where('status', '!=', '1')->get();
        $cart_subtotal = $get_subtotal->sum(function ($cart) {
            return $cart->quantity * ($cart->item->item_price ?? 0);
        });

        $items = Item::where('user_id', Auth::user()->id)->get();
        $cart_status = Item::with('cart_status')->where('user_id', Auth()->id())->get();
        $totaltransaction = TicketTransaction::where('user_id', Auth::user()->id)->count('id');
   


       

        return view('dashboard', compact('totalproduct', 'totaltransaction'));
    }
}
