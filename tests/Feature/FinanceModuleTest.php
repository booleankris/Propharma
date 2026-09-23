<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceModuleTest extends TestCase
{
    private function userWithRole(string $name): User
    {
        $user = new User;
        $user->name = 'Test ' . $name;
        $user->email = strtolower($name) . '@test.com';
        $user->setRelation('roles', collect([new Role(['name' => $name, 'guard_name' => 'web'])]));

        return $user;
    }

    /**
     * Test guest cannot access finance module.
     */
    public function test_guest_is_redirected_from_finance(): void
    {
        $response = $this->get('/finance');
        $response->assertRedirect('/login');
    }

    /**
     * Test role Kasir is blocked by role middleware on /finance.
     */
    public function test_kasir_role_blocked_from_finance(): void
    {
        $user = $this->userWithRole('Kasir');
        $request = Request::create('/finance');
        $request->setUserResolver(fn () => $user);

        $response = (new CheckRole)->handle($request, fn () => response('allowed'), 'Finance|General Manager|administrator');
        $this->assertSame(route('home'), $response->getTargetUrl());
    }

    /**
     * Test role Finance is allowed by role middleware.
     */
    public function test_finance_role_allowed_for_finance(): void
    {
        $user = $this->userWithRole('Finance');
        $request = Request::create('/finance');
        $request->setUserResolver(fn () => $user);

        $response = (new CheckRole)->handle($request, fn () => response('allowed'), 'Finance|General Manager|administrator');
        $this->assertSame('allowed', $response->getContent());
    }

    /**
     * Test role General Manager is allowed by role middleware.
     */
    public function test_general_manager_role_allowed_for_finance(): void
    {
        $user = $this->userWithRole('General Manager');
        $request = Request::create('/finance');
        $request->setUserResolver(fn () => $user);

        $response = (new CheckRole)->handle($request, fn () => response('allowed'), 'Finance|General Manager|administrator');
        $this->assertSame('allowed', $response->getContent());
    }
}
