<?php

namespace Tests\Feature;

use App\Http\Controllers\StaffStatsController;
use App\Http\Middleware\CheckRole;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class KoordinatorAccessTest extends TestCase
{
    private function userWithRoles(array $roles): User
    {
        $user = new User;
        $roleCollection = collect($roles)->map(fn ($r) => new Role(['name' => $r, 'guard_name' => 'web']));
        $user->setRelation('roles', $roleCollection);

        return $user;
    }

    public function test_master_data_routes_exclude_koordinator_and_manager(): void
    {
        $routes = [
            'medicines.index',
            'creditors.index',
            'debtors.index',
            'factories.index',
            'categories.index',
            'parameters.index',
            'locations.index',
        ];

        $koordinator = $this->userWithRoles(['Koordinator']);

        foreach ($routes as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "Route {$routeName} must exist.");

            $roleMiddlewares = array_values(array_filter(
                $route->gatherMiddleware(),
                fn ($m) => str_starts_with($m, 'role:')
            ));

            // The last role middleware applied is the specific Master Data group middleware
            $masterDataMiddleware = end($roleMiddlewares);
            $allowedRoles = explode('|', substr($masterDataMiddleware, 5));

            $this->assertNotContains('Koordinator', $allowedRoles, "Koordinator should not have access to {$routeName}");
            $this->assertNotContains('manager', $allowedRoles, "manager should not have access to {$routeName}");
            $this->assertNotContains('Manager', $allowedRoles, "Manager should not have access to {$routeName}");

            $this->assertContains('HO', $allowedRoles, "HO should have access to {$routeName}");
            $this->assertContains('administrator', $allowedRoles, "administrator should have access to {$routeName}");

            // Verify CheckRole rejects Koordinator
            $request = Request::create('/');
            $request->setUserResolver(fn () => $koordinator);

            try {
                $response = (new CheckRole)->handle($request, fn () => response('allowed'), substr($masterDataMiddleware, 5));
                $this->assertNotSame('allowed', $response->getContent(), "Koordinator must be blocked from {$routeName}");
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode(), "Koordinator must receive 403 Forbidden from {$routeName}");
            }
        }
    }

    public function test_staff_stats_controller_blocks_koordinator_and_manager(): void
    {
        $controller = new StaffStatsController();

        $controllerMiddleware = $controller->getMiddleware();
        $this->assertNotEmpty($controllerMiddleware, 'StaffStatsController should have protection middleware.');

        foreach (['Koordinator', 'manager', 'Manager', 'administrator', 'Kasir'] as $role) {
            $user = $this->userWithRoles([$role]);
            $this->actingAs($user);

            $request = Request::create('/staff-stats');
            $blocked = false;

            try {
                $closure = $controllerMiddleware[0]['middleware'];
                $closure($request, fn () => response('ok'));
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode(), "Role {$role} should receive 403 Forbidden");
                $blocked = true;
            }

            $this->assertTrue($blocked, "Role {$role} should have been blocked from StaffStatsController");
        }
    }

    public function test_staff_stats_controller_allows_general_manager_only(): void
    {
        $controller = new StaffStatsController();
        $controllerMiddleware = $controller->getMiddleware();

        $gm = $this->userWithRoles(['General Manager']);
        $this->actingAs($gm);

        $request = Request::create('/staff-stats');
        $closure = $controllerMiddleware[0]['middleware'];
        $response = $closure($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_koordinator_can_access_reported_medicines_and_special_medicines_export(): void
    {
        $koordinator = $this->userWithRoles(['Koordinator']);

        // Check reported-medicines route middleware allows Koordinator
        $route = app('router')->getRoutes()->getByName('reported-medicines.index');
        $this->assertNotNull($route);
        $roleMiddlewares = array_values(array_filter(
            $route->gatherMiddleware(),
            fn ($m) => str_starts_with($m, 'role:')
        ));
        $this->assertNotEmpty($roleMiddlewares);
        $allowedRoles = explode('|', substr(end($roleMiddlewares), 5));
        $this->assertContains('Koordinator', $allowedRoles);
        $this->assertContains('General Manager', $allowedRoles);

        // Check special medicines export route middleware allows Koordinator
        $exportRoute = app('router')->getRoutes()->getByName('reports.export.specialMedicines');
        $this->assertNotNull($exportRoute);
        $exportRoleMiddlewares = array_values(array_filter(
            $exportRoute->gatherMiddleware(),
            fn ($m) => str_starts_with($m, 'role:')
        ));
        $this->assertNotEmpty($exportRoleMiddlewares);
        $exportAllowedRoles = explode('|', substr(end($exportRoleMiddlewares), 5));
        $this->assertContains('Koordinator', $exportAllowedRoles);
        $this->assertContains('General Manager', $exportAllowedRoles);
    }
}

