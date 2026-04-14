<?php

namespace App\Http\Middleware;

// app/Http/Middleware/RedirectIfAuthenticated.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                if ($user->hasRole('administrator') || $user->hasRole('Manager')) {
                    return redirect()->route('dashboard');
                }

                if ($user->hasRole('Kasir')) {
                    return redirect()->route('kasir');
                }
                // if ($user->hasRole('APJ')) {
                //     return redirect()->route('homeapj');
                // }

                return redirect('/'); // fallback
            }
        }

        return $next($request);
    }
}