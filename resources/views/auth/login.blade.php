<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <link rel="icon" type="image/png" href="{{ asset('img/walikota.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>Login &mdash; {{ config('app.name', 'Propharma') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=DM+Serif+Display&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
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
            --primary-blue: #1e40af;
            --accent-blue: #3b82f6;
            --light-blue: #60a5fa;
            --glass-bg: rgba(255, 255, 255, 0.14);
            --glass-border: rgba(255, 255, 255, 0.28);
            --glass-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #091e42 0%, #0d3880 25%, #1565C0 60%, #1e88e5 85%, #42a5f5 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
        }

        /* Ambient floating light blobs */
        .ambient-blob {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            opacity: 0.45;
            pointer-events: none;
            z-index: 0;
            animation: floatBlob 12s ease-in-out infinite alternate;
        }

        .blob-1 {
            width: 520px;
            height: 520px;
            background: #2563eb;
            top: -160px;
            left: -160px;
            animation-duration: 14s;
        }

        .blob-2 {
            width: 440px;
            height: 440px;
            background: #38bdf8;
            bottom: -120px;
            right: -120px;
            animation-duration: 16s;
            animation-delay: -4s;
        }

        .blob-3 {
            width: 320px;
            height: 320px;
            background: #6366f1;
            top: 45%;
            left: 65%;
            animation-duration: 18s;
            animation-delay: -8s;
        }

        @keyframes floatBlob {
            0% {
                transform: translate(0, 0) scale(1);
            }

            50% {
                transform: translate(35px, -25px) scale(1.08);
            }

            100% {
                transform: translate(-25px, 35px) scale(0.95);
            }
        }

        .login-wrapper {
            width: 100%;
            max-width: 430px;
            position: relative;
            z-index: 10;
            margin: auto;
        }

        .login-card {
            width: 100%;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 28px;
            backdrop-filter: blur(28px);
            -webkit-backdrop-filter: blur(28px);
            box-shadow: var(--glass-shadow), inset 0 1px 1px 0 rgba(255, 255, 255, 0.4);
            padding: 2.25rem 2.25rem 2rem;
            position: relative;
            animation: slideUpFade 0.65s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes slideUpFade {
            from {
                opacity: 0;
                transform: translateY(24px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #e0f2fe;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 0.85rem;
        }

        .mascot-img {
            max-width: 135px;
            height: auto;
            filter: drop-shadow(0 10px 18px rgba(0, 0, 0, 0.2));
            transition: transform 0.3s ease;
        }

        .mascot-img:hover {
            transform: scale(1.04) rotate(-1deg);
        }

        .login-heading {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.4px;
            line-height: 1.25;
            margin-bottom: 0.35rem;
        }

        .login-sub {
            font-size: 13.5px;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.45;
            margin-bottom: 1.5rem;
        }

        .field {
            margin-bottom: 1.15rem;
        }

        .field label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.85);
            margin-bottom: 7px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 14px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 14px;
            pointer-events: none;
            transition: color 0.2s;
        }

        .input-wrap input {
            width: 100%;
            padding: 12.5px 42px 12.5px 42px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 14px;
            color: #ffffff;
            font-size: 14px;
            font-family: inherit;
            font-weight: 500;
            outline: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .input-wrap input::placeholder {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 400;
        }

        .input-wrap input:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.65);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15);
        }

        .input-wrap input:focus~.input-icon-left,
        .input-wrap:focus-within .input-icon-left {
            color: #ffffff;
        }

        .toggle-password-btn {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.55);
            font-size: 14px;
            cursor: pointer;
            padding: 6px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .toggle-password-btn:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.15);
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 1.1rem 0 1.4rem;
        }

        .remember-checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
        }

        .remember-checkbox-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #3b82f6;
            cursor: pointer;
            border-radius: 4px;
        }

        .remember-checkbox-group span {
            font-size: 13px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.8);
        }

        .btn-login {
            width: 100%;
            padding: 13.5px;
            background: #ffffff;
            color: #0f3780;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-login:hover {
            background: #f0f7ff;
            color: #0a2558;
            transform: translateY(-1.5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.22);
        }

        .btn-login:active {
            transform: translateY(0);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
        }

        .btn-login:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Modern Glass Alert */
        .glass-alert {
            background: rgba(245, 158, 11, 0.18);
            border: 1px solid rgba(245, 158, 11, 0.45);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: #fef3c7;
            border-radius: 14px;
            padding: 11px 14px;
            font-size: 13px;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.45;
            animation: fadeIn 0.3s ease;
        }

        .glass-alert-icon {
            color: #fcd34d;
            font-size: 16px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .is-invalid {
            border-color: rgba(248, 113, 113, 0.75) !important;
            background: rgba(239, 68, 68, 0.12) !important;
        }

        .invalid-feedback {
            font-size: 12px;
            font-weight: 500;
            color: #fca5a5;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .footer-text {
            text-align: center;
            margin-top: 1.65rem;
            font-size: 12px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.45);
            letter-spacing: 0.2px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-4px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <!-- Ambient glowing backgrounds -->
    <div class="ambient-blob blob-1"></div>
    <div class="ambient-blob blob-2"></div>
    <div class="ambient-blob blob-3"></div>

    <div class="login-wrapper">
        <div class="login-card">

            <!-- Mascot & Brand -->
            <div class="flex items-center justify-between mb-2">
                <img src="{{ asset('img/sahabat-mascot.png') }}" alt="Apotek Sahabat Mascot" class="mascot-img">

            </div>

            <h1 class="login-heading">Hai, Selamat Datang!</h1>
            <p class="login-sub">Silakan masuk dengan akun Anda untuk memulai operasional shift.</p>

            <!-- Flash Warnings / Session Expiry Alerts -->
            @if (session('warning') || session('status') || session('message'))
                <div class="glass-alert">
                    <i class="fas fa-exclamation-triangle glass-alert-icon"></i>
                    <div>
                        <span>{{ session('warning') ?? (session('status') ?? session('message')) }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="glass-alert"
                    style="background: rgba(239, 68, 68, 0.18); border-color: rgba(239, 68, 68, 0.45); color: #fee2e2;">
                    <i class="fas fa-circle-exclamation glass-alert-icon" style="color: #fca5a5;"></i>
                    <div>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <!-- Login Form -->
            <form method="POST" action="{{ route('login') }}" id="loginForm" autocomplete="on">
                @csrf

                <!-- Username Field -->
                <div class="field">
                    <label for="username">Username</label>
                    <div class="input-wrap">
                        <input id="username" type="text" name="username" placeholder="Masukkan username"
                            value="{{ old('username') }}" tabindex="1" required autofocus
                            class="{{ $errors->has('username') ? 'is-invalid' : '' }}" autocomplete="username">
                        <i class="fas fa-user input-icon-left"></i>
                    </div>
                    @error('username')
                        <div class="invalid-feedback">
                            <i class="fas fa-circle-exclamation text-[10px]"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Password Field with Visibility Toggle -->
                <div class="field">
                    <label for="password">Password / PIN</label>
                    <div class="input-wrap">
                        <input id="password" type="password" name="password" placeholder="Masukkan password atau PIN"
                            tabindex="2" required class="{{ $errors->has('password') ? 'is-invalid' : '' }}"
                            autocomplete="current-password">
                        <i class="fas fa-lock input-icon-left"></i>
                        <button type="button" id="togglePasswordBtn" class="toggle-password-btn"
                            title="Lihat / Sembunyikan Password" tabindex="-1">
                            <i class="fas fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="invalid-feedback">
                            <i class="fas fa-circle-exclamation text-[10px]"></i>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="remember-row">
                    <label class="remember-checkbox-group" for="remember-me">
                        <input type="checkbox" name="remember" id="remember-me" tabindex="3"
                            {{ old('remember') ? 'checked' : '' }}>
                        <span>Ingat saya di perangkat ini</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login" id="submitBtn" tabindex="4">
                    <span id="btnText"><i class="fas fa-arrow-right-to-bracket mr-1"></i> Masuk ke Sistem</span>
                    <span id="btnLoading" style="display: none;">
                        <i class="fas fa-circle-notch fa-spin mr-1.5"></i> Memproses...
                    </span>
                </button>
            </form>

            <p class="footer-text">Copyright &copy; {{ date('Y') }} Apotek Sahabat. All rights reserved.</p>
        </div>
    </div>

    <!-- Scripts -->
    <script src="{{ asset('templates/library/jquery/dist/jquery.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password Show / Hide Toggle
            const togglePasswordBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('password');
            const togglePasswordIcon = document.getElementById('togglePasswordIcon');

            if (togglePasswordBtn && passwordInput && togglePasswordIcon) {
                togglePasswordBtn.addEventListener('click', function() {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    togglePasswordIcon.classList.toggle('fa-eye', !isPassword);
                    togglePasswordIcon.classList.toggle('fa-eye-slash', isPassword);
                });
            }

            // Form Submit Loading State
            const loginForm = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnLoading = document.getElementById('btnLoading');

            if (loginForm && submitBtn) {
                loginForm.addEventListener('submit', function() {
                    submitBtn.disabled = true;
                    if (btnText) btnText.style.display = 'none';
                    if (btnLoading) btnLoading.style.display = 'inline-flex';
                });
            }
        });
    </script>
</body>

</html>
