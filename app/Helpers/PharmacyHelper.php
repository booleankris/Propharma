<?php

if (!function_exists('getActivePharmacyId')) {
    /**
     * Get the active pharmacy ID for the current user.
     * - If user is HO and has selected a pharmacy in session -> return that selected pharmacy ID.
     * - If user only has 'Gudang PMI' role (without selecting branch) -> return 9 (Gudang PMI).
     * - Otherwise return user's own pharmacy_id (defaults to 1 - SAHABAT PMI).
     */
    function getActivePharmacyId(): int
    {
        $user = auth()->user();
        if (!$user) {
            return 0;
        }

        // Jika user HO dan memilih cabang di session
        if ($user->hasRole('HO')) {
            if (session()->has('ho_pharmacy_id')) {
                return (int) session('ho_pharmacy_id');
            }
            if ($user->pharmacy_id && (int) $user->pharmacy_id !== 6 && (int) $user->pharmacy_id !== 1) {
                return (int) $user->pharmacy_id;
            }
            return 1;
        }

        // Jika staf khusus Gudang PMI
        if ($user->hasRole('Gudang PMI')) {
            return 9;
        }

        return (int) ($user->pharmacy_id ?? 1);
    }
}

if (!function_exists('getWarehousePharmacyId')) {
    /**
     * Get the Pharmacy ID for Central Warehouse (Gudang).
     */
    function getWarehousePharmacyId(): int
    {
        return 9;  // GUDANG PMI
    }
}

if (!function_exists('getPurchasingPharmacyId')) {
    /**
     * Get the target pharmacy ID specifically for Purchasing & Receiving (Pemesanan & Pembelian).
     * - If user is at PMI (pharmacy_id = 1 or 9) with role 'Gudang PMI', 'HO', 'administrator', 'Manager' -> returns 9 (Gudang PMI).
     * - If user is at Branch (pharmacy_id = 2, 3, 4, 5) -> returns that branch pharmacy_id.
     * - If user is HO and switched to a specific branch via session -> returns that branch ID.
     */
    function getPurchasingPharmacyId(): int
    {
        $user = auth()->user();
        if (!$user) {
            return 0;
        }

        // Jika user HO dan memilih cabang spesifik di session
        if ($user->hasRole('HO') && session()->has('ho_pharmacy_id')) {
            $hoSelected = (int) session('ho_pharmacy_id');
            if ($hoSelected === 1 || $hoSelected === 9) {
                return 9; // Gudang PMI
            }
            return $hoSelected;
        }

        $userPharmacyId = (int) ($user->pharmacy_id ?? 1);

        // Jika akun berada di PMI (1 atau 9) dan memiliki role Gudang PMI, HO, administrator, manager
        if (($userPharmacyId === 1 || $userPharmacyId === 9) && ($user->hasRole('Gudang PMI') || $user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('Manager'))) {
            return 9; // Selalu ke Gudang PMI
        }

        // Untuk cabang (ID != 1 dan ID != 9)
        if ($userPharmacyId !== 1 && $userPharmacyId !== 9) {
            return $userPharmacyId;
        }

        return 9;
    }
}

if (!function_exists('isWarehousePharmacy')) {
    /**
     * Check if the specified pharmacy (or active pharmacy) is the Central Warehouse (ID 9).
     */
    function isWarehousePharmacy($pharmacyId = null): bool
    {
        $id = $pharmacyId !== null ? (int) $pharmacyId : getActivePharmacyId();
        return $id === getWarehousePharmacyId();
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
        if (!$user)
            return false;
        return $user->hasAnyRole(['Online', 'Online Grab', 'Online Shopee', 'Digital']);
    }
}

if (!function_exists('getOnlineChannelName')) {
    /**
     * Get human-friendly channel title for online user.
     */
    function getOnlineChannelName($user = null): string
    {
        $user = $user ?? auth()->user();
        if (!$user)
            return 'Online';
        if ($user->hasRole('Digital'))
            return 'Aplikasi Digital';
        if ($user->hasRole('Online Shopee'))
            return 'Online Shopee';
        if ($user->hasRole('Online Grab'))
            return 'Online Grab';
        if ($user->hasRole('Online'))
            return 'Online (WA)';
        return 'Online';
    }
}
