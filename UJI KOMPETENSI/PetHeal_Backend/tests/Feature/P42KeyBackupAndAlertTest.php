<?php

namespace Tests\Feature;

use App\Services\MedicalRecordFileEncryptionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P4.2 — backup kunci offline, sidik jari kunci, dan alert health check.
 */
class P42KeyBackupAndAlertTest extends TestCase
{
    use DatabaseTransactions;

    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public function test_key_info_shows_fingerprint_and_source_without_material(): void
    {
        $service = app(MedicalRecordFileEncryptionService::class);

        $this->assertSame(16, strlen($service->keyFingerprint()));
        $this->assertContains($service->keySource(), ['APP_KEY', 'MEDICAL_RECORDS_ATTACHMENT_KEY']);

        $this->artisan('medical-records:encryption-key-info', ['--json' => true])
            ->expectsOutputToContain('"fingerprint"')
            ->assertSuccessful();
    }

    public function test_backup_command_requires_confirmation_and_writes_offline_file(): void
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'petheal-p42-' . Str::random(8) . '.json';
        $this->tempPaths[] = $file;

        $this->artisan('medical-records:backup-encryption-key', [
            '--output' => $file,
        ])->assertFailed();

        $this->assertFileDoesNotExist($file);

        $this->artisan('medical-records:backup-encryption-key', [
            '--output' => $file,
            '--confirm' => 'BACKUP',
            '--force' => true,
        ])
            ->expectsOutputToContain('Backup kunci ditulis')
            ->assertSuccessful();

        $this->assertFileExists($file);
        $payload = json_decode((string) file_get_contents($file), true);

        $this->assertSame('petheal-medical-record-attachment-key', $payload['type']);
        $this->assertSame(1, $payload['version']);
        $this->assertSame(
            app(MedicalRecordFileEncryptionService::class)->keyFingerprint(),
            $payload['fingerprint']
        );
        $this->assertNotEmpty($payload['key_material_base64']);
    }

    public function test_backup_command_refuses_to_write_inside_project(): void
    {
        $this->artisan('medical-records:backup-encryption-key', [
            '--output' => base_path('storage/p42-forbidden-key.json'),
            '--confirm' => 'BACKUP',
            '--force' => true,
        ])
            ->expectsOutputToContain('tidak boleh ditulis di dalam folder proyek')
            ->assertFailed();

        $this->assertFileDoesNotExist(base_path('storage/p42-forbidden-key.json'));
    }

    public function test_health_failure_posts_sanitized_webhook_alert(): void
    {
        config()->set('medical_records.health_extra_required_triggers', ['p42_missing_trigger']);
        config()->set('medical_records.health_alert_enabled', true);
        config()->set('medical_records.health_alert_webhook', 'https://alerts.test/p42');
        config()->set('medical_records.health_alert_token', 'p42-token');
        config()->set('medical_records.health_alert_timeout', 2);

        Http::fake([
            'https://alerts.test/p42' => Http::response(['ok' => true], 200),
        ]);

        $this->artisan('medical-records:health-check --json')
            ->expectsOutputToContain('"ok": false')
            ->assertExitCode(1);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://alerts.test/p42'
                && $request->hasHeader('Authorization', 'Bearer p42-token')
                && ($data['event'] ?? null) === 'medical_records.health_check_failed'
                && ($data['ok'] ?? null) === false
                && ($data['checks']['triggers']['ok'] ?? null) === false
                && in_array('p42_missing_trigger', $data['checks']['triggers']['missing'] ?? [], true)
                && !array_key_exists('diagnosis', $data)
                && !array_key_exists('patients', $data);
        });
    }
}
