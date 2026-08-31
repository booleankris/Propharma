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
        if ($user->hasRole('HO')) {
            if (session()->has('ho_pharmacy_id')) {
                return (int) session('ho_pharmacy_id');
            }
            // Jika user punya pharmacy_id sendiri (misal user Gudang PMI ID 9), jadikan default
            if ($user->pharmacy_id && (int) $user->pharmacy_id !== 6) {
                return (int) $user->pharmacy_id;
            }
            // Default ke cabang pertama (SAHABAT PMI = 1) jika user murni HO dan belum memilih
            return 1;
        }

        return (int) $user->pharmacy_id;
    }
}

if (!function_exists('getWarehousePharmacyId')) {
    /**
     * Get the Pharmacy ID for Central Warehouse (Gudang).
     */
    function getWarehousePharmacyId(): int
    {
        return 9; // GUDANG PMI
    }
}

if (!function_exists('isWarehousePharmacy')) {
    /**
     * Check if the specified pharmacy (or active pharmacy) is the Central Warehouse,
     * or if the current user has the 'Gudang PMI' role.
     */
    function isWarehousePharmacy($pharmacyId = null): bool
    {
        $id = $pharmacyId !== null ? (int) $pharmacyId : getActivePharmacyId();
        if ($id === getWarehousePharmacyId()) {
            return true;
        }

        $user = auth()->user();
        if ($user && $user->hasRole('Gudang PMI') && $id === getWarehousePharmacyId()) {
            return true;
        }

        return false;
    }
}

if (!function_exists('isPmiPharmacy')) {
    /**
     * Check if the specified pharmacy (or active pharmacy) is SAHABAT PMI (ID: 1).
     */
    function isPmiPharmacy($pharmacyId = null): bool
    {
        $id = $pharmacyId !== null ? (int) $pharmacyId : getActivePharmacyId();
        return $id === 1;
    }
}

if (!function_exists('canAccessWarehouseStock')) {
    /**
     * Check if current user / pharmacy can view/manage warehouse stock.
     * Allowed only for: SAHABAT PMI (ID 1), GUDANG PMI (ID 9), and HO / Administrator / Manager.
     */
    function canAccessWarehouseStock($pharmacyId = null): bool
    {
        $user = auth()->user();
        if ($user && ($user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('Manager') || $user->hasRole('Gudang PMI'))) {
            return true;
        }

        $id = $pharmacyId !== null ? (int) $pharmacyId : getActivePharmacyId();
        return $id === 1 || $id === getWarehousePharmacyId();
    }
}

if (!function_exists('isBranchPharmacy')) {
    /**
     * Check if current pharmacy is an external branch pharmacy (not Gudang PMI and not SAHABAT PMI).
     */
    function isBranchPharmacy($pharmacyId = null): bool
    {
        $id = $pharmacyId !== null ? (int) $pharmacyId : getActivePharmacyId();
        return $id !== getWarehousePharmacyId() && $id !== 1;
    }
}

if (!function_exists('canAccessPurchasing')) {
    /**
     * Check if current pharmacy / user can access purchasing (SP & Receiving).
     * Allowed for: Gudang PMI, Branch Pharmacies, and HO / Administrator.
     * Denied for: SAHABAT PMI Retail Cashier (ID 1) and Online Cashier roles.
     */
    function canAccessPurchasing($pharmacyId = null): bool
    {
        $user = auth()->user();
        if ($user && isOnlineRole($user)) {
            return false;
        }

        if ($user && ($user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('Manager') || $user->hasRole('Gudang PMI'))) {
            return true;
        }

        $id = $pharmacyId !== null ? (int) $pharmacyId : getActivePharmacyId();
        return $id === getWarehousePharmacyId() || isBranchPharmacy($id);
    }
}

if (!function_exists('isOnlineRole')) {
    /**
     * Check if the current user has an Online cashier role.
     */
    function isOnlineRole($user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) return false;
        return $user->hasAnyRole(['Online', 'Online Grab', 'Online Shopee']);
    }
}

if (!function_exists('getOnlineChannelName')) {
    /**
     * Get human-friendly channel title for online user.
     */
    function getOnlineChannelName($user = null): string
    {
        $user = $user ?? auth()->user();
        if (!$user) return 'Online';
        if ($user->hasRole('Online Shopee')) return 'Online Shopee';
        if ($user->hasRole('Online Grab')) return 'Online Grab';
        if ($user->hasRole('Online')) return 'Online (WA)';
        return 'Online';
    }
}


