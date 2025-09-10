@extends('layouts.app')

@section('title', 'Edit Profile')

@section('style')
    <!-- CSS Libraries -->
@endsection

@section('content')
    <section class="px-6 py-8 max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Profil Akun</h1>
            <nav class="text-sm text-gray-500 flex space-x-2">
                <span class="text-blue-600 font-medium">Akun</span>
                <span>/</span>
                <span>Edit</span>
            </nav>
        </div>

        <!-- Validation Errors -->
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-100 border border-red-300 text-red-800 rounded-md text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Profile Edit Form -->
        <div class="bg-white shadow-md rounded-lg border border-gray-100 overflow-hidden">
            <form action="{{ route('account.profile-update') }}" method="POST" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Nama</label>
                    <input type="text" id="name" name="name" value="{{ $user['name'] }}"
                        placeholder="Masukkan Nama Lengkap"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required>
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Alamat Email Aktif</label>
                    <input type="email" id="email" name="email" value="{{ $user['email'] }}"
                        placeholder="Masukkan Email Aktif"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required>
                </div>

                <!-- New Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Ubah Password</label>
                    <input type="password" id="password" name="password" placeholder="Isi bila ingin ubah password"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <small class="text-xs text-gray-500">Silakan isi bila ingin mengubah password</small>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi
                        Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                        placeholder="Ulangi ubah password"
                        class="w-full px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <small class="text-xs text-gray-500">Ulangi perubahan password</small>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 border-t border-gray-100 flex justify-center gap-3">
                    <a href="{{ route('account.profile') }}"
                        class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300 transition">
                        <i class="fa fa-arrow-left mr-2"></i> Kembali
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-6 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition">
                        <i class="fa fa-save mr-2"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </section>

@endsection

@section('scripts')
    <!-- JS Libraies -->

    <!-- Page Specific JS File -->
@endsection
