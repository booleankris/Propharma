<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    @if ((isset($_SERVER['REQUEST_SCHEME']) && $_SERVER['REQUEST_SCHEME'] === 'https') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'))
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])


    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" href="{{ asset('img/walikota.png') }}">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="theme-color" content="#ffffff">

    <title>@yield('title') | ProPharma</title>

    <!-- General CSS Files -->
    <link rel="stylesheet"
        href="{{ asset('templates/library/bootstrap/dist/css/bootstrap.min.css') }}">
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"
        integrity="sha512-KfkfwYDsLkIlwQp6LFnl8zNdLGxu9YAA1QvwINks4PhcElQSvqcyVLLD9aMhXd13uQjoXtEKNosOWaZqXgel0g=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer" />

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <!-- Template CSS -->
    <link rel="stylesheet"
        href="{{ asset('templates/css/style.css') }}">
    <link rel="stylesheet"
        href="{{ asset('templates/css/components.css') }}">

    <!-- Addon CSS -->
    <link rel="stylesheet" href="{{ asset('templates/library/izitoast/dist/css/iziToast.min.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/library/chocolat/dist/css/chocolat.css') }}">

    <!-- Scripts -->
    {{-- @vite(['resources/sass/app.scss', 'resources/js/app.js']) --}}

    @yield('style')
</head>
<body>
    <div id="app">
        <div class="main-wrapper">
            <!-- Header -->
            @include('components.admin-header')

            <!-- Sidebar -->
            @include('components.sidebar')

            <main class="">
                <div class="main-content">
                    @yield('content')
                </div>
            </main>

            @include('components.admin-footer')
        </div>
    </div>

    <!-- General JS Scripts -->
    <script src="{{ asset('templates/library/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('templates/library/popper.js/dist/umd/popper.js') }}"></script>
    <script src="{{ asset('templates/library/tooltip.js/dist/umd/tooltip.js') }}"></script>
    <script src="{{ asset('templates/library/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery.nicescroll/dist/jquery.nicescroll.min.js') }}"></script>
    <script src="{{ asset('templates/library/moment/min/moment.min.js') }}"></script>
    <script src="{{ asset('templates/js/stisla.js') }}"></script>

    <!-- Addon JS Scripts -->
    <script src="{{ asset('templates/library/izitoast/dist/js/iziToast.min.js') }}"></script>
    <script src="{{ asset('templates/library/sweetalert/dist/sweetalert.min.js') }}"></script>
    <script src="{{ asset('templates/js/page/modules-sweetalert.js') }}"></script>
    <script src="{{ asset('templates/library/chocolat/dist/js/jquery.chocolat.min.js') }}"></script>
   
    @yield('scripts')
    
    <!-- Template JS File -->
    <script src="{{ asset('templates/js/scripts.js') }}"></script>
    <script src="{{ asset('templates/js/custom.js') }}"></script>
</body>
</html>
