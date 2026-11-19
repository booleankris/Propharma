<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
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

        PermissionCatalog::ensureSynced();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name'       => 'supplies.revision',
            'guard_name' => 'web',
        ]);

        $allowedLower = ['general manager', 'koordinator', 'administrator'];
        $roles = Role::where('guard_name', 'web')->get();

        foreach ($roles as $role) {
            $isAllowed = in_array(strtolower((string) $role->name), $allowedLower, true);
            if ($isAllowed && !$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            } elseif (!$isAllowed && $role->hasPermissionTo($permission)) {
                $role->revokePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Tidak perlu mengembalikan permission ke role non-manajemen saat rollback
    }
};
