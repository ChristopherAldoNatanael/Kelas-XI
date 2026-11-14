<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyelaraskan origin aset dengan host yang benar-benar dipakai browser.
 *
 * `ASSET_URL` di .env kerap diisi alamat lokal (http://127.0.0.1:8000) agar
 * gambar bisa dibuka dari emulator. Akibatnya, saat aplikasi dibuka lewat
 * tunnel HTTPS (ngrok), seluruh `asset()` — termasuk @vite dan logo klinik —
 * tetap menunjuk ke 127.0.0.1 dan diblokir browser sebagai mixed content.
 *
 * Middleware ini hanya mengganti origin bila konfigurasi menunjuk loopback,
 * sehingga CDN/asset URL khusus (mis. S3) tidak pernah ditimpa. Akses lokal
 * tetap memakai konfigurasi apa adanya.
 */
class UseRequestAssetOrigin
{
    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    public function handle(Request $request, Closure $next): Response
    {
        $this->alignAssetOrigin($request);

        return $next($request);
    }

    private function alignAssetOrigin(Request $request): void
    {
        $configured = (string) config('app.asset_url');
        if ($configured === '') {
            return;
        }

        $configuredHost = parse_url($configured, PHP_URL_HOST);
        if (!is_string($configuredHost)
            || !in_array(strtolower($configuredHost), self::LOOPBACK_HOSTS, true)) {
            return;
        }

        $requestHost = strtolower($request->getHost());
        $scheme = $this->resolveScheme($request, $requestHost);

        // Akses langsung ke server lokal: biarkan konfigurasi loopback dipakai.
        if (in_array($requestHost, self::LOOPBACK_HOSTS, true)
            && $scheme === parse_url($configured, PHP_URL_SCHEME)) {
            return;
        }

        $origin = $scheme . '://' . $request->getHttpHost();

        URL::useAssetOrigin($origin);

        // Disk "public" juga menyimpan URL absolut dari APP_URL; samakan agar
        // Storage::url() tidak menghasilkan mixed content saat lewat tunnel.
        $publicUrl = (string) config('filesystems.disks.public.url');
        $publicHost = parse_url($publicUrl, PHP_URL_HOST);

        if (is_string($publicHost)
            && in_array(strtolower($publicHost), self::LOOPBACK_HOSTS, true)) {
            config(['filesystems.disks.public.url' => $origin . '/storage']);
        }
    }

    private function resolveScheme(Request $request, string $host): string
    {
        // Ngrok selalu mengakhiri TLS. Bila header X-Forwarded-Proto tidak
        // sampai ke PHP (mis. host di-rewrite), tetap paksa https agar aset
        // tidak diblokir sebagai mixed content.
        foreach (['.ngrok-free.dev', '.ngrok-free.app', '.ngrok.io', '.ngrok.app'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return 'https';
            }
        }

        return $request->getScheme();
    }
}
