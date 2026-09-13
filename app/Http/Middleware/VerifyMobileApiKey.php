<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyMobileApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $expectedKey = config('services.mobile.api_key', env('MOBILE_API_KEY'));

        // 1. Header X-API-KEY / x-api-key
        $apiKey = $request->header('X-API-KEY') ?: $request->header('x-api-key');

        // 2. Authorization: Bearer <token>
        if (!$apiKey) {
            $apiKey = $request->bearerToken();
        }

        // 3. Fallback: Query parameter ?api_key=... / ?auth_key=...
        if (!$apiKey) {
            $apiKey = $request->query('api_key') ?: $request->query('auth_key');
        }

        if (empty($expectedKey) || empty($apiKey) || !hash_equals((string) $expectedKey, (string) $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: API Key tidak valid atau tidak disertakan. Harap sertakan header X-API-KEY atau Authorization: Bearer <token>.',
            ], 401);
        }

        return $next($request);
    }
}
