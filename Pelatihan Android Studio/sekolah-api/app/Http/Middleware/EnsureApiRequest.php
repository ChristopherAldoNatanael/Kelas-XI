<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiRequest
{
    /**
     * Handle an incoming request.
     * Memastikan bahwa request ke API adalah request yang valid dari aplikasi mobile atau API client
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Jika request ke API endpoint
        if ($request->is('api/*')) {
            $acceptHeader = $request->header('Accept', '');
            $userAgent = $request->header('User-Agent', '');
            $contentType = $request->header('Content-Type', '');

            // Cek apakah ini request yang valid dari aplikasi mobile/API client
            $isValidApiRequest =
                str_contains($acceptHeader, 'application/json') || // Request expects JSON
                str_contains($contentType, 'application/json') || // Sending JSON
                str_contains($userAgent, 'okhttp') || // Android HTTP client
                str_contains($userAgent, 'Retrofit') || // Retrofit client
                str_contains($userAgent, 'Postman') || // Postman
                str_contains($userAgent, 'curl') || // cURL
                str_contains($userAgent, 'insomnia') || // Insomnia
                $request->ajax() || // AJAX request
                $request->wantsJson(); // Laravel's built-in JSON detection

            // Jika bukan request API yang valid dan tampaknya dari browser
            if (!$isValidApiRequest && str_contains($acceptHeader, 'text/html')) {
                // Redirect ke halaman web yang sesuai
                if (!Auth::check()) {
                    return redirect()->route('login')
                        ->with('warning', 'Akses langsung ke API tidak diizinkan. Silakan gunakan interface web.');
                }

                return redirect()->route('dashboard')
                    ->with('info', 'Anda telah diarahkan ke dashboard web.');
            }
        }

        return $next($request);
    }
}
