<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $activePharmacyId = $user ? getActivePharmacyId() : 0;
        $activePharmacy = $activePharmacyId ? \App\Models\Pharmacies::find($activePharmacyId) : null;
        if (!$activePharmacy && in_array((int) $activePharmacyId, [1, 9, 6])) {
            $activePharmacy = \App\Models\Pharmacies::find(1);
        }
        $isHoOrAdmin = $user && ($user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('General Manager'));
        $branches = $isHoOrAdmin ? \App\Models\Pharmacies::whereIn('id', [1, 9, 2, 3, 4, 5])
            ->orderByRaw('FIELD(id, 1, 9, 2, 3, 4, 5)')
            ->get(['id', 'name']) : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email ?? null,
                    'username' => $user->username,
                    'role' => $user->roles?->pluck('name')->first() ?? 'Staff',
                    'pharmacy_id' => $user->pharmacy_id,
                ] : null,
            ],
            'branchContext' => [
                'activePharmacy' => $activePharmacy ? [
                    'id' => $activePharmacy->id,
                    'name' => $activePharmacy->name,
                ] : [
                    'id' => $activePharmacyId,
                    'name' => 'Apotek Cabang',
                ],
                'branches' => $branches,
                'canSwitchBranch' => $isHoOrAdmin,
            ],
        ];
    }
}
