<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Auth\AuthenticationException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Tangani Page Expired (419 / CSRF Token Mismatch) secara halus
        if ($e instanceof TokenMismatchException) {
            // Jika user sebenarnya masih login, jangan arahkan ke /login (yang akan bounce ke /home).
            // Arahkan kembali ke halaman aktif agar token CSRF diperbarui tanpa membuang user ke /home.
            if (auth()->check()) {
                $targetUrl = $request->headers->get('referer')
                    ?: ($request->is('finance*') ? route('finance.kasBank') : url()->previous());

                if ($request->inertia()) {
                    return redirect()->to($targetUrl)->withErrors([
                        '_token' => 'Sesi formulir telah diperbarui. Silakan klik Simpan sekali lagi.',
                    ]);
                }

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => 'Sesi formulir telah diperbarui. Silakan coba lagi.',
                        'csrf_refreshed' => true,
                    ], 419);
                }

                return redirect()->to($targetUrl)->with('warning', 'Sesi formulir telah diperbarui. Silakan kirim ulang.');
            }

            // 1. Tangani request Inertia terlebih dahulu
            if ($request->inertia()) {
                return \Inertia\Inertia::location(route('login'));
            }

            // 2. Tangani request AJAX murni atau API (Non-Inertia)
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Sesi Anda telah berakhir. Silakan login kembali.',
                    'session_expired' => true,
                    'redirect' => route('login'),
                ], 419);
            }

            // 3. Tangani request web tradisional
            return redirect()->route('login')->with('warning', 'Sesi Anda telah berakhir karena tidak aktif atau telah logout di tab lain. Silakan login kembali.');
        }

        return parent::render($request, $e);
    }
}
