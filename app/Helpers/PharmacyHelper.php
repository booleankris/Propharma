<?php

if (!function_exists('getActivePharmacyId')) {
    /**
     * Get the active pharmacy ID for the current user.
     * If user is HO and has selected a pharmacy, return that pharmacy ID.
     * Otherwise return the user's own pharmacy_id.
     */
    function getActivePharmacyId(): int
    {
        $user = auth()->user();
        if (!$user) {
            return 0;
        }

        // Jika user HO dan ada session pharmacy terpilih
        if ($user->hasRole('HO') && session()->has('ho_pharmacy_id')) {
            return (int) session('ho_pharmacy_id');
        }

        return (int) $user->pharmacy_id;
    }
}
