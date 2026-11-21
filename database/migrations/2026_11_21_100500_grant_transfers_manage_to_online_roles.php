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

        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forever('permission_catalog_online_transfers_v1', true);
    }

    public function down(): void
    {
        Cache::forget('permission_catalog_online_transfers_v1');
    }
};
