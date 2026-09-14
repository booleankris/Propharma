<?php

namespace App\Http\Controllers;

use App\Models\Pharmacies;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class GeneralManagerController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasRole('General Manager'), 403);
        $search = trim((string) $request->query('search', ''));
        $users = User::with(['roles', 'pharmacy'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('fullname', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();
        $roles = Role::where('guard_name', 'web')->where('name', '!=', 'administrator')->orderBy('name')->get();
        $pharmacies = Pharmacies::orderBy('name')->get();

        return view('general-manager.home', compact('users', 'roles', 'pharmacies', 'search'));
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->hasRole('General Manager'), 403);
        abort_if(
            $user->hasRole('administrator') || $user->is_fixed || $user->id === $request->user()->id,
            403,
            'Akun ini hanya dapat diubah oleh administrator.'
        );

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'fullname'    => ['nullable', 'string', 'max:255'],
            'username'    => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'nik'         => ['nullable', 'string', 'max:50'],
            'pharmacy_id' => ['required', 'exists:pharmacies,id'],
            'password'    => ['nullable', 'string', 'min:4'],
            'position'    => ['nullable', 'string', 'max:100'],
            'department'  => ['nullable', 'string', 'max:100'],
            'division'    => ['nullable', 'string', 'max:100'],
            'roles'       => ['nullable', 'array'],
            'roles.*'     => ['string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web'), Rule::notIn(['administrator'])],
        ], [
            'name.required'        => 'Nama pengguna wajib diisi.',
            'username.required'    => 'Username wajib diisi.',
            'username.unique'      => 'Username sudah digunakan oleh akun lain.',
            'pharmacy_id.required' => 'Apotek cabang wajib dipilih.',
            'pharmacy_id.exists'   => 'Apotek yang dipilih tidak valid.',
            'password.min'         => 'Password / PIN minimal 4 karakter.',
        ]);

        $updateData = [
            'name'        => $data['name'],
            'fullname'    => $data['fullname'] ?? null,
            'username'    => $data['username'],
            'nik'         => $data['nik'] ?? null,
            'pharmacy_id' => $data['pharmacy_id'],
            'position'    => $data['position'] ?? null,
            'department'  => $data['department'] ?? null,
            'division'    => $data['division'] ?? null,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            $updateData['secret_pin'] = $data['password'];
        }

        $user->update($updateData);

        if ($request->has('roles') && is_array($data['roles']) && count($data['roles']) > 0) {
            $user->syncRoles($data['roles']);
        }

        return redirect()->back()->with('success', "Informasi pengguna {$user->name} berhasil diperbarui.");
    }

    public function updateRoles(Request $request, User $user)
    {
        abort_unless($request->user()->hasRole('General Manager'), 403);
        abort_if(
            $user->hasRole('administrator') || $user->is_fixed || $user->id === $request->user()->id,
            403,
            'Role akun ini hanya dapat diubah oleh administrator.'
        );

        $data = $request->validate([
            'roles'   => ['required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')->where('guard_name', 'web'), Rule::notIn(['administrator'])],
        ]);
        $user->syncRoles($data['roles']);

        return redirect()->back()->with('success', "Role {$user->name} berhasil diperbarui.");
    }
}
