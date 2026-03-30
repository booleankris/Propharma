<?php

use App\Models\ShiftLogs;
use Illuminate\Support\Facades\Auth;

if (!function_exists('activeShift')) {
    /**
     * Get the current active shift for the logged-in user.
     * Checks session first, then DB if session expired.
     *
     * @return ShiftLog|null
     */
    function activeShift()
    {
        $shiftLogId = session('active_shift_log_id');

        if ($shiftLogId) {
            return ShiftLogs::find($shiftLogId);
        }

        if (Auth::check()) {
            // fallback: find active shift from DB
            return ShiftLogs::where('user_id', Auth::id())
                ->whereNull('clock_out')
                ->latest()
                ->first();
        }

        return null;
    }
}

if (!function_exists('isShiftActive')) {
    /**
     * Check if the user has an active shift.
     *
     * @return bool
     */
    function isShiftActive(): bool
    {
        return activeShift() !== null;
    }
}

if (!function_exists('shiftStatus')) {
    /**
     * Get the status of the current active shift.
     *
     * @return string|null
     */
    function shiftStatus(): ?string
    {
        $shift = activeShift();
        return $shift ? $shift->status : null;
    }
}

if (!function_exists('shiftClockInTime')) {
    /**
     * Get the clock-in time of the current active shift.
     *
     * @return \Illuminate\Support\Carbon|null
     */
    function shiftClockInTime()
    {
        $shift = activeShift();
        return $shift ? $shift->clock_in : null;
    }
}

if (!function_exists('shiftClockOutTime')) {
    /**
     * Get the clock-out time of the current shift (if any).
     *
     * @return \Illuminate\Support\Carbon|null
     */
    function shiftClockOutTime()
    {
        $shift = activeShift();
        return $shift ? $shift->clock_out : null;
    }
}
if (!function_exists('currentShift')) {
    /**
     * Get the Shift model of the current active shift.
     *
     * @return \App\Models\Shift|null
     */
    function currentShift()
    {
        return activeShift()?->shift; // null-safe
    }
}
