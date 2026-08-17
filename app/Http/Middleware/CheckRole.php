<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();

        $roles = explode('|', $role);
        
        // HO and Online can access Kasir routes
        if (in_array('Kasir', $roles)) {
            if (!in_array('HO', $roles)) {
                $roles[] = 'HO';
            }
            if (!in_array('Online', $roles)) {
                $roles[] = 'Online';
            }
        }

        if (! $user->hasAnyRole($roles)) {
            if ($user->hasRole('administrator') || $user->hasRole('Manager')) {
                return redirect('/dashboard');
            }

            if ($user->hasRole('UMKM')) {
                return redirect('/home');
            }

            if ($user->hasRole('Kasir') || $user->hasRole('HO') || $user->hasRole('Online')) {
                return redirect('/home');
            }

            return abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}
