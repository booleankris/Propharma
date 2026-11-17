<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionBaselineSeeder extends Seeder
{
    /**
     * Seed seluruh permission pada PermissionCatalog dan berikan ke role existing
     * secara additive (givePermissionTo) sesuai mapping 1:1 sistem berjalan.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $existingRoles = Role::where('guard_name', 'web')->get();

        foreach (PermissionCatalog::allDefinitions() as $permName => $meta) {
            $permission = Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);

            $targetRolesLower = array_map('strtolower', $meta['roles']);
            foreach ($existingRoles as $role) {
                if (in_array(strtolower($role->name), $targetRolesLower, true)) {
                    if (!$role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
