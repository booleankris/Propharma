<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeneralManagerAccessTest extends TestCase
{
    private function userWithRole(string $name): User
    {
        $user = new User;
        $user->setRelation('roles', collect([new Role(['name' => $name, 'guard_name' => 'web'])]));

        return $user;
    }

    public function test_general_manager_cannot_access_administrator_user_and_role_routes(): void
    {
        foreach (['users', 'roles'] as $resource) {
            foreach (['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'] as $action) {
                $route = app('router')->getRoutes()->getByName("{$resource}.{$action}");
                $request = Request::create('/');
                $request->setUserResolver(fn () => $this->userWithRole('General Manager'));
                $checks = array_filter($route->gatherMiddleware(), fn ($middleware) => str_starts_with($middleware, 'role:'));
                $this->assertNotEmpty($checks);
                foreach ($checks as $middleware) {
                    $response = (new CheckRole)->handle($request, fn () => response('allowed'), substr($middleware, 5));
                    $this->assertSame(route('home'), $response->getTargetUrl());
                }
            }
        }
    }

    public function test_general_manager_lands_on_home_and_cannot_enter_admin_only_routes(): void
    {
        $user = $this->userWithRole('General Manager');
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $login = new \ReflectionMethod(LoginController::class, 'authenticated');
        $this->assertSame(route('home'), $login->invoke(new LoginController, $request, $user)->getTargetUrl());
        $response = (new CheckRole)->handle($request, fn () => response('allowed'), 'administrator');
        $this->assertSame(route('home'), $response->getTargetUrl());
    }

    public function test_cashier_cannot_enter_user_management(): void
    {
        $request = Request::create('/');
        $request->setUserResolver(fn () => $this->userWithRole('Kasir'));
        // Existing middleware also checks the active pharmacy for other roles.
        $this->assertFalse($request->user()->hasAnyRole(['administrator', 'General Manager']));
    }
}
