<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `petheal:tunnel-url` dipakai saat demo lewat ngrok. Yang diuji hanya dua
 * hal yang mudah salah dan berdampak besar:
 *   - hanya `APP_URL` + `ASSET_URL` yang berubah (kredensial tidak boleh ikut);
 *   - perintah gagal dengan rapi kalau ngrok/API tidak tersedia.
 */
class SyncTunnelUrlTest extends TestCase
{
    private string $envPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->envPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'petheal-env-' . uniqid() . '.env';

        file_put_contents($this->envPath, implode(PHP_EOL, [
            'APP_NAME=PetHeal',
            'APP_ENV=local',
            'APP_URL=http://127.0.0.1:8000',
            'MAIL_PASSWORD=rahasia-jangan-disentuh',
            'ASSET_URL=http://127.0.0.1:8000',
            '',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->envPath);

        parent::tearDown();
    }

    public function test_it_replaces_only_the_two_url_keys(): void
    {
        Http::fake([
            '*/api/tunnels' => Http::response([
                'tunnels' => [
                    ['name' => 'command_line', 'public_url' => 'http://contoh.ngrok-free.dev', 'proto' => 'http'],
                    ['name' => 'command_line', 'public_url' => 'https://contoh.ngrok-free.dev', 'proto' => 'https'],
                ],
            ]),
        ]);

        Artisan::call('petheal:tunnel-url', ['--env-file' => $this->envPath]);

        $env = file_get_contents($this->envPath);

        $this->assertStringContainsString('APP_URL=https://contoh.ngrok-free.dev', $env);
        $this->assertStringContainsString('ASSET_URL=https://contoh.ngrok-free.dev', $env);
        $this->assertStringContainsString('APP_ENV=local', $env);
        $this->assertStringContainsString('MAIL_PASSWORD=rahasia-jangan-disentuh', $env);
        $this->assertStringNotContainsString('http://127.0.0.1:8000', $env);
    }

    public function test_dry_run_does_not_touch_the_file(): void
    {
        Http::fake([
            '*/api/tunnels' => Http::response([
                'tunnels' => [['public_url' => 'https://contoh.ngrok-free.dev']],
            ]),
        ]);

        Artisan::call('petheal:tunnel-url', ['--env-file' => $this->envPath, '--dry-run' => true]);

        $this->assertStringContainsString('APP_URL=http://127.0.0.1:8000', file_get_contents($this->envPath));
    }

    public function test_it_fails_cleanly_when_ngrok_is_not_running(): void
    {
        Http::fake([
            '*/api/tunnels' => Http::response('connection refused', 502),
        ]);

        $this->assertSame(1, Artisan::call('petheal:tunnel-url', ['--env-file' => $this->envPath]));
        $this->assertStringContainsString('APP_URL=http://127.0.0.1:8000', file_get_contents($this->envPath));
    }

    public function test_it_fails_cleanly_when_no_https_tunnel_exists(): void
    {
        Http::fake([
            '*/api/tunnels' => Http::response(['tunnels' => [['public_url' => 'http://tanpa-https.ngrok-free.dev']]]),
        ]);

        $this->assertSame(1, Artisan::call('petheal:tunnel-url', ['--env-file' => $this->envPath]));
        $this->assertStringContainsString('APP_URL=http://127.0.0.1:8000', file_get_contents($this->envPath));
    }
}
