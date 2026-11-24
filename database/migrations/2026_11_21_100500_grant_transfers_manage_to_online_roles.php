<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Ignore file cache permission mismatch between CLI and web user
        }

        $permission = Permission::firstOrCreate([
            'name' => 'transfers.manage',
            'guard_name' => 'web',
        ]);

        $onlineRoles = ['online', 'online grab', 'online shopee', 'digital'];
        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if (in_array(strtolower($role->name), $onlineRoles, true) && !$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        try {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Cache::forever('permission_catalog_online_transfers_v1', true);
        } catch (\Throwable $e) {
            // Ignore file cache permission mismatch between CLI and web user
        }
    }

    public function down(): void
    {
        try {
            Cache::forget('permission_catalog_online_transfers_v1');
        } catch (\Throwable $e) {
            // Ignore file cache permission mismatch
        }
    }
};
