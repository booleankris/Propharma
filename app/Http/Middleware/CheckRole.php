<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, $role)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->hasRole('administrator')) {
            return $next($request);
        }

        $roles = explode('|', $role);

        // HO and all Online roles can access Kasir routes
        if (in_array('Kasir', $roles, true)) {
            if (!in_array('HO', $roles, true)) {
                $roles[] = 'HO';
            }
            foreach (['Online', 'Online Grab', 'Online Shopee', 'Digital'] as $onlineRole) {
                if (!in_array($onlineRole, $roles, true)) {
                    $roles[] = $onlineRole;
                }
            }
        }

        if ($this->isAuthorized($request, $user, $roles)) {
            return $next($request);
        }

        // Jika user non-administrator membuka /dashboard (misal dari klik menu Dashboard di sidebar),
        // arahkan dengan aman ke /home tanpa menyebabkan redirect loop.
        if ($request->is('dashboard')) {
            return redirect()->route('home');
        }

        if (!$request->is('home') && (
            $user->hasAnyRole([
                'General Manager',
                'Manager',
                'manager',
                'Koordinator',
                'koordinator',
                'Kasir',
                'HO',
                'Finance',
                'operator',
                'Operator',
                'UMKM',
                'Gudang PMI',
            ]) || isOnlineRole($user) || isWarehousePharmacy() || $user->roles()->exists()
        )) {
            return redirect()->route('home');
        }

        return abort(403, 'Unauthorized role.');
    }

    private function isAuthorized(Request $request, $user, array $roles): bool
    {
        // Grup utama /home & operasional dasar: izinkan jika user punya salah satu role atau permission aktif
        if (in_array('Kasir', $roles, true) && in_array('Finance', $roles, true)) {
            return $user->hasAnyRole($roles) || $user->roles()->exists() || $user->getAllPermissions()->isNotEmpty();
        }

        $mappedPermissions = $this->resolveMappedPermissions($request, $roles);
        if ($mappedPermissions !== null) {
            return hasFeatureAccess($mappedPermissions, $roles, $user);
        }

        return $user->hasAnyRole($roles);
    }

    /**
     * Petakan grup route ke permission katalog agar centang / hapus centang
     * di Manager Access -> Manajemen Role langsung sinkron antara Sidebar UI & Route Middleware.
     */
    private function resolveMappedPermissions(Request $request, array $roles): ?array
    {
        if ($request->is('general-manager*') || $request->is('users*') || $request->is('roles*') || $request->is('pharmacies*')) {
            return ['manager.access', 'users.manage', 'roles.manage', 'pharmacies.manage'];
        }

        if ($request->is('ho/analytics*')) {
            return ['ho.analytics'];
        }

        if ($request->is('pharmacy/switch')) {
            return ['branch.switch_and_filter', 'ho.analytics'];
        }

        if ($request->is('reported-medicines*') || $request->is('reports/export/special-medicines')) {
            return ['reports.special_medicines'];
        }

        if ($request->is('patients*') || $request->is('master/patient-transactions*')) {
            return ['patients.manage'];
        }

        if ($request->is('finance/return-policies*') || $request->is('finance/purchase-returns*')) {
            return ['finance.return_policy'];
        }

        if ($request->is('finance*')) {
            return ['finance.manage'];
        }

        if ($request->is('staff-stats*')) {
            return ['reports.staff_stats'];
        }

        if (in_array('operator', $roles, true) && in_array('HO', $roles, true)) {
            return ['master.data'];
        }

        return null;
    }
}
