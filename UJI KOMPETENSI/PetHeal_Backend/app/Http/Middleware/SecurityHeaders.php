<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan dasar untuk seluruh respons web dan API.
 *
 * CSP dikirim report-only secara default karena panel masih memakai script
 * inline dan font/peta/frame pembayaran eksternal.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(), geolocation=(self), payment=(self), usb=()'
        );

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');
        }

        $csp = (string) config('security.csp', '');
        if ($csp !== '') {
            $header = config('security.csp_report_only', true)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $csp);
        }

        return $response;
    }
}
