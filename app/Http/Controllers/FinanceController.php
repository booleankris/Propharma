<?php

namespace App\Http\Controllers;

use App\Models\Creditor;
use App\Models\Debtors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || (!$user->hasRole('Finance') && !$user->hasRole('General Manager') && !$user->hasRole('administrator'))) {
                abort(403, 'Akses ditolak: Modul SAHABAT Finances hanya dapat diakses oleh role Finance dan General Manager.');
            }
            return $next($request);
        });
    }

    /**
     * Tampilkan halaman utama modul SAHABAT Finances (React Realtime SPA).
     */
    public function index()
    {
        $user = Auth::user();
        $creditors = Creditor::orderBy('name')->take(50)->get(['code', 'name']);
        $debtors = Debtors::orderBy('name')->take(50)->get(['id', 'name']);

        return view('finance.index', compact('user', 'creditors', 'debtors'));
    }
}
