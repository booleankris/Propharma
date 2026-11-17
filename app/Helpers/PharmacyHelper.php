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

        // Jika user memiliki akses pindah cabang dan memilih cabang spesifik di session
        if (hasFeatureAccess('branch.switch_and_filter', ['HO', 'administrator', 'General Manager', 'Koordinator', 'koordinator'], $user) && session()->has('ho_pharmacy_id')) {
            return (int) session('ho_pharmacy_id');
        }

        // Jika staf / akun memiliki role Gudang PMI -> selalu ke Gudang PMI (9)
        if ($user->hasRole('Gudang PMI')) {
            return 9;
        }

        // Jika user HO (tanpa role Gudang PMI dan tanpa session cabang)
        if ($user->hasRole('HO')) {
            if ($user->pharmacy_id && (int) $user->pharmacy_id !== 6 && (int) $user->pharmacy_id !== 1) {
                return (int) $user->pharmacy_id;
            }
            return 1;
        }

        // Untuk cabang lain atau kasir/pelayanan PMI
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

        // Jika user memiliki akses pindah cabang dan memilih cabang spesifik di session
        if (hasFeatureAccess('branch.switch_and_filter', ['HO', 'administrator', 'General Manager', 'Koordinator', 'koordinator'], $user) && session()->has('ho_pharmacy_id')) {
            $hoSelected = (int) session('ho_pharmacy_id');
            if ($hoSelected === 1 || $hoSelected === 9) {
                return 9; // Gudang PMI
            }
            return $hoSelected;
        }

        $userPharmacyId = (int) ($user->pharmacy_id ?? 1);

        // Jika akun berada di PMI (1 atau 9) dan memiliki role Gudang PMI, HO, administrator, manager, Koordinator
        if (($userPharmacyId === 1 || $userPharmacyId === 9) && ($user->hasRole('Gudang PMI') || $user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('Manager') || $user->hasRole('manager') || $user->hasRole('Koordinator'))) {
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
     * Allowed only for: SAHABAT PMI (ID 1), GUDANG PMI (ID 9), and HO / Administrator / Manager (when not on a branch).
     * Apotek cabang TIDAK memiliki gudang.
     */
    function canAccessWarehouseStock($pharmacyId = null): bool
    {
        $id = $pharmacyId !== null ? (int) $pharmacyId : getActivePharmacyId();

        // Apotek cabang tidak memiliki gudang fisik
        if (isBranchPharmacy($id)) {
            return false;
        }

        $user = auth()->user();
        if ($user && hasFeatureAccess('supplies.warehouse_stock', ['HO', 'administrator', 'General Manager', 'Manager', 'manager', 'Koordinator', 'koordinator', 'Gudang PMI'], $user)) {
            return true;
        }

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

        if ($user && !hasFeatureAccess(['orders.manage', 'receiving.manage'], ['General Manager', 'Kasir', 'Gudang PMI', 'HO', 'administrator', 'manager', 'Manager', 'Koordinator', 'koordinator', 'UMKM', 'operator', 'Operator', 'Finance'], $user)) {
            return false;
        }

        if ($user && ($user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('General Manager') || $user->hasRole('Manager') || $user->hasRole('manager') || $user->hasRole('Koordinator') || $user->hasRole('koordinator') || $user->hasRole('Gudang PMI'))) {
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

if (!function_exists('getRestrictedOnlineRole')) {
    /**
     * Get the specific online role if the user should be restricted to their online role's sales data.
     * Roles like administrator, General Manager, HO, Manager, Koordinator are not restricted (returns null).
     */
    function getRestrictedOnlineRole($user = null): ?string
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return null;
        }

        // Jika user memiliki role manajerial / supervisor / HO, tidak dibatasi
        if ($user->hasAnyRole(['administrator', 'General Manager', 'HO', 'Manager', 'manager', 'Koordinator'])) {
            return null;
        }

        if ($user->hasRole('Online Shopee')) {
            return 'Online Shopee';
        }
        if ($user->hasRole('Online Grab')) {
            return 'Online Grab';
        }
        if ($user->hasRole('Digital')) {
            return 'Digital';
        }
        if ($user->hasRole('Online')) {
            return 'Online';
        }

        return null;
    }
}

if (!function_exists('getRestrictedOnlineChannelKey')) {
    /**
     * Get channel key ('shopee', 'grab', 'digital', 'online') if the user is restricted to an online role.
     */
    function getRestrictedOnlineChannelKey($user = null): ?string
    {
        $role = getRestrictedOnlineRole($user);
        if (!$role) {
            return null;
        }

        return match ($role) {
            'Online Shopee' => 'shopee',
            'Online Grab'   => 'grab',
            'Digital'       => 'digital',
            'Online'        => 'online',
            default         => null,
        };
    }
}

if (!function_exists('hasFeatureAccess')) {
    /**
     * Periksa apakah user memiliki hak akses ke suatu fitur berdasarkan PermissionCatalog
     * dengan fallback aman ke daftar role bawaan.
     *
     * Begitu permission terdaftar di DB (otomatis disinkronisasi 1:1 oleh PermissionCatalog::ensureSynced()),
     * perubahan centang / hapus centang di Manager Access -> Manajemen Role langsung mengubah tampilan
     * menu di sidebar, tombol di halaman, maupun akses di controller/route secara real-time.
     */
    function hasFeatureAccess($permissions, $fallbackRoles = [], $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return true;
        }

        $permList = is_array($permissions) ? $permissions : [$permissions];
        $roleList = is_array($fallbackRoles)
            ? $fallbackRoles
            : ($fallbackRoles ? explode('|', (string) $fallbackRoles) : []);

        try {
            static $catalogSynced = false;
            static $knownPermNames = null;

            if (!$catalogSynced) {
                \App\Support\PermissionCatalog::ensureSynced();
                $catalogSynced = true;
            }

            if ($knownPermNames === null) {
                $knownPermNames = \Spatie\Permission\Models\Permission::where('guard_name', 'web')
                    ->pluck('name')
                    ->flip()
                    ->all();
            }

            $existingTargetPerms = array_values(array_filter($permList, fn ($p) => isset($knownPermNames[$p])));
            if (!empty($existingTargetPerms)) {
                return $user->hasAnyPermission($existingTargetPerms);
            }
        } catch (\Throwable $e) {
            // Fallback ke pengecekan role jika tabel permission belum siap
        }

        return !empty($roleList) ? $user->hasAnyRole($roleList) : false;
    }
}


