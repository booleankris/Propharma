<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function username()
    {
        return 'username';
    }

    protected function authenticated(Request $request, $user)
    {
        if ($user->hasRole('administrator') || $user->hasRole('Manager')) {
            return redirect()->route('dashboard');
        }
        if ($user->hasRole('Kasir') || $user->hasRole('UMKM') || $user->hasRole('HO')) {
            return redirect()->route('home');
        }
        return redirect('/');
    }

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}