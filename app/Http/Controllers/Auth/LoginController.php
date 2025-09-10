<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected function redirectTo()
    {
        $user = Auth::user();

        if ($user->hasRole('administrator') || $user->hasRole('Manager')) {
            return '/dashboard';
        } elseif ($user->hasRole('UMKM')) {
            return '/home';
        } elseif ($user->hasRole('Kasir')) {
            return '/home';
        }

        return '/';
    }

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }
}
