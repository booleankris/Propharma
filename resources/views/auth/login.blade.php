<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <link rel="icon" type="image/png" href="{{ asset('img/walikota.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>Login &mdash; {{ config('app.name', 'Laravel') }}</title>

    <!-- General CSS Files -->
    <link rel="stylesheet" href="{{ asset('templates/library/bootstrap/dist/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"
        integrity="sha512-KfkfwYDsLkIlwQp6LFnl8zNdLGxu9YAA1QvwINks4PhcElQSvqcyVLLD9aMhXd13uQjoXtEKNosOWaZqXgel0g=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- CSS Libraries -->
    <link rel="stylesheet" href="{{ asset('templates/library/bootstrap-social/bootstrap-social.css') }}">

    <!-- Template CSS -->
    <link rel="stylesheet" href="{{ asset('templates/css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('templates/css/components.css') }}">
</head>

<body>
    <div id="app">
        <section class="section">
            <div class="d-flex align-items-stretch flex-wrap">
                <div class="col-lg-4 col-md-6 col-12 order-1 order-lg-1 order-sm-1 min-vh-100  bg-white">
                    <div class="m-3 p-4">
                        <div class="mt-3">
                            &nbsp;
                        </div>
                        <div class="mt-5">
                            &nbsp;
                        </div>
                        <h4 class="text-dark font-weight-normal">Welcome to <span
                            class="font-weight-bold text-[#2196F3]">Propharma</span>
                        </h4>
                        <form method="POST" action="{{ route('login') }}">
                            @csrf
                            <div class="form-group">
                                <label for="email">{{ __('Email Address') }}</label>
                                <input id="email" type="email" class="form-control" name="email" tabindex="1"
                                    required autofocus>
                                <div class="invalid-feedback">
                                    Please fill in your email
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="d-block">
                                    <label for="password" class="control-label">Password</label>
                                </div>
                                <input id="password" type="password" class="form-control" name="password"
                                    tabindex="2" required>
                                <div class="invalid-feedback">
                                    please fill in your password
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" name="remember" class="custom-control-input" tabindex="3"
                                        id="remember-me">
                                    <label class="custom-control-label" for="remember-me">Remember Me</label>
                                </div>
                            </div>

                            <div class="form-group text-right">
                                <button type="submit" class="btn btn-pharma !bg-[#2196F3] btn-lg btn-icon icon-right"
                                    tabindex="4">
                                    Login
                                </button>
                            </div>

                        </form>

                        <div class="text-small mt-5 text-center">
                            Copyright 2025&copy; Propharma
                        </div>
                    </div>
                </div>
                <div class="col-lg-8 col-12 order-lg-2 order-sm-2 min-vh-100 background-walk-y position-relative overlay-gradient-bottom order-2"
                    data-background="{{ asset('img/mobile.jpeg') }}">
                    <div class="absolute-bottom-left index-2">
                        <div class="text-light p-5 pb-2">
                            <div class="mb-5 pb-3">
                                <h1 class="display-4 font-weight-bold mb-2">Propharma </h1>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- General JS Scripts -->
    <script src="{{ asset('templates/library/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('templates/library/popper.js/dist/umd/popper.js') }}"></script>
    <script src="{{ asset('templates/library/tooltip.js/dist/umd/tooltip.js') }}"></script>
    <script src="{{ asset('templates/library/bootstrap/dist/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('templates/library/jquery.nicescroll/dist/jquery.nicescroll.min.js') }}"></script>
    <script src="{{ asset('templates/library/moment/min/moment.min.js') }}"></script>
    <script src="{{ asset('templates/js/stisla.js') }}"></script>

    <!-- JS Libraies -->

    <!-- Page Specific JS File -->

    <!-- Template JS File -->
    <script src="{{ asset('templates/js/scripts.js') }}"></script>
    <script src="{{ asset('templates/js/custom.js') }}"></script>
</body>

</html>
