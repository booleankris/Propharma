<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GeneralManagerRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'General Manager', 'guard_name' => 'web']);

        foreach (['users', 'roles'] as $module) {
            foreach (['list', 'create', 'edit', 'delete'] as $action) {
                $permission = Permission::firstOrCreate([
                    'name' => "{$module}-{$action}",
                    'guard_name' => 'web',
                ]);
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
