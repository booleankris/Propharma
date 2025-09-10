<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                if ($user->hasRole('administrator') || $user->hasRole('Manager')) {
                    return redirect('/dashboard');
                } elseif ($user->hasRole('Kasir')) {
                    return redirect('/home');
                }

                return redirect('/'); // fallback
            }
        }

        return $next($request);
    }
}
