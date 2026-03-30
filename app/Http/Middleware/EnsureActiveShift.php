<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\ShiftLogs;

class EnsureActiveShift
{
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {
            if (!session()->has('active_shift_log_id')) {
                $shiftLog = ShiftLogs::where('user_id', auth()->id())
                    ->whereNull('clock_out')
                    ->latest()
                    ->first();

                if ($shiftLog) {
                    session(['active_shift_log_id' => $shiftLog->id]);
                }
            }
        }

        return $next($request);
    }
}
