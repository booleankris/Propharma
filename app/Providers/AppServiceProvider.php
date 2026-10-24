<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        config(['app.locale' => 'id']);
        Carbon::setLocale('id');

        // if(env('SECURE_HTTP')) {
        //     URL::forceScheme('https');
        // }

        if (
            App::environment('production') ||
            env('SECURE_HTTP') ||
            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (function_exists('request') && (request()->isSecure() || request()->header('x-forwarded-proto') === 'https')) ||
            str_starts_with(config('app.url', ''), 'https://')
        ) {
            URL::forceScheme('https');
        }
    }
}
