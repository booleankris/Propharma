<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();

        if (! $user->hasRole($role)) {
            if ($user->hasRole('administrator') || $user->hasRole('Manager')) {
                return redirect('/dashboard');
            }

            if ($user->hasRole('UMKM')) {
                return redirect('/home');
            }

            if ($user->hasRole('Kasir')) {
                return redirect('/home');
            }

            return abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}
