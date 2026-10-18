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
        
        // HO and all Online roles can access Kasir routes
        if (in_array('Kasir', $roles)) {
            if (!in_array('HO', $roles)) {
                $roles[] = 'HO';
            }
            foreach (['Online', 'Online Grab', 'Online Shopee', 'Digital'] as $onlineRole) {
                if (!in_array($onlineRole, $roles)) {
                    $roles[] = $onlineRole;
                }
            }
        }

        if (! $user->hasAnyRole($roles)) {
            if ($user->hasRole('General Manager')) {
                return redirect()->route('home');
            }

            // Semua role online tidak mengakses /dashboard, dialihkan ke /home
            if (isOnlineRole($user)) {
                return redirect()->route('home');
            }

            if ($user->hasRole('administrator') || $user->hasRole('Manager')) {
                return redirect('/dashboard');
            }

            if ($user->hasRole('UMKM') || $user->hasRole('Gudang PMI') || isWarehousePharmacy()) {
                return redirect('/home');
            }

            if ($user->hasRole('Kasir') || $user->hasRole('HO')) {
                return redirect('/home');
            }

            return abort(403, 'Unauthorized role.');
        }

        return $next($request);
    }
}
