<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Saat aplikasi dibuka lewat tunnel HTTPS (ngrok) sedangkan ASSET_URL masih
 * menunjuk 127.0.0.1, aset harus mengikuti host request agar tidak diblokir
 * browser sebagai mixed content.
 */
class TunnelAssetOriginTest extends TestCase
{
    public function test_asset_urls_follow_tunnel_host_when_configured_asset_url_is_loopback(): void
    {
        config(['app.asset_url' => 'http://127.0.0.1:8000']);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'contoh-tunnel.ngrok-free.dev',
                'X-Forwarded-Port' => '443',
            ])
            ->get('http://127.0.0.1:8000/admin/login');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringNotContainsString('http://127.0.0.1:8000/build', $html);
        $this->assertStringNotContainsString('http://127.0.0.1:8000/storage', $html);
        $this->assertStringNotContainsString('http://127.0.0.1:8000', $html);
        $this->assertStringContainsString('https://contoh-tunnel.ngrok-free.dev', $html);
    }
}
