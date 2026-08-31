<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <link rel="icon" type="image/png" href="{{ asset('img/walikota.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>Login &mdash; {{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --blue: #2196F3;
            --blue-dark: #1565C0;
            --blue-light: #90CAF9;
            --glass-bg: rgba(255, 255, 255, 0.12);
            --glass-border: rgba(255, 255, 255, 0.25);
            --glass-card: rgba(255, 255, 255, 0.15);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #0d47a1 0%, #1976D2 40%, #42A5F5 80%, #90CAF9 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
        }

        /* Ambient blobs */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.35;
            pointer-events: none;
        }

        body::before {
            width: 500px;
            height: 500px;
            background: #1565C0;
            top: -150px;
            left: -150px;
        }

        body::after {
            width: 400px;
            height: 400px;
            background: #90CAF9;
            bottom: -100px;
            right: -100px;
        }

        .login-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 28px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            padding: 2.75rem 2.5rem 2.25rem;
            position: relative;
            z-index: 1;
            animation: fadeUp 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(28px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 17px;
        }

        .brand-name {
            font-family: 'DM Serif Display', serif;
            font-size: 20px;
            color: #fff;
            letter-spacing: 0.2px;
        }

        .login-heading {
            font-size: 26px;
            font-weight: 600;
            color: #fff;
            letter-spacing: -0.5px;
            line-height: 1.25;
            margin-bottom: 0.3rem;
        }

        .login-sub {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 2rem;
        }

        .field {
            margin-bottom: 1.1rem;
        }

        .field label {
            display: block;
            font-size: 12.5px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.75);
            margin-bottom: 7px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.45);
            font-size: 14px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrap input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: #fff;
            font-size: 14.5px;
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color 0.2s, background 0.2s;
        }

        .input-wrap input::placeholder {
            color: rgba(255, 255, 255, 0.35);
        }

        .input-wrap input:focus {
            border-color: rgba(255, 255, 255, 0.55);
            background: rgba(255, 255, 255, 0.16);
        }

        .input-wrap input:focus+i,
        .input-wrap:focus-within i {
            color: rgba(255, 255, 255, 0.75);
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 1.2rem 0 1.6rem;
        }

        .remember-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #fff;
            cursor: pointer;
        }

        .remember-row label {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.65);
            cursor: pointer;
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: #fff;
            color: #1565C0;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'DM Sans', sans-serif;
            cursor: pointer;
            letter-spacing: 0.1px;
            transition: background 0.2s, transform 0.15s, opacity 0.2s;
        }

        .btn-login:hover {
            background: #e3f2fd;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
            opacity: 0.9;
        }

        .footer-text {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.35);
        }

        /* Error state from Laravel */
        .is-invalid {
            border-color: rgba(255, 100, 100, 0.6) !important;
        }

        .invalid-feedback {
            font-size: 12px;
            color: rgba(255, 150, 150, 0.9);
            margin-top: 5px;
            display: none;
        }

        .is-invalid~.invalid-feedback {
            display: block;
        }
    </style>
</head>

<body>
    <div id="app" class="md:w-[30%]">
        <div class="login-card">

            <div class="brand-logos my-2 flex justify-start">
                <div>
                    <img src="{{ asset('img/sahabat-mascot.png') }}" width="150px">

                </div>
            </div>

            <h1 class="login-heading">Hai, Selamat Datang!</h1>
            <p class="login-sub">Silahkan Login Untuk Memulai Shift</p>

            @if (session('warning') || session('status') || session('message'))
                <div style="background: rgba(254, 243, 199, 0.95); border: 1px solid #f59e0b; color: #92400e; border-radius: 12px; padding: 10px 14px; font-size: 12.5px; margin: 1rem 0; display: flex; align-items: center; gap: 8px; line-height: 1.4;">
                    <i class="fas fa-exclamation-triangle" style="color: #d97706; font-size: 15px; flex-shrink: 0;"></i>
                    <span>{{ session('warning') ?? session('status') ?? session('message') }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="field">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <input id="username" type="text" name="username" placeholder="Enter your username"
                            tabindex="1" required autofocus
                            class="{{ $errors->has('username') ? 'is-invalid' : '' }}">
                        <i class="fas fa-user"></i>
                    </div>
                    @error('username')
                        <div class="invalid-feedback" style="display:block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input id="password" type="password" name="password" placeholder="Enter your password"
                            tabindex="2" required class="{{ $errors->has('password') ? 'is-invalid' : '' }}">
                        <i class="fas fa-lock"></i>
                    </div>
                    @error('password')
                        <div class="invalid-feedback" style="display:block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="remember-row">
                    <input type="checkbox" name="remember" id="remember-me" tabindex="3">
                    <label for="remember-me">Remember me</label>
                </div>

                <button type="submit" class="btn-login" tabindex="4">
                    Sign In
                </button>

            </form>

            <p class="footer-text">Copyright 2026 &copy; Apotek Sahabat</p>
        </div>
    </div>

    <script src="{{ asset('templates/library/jquery/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('templates/js/stisla.js') }}"></script>
    <script src="{{ asset('templates/js/scripts.js') }}"></script>
    <script src="{{ asset('templates/js/custom.js') }}"></script>
</body>

</html>
