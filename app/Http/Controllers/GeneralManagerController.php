<?php

namespace App\Http\Controllers;

use App\Models\Pharmacies;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GeneralManagerController extends Controller
{
    private function ensureManagerAccess(Request $request): void
    {
        abort_unless(
            $request->user() && hasFeatureAccess(['manager.access', 'users.manage', 'roles.manage', 'pharmacies.manage'], ['General Manager', 'manager', 'Manager', 'administrator'], $request->user()),
            403,
            'Anda tidak memiliki akses ke halaman Manager Access.'
        );
    }

    private function isAdmin(Request $request): bool
    {
        return (bool) ($request->user() && $request->user()->hasRole('administrator'));
    }

    private function canManageUser(Request $request, User $targetUser): bool
    {
        if ($this->isAdmin($request)) {
            return true;
        }

        return !$targetUser->hasRole('administrator') &&
            !$targetUser->is_fixed &&
            $targetUser->id !== $request->user()->id;
    }

    private function isCoreSystemRole(Role $role): bool
    {
        $coreNames = [
            'administrator',
            'general manager',
            'ho',
            'finance',
            'kasir',
            'gudang pmi',
            'koordinator',
            'online',
            'online grab',
            'online shopee',
            'digital',
            'umkm',
            'operator',
            'manager',
        ];

        $roleName = strtolower((string) $role->name);
        $isFixed = (bool) ($role->fixed ?? $role->is_fixed ?? false);

        return $isFixed || in_array($roleName, $coreNames, true);
    }

    private function isProtectedRole(Request $request, Role $role): bool
    {
        $roleName = strtolower((string) $role->name);

        if ($roleName === 'administrator') {
            return !$this->isAdmin($request);
        }

        return $this->isCoreSystemRole($role);
    }

    private function standardPositions(): array
    {
        return [
            'Apoteker',
            'AA',
            'Kasir',
            'Keuangan',
            'Pajak',
            'Cokir',
            'Adm. Gudang',
            'Fakturis',
            'Logistik',
            'Kepala Gudang',
            'Koordinator',
            'General Manager',
            'Manager',
            'HO',
            'Admin',
            'Kurir',
            'Lainnya',
        ];
    }

    public function index(Request $request)
    {
        $this->ensureManagerAccess($request);
        PermissionCatalog::ensureSynced();

        $isAdmin = $this->isAdmin($request);
        $activeTab = $request->query('tab', 'users');
        if (!in_array($activeTab, ['users', 'roles', 'pharmacies'], true)) {
            $activeTab = 'users';
        }

        $search = trim((string) $request->query('search', ''));
        $pharmacyFilter = $request->query('pharmacy_id', '');
        $roleFilter = trim((string) $request->query('role_filter', ''));

        $usersQuery = User::with(['roles', 'pharmacy'])
            ->when(!$isAdmin, function ($query) {
                $query->whereDoesntHave('roles', function ($rq) {
                    $rq->where('name', 'administrator');
                });
            })
            ->when($activeTab === 'users' && $search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('fullname', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%")
                        ->orWhereHas('pharmacy', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($pharmacyFilter !== '', function ($query) use ($pharmacyFilter) {
                $query->where('pharmacy_id', $pharmacyFilter);
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter) {
                $query->whereHas('roles', function ($rq) use ($roleFilter) {
                    $rq->where('name', $roleFilter);
                });
            })
            ->orderBy('name')
            ->orderBy('id');

        $users = $usersQuery->paginate(15)->withQueryString();

        $totalUsersCount = User::when(!$isAdmin, function ($query) {
            $query->whereDoesntHave('roles', function ($rq) {
                $rq->where('name', 'administrator');
            });
        })->count();

        $roles = Role::where('guard_name', 'web')
            ->with('permissions')
            ->withCount('users')
            ->when(!$isAdmin, function ($query) {
                $query->where('name', '!=', 'administrator');
            })
            ->when($activeTab === 'roles' && $search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->get();

        $assignableRoles = Role::where('guard_name', 'web')
            ->when(!$isAdmin, function ($query) {
                $query->where('name', '!=', 'administrator');
            })
            ->orderBy('name')
            ->get();

        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get();
        $permissionGroups = PermissionCatalog::groupedForView($permissions);

        $pharmacies = Pharmacies::when($activeTab === 'pharmacies' && $search !== '', function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('pharmacist', 'like', "%{$search}%");
            });
        })
            ->orderBy('name')
            ->get();

        $allPharmacies = Pharmacies::orderBy('name')->get();
        $pharmacyUserCounts = User::selectRaw('pharmacy_id, COUNT(*) as total')
            ->groupBy('pharmacy_id')
            ->pluck('total', 'pharmacy_id');

        $positions = $this->standardPositions();

        return view('general-manager.home', compact(
            'activeTab',
            'users',
            'totalUsersCount',
            'roles',
            'assignableRoles',
            'permissions',
            'permissionGroups',
            'pharmacies',
            'allPharmacies',
            'pharmacyUserCounts',
            'positions',
            'search',
            'pharmacyFilter',
            'roleFilter',
            'isAdmin'
        ));
    }

    public function storeUser(Request $request)
    {
        $this->ensureManagerAccess($request);
        $isAdmin = $this->isAdmin($request);

        $roleRules = ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')];
        if (!$isAdmin) {
            $roleRules[] = Rule::notIn(['administrator']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'fullname' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:255', Rule::unique('users', 'username')],
            'nik' => ['nullable', 'string', 'max:50'],
            'pharmacy_id' => ['required', 'exists:pharmacies,id'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
            'secret_pin' => ['nullable', 'string', 'min:4', 'max:20'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => $roleRules,
        ], [
            'name.required' => 'Nama pengguna wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.min' => 'Username minimal 3 karakter.',
            'username.unique' => 'Username sudah digunakan oleh akun lain.',
            'pharmacy_id.required' => 'Apotek cabang wajib dipilih.',
            'pharmacy_id.exists' => 'Apotek yang dipilih tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 4 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'secret_pin.min' => 'Secret PIN minimal 4 karakter.',
            'roles.required' => 'Minimal pilih 1 role untuk pengguna.',
            'roles.min' => 'Minimal pilih 1 role untuk pengguna.',
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'fullname' => $data['fullname'] ?? null,
                'username' => $data['username'],
                'nik' => $data['nik'] ?? null,
                'pharmacy_id' => $data['pharmacy_id'],
                'position' => $data['position'] ?? null,
                'department' => $data['department'] ?? null,
                'division' => $data['division'] ?? null,
                'password' => Hash::make($data['password']),
                'secret_pin' => !empty($data['secret_pin']) ? $data['secret_pin'] : $data['password'],
                'is_fixed' => 0,
            ]);

            $user->syncRoles($data['roles']);
        });

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'users'])
            ->with('success', "Pengguna {$data['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, User $user)
    {
        $this->ensureManagerAccess($request);
        $isAdmin = $this->isAdmin($request);

        abort_unless(
            $this->canManageUser($request, $user),
            403,
            'Akun ini hanya dapat diubah oleh administrator.'
        );

        $roleRules = ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')];
        if (!$isAdmin) {
            $roleRules[] = Rule::notIn(['administrator']);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'fullname' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'nik' => ['nullable', 'string', 'max:50'],
            'pharmacy_id' => ['required', 'exists:pharmacies,id'],
            'password' => ['nullable', 'string', 'min:4'],
            'secret_pin' => ['nullable', 'string', 'min:4', 'max:20'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'division' => ['nullable', 'string', 'max:100'],
            'roles' => ['nullable', 'array'],
            'roles.*' => $roleRules,
        ], [
            'name.required' => 'Nama pengguna wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique' => 'Username sudah digunakan oleh akun lain.',
            'pharmacy_id.required' => 'Apotek cabang wajib dipilih.',
            'pharmacy_id.exists' => 'Apotek yang dipilih tidak valid.',
            'password.min' => 'Password minimal 4 karakter.',
            'secret_pin.min' => 'Secret PIN minimal 4 karakter.',
        ]);

        $updateData = [
            'name' => $data['name'],
            'fullname' => $data['fullname'] ?? null,
            'username' => $data['username'],
            'nik' => $data['nik'] ?? null,
            'pharmacy_id' => $data['pharmacy_id'],
            'position' => $data['position'] ?? null,
            'department' => $data['department'] ?? null,
            'division' => $data['division'] ?? null,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            if (empty($data['secret_pin'])) {
                $updateData['secret_pin'] = $data['password'];
            }
        }

        if (!empty($data['secret_pin'])) {
            $updateData['secret_pin'] = $data['secret_pin'];
        }

        DB::transaction(function () use ($user, $updateData, $request, $data) {
            $user->update($updateData);

            if ($request->has('roles') && is_array($data['roles'] ?? null) && count($data['roles']) > 0) {
                $user->syncRoles($data['roles']);
            }
        });

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'users'])
            ->with('success', "Informasi pengguna {$user->name} berhasil diperbarui.");
    }

    public function updateRoles(Request $request, User $user)
    {
        $this->ensureManagerAccess($request);
        $isAdmin = $this->isAdmin($request);

        abort_unless(
            $this->canManageUser($request, $user),
            403,
            'Role akun ini hanya dapat diubah oleh administrator.'
        );

        $roleRules = ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web')];
        if (!$isAdmin) {
            $roleRules[] = Rule::notIn(['administrator']);
        }

        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => $roleRules,
        ], [
            'roles.required' => 'Minimal pilih 1 role untuk pengguna.',
            'roles.min' => 'Minimal pilih 1 role untuk pengguna.',
        ]);

        $user->syncRoles($data['roles']);

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'users'])
            ->with('success', "Role {$user->name} berhasil diperbarui.");
    }

    public function destroyUser(Request $request, User $user)
    {
        $this->ensureManagerAccess($request);

        if ($user->id === $request->user()->id) {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'users'])
                ->with('error', 'Anda tidak dapat menghapus akun yang sedang digunakan login.');
        }

        if ($user->is_fixed || (!$this->isAdmin($request) && $user->hasRole('administrator'))) {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'users'])
                ->with('error', 'Akun bawaan sistem / administrator tidak dapat dihapus.');
        }

        try {
            $name = $user->name;
            $user->delete();

            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'users'])
                ->with('success', "Pengguna {$name} berhasil dihapus.");
        } catch (QueryException $e) {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'users'])
                ->with('error', "Pengguna {$user->name} tidak dapat dihapus karena sudah terikat dengan data transaksi.");
        }
    }

    public function storeRole(Request $request)
    {
        $this->ensureManagerAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', 'web')],
        ], [
            'name.required' => 'Nama role / level wajib diisi.',
            'name.unique' => 'Nama role sudah terdaftar.',
        ]);

        DB::transaction(function () use ($data) {
            $attributes = [
                'name' => trim($data['name']),
                'guard_name' => 'web',
            ];
            if (Schema::hasColumn('roles', 'fixed')) {
                $attributes['fixed'] = 0;
            }

            $role = Role::create($attributes);
            $permissionModels = Permission::whereIn('id', $data['permissions'] ?? [])->where('guard_name', 'web')->get();
            $role->syncPermissions($permissionModels);
        });

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'roles'])
            ->with('success', "Role {$data['name']} berhasil dibuat.");
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->ensureManagerAccess($request);

        if (strtolower((string) $role->name) === 'administrator' && !$this->isAdmin($request)) {
            abort(403, 'Role administrator hanya dapat diubah oleh administrator.');
        }

        $isCoreRole = $this->isCoreSystemRole($role);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', Rule::exists('permissions', 'id')->where('guard_name', 'web')],
        ], [
            'name.required' => 'Nama role / level wajib diisi.',
            'name.unique' => 'Nama role sudah digunakan oleh role lain.',
        ]);

        $beforePermissions = $role->permissions()->pluck('name')->sort()->values()->all();
        $selectedPermissionIds = collect($data['permissions'] ?? [])->map(fn($id) => (int) $id)->all();

        // Anti-lockout: hanya pertahankan akses Manager Access pada role General Manager jika diubah oleh non-administrator
        if (!$this->isAdmin($request) && strtolower((string) $role->name) === 'general manager' && $request->user()->hasRole($role->name)) {
            $lockedPermIds = Permission::where('guard_name', 'web')
                ->whereIn('name', ['manager.access', 'roles.manage'])
                ->pluck('id')
                ->all();
            $selectedPermissionIds = array_values(array_unique(array_merge($selectedPermissionIds, $lockedPermIds)));
        }

        DB::transaction(function () use ($role, $data, $isCoreRole, $selectedPermissionIds) {
            if (!$isCoreRole && strtolower((string) $role->name) !== 'administrator') {
                $role->name = trim($data['name']);
                $role->save();
            }

            $permissionModels = Permission::whereIn('id', $selectedPermissionIds)->where('guard_name', 'web')->get();
            $role->syncPermissions($permissionModels);
        });

        $afterPermissions = $role->permissions()->pluck('name')->sort()->values()->all();
        Log::info('Manager Access: Role permissions updated', [
            'actor_id' => $request->user()->id,
            'actor_name' => $request->user()->name,
            'role_id' => $role->id,
            'role_name' => $role->name,
            'before' => $beforePermissions,
            'after' => $afterPermissions,
        ]);

        // Refresh relasi role/permission user saat ini; jika akses Manager Access role miliknya baru dicabut, arahkan ke /home
        $freshUser = $request->user()->fresh();
        if ($freshUser && !hasFeatureAccess(['manager.access', 'users.manage', 'roles.manage', 'pharmacies.manage'], ['General Manager', 'manager', 'Manager', 'administrator'], $freshUser)) {
            return redirect()
                ->route('home')
                ->with('success', "Role {$role->name} berhasil diperbarui. Akses Manager Access pada role Anda telah dicabut.");
        }

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'roles'])
            ->with('success', "Role {$role->name} berhasil diperbarui.");
    }

    public function destroyRole(Request $request, Role $role)
    {
        $this->ensureManagerAccess($request);

        if ($this->isProtectedRole($request, $role) || strtolower((string) $role->name) === 'administrator') {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'roles'])
                ->with('error', "Role {$role->name} merupakan role inti sistem dan tidak dapat dihapus.");
        }

        if ($request->user()->hasRole($role->name)) {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'roles'])
                ->with('error', 'Anda tidak dapat menghapus role yang sedang digunakan oleh akun Anda sendiri.');
        }

        if ($role->users()->exists()) {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'roles'])
                ->with('error', "Role {$role->name} masih digunakan oleh akun pengguna. Lepaskan role ini dari seluruh user terlebih dahulu.");
        }

        $name = $role->name;
        $role->delete();

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'roles'])
            ->with('success', "Role {$name} berhasil dihapus.");
    }

    public function storePharmacy(Request $request)
    {
        $this->ensureManagerAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'permit' => ['nullable', 'string', 'max:500'],
            'pharmacist_permit' => ['nullable', 'string', 'max:500'],
            'pharmacist' => ['nullable', 'string', 'max:255'],
            'footnote1' => ['nullable', 'string', 'max:255'],
            'footnote2' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
        ], [
            'name.required' => 'Nama apotek wajib diisi.',
            'address.required' => 'Alamat apotek wajib diisi.',
        ]);

        Pharmacies::create([
            'name' => trim($data['name']),
            'address' => trim($data['address']),
            'phone' => $data['phone'] ?? '',
            'city' => $data['city'] ?? '',
            'npwp' => $data['npwp'] ?? '',
            'permit' => $data['permit'] ?? '',
            'pharmacist_permit' => $data['pharmacist_permit'] ?? '',
            'pharmacist' => $data['pharmacist'] ?? '',
            'footnote1' => $data['footnote1'] ?? '',
            'footnote2' => $data['footnote2'] ?? '',
            'status' => (int) $data['status'],
        ]);

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'pharmacies'])
            ->with('success', "Apotek {$data['name']} berhasil ditambahkan.");
    }

    public function updatePharmacy(Request $request, Pharmacies $pharmacy)
    {
        $this->ensureManagerAccess($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'permit' => ['nullable', 'string', 'max:500'],
            'pharmacist_permit' => ['nullable', 'string', 'max:500'],
            'pharmacist' => ['nullable', 'string', 'max:255'],
            'footnote1' => ['nullable', 'string', 'max:255'],
            'footnote2' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
        ], [
            'name.required' => 'Nama apotek wajib diisi.',
            'address.required' => 'Alamat apotek wajib diisi.',
        ]);

        $pharmacy->update([
            'name' => trim($data['name']),
            'address' => trim($data['address']),
            'phone' => $data['phone'] ?? '',
            'city' => $data['city'] ?? '',
            'npwp' => $data['npwp'] ?? '',
            'permit' => $data['permit'] ?? '',
            'pharmacist_permit' => $data['pharmacist_permit'] ?? '',
            'pharmacist' => $data['pharmacist'] ?? '',
            'footnote1' => $data['footnote1'] ?? '',
            'footnote2' => $data['footnote2'] ?? '',
            'status' => (int) $data['status'],
        ]);

        return redirect()
            ->route('general-manager.users.roles.index', ['tab' => 'pharmacies'])
            ->with('success', "Data apotek {$pharmacy->name} berhasil diperbarui.");
    }

    public function destroyPharmacy(Request $request, Pharmacies $pharmacy)
    {
        $this->ensureManagerAccess($request);

        if (User::where('pharmacy_id', $pharmacy->id)->exists()) {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'pharmacies'])
                ->with('error', "Apotek {$pharmacy->name} masih memiliki akun pengguna terdaftar. Pindahkan user atau nonaktifkan status apotek.");
        }

        try {
            $name = $pharmacy->name;
            $pharmacy->delete();

            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'pharmacies'])
                ->with('success', "Apotek {$name} berhasil dihapus.");
        } catch (QueryException $e) {
            return redirect()
                ->route('general-manager.users.roles.index', ['tab' => 'pharmacies'])
                ->with('error', "Apotek {$pharmacy->name} tidak dapat dihapus karena masih memiliki riwayat data operasional. Anda dapat mengubah statusnya menjadi Nonaktif.");
        }
    }
}
