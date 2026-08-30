<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreventWarehouseAccess
{
    /**
     * Handle an incoming request.
     * Block warehouse pharmacy users (pharmacy_id = 9 / Gudang PMI) from retail/POS/Master routes.
     */
    public function handle(Request $request, Closure $next)
    {
        if (isWarehousePharmacy()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gudang PMI tidak memiliki akses ke fitur ini.'
                ], 403);
            }

            return redirect('/home')->with('message', 'Gudang PMI tidak memiliki akses ke fitur ini.');
        }

        return $next($request);
    }
}
