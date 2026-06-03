<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS hanya di production, atau kalau APP_URL pakai ngrok/https
        // Di local dengan APP_URL=http://127.0.0.1:8000 → TIDAK force HTTPS
        $appUrl = config('app.url', '');
        $isProduction = config('app.env') === 'production';
        $isNgrok = str_contains($appUrl, 'ngrok');
        $isHttpsUrl = str_starts_with($appUrl, 'https://');

        if ($isProduction || ($isNgrok && $isHttpsUrl)) {
            URL::forceScheme('https');
        }
        
        // Configure rate limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
        
        RateLimiter::for('api-heavy', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
