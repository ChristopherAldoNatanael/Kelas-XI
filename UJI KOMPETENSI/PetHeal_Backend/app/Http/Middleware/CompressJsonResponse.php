<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kompresi JSON API bila klien mendukung gzip.
 *
 * Android OkHttp mengirim Accept-Encoding: gzip dan otomatis men-decompress.
 * Ini memotong payload daftar/dashboard tanpa mengubah kontrak JSON.
 */
class CompressJsonResponse
{
    private const MIN_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || ! $request->is('api/*')) {
            return $response;
        }

        if (! str_contains(strtolower((string) $request->header('Accept-Encoding', '')), 'gzip')) {
            return $response;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        if (! str_contains($contentType, 'application/json')) {
            return $response;
        }

        if ($response->headers->has('Content-Encoding') || $response->getStatusCode() !== 200) {
            return $response;
        }

        $content = (string) $response->getContent();

        if ($content === '') {
            return $response;
        }

        if (strlen($content) < self::MIN_BYTES || ! function_exists('gzencode')) {
            return $response;
        }

        $compressed = gzencode($content, 5);

        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->set('Vary', 'Accept-Encoding', false);

        return $response;
    }
}
