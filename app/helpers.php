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

if (!function_exists('terbilang')) {
    /**
     * Convert a number into its Indonesian word representation.
     * e.g. terbilang(150) => "seratus lima puluh"
     *
     * @param  int|float $number
     * @return string
     */
    function terbilang(int|float $number): string
    {
        $number = (int) abs($number);

        if ($number === 0) {
            return 'nol';
        }

        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima',
                  'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh',
                  'sebelas'];

        if ($number < 12) {
            return $words[$number];
        }

        if ($number < 20) {
            return terbilang($number - 10) . ' belas';
        }

        if ($number < 100) {
            $quotient  = intdiv($number, 10);
            $remainder = $number % 10;
            $result    = $words[$quotient] . ' puluh';
            if ($remainder > 0) {
                $result .= ' ' . terbilang($remainder);
            }
            return $result;
        }

        if ($number < 200) {
            $remainder = $number - 100;
            $result    = 'seratus';
            if ($remainder > 0) {
                $result .= ' ' . terbilang($remainder);
            }
            return $result;
        }

        if ($number < 1000) {
            $quotient  = intdiv($number, 100);
            $remainder = $number % 100;
            $result    = $words[$quotient] . ' ratus';
            if ($remainder > 0) {
                $result .= ' ' . terbilang($remainder);
            }
            return $result;
        }

        if ($number < 2000) {
            $remainder = $number - 1000;
            $result    = 'seribu';
            if ($remainder > 0) {
                $result .= ' ' . terbilang($remainder);
            }
            return $result;
        }

        if ($number < 1_000_000) {
            $quotient  = intdiv($number, 1000);
            $remainder = $number % 1000;
            $result    = terbilang($quotient) . ' ribu';
            if ($remainder > 0) {
                $result .= ' ' . terbilang($remainder);
            }
            return $result;
        }

        if ($number < 1_000_000_000) {
            $quotient  = intdiv($number, 1_000_000);
            $remainder = $number % 1_000_000;
            $result    = terbilang($quotient) . ' juta';
            if ($remainder > 0) {
                $result .= ' ' . terbilang($remainder);
            }
            return $result;
        }

        $quotient  = intdiv($number, 1_000_000_000);
        $remainder = $number % 1_000_000_000;
        $result    = terbilang($quotient) . ' miliar';
        if ($remainder > 0) {
            $result .= ' ' . terbilang($remainder);
        }
        return $result;
    }
}
