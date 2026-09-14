<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeneralManagerHomeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        app('db')->purge('sqlite');
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username');
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->boolean('is_fixed')->default(false);
            $table->timestamps();
        });
        Schema::create('shift_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('clock_out')->nullable();
            $table->timestamps();
        });
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        (require database_path('migrations/2024_01_30_060118_create_permission_tables.php'))->up();
        foreach (['General Manager', 'administrator', 'Kasir', 'HO'] as $name) {
            Role::create(['name' => $name, 'guard_name' => 'web']);
        }
    }

    private function makeUser(string $name, string $role): User
    {
        $user = User::create(['name' => $name, 'username' => $name]);
        $user->assignRole($role);

        return $user;
    }

    public function test_home_renders_separate_management_page_and_search(): void
    {
        $manager = $this->makeUser('Manager Satu', 'General Manager');
        $this->makeUser('Pengguna Target', 'Kasir');
        $this->actingAs($manager)->get('/home?search=Target')->assertOk()
            ->assertSee('Manajemen Role Pengguna')->assertSee('Pengguna Target')
            ->assertSee('Simpan Role')->assertDontSee('Manage Apotek');
    }

    public function test_manager_can_save_multiple_roles_without_changing_user_profile(): void
    {
        $manager = $this->makeUser('Manager Satu', 'General Manager');
        $target = $this->makeUser('Target', 'Kasir');
        $this->actingAs($manager)->patch(route('general-manager.users.roles', $target), [
            'roles' => ['Kasir', 'HO'], 'name' => 'Changed',
        ])->assertRedirect(route('home'))->assertSessionHas('success');
        $this->assertEqualsCanonicalizing(['Kasir', 'HO'], $target->fresh()->getRoleNames()->all());
        $this->assertSame('Target', $target->fresh()->name);
    }

    public function test_manager_cannot_assign_admin_unknown_or_empty_roles(): void
    {
        $manager = $this->makeUser('Manager Satu', 'General Manager');
        $target = $this->makeUser('Target', 'Kasir');
        foreach ([['administrator'], ['Unknown'], []] as $roles) {
            $this->actingAs($manager)->patch(route('general-manager.users.roles', $target), ['roles' => $roles])
                ->assertSessionHasErrors();
            $this->assertSame(['Kasir'], $target->fresh()->getRoleNames()->all());
        }
    }

    public function test_admin_fixed_and_own_accounts_cannot_be_changed(): void
    {
        $manager = $this->makeUser('Manager Satu', 'General Manager');
        $admin = $this->makeUser('Admin', 'administrator');
        $fixed = $this->makeUser('Fixed', 'Kasir');
        $fixed->update(['is_fixed' => true]);
        foreach ([$manager, $admin, $fixed] as $target) {
            $this->actingAs($manager)->patch(route('general-manager.users.roles', $target), ['roles' => ['HO']])
                ->assertForbidden();
        }
    }
}
