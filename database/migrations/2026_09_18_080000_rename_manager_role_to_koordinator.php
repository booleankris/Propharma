<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roleManager = Role::where('name', 'manager')->where('guard_name', 'web')->first();
        $roleKoordinator = Role::where('name', 'Koordinator')->where('guard_name', 'web')->first();

        if (!$roleKoordinator) {
            if ($roleManager) {
                $roleManager->update(['name' => 'Koordinator']);
            } else {
                Role::create(['name' => 'Koordinator', 'guard_name' => 'web']);
            }
        } elseif ($roleManager) {
            foreach ($roleManager->users as $user) {
                $user->assignRole($roleKoordinator);
                $user->removeRole($roleManager);
            }
            $roleManager->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleKoordinator = Role::where('name', 'Koordinator')->where('guard_name', 'web')->first();
        if ($roleKoordinator) {
            $roleKoordinator->update(['name' => 'manager']);
        }
    }
};
