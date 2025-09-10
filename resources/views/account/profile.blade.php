@extends('layouts.app')

@section('title', 'Profile')

@section('style')
    <!-- CSS Libraries -->
@endsection

@section('content')
    <section class="p-6">
        <!-- Section Header -->
        <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between">
            <h1 class="text-2xl font-semibold text-gray-800">Profil Akun</h1>
            <div class="flex space-x-2 mt-2 md:mt-0 text-sm text-gray-600">
                <div class="text-blue-600 font-medium">Akun</div>
                <span>/</span>
                <div class="text-gray-500">Profil</div>
            </div>
        </div>

        <!-- Section Body -->
        <div class="w-full md:w-1/3">
            @if (Session::has('status'))
                <div
                    class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-md text-sm flex items-center space-x-2">
                    <i class="fa fa-check"></i>
                    <strong>{{ Session::get('status') }}</strong>
                </div>
            @endif

            <div class="bg-white shadow-lg rounded-lg overflow-hidden border border-red-100">
                <div class="p-6">
                    <div class="mb-4">
                        <h6 class="text-sm text-gray-500">Nama</h6>
                        <h6 class="text-lg font-medium text-gray-800">{{ $user['name'] }}</h6>
                    </div>
                    <div>
                        <h6 class="text-sm text-gray-500">Alamat Email</h6>
                        <h6 class="text-lg font-medium text-gray-800">{{ $user['email'] }}</h6>
                    </div>
                </div>
                <div class="bg-gray-50 p-4 text-center">
                    <a href="{{ route('account.profile', 'edit') }}"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded hover:bg-blue-700 transition">
                        <i class="fa fa-edit mr-2"></i> Edit
                    </a>
                </div>
            </div>
        </div>
    </section>

@endsection

@section('scripts')
    <!-- JS Libraies -->


    <!-- Page Specific JS File -->
    <script>
        $(function() {
            @if ($message = Session::get('success'))
                iziToast.success({
                    title: 'Berhasil!',
                    message: '{{ $message }}',
                    position: 'topRight'
                });
            @endif
        });
    </script>
@endsection
