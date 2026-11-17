@extends('layouts.app')

@section('title', 'Manager Access')

@section('content')
    <section class="section px-4 md:px-8 py-4">
        <div class="space-y-6">

            {{-- ─── Header Card ─── --}}
            <div
                class="bg-gradient-to-r from-slate-900 via-indigo-900 to-blue-800 rounded-3xl p-6 text-white shadow-lg relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 opacity-10 pointer-events-none">
                    <svg class="w-64 h-64 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div>
                        <div
                            class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 text-xs font-semibold backdrop-blur-sm mb-2">
                            <svg class="w-3.5 h-3.5 text-sky-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Kontrol Administrator &amp; General Manager
                        </div>
                        <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Manager Access</h1>
                        <p class="text-blue-100/90 text-sm mt-1 max-w-2xl">
                            Pusat kendali untuk Manajemen User (termasuk Atur Role Users), Manajemen Role &amp; Permission,
                            serta Manajemen Cabang Apotek.
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5 sm:gap-3 shrink-0">
                        <div class="rounded-2xl bg-white/10 border border-white/15 backdrop-blur-sm px-4 py-3 text-center">
                            <div class="text-[10px] uppercase tracking-wider text-blue-200 font-bold">Total User</div>
                            <div class="text-lg sm:text-xl font-extrabold text-white mt-0.5">{{ $totalUsersCount }}</div>
                        </div>
                        <div class="rounded-2xl bg-white/10 border border-white/15 backdrop-blur-sm px-4 py-3 text-center">
                            <div class="text-[10px] uppercase tracking-wider text-blue-200 font-bold">Total Role</div>
                            <div class="text-lg sm:text-xl font-extrabold text-white mt-0.5">{{ $roles->count() }}</div>
                        </div>
                        <div class="rounded-2xl bg-white/10 border border-white/15 backdrop-blur-sm px-4 py-3 text-center">
                            <div class="text-[10px] uppercase tracking-wider text-blue-200 font-bold">Apotek Aktif</div>
                            <div class="text-lg sm:text-xl font-extrabold text-white mt-0.5">
                                {{ $allPharmacies->where('status', 1)->count() }}<span
                                    class="text-xs font-medium text-blue-200">/{{ $allPharmacies->count() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ─── Flash & Validation Notifications ─── --}}
            @if (session('success'))
                <div
                    class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl flex items-center gap-3 text-sm shadow-sm">
                    <svg class="w-5 h-5 text-emerald-600 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div
                    class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl flex items-center gap-3 text-sm shadow-sm">
                    <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-sm shadow-sm">
                    <div class="font-bold flex items-center gap-2 mb-1">
                        <svg class="w-5 h-5 text-rose-600 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Terjadi kesalahan validasi:
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-xs text-rose-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ─── Navigation Tabs ─── --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-2">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <a href="{{ route('general-manager.users.roles.index', ['tab' => 'users']) }}"
                        class="flex items-center justify-center sm:justify-start gap-3 px-4 py-3 rounded-xl text-xs font-bold transition {{ $activeTab === 'users' ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        <span
                            class="w-8 h-8 rounded-lg flex items-center justify-center {{ $activeTab === 'users' ? 'bg-white/20 text-white' : 'bg-blue-50 text-blue-600' }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </span>
                        <div class="text-left">
                            <div>Manajemen User</div>
                            <div
                                class="text-[10px] font-medium {{ $activeTab === 'users' ? 'text-blue-100' : 'text-slate-400' }}">
                                Akun, PIN &amp; Atur Role Users
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('general-manager.users.roles.index', ['tab' => 'roles']) }}"
                        class="flex items-center justify-center sm:justify-start gap-3 px-4 py-3 rounded-xl text-xs font-bold transition {{ $activeTab === 'roles' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        <span
                            class="w-8 h-8 rounded-lg flex items-center justify-center {{ $activeTab === 'roles' ? 'bg-white/20 text-white' : 'bg-indigo-50 text-indigo-600' }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </span>
                        <div class="text-left">
                            <div>Manajemen Role</div>
                            <div
                                class="text-[10px] font-medium {{ $activeTab === 'roles' ? 'text-indigo-100' : 'text-slate-400' }}">
                                Level Jabatan &amp; Hak Akses Permission
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('general-manager.users.roles.index', ['tab' => 'pharmacies']) }}"
                        class="flex items-center justify-center sm:justify-start gap-3 px-4 py-3 rounded-xl text-xs font-bold transition {{ $activeTab === 'pharmacies' ? 'bg-emerald-600 text-white shadow-md shadow-emerald-500/20' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                        <span
                            class="w-8 h-8 rounded-lg flex items-center justify-center {{ $activeTab === 'pharmacies' ? 'bg-white/20 text-white' : 'bg-emerald-50 text-emerald-600' }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </span>
                        <div class="text-left">
                            <div>Manajemen Apotek</div>
                            <div
                                class="text-[10px] font-medium {{ $activeTab === 'pharmacies' ? 'text-emerald-100' : 'text-slate-400' }}">
                                Cabang Apotek, SIPA/SIA &amp; Status
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            {{-- ===================================================================== --}}
            {{-- TAB 1: MANAJEMEN USER & ATUR ROLE USERS --}}
            {{-- ===================================================================== --}}
            @if ($activeTab === 'users')
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
                    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                        <form method="GET" action="{{ route('general-manager.users.roles.index') }}"
                            class="flex flex-col md:flex-row items-stretch md:items-center gap-3 flex-1">
                            <input type="hidden" name="tab" value="users">

                            <div class="relative flex-1 min-w-[220px]">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" name="search" value="{{ $search }}"
                                    placeholder="Cari nama, username, NIK, jabatan, cabang..."
                                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition">
                            </div>

                            <select name="pharmacy_id"
                                class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition cursor-pointer">
                                <option value="">Semua Cabang Apotek</option>
                                @foreach ($allPharmacies as $pharmacy)
                                    <option value="{{ $pharmacy->id }}"
                                        {{ (string) $pharmacyFilter === (string) $pharmacy->id ? 'selected' : '' }}>
                                        {{ $pharmacy->name }}
                                    </option>
                                @endforeach
                            </select>

                            <select name="role_filter"
                                class="px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none transition cursor-pointer">
                                <option value="">Semua Role</option>
                                @foreach ($assignableRoles as $role)
                                    <option value="{{ $role->name }}"
                                        {{ $roleFilter === $role->name ? 'selected' : '' }}>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="flex items-center gap-2">
                                <button type="submit"
                                    class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-sm shadow-blue-500/20 transition cursor-pointer flex items-center justify-center gap-1.5">
                                    Filter
                                </button>
                                <a href="{{ route('general-manager.users.roles.index', ['tab' => 'users']) }}"
                                    class="px-3.5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition text-center">
                                    Reset
                                </a>
                            </div>
                        </form>

                        <button type="button" onclick="openCreateUserModal()"
                            class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-indigo-500/20 transition cursor-pointer flex items-center justify-center gap-2 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah User Baru
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div
                        class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h2 class="text-sm sm:text-base font-bold text-slate-800">Daftar Pengguna &amp; Atur Role Users
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">Menampilkan {{ $users->count() }} dari
                                {{ $users->total() }} akun terdaftar</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-3.5 px-5">Pengguna</th>
                                    <th class="py-3.5 px-5">Apotek</th>
                                    <th class="py-3.5 px-5">NIK &amp; Jabatan</th>
                                    <th class="py-3.5 px-5">Secret PIN</th>
                                    <th class="py-3.5 px-5">Role Saat Ini</th>
                                    <th class="py-3.5 px-5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @forelse($users as $user)
                                    @php
                                        $canManage =
                                            $isAdmin ||
                                            (!$user->hasRole('administrator') &&
                                                !$user->is_fixed &&
                                                $user->id !== auth()->id());
                                        $canDelete =
                                            !$user->is_fixed &&
                                            $user->id !== auth()->id() &&
                                            ($isAdmin || !$user->hasRole('administrator'));
                                        $userPayload = [
                                            'id' => $user->id,
                                            'name' => $user->name,
                                            'fullname' => $user->fullname,
                                            'username' => $user->username,
                                            'nik' => $user->nik,
                                            'pharmacy_id' => $user->pharmacy_id,
                                            'pharmacy_name' => $user->pharmacy->name ?? '-',
                                            'position' => $user->position,
                                            'department' => $user->department,
                                            'division' => $user->division,
                                            'roles' => $user->roles->pluck('name')->toArray(),
                                        ];
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-4 px-5 align-top">
                                            <div class="flex items-center gap-3">
                                                <div
                                                    class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-2xs flex-shrink-0">
                                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div
                                                        class="font-bold text-slate-800 text-[13px] truncate flex items-center gap-1.5">
                                                        <span>{{ $user->name }}</span>
                                                        @if ($user->id === auth()->id())
                                                            <span
                                                                class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 text-[9px] font-bold uppercase">Anda</span>
                                                        @endif
                                                    </div>
                                                    @if ($user->fullname && $user->fullname !== $user->name)
                                                        <div class="text-[11px] text-slate-500 truncate">
                                                            {{ $user->fullname }}</div>
                                                    @endif
                                                    <div class="text-[11px] font-mono text-slate-400 mt-0.5">
                                                        <span>&#64;</span>{{ $user->username }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-medium">
                                                <svg class="w-3.5 h-3.5 mr-1.5 text-slate-400 flex-shrink-0"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                </svg>
                                                {{ $user->pharmacy->name ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <div class="space-y-1">
                                                @if ($user->nik)
                                                    <div
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-slate-100 text-slate-700 font-mono text-[11px]">
                                                        <span
                                                            class="text-slate-400 text-[9px] uppercase font-bold tracking-wider">NIK</span>
                                                        <span class="font-semibold">{{ $user->nik }}</span>
                                                    </div>
                                                @else
                                                    <div class="text-slate-400 text-[11px] italic">- NIK belum diisi -
                                                    </div>
                                                @endif

                                                @if ($user->position || $user->department)
                                                    <div class="text-[11px] text-slate-600 font-medium">
                                                        {{ $user->position ?? '-' }}
                                                        @if ($user->department)
                                                            <span
                                                                class="text-slate-400 font-normal">({{ $user->department }})</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            @if (!empty($user->secret_pin))
                                                <span
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    PIN Terpasang
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 text-[11px] font-semibold">
                                                    Belum Diset
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <div class="flex flex-wrap gap-1 max-w-xs">
                                                @forelse($user->roles as $role)
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-50 text-blue-700 border border-blue-100">
                                                        {{ $role->name }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400 italic text-[11px]">Belum ada role</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 align-top text-right">
                                            @if ($canManage)
                                                <div class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                                    <button type="button" data-payload="{{ json_encode($userPayload) }}"
                                                        onclick="openQuickRoleModal(this)"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200/80 text-[11px] font-bold transition cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                        </svg>
                                                        Atur Role
                                                    </button>

                                                    <button type="button" data-payload="{{ json_encode($userPayload) }}"
                                                        onclick="openEditUserModal(this)"
                                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/80 text-[11px] font-bold transition cursor-pointer">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        Edit
                                                    </button>

                                                    @if ($canDelete)
                                                        <button type="button"
                                                            data-payload="{{ json_encode([
                                                                'title' => 'Hapus Pengguna',
                                                                'message' =>
                                                                    'Anda yakin ingin menghapus akun pengguna "' .
                                                                    $user->name .
                                                                    '" (@' .
                                                                    $user->username .
                                                                    ')? Aksi ini tidak dapat dibatalkan.',
                                                                'action' => route('general-manager.users.destroy', $user->id),
                                                            ]) }}"
                                                            onclick="openDeleteConfirmModal(this)"
                                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/80 text-[11px] font-bold transition cursor-pointer"
                                                            title="Hapus Pengguna">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="inline-flex items-center gap-1 text-slate-400 text-xs italic">
                                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    {{ $user->id === auth()->id() ? 'Akun Aktif Anda' : 'Dikelola administrator' }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-12 text-slate-400">
                                            <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <p class="font-medium">Tidak ada pengguna yang cocok dengan filter pencarian.
                                            </p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($users->hasPages())
                        <div class="p-4 border-t border-slate-100">
                            {{ $users->links() }}
                        </div>
                    @endif
                </div>
            @endif

            {{-- ===================================================================== --}}
            {{-- TAB 2: MANAJEMEN ROLE & PERMISSION --}}
            {{-- ===================================================================== --}}
            @if ($activeTab === 'roles')
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <form method="GET" action="{{ route('general-manager.users.roles.index') }}"
                            class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1 max-w-xl">
                            <input type="hidden" name="tab" value="roles">
                            <div class="relative flex-1">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" name="search" value="{{ $search }}"
                                    placeholder="Cari nama role / level..."
                                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit"
                                    class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-sm transition cursor-pointer">
                                    Cari
                                </button>
                                <a href="{{ route('general-manager.users.roles.index', ['tab' => 'roles']) }}"
                                    class="px-3.5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition text-center">
                                    Reset
                                </a>
                            </div>
                        </form>

                        <button type="button" onclick="openCreateRoleModal()"
                            class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-indigo-500/20 transition cursor-pointer flex items-center justify-center gap-2 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Role Baru
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="p-4 sm:p-5 border-b border-slate-100">
                        <h2 class="text-sm sm:text-base font-bold text-slate-800">Daftar Role / Level Jabatan</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola nama role serta detail hak akses (permission) yang
                            dimiliki setiap role</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-3.5 px-5">Nama Role</th>
                                    <th class="py-3.5 px-5">Jumlah Pengguna</th>
                                    <th class="py-3.5 px-5">Hak Akses (Permissions)</th>
                                    <th class="py-3.5 px-5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @forelse ($roles as $role)
                                    @php
                                        $coreRoleNames = [
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
                                        $isFixedRole =
                                            (bool) ($role->fixed ?? ($role->is_fixed ?? false)) ||
                                            in_array(strtolower((string) $role->name), $coreRoleNames, true);
                                        $isAdminRole = strtolower((string) $role->name) === 'administrator';
                                        $canDeleteRole =
                                            !$isFixedRole &&
                                            !$isAdminRole &&
                                            $role->users_count === 0 &&
                                            !auth()->user()->hasRole($role->name);
                                        $rolePayload = [
                                            'id' => $role->id,
                                            'name' => $role->name,
                                            'is_fixed' => $isFixedRole || $isAdminRole,
                                            'permission_ids' => $role->permissions->pluck('id')->toArray(),
                                        ];
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-4 px-5 align-top">
                                            <div class="flex items-center gap-2.5">
                                                <div
                                                    class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs border border-indigo-100 flex-shrink-0">
                                                    {{ strtoupper(substr($role->name, 0, 2)) }}
                                                </div>
                                                <div>
                                                    <div
                                                        class="font-bold text-slate-800 text-[13px] flex items-center gap-2">
                                                        <span>{{ $role->name }}</span>
                                                        @if ($isFixedRole || $isAdminRole)
                                                            <span
                                                                class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                                                Sistem
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] text-slate-400">Guard: {{ $role->guard_name }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 border border-blue-100 font-bold text-[11px]">
                                                {{ $role->users_count }} pengguna
                                            </span>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <div class="flex flex-wrap gap-1 max-w-lg">
                                                @forelse ($role->permissions->take(6) as $perm)
                                                    <span
                                                        class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 text-[11px] font-medium">
                                                        {{ $perm->name }}
                                                    </span>
                                                @empty
                                                    <span class="text-slate-400 italic text-[11px]">Belum ada permission
                                                        khusus</span>
                                                @endforelse
                                                @if ($role->permissions->count() > 6)
                                                    <span
                                                        class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-[11px] font-bold border border-indigo-100">
                                                        +{{ $role->permissions->count() - 6 }} lainnya
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 align-top text-right">
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <button type="button" data-payload="{{ json_encode($rolePayload) }}"
                                                    onclick="openEditRoleModal(this)"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200/80 text-[11px] font-bold transition cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Edit Role &amp; Permission
                                                </button>

                                                @if ($canDeleteRole)
                                                    <button type="button"
                                                        data-payload="{{ json_encode([
                                                            'title' => 'Hapus Role',
                                                            'message' => 'Anda yakin ingin menghapus role "' . $role->name . '"? Aksi ini tidak dapat dibatalkan.',
                                                            'action' => route('general-manager.roles.destroy', $role->id),
                                                        ]) }}"
                                                        onclick="openDeleteConfirmModal(this)"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/80 text-[11px] font-bold transition cursor-pointer"
                                                        title="Hapus Role">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                @else
                                                    <span class="px-2 py-1 text-[10px] text-slate-400 italic"
                                                        title="Role yang masih dipakai user atau role sistem tidak dapat dihapus">
                                                        Terpakai
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-12 text-slate-400">
                                            <p class="font-medium">Tidak ada role yang ditemukan.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- ===================================================================== --}}
            {{-- TAB 3: MANAJEMEN APOTEK --}}
            {{-- ===================================================================== --}}
            @if ($activeTab === 'pharmacies')
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <form method="GET" action="{{ route('general-manager.users.roles.index') }}"
                            class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 flex-1 max-w-xl">
                            <input type="hidden" name="tab" value="pharmacies">
                            <div class="relative flex-1">
                                <div
                                    class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" name="search" value="{{ $search }}"
                                    placeholder="Cari nama apotek, kota, alamat, apoteker..."
                                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none transition">
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit"
                                    class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition cursor-pointer">
                                    Cari
                                </button>
                                <a href="{{ route('general-manager.users.roles.index', ['tab' => 'pharmacies']) }}"
                                    class="px-3.5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition text-center">
                                    Reset
                                </a>
                            </div>
                        </form>

                        <button type="button" onclick="openCreatePharmacyModal()"
                            class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer flex items-center justify-center gap-2 shrink-0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Apotek
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
                    <div class="p-4 sm:p-5 border-b border-slate-100">
                        <h2 class="text-sm sm:text-base font-bold text-slate-800">Daftar Cabang Apotek</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Kelola identitas apotek, nomor izin SIA/SIPA, apoteker
                            penanggung jawab, footer struk, dan status operasional</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr
                                    class="bg-slate-50/80 border-b border-slate-200 text-slate-500 text-[11px] font-bold uppercase tracking-wider">
                                    <th class="py-3.5 px-5">Apotek Cabang</th>
                                    <th class="py-3.5 px-5">Kontak &amp; Apoteker</th>
                                    <th class="py-3.5 px-5">Legalitas (SIA / SIPA / NPWP)</th>
                                    <th class="py-3.5 px-5">Pengguna</th>
                                    <th class="py-3.5 px-5">Status</th>
                                    <th class="py-3.5 px-5 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                                @forelse ($pharmacies as $pharmacy)
                                    @php
                                        $userCount = (int) ($pharmacyUserCounts[$pharmacy->id] ?? 0);
                                        $pharmacyPayload = [
                                            'id' => $pharmacy->id,
                                            'name' => $pharmacy->name,
                                            'address' => $pharmacy->address,
                                            'phone' => $pharmacy->phone,
                                            'city' => $pharmacy->city,
                                            'npwp' => $pharmacy->npwp,
                                            'permit' => $pharmacy->permit,
                                            'pharmacist_permit' => $pharmacy->pharmacist_permit,
                                            'pharmacist' => $pharmacy->pharmacist,
                                            'footnote1' => $pharmacy->footnote1,
                                            'footnote2' => $pharmacy->footnote2,
                                            'status' => (int) $pharmacy->status,
                                        ];
                                    @endphp
                                    <tr class="hover:bg-slate-50/70 transition">
                                        <td class="py-4 px-5 align-top max-w-xs">
                                            <div class="font-bold text-slate-800 text-[13px]">{{ $pharmacy->name }}</div>
                                            @if ($pharmacy->city)
                                                <div class="text-[11px] font-semibold text-emerald-700 mt-0.5">
                                                    {{ $pharmacy->city }}</div>
                                            @endif
                                            <div class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                                {{ $pharmacy->address }}</div>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <div class="space-y-1">
                                                <div class="text-[11px] text-slate-700">
                                                    <span class="text-slate-400">Telp:</span>
                                                    <span class="font-semibold">{{ $pharmacy->phone ?: '-' }}</span>
                                                </div>
                                                <div class="text-[11px] text-slate-700">
                                                    <span class="text-slate-400">Apoteker:</span>
                                                    <span class="font-semibold">{{ $pharmacy->pharmacist ?: '-' }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <div class="space-y-1 text-[11px]">
                                                <div><span class="text-slate-400">SIA:</span> <span
                                                        class="font-mono">{{ $pharmacy->permit ?: '-' }}</span></div>
                                                <div><span class="text-slate-400">SIPA:</span> <span
                                                        class="font-mono">{{ $pharmacy->pharmacist_permit ?: '-' }}</span>
                                                </div>
                                                <div><span class="text-slate-400">NPWP:</span> <span
                                                        class="font-mono">{{ $pharmacy->npwp ?: '-' }}</span></div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            <span
                                                class="inline-flex items-center px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-bold text-[11px]">
                                                {{ $userCount }} akun
                                            </span>
                                        </td>
                                        <td class="py-4 px-5 align-top">
                                            @if ((int) $pharmacy->status === 1)
                                                <span
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    Aktif
                                                </span>
                                            @else
                                                <span
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-bold">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                    Nonaktif
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-4 px-5 align-top text-right">
                                            <div class="inline-flex items-center justify-end gap-1.5">
                                                <button type="button" data-payload="{{ json_encode($pharmacyPayload) }}"
                                                    onclick="openEditPharmacyModal(this)"
                                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/80 text-[11px] font-bold transition cursor-pointer">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                    Edit
                                                </button>

                                                @if ($userCount === 0)
                                                    <button type="button"
                                                        data-payload="{{ json_encode([
                                                            'title' => 'Hapus Cabang Apotek',
                                                            'message' => 'Anda yakin ingin menghapus cabang apotek "' . $pharmacy->name . '"? Aksi ini tidak dapat dibatalkan.',
                                                            'action' => route('general-manager.pharmacies.destroy', $pharmacy->id),
                                                        ]) }}"
                                                        onclick="openDeleteConfirmModal(this)"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/80 text-[11px] font-bold transition cursor-pointer"
                                                        title="Hapus Apotek">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                @else
                                                    <span class="px-2 py-1 text-[10px] text-slate-400 italic"
                                                        title="Masih memiliki akun pengguna terdaftar">
                                                        Terpakai
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-12 text-slate-400">
                                            <p class="font-medium">Tidak ada apotek yang cocok dengan pencarian.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </section>

    <datalist id="standard_positions_list">
        @foreach ($positions as $pos)
            <option value="{{ $pos }}">
        @endforeach
    </datalist>

    {{-- ===================================================================== --}}
    {{-- MODAL 1: TAMBAH USER BARU --}}
    {{-- ===================================================================== --}}
    <div id="createUserModal"
        class="gm-modal fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div
            class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden">
            <div
                class="px-6 py-5 bg-gradient-to-r from-indigo-600 via-blue-600 to-sky-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold">Tambah Pengguna Baru</h3>
                        <p class="text-xs text-blue-100 mt-0.5">Buat akun baru sekaligus atur cabang apotek, PIN, dan
                            rolenya</p>
                    </div>
                </div>
                <button type="button" onclick="closeGmModal('createUserModal')"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('general-manager.users.store') }}"
                class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
                @csrf
                <input type="hidden" name="_modal" value="createUserModal">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Pengguna / Display <span
                                class="text-rose-500">*</span></label>
                        <input type="text" name="name"
                            value="{{ old('_modal') === 'createUserModal' ? old('name') : '' }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                        <input type="text" name="fullname"
                            value="{{ old('_modal') === 'createUserModal' ? old('fullname') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Sesuai KTP / Identitas">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Username <span
                                class="text-rose-500">*</span></label>
                        <input type="text" name="username"
                            value="{{ old('_modal') === 'createUserModal' ? old('username') : '' }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">NIK</label>
                        <input type="text" name="nik"
                            value="{{ old('_modal') === 'createUserModal' ? old('nik') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="16 digit NIK">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Apotek Cabang <span
                                class="text-rose-500">*</span></label>
                        <select name="pharmacy_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option value="">-- Pilih Apotek Cabang --</option>
                            @foreach ($allPharmacies as $pharmacy)
                                <option value="{{ $pharmacy->id }}"
                                    {{ old('_modal') === 'createUserModal' && (string) old('pharmacy_id') === (string) $pharmacy->id ? 'selected' : '' }}>
                                    {{ $pharmacy->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Jabatan / Posisi</label>
                        <input type="text" name="position" list="standard_positions_list"
                            value="{{ old('_modal') === 'createUserModal' ? old('position') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Pilih atau ketik jabatan...">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Departemen</label>
                        <input type="text" name="department"
                            value="{{ old('_modal') === 'createUserModal' ? old('department') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Divisi</label>
                        <input type="text" name="division"
                            value="{{ old('_modal') === 'createUserModal' ? old('division') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Password Login <span
                                class="text-rose-500">*</span></label>
                        <input type="password" name="password" minlength="4" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Minimal 4 karakter">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Konfirmasi Password <span
                                class="text-rose-500">*</span></label>
                        <input type="password" name="password_confirmation" minlength="4" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Ulangi password">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Secret PIN (untuk Mutasi / Otorisasi
                        Cepat)</label>
                    <input type="text" name="secret_pin"
                        value="{{ old('_modal') === 'createUserModal' ? old('secret_pin') : '' }}" minlength="4"
                        maxlength="20"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        placeholder="Opsional (kosongkan untuk disamakan dengan Password)">
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-800 uppercase tracking-wider">Pilih Role Pengguna <span
                                class="text-rose-500">*</span></label>
                        <span class="text-[11px] text-slate-400">Minimal pilih 1 role</span>
                    </div>
                    <div
                        class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl max-h-44 overflow-y-auto">
                        @foreach ($assignableRoles as $role)
                            <label
                                class="flex items-center gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer select-none p-1.5 rounded-lg hover:bg-white transition">
                                <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                    {{ old('_modal') === 'createUserModal' && is_array(old('roles')) && in_array($role->name, old('roles')) ? 'checked' : '' }}
                                    class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="truncate">{{ $role->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeGmModal('createUserModal')"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-500/20 transition cursor-pointer">
                        Simpan Pengguna
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 2: EDIT INFORMASI & ROLE PENGGUNA --}}
    {{-- ===================================================================== --}}
    <div id="editUserModal"
        class="gm-modal fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div
            class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden">
            <div
                class="px-6 py-5 bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-extrabold" id="modalUserTitle">Edit Informasi Pengguna</h3>
                        <p class="text-xs text-blue-100 mt-0.5">Ubah profil akun, cabang apotek, PIN, dan delegasi role</p>
                    </div>
                </div>
                <button type="button" onclick="closeGmModal('editUserModal')"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="editUserForm" method="POST" action="" class="p-6 space-y-5 max-h-[80vh] overflow-y-auto">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="editUserModal">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_name" class="block text-xs font-semibold text-slate-700 mb-1">Nama Pengguna /
                            Display <span class="text-rose-500">*</span></label>
                        <input type="text" id="edit_name" name="name" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_fullname" class="block text-xs font-semibold text-slate-700 mb-1">Nama
                            Lengkap</label>
                        <input type="text" id="edit_fullname" name="fullname"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_username" class="block text-xs font-semibold text-slate-700 mb-1">Username <span
                                class="text-rose-500">*</span></label>
                        <input type="text" id="edit_username" name="username" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_nik" class="block text-xs font-semibold text-slate-700 mb-1">Nomor Induk
                            Kependudukan (NIK)</label>
                        <input type="text" id="edit_nik" name="nik"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_pharmacy_id" class="block text-xs font-semibold text-slate-700 mb-1">Apotek
                            Cabang <span class="text-rose-500">*</span></label>
                        <select id="edit_pharmacy_id" name="pharmacy_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            @foreach ($allPharmacies as $pharmacy)
                                <option value="{{ $pharmacy->id }}">{{ $pharmacy->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="edit_position" class="block text-xs font-semibold text-slate-700 mb-1">Jabatan /
                            Posisi</label>
                        <input type="text" id="edit_position" name="position" list="standard_positions_list"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_department"
                            class="block text-xs font-semibold text-slate-700 mb-1">Departemen</label>
                        <input type="text" id="edit_department" name="department"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_division" class="block text-xs font-semibold text-slate-700 mb-1">Divisi</label>
                        <input type="text" id="edit_division" name="division"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="edit_password" class="block text-xs font-semibold text-slate-700 mb-1">Password Login
                            Baru</label>
                        <input type="password" id="edit_password" name="password" minlength="4"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Kosongkan jika tidak ubah password">
                    </div>
                    <div>
                        <label for="edit_secret_pin" class="block text-xs font-semibold text-slate-700 mb-1">Secret PIN
                            Baru</label>
                        <input type="text" id="edit_secret_pin" name="secret_pin" minlength="4" maxlength="20"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            placeholder="Kosongkan jika tidak ubah PIN">
                    </div>
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Role &amp; Hak Akses</h4>
                        <span class="text-[11px] text-slate-400">Pilih satu atau beberapa role</span>
                    </div>
                    <div
                        class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl max-h-44 overflow-y-auto">
                        @foreach ($assignableRoles as $role)
                            <label
                                class="flex items-center gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer select-none p-1.5 rounded-lg hover:bg-white transition">
                                <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                    class="edit-user-role-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="truncate">{{ $role->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeGmModal('editUserModal')"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition cursor-pointer">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 3: ATUR ROLE USERS CEPAT --}}
    {{-- ===================================================================== --}}
    <div id="quickRoleModal"
        class="gm-modal fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden">
            <div class="px-6 py-5 bg-gradient-to-r from-sky-600 to-blue-600 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold" id="quickRoleTitle">Atur Role User</h3>
                    <p class="text-xs text-sky-100 mt-0.5" id="quickRoleSubtitle">Pilih role untuk pengguna ini</p>
                </div>
                <button type="button" onclick="closeGmModal('quickRoleModal')"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="quickRoleForm" method="POST" action="" class="p-6 space-y-5">
                @csrf
                @method('PATCH')
                <div
                    class="grid grid-cols-2 gap-2.5 p-3.5 bg-slate-50 border border-slate-200/80 rounded-2xl max-h-64 overflow-y-auto">
                    @foreach ($assignableRoles as $role)
                        <label
                            class="flex items-center gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer select-none p-2 rounded-xl hover:bg-white border border-transparent hover:border-slate-200 transition">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}"
                                class="quick-role-checkbox rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                            <span class="font-medium truncate">{{ $role->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeGmModal('quickRoleModal')"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold shadow-md shadow-sky-500/20 transition cursor-pointer">
                        Simpan Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 4: TAMBAH ROLE --}}
    {{-- ===================================================================== --}}
    <div id="createRoleModal"
        class="gm-modal fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div
            class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden">
            <div
                class="px-6 py-5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold">Tambah Role / Level Baru</h3>
                    <p class="text-xs text-indigo-100 mt-0.5">Tentukan nama role serta hak akses (permission) yang
                        diizinkan</p>
                </div>
                <button type="button" onclick="closeGmModal('createRoleModal')"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('general-manager.roles.store') }}"
                class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                @csrf
                <input type="hidden" name="_modal" value="createRoleModal">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Role <span
                            class="text-rose-500">*</span></label>
                    <input type="text" name="name"
                        value="{{ old('_modal') === 'createRoleModal' ? old('name') : '' }}" required
                        placeholder="Contoh: Supervisor Cabang, Kepala Apotek"
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <label class="text-xs font-bold text-slate-800 uppercase tracking-wider">Hak Akses
                            (Permissions)</label>
                        <div class="flex items-center gap-2">
                            <input type="text" oninput="filterPermissionCheckboxes('create_role_perms', this.value)"
                                placeholder="Filter permission..."
                                class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px] focus:bg-white focus:outline-none">
                            <button type="button" onclick="toggleAllPermissions('create_role_perms', true)"
                                class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[11px] font-semibold cursor-pointer">Semua</button>
                            <button type="button" onclick="toggleAllPermissions('create_role_perms', false)"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-semibold cursor-pointer">Reset</button>
                        </div>
                    </div>

                    <div id="create_role_perms"
                        class="space-y-3 p-3.5 bg-slate-50 border border-slate-200 rounded-2xl max-h-72 overflow-y-auto">
                        @forelse ($permissionGroups as $groupKey => $group)
                            <div class="perm-group bg-white rounded-xl border border-slate-200/80 p-3 space-y-2">
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 border-b border-slate-100 pb-1.5">
                                    {{ $group['label'] }}
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($group['items'] as $perm)
                                        <label
                                            data-perm-name="{{ strtolower($perm['name'] . ' ' . $perm['label'] . ' ' . $perm['description']) }}"
                                            class="perm-item flex items-start gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer select-none p-1.5 rounded-lg hover:bg-slate-50 transition">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm['id'] }}"
                                                {{ old('_modal') === 'createRoleModal' && is_array(old('permissions')) && in_array($perm['id'], old('permissions')) ? 'checked' : '' }}
                                                class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            <div class="min-w-0">
                                                <div class="font-semibold text-slate-800 leading-snug">
                                                    {{ $perm['label'] }}</div>
                                                <div class="text-[10px] text-slate-400 font-mono truncate">
                                                    {{ $perm['name'] }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 italic py-4 text-center">Belum ada daftar permission di
                                database.</div>
                        @endforelse
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeGmModal('createRoleModal')"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-500/20 transition cursor-pointer">
                        Buat Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 5: EDIT ROLE --}}
    {{-- ===================================================================== --}}
    <div id="editRoleModal"
        class="gm-modal fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div
            class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden">
            <div
                class="px-6 py-5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold" id="editRoleTitle">Edit Role &amp; Permission</h3>
                    <p class="text-xs text-indigo-100 mt-0.5">Perbarui nama role dan daftar permission yang dimiliki</p>
                </div>
                <button type="button" onclick="closeGmModal('editRoleModal')"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="editRoleForm" method="POST" action="" class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="editRoleModal">

                <div>
                    <label for="edit_role_name" class="block text-xs font-semibold text-slate-700 mb-1">Nama Role <span
                            class="text-rose-500">*</span></label>
                    <input type="text" id="edit_role_name" name="name" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <p id="edit_role_fixed_hint" class="hidden text-[11px] text-amber-600 mt-1 font-medium">
                        Role inti sistem: nama role dikunci untuk menjaga stabilitas sistem, namun hak akses permission
                        tetap dapat disesuaikan.
                    </p>
                </div>

                <div class="space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <label class="text-xs font-bold text-slate-800 uppercase tracking-wider">Hak Akses
                            (Permissions)</label>
                        <div class="flex items-center gap-2">
                            <input type="text" oninput="filterPermissionCheckboxes('edit_role_perms', this.value)"
                                placeholder="Filter permission..."
                                class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-[11px] focus:bg-white focus:outline-none">
                            <button type="button" onclick="toggleAllPermissions('edit_role_perms', true)"
                                class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-[11px] font-semibold cursor-pointer">Semua</button>
                            <button type="button" onclick="toggleAllPermissions('edit_role_perms', false)"
                                class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-semibold cursor-pointer">Reset</button>
                        </div>
                    </div>

                    <div id="edit_role_perms"
                        class="space-y-3 p-3.5 bg-slate-50 border border-slate-200 rounded-2xl max-h-72 overflow-y-auto">
                        @forelse ($permissionGroups as $groupKey => $group)
                            <div class="perm-group bg-white rounded-xl border border-slate-200/80 p-3 space-y-2">
                                <div
                                    class="text-[11px] font-bold uppercase tracking-wider text-indigo-700 border-b border-slate-100 pb-1.5">
                                    {{ $group['label'] }}
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($group['items'] as $perm)
                                        <label
                                            data-perm-name="{{ strtolower($perm['name'] . ' ' . $perm['label'] . ' ' . $perm['description']) }}"
                                            class="perm-item flex items-start gap-2 text-xs text-slate-700 hover:text-slate-900 cursor-pointer select-none p-1.5 rounded-lg hover:bg-slate-50 transition">
                                            <input type="checkbox" name="permissions[]" value="{{ $perm['id'] }}"
                                                class="edit-role-perm-checkbox mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            <div class="min-w-0">
                                                <div class="font-semibold text-slate-800 leading-snug">
                                                    {{ $perm['label'] }}</div>
                                                <div class="text-[10px] text-slate-400 font-mono truncate">
                                                    {{ $perm['name'] }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <div class="text-xs text-slate-400 italic py-4 text-center">Belum ada daftar permission di
                                database.</div>
                        @endforelse
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeGmModal('editRoleModal')"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-md shadow-indigo-500/20 transition cursor-pointer">
                        Simpan Perubahan Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 6: TAMBAH APOTEK --}}
    {{-- ===================================================================== --}}
    <div id="createPharmacyModal"
        class="gm-modal fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div
            class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden">
            <div
                class="px-6 py-5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold">Tambah Cabang Apotek</h3>
                    <p class="text-xs text-emerald-100 mt-0.5">Tambahkan cabang apotek baru beserta legalitas izinnya</p>
                </div>
                <button type="button" onclick="closeGmModal('createPharmacyModal')"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('general-manager.pharmacies.store') }}"
                class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                @csrf
                <input type="hidden" name="_modal" value="createPharmacyModal">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nama Apotek <span
                                class="text-rose-500">*</span></label>
                        <input type="text" name="name"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('name') : '' }}" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Kota</label>
                        <input type="text" name="city"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('city') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">No. Telepon</label>
                        <input type="text" name="phone"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('phone') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Status <span
                                class="text-rose-500">*</span></label>
                        <select name="status" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="1"
                                {{ old('_modal') === 'createPharmacyModal' && old('status') === '0' ? '' : 'selected' }}>
                                Aktif</option>
                            <option value="0"
                                {{ old('_modal') === 'createPharmacyModal' && old('status') === '0' ? 'selected' : '' }}>
                                Nonaktif</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Alamat Lengkap <span
                                class="text-rose-500">*</span></label>
                        <textarea name="address" rows="2" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">{{ old('_modal') === 'createPharmacyModal' ? old('address') : '' }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Apoteker Penanggung Jawab</label>
                        <input type="text" name="pharmacist"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('pharmacist') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">NPWP</label>
                        <input type="text" name="npwp"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('npwp') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">No. Izin Apotek (SIA)</label>
                        <input type="text" name="permit"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('permit') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">No. Izin Apoteker (SIPA)</label>
                        <input type="text" name="pharmacist_permit"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('pharmacist_permit') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Footer Struk 1</label>
                        <input type="text" name="footnote1"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('footnote1') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Footer Struk 2</label>
                        <input type="text" name="footnote2"
                            value="{{ old('_modal') === 'createPharmacyModal' ? old('footnote2') : '' }}"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeGmModal('createPharmacyModal')"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer">
                        Simpan Apotek
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 7: EDIT APOTEK --}}
    {{-- ===================================================================== --}}
    <div id="editPharmacyModal"
        class="gm-modal fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div
            class="relative w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-100 my-8 overflow-hidden">
            <div
                class="px-6 py-5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold" id="editPharmacyTitle">Edit Data Apotek</h3>
                    <p class="text-xs text-emerald-100 mt-0.5">Perbarui profil cabang apotek, izin SIA/SIPA, dan status</p>
                </div>
                <button type="button" onclick="closeGmModal('editPharmacyModal')"
                    class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="editPharmacyForm" method="POST" action=""
                class="p-6 space-y-4 max-h-[80vh] overflow-y-auto">
                @csrf
                @method('PUT')
                <input type="hidden" name="_modal" value="editPharmacyModal">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="ph_edit_name" class="block text-xs font-semibold text-slate-700 mb-1">Nama Apotek
                            <span class="text-rose-500">*</span></label>
                        <input type="text" id="ph_edit_name" name="name" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_city" class="block text-xs font-semibold text-slate-700 mb-1">Kota</label>
                        <input type="text" id="ph_edit_city" name="city"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_phone" class="block text-xs font-semibold text-slate-700 mb-1">No.
                            Telepon</label>
                        <input type="text" id="ph_edit_phone" name="phone"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_status" class="block text-xs font-semibold text-slate-700 mb-1">Status <span
                                class="text-rose-500">*</span></label>
                        <select id="ph_edit_status" name="status" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label for="ph_edit_address" class="block text-xs font-semibold text-slate-700 mb-1">Alamat
                            Lengkap <span class="text-rose-500">*</span></label>
                        <textarea id="ph_edit_address" name="address" rows="2" required
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
                    </div>
                    <div>
                        <label for="ph_edit_pharmacist" class="block text-xs font-semibold text-slate-700 mb-1">Apoteker
                            Penanggung Jawab</label>
                        <input type="text" id="ph_edit_pharmacist" name="pharmacist"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_npwp" class="block text-xs font-semibold text-slate-700 mb-1">NPWP</label>
                        <input type="text" id="ph_edit_npwp" name="npwp"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_permit" class="block text-xs font-semibold text-slate-700 mb-1">No. Izin
                            Apotek (SIA)</label>
                        <input type="text" id="ph_edit_permit" name="permit"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_pharmacist_permit"
                            class="block text-xs font-semibold text-slate-700 mb-1">No. Izin Apoteker (SIPA)</label>
                        <input type="text" id="ph_edit_pharmacist_permit" name="pharmacist_permit"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_footnote1" class="block text-xs font-semibold text-slate-700 mb-1">Footer
                            Struk 1</label>
                        <input type="text" id="ph_edit_footnote1" name="footnote1"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                    <div>
                        <label for="ph_edit_footnote2" class="block text-xs font-semibold text-slate-700 mb-1">Footer
                            Struk 2</label>
                        <input type="text" id="ph_edit_footnote2" name="footnote2"
                            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 focus:bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="closeGmModal('editPharmacyModal')"
                        class="px-5 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer">
                        Simpan Perubahan Apotek
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ===================================================================== --}}
    {{-- MODAL 8: KONFIRMASI HAPUS MODERN --}}
    {{-- ===================================================================== --}}
    <div id="deleteConfirmModal"
        class="gm-modal fixed inset-0 z-[10010] hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs overflow-y-auto">
        <div
            class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl border border-slate-100 overflow-hidden p-6 text-center space-y-4">
            <div
                class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-100">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-800" id="deleteConfirmTitle">Konfirmasi Hapus</h3>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed" id="deleteConfirmMessage"></p>
            </div>
            <form id="deleteConfirmForm" method="POST" action=""
                class="flex items-center justify-center gap-2.5 pt-2">
                @csrf
                @method('DELETE')
                <button type="button" onclick="closeGmModal('deleteConfirmModal')"
                    class="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 hover:bg-slate-50 text-slate-600 text-xs font-semibold transition cursor-pointer">
                    Batal
                </button>
                <button type="submit"
                    class="flex-1 px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold shadow-md shadow-rose-500/20 transition cursor-pointer">
                    Ya, Hapus
                </button>
            </form>
        </div>
    </div>

    <script>
        function openGmModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeGmModal(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('flex');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        function closeAllGmModals() {
            document.querySelectorAll('.gm-modal').forEach(modal => {
                modal.classList.remove('flex');
                modal.classList.add('hidden');
            });
            document.body.style.overflow = '';
        }

        function openCreateUserModal() {
            openGmModal('createUserModal');
        }

        function openEditUserModal(btn) {
            const user = JSON.parse(btn.dataset.payload || '{}');
            const form = document.getElementById('editUserForm');
            form.action = "{{ url('general-manager/users') }}/" + user.id;

            document.getElementById('modalUserTitle').innerText = 'Edit Informasi: ' + (user.name || user.username);
            document.getElementById('edit_name').value = user.name || '';
            document.getElementById('edit_fullname').value = user.fullname || '';
            document.getElementById('edit_username').value = user.username || '';
            document.getElementById('edit_nik').value = user.nik || '';
            document.getElementById('edit_pharmacy_id').value = user.pharmacy_id || '';
            document.getElementById('edit_position').value = user.position || '';
            document.getElementById('edit_department').value = user.department || '';
            document.getElementById('edit_division').value = user.division || '';
            document.getElementById('edit_password').value = '';
            document.getElementById('edit_secret_pin').value = '';

            const userRoles = Array.isArray(user.roles) ? user.roles : [];
            document.querySelectorAll('.edit-user-role-checkbox').forEach(cb => {
                cb.checked = userRoles.includes(cb.value);
            });

            openGmModal('editUserModal');
        }

        function openQuickRoleModal(btn) {
            const user = JSON.parse(btn.dataset.payload || '{}');
            const form = document.getElementById('quickRoleForm');
            form.action = "{{ url('general-manager/users') }}/" + user.id + "/roles";

            document.getElementById('quickRoleTitle').innerText = 'Atur Role: ' + (user.name || user.username);
            document.getElementById('quickRoleSubtitle').innerText = '@' + (user.username || '-') + ' • ' + (user
                .pharmacy_name || '-');

            const userRoles = Array.isArray(user.roles) ? user.roles : [];
            document.querySelectorAll('.quick-role-checkbox').forEach(cb => {
                cb.checked = userRoles.includes(cb.value);
            });

            openGmModal('quickRoleModal');
        }

        function openCreateRoleModal() {
            openGmModal('createRoleModal');
        }

        function openEditRoleModal(btn) {
            const role = JSON.parse(btn.dataset.payload || '{}');
            const form = document.getElementById('editRoleForm');
            form.action = "{{ url('general-manager/roles') }}/" + role.id;

            document.getElementById('editRoleTitle').innerText = 'Edit Role: ' + (role.name || '');
            const nameInput = document.getElementById('edit_role_name');
            const fixedHint = document.getElementById('edit_role_fixed_hint');
            nameInput.value = role.name || '';

            if (role.is_fixed) {
                nameInput.readOnly = true;
                nameInput.classList.add('opacity-70', 'cursor-not-allowed');
                fixedHint.classList.remove('hidden');
            } else {
                nameInput.readOnly = false;
                nameInput.classList.remove('opacity-70', 'cursor-not-allowed');
                fixedHint.classList.add('hidden');
            }

            const permIds = Array.isArray(role.permission_ids) ? role.permission_ids.map(Number) : [];
            document.querySelectorAll('.edit-role-perm-checkbox').forEach(cb => {
                cb.checked = permIds.includes(Number(cb.value));
            });

            openGmModal('editRoleModal');
        }

        function filterPermissionCheckboxes(containerId, keyword) {
            const container = document.getElementById(containerId);
            if (!container) return;
            const term = (keyword || '').trim().toLowerCase();
            container.querySelectorAll('.perm-item').forEach(item => {
                const name = item.dataset.permName || '';
                item.style.display = (term === '' || name.includes(term)) ? '' : 'none';
            });
            container.querySelectorAll('.perm-group').forEach(group => {
                const visibleItems = Array.from(group.querySelectorAll('.perm-item')).some(
                    item => item.style.display !== 'none'
                );
                group.style.display = visibleItems ? '' : 'none';
            });
        }

        function toggleAllPermissions(containerId, checked) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll('.perm-item').forEach(item => {
                if (item.style.display !== 'none') {
                    const cb = item.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = checked;
                }
            });
        }

        function openCreatePharmacyModal() {
            openGmModal('createPharmacyModal');
        }

        function openEditPharmacyModal(btn) {
            const ph = JSON.parse(btn.dataset.payload || '{}');
            const form = document.getElementById('editPharmacyForm');
            form.action = "{{ url('general-manager/pharmacies') }}/" + ph.id;

            document.getElementById('editPharmacyTitle').innerText = 'Edit Apotek: ' + (ph.name || '');
            document.getElementById('ph_edit_name').value = ph.name || '';
            document.getElementById('ph_edit_city').value = ph.city || '';
            document.getElementById('ph_edit_phone').value = ph.phone || '';
            document.getElementById('ph_edit_status').value = String(ph.status ?? 1);
            document.getElementById('ph_edit_address').value = ph.address || '';
            document.getElementById('ph_edit_pharmacist').value = ph.pharmacist || '';
            document.getElementById('ph_edit_npwp').value = ph.npwp || '';
            document.getElementById('ph_edit_permit').value = ph.permit || '';
            document.getElementById('ph_edit_pharmacist_permit').value = ph.pharmacist_permit || '';
            document.getElementById('ph_edit_footnote1').value = ph.footnote1 || '';
            document.getElementById('ph_edit_footnote2').value = ph.footnote2 || '';

            openGmModal('editPharmacyModal');
        }

        function openDeleteConfirmModal(btn) {
            const data = JSON.parse(btn.dataset.payload || '{}');
            document.getElementById('deleteConfirmTitle').innerText = data.title || 'Konfirmasi Hapus';
            document.getElementById('deleteConfirmMessage').innerText = data.message || '';
            document.getElementById('deleteConfirmForm').action = data.action || '';
            openGmModal('deleteConfirmModal');
        }

        document.querySelectorAll('.gm-modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeGmModal(this.id);
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllGmModals();
            }
        });

        @if ($errors->any() && in_array(old('_modal'), ['createUserModal', 'createRoleModal', 'createPharmacyModal'], true))
            document.addEventListener('DOMContentLoaded', function() {
                openGmModal(@json(old('_modal')));
            });
        @endif
    </script>
@endsection
