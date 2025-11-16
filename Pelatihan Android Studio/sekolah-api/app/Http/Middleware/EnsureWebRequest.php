<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebRequest
{
    /**
     * Handle an incoming request.
     * Memastikan bahwa request ke route web tidak diarahkan ke API
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Jika request dimulai dengan '/api/' dan bukan dari aplikasi mobile/API client
        if ($request->is('api/*')) {
            // Cek apakah ini request yang seharusnya ke web interface
            $acceptHeader = $request->header('Accept', '');
            $userAgent = $request->header('User-Agent', '');

            // Jika request mengharapkan HTML (dari browser) tapi masuk ke API route
            if (
                str_contains($acceptHeader, 'text/html') &&
                !str_contains($acceptHeader, 'application/json') &&
                !str_contains($userAgent, 'okhttp') && // Android HTTP client
                !str_contains($userAgent, 'Retrofit') && // Retrofit client
                !str_contains($userAgent, 'Postman') && // Postman
                !str_contains($userAgent, 'curl')
            ) { // cURL

                // Redirect ke halaman login web jika belum login
                if (!Auth::check()) {
                    return redirect()->route('login')->with('error', 'Halaman yang Anda cari tidak ditemukan. Silakan login terlebih dahulu.');
                }

                // Redirect ke dashboard jika sudah login
                return redirect()->route('dashboard')->with('info', 'Anda telah diarahkan ke dashboard.');
            }
        }

        return $next($request);
    }
}
