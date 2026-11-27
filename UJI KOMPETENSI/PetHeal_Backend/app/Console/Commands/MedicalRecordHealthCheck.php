<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordAttachment;
use App\Models\PrivacyRequest;
use App\Services\AuditChainService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pemeriksaan operasional read-only untuk rekam medis.
 *
 * Tidak memperbaiki data. Keluaran JSON cocok untuk monitoring/alert.
 */
class MedicalRecordHealthCheck extends Command
{
    protected $signature = 'medical-records:health-check {--json : Keluaran JSON} {--no-alert : Jangan kirim webhook}';

    protected $description = 'Periksa skema, trigger, rantai audit, retensi, PDP, dan enkripsi lampiran';

    private const REQUIRED_TABLES = [
        'medical_records',
        'medical_record_versions',
        'medical_record_revisions',
        'medical_record_attachments',
        'audit_logs',
        'privacy_requests',
    ];

    private const REQUIRED_TRIGGERS = [
        'medical_record_revisions_no_update',
        'medical_record_revisions_no_delete',
        'audit_logs_no_update',
        'audit_logs_no_delete',
    ];

    private const REQUIRED_INDEXES = [
        'idx_mr_clinic_created',
        'idx_mr_doctor_created',
        'idx_mr_clinic_status_created',
        'idx_mr_clinic_payment_created',
    ];

    public function handle(AuditChainService $chain): int
    {
        $tables = DB::select('SHOW TABLES');
        $tableNames = array_map(fn ($row) => array_values((array) $row)[0], $tables);
        $missingTables = array_values(array_diff(self::REQUIRED_TABLES, $tableNames));

        $triggerNames = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::getDatabaseName())
            ->pluck('TRIGGER_NAME')
            ->all();
        $requiredTriggers = array_merge(
            self::REQUIRED_TRIGGERS,
            (array) config('medical_records.health_extra_required_triggers', [])
        );
        $missingTriggers = array_values(array_diff($requiredTriggers, $triggerNames));

        $indexNames = DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'medical_records')
            ->pluck('INDEX_NAME')
            ->unique()
            ->all();
        $missingIndexes = array_values(array_diff(self::REQUIRED_INDEXES, $indexNames));

        $audit = $chain->verify();
        $auditValid = ($audit['valid'] ?? false) === true;

        $checks = [
            'tables' => [
                'ok' => $missingTables === [],
                'missing' => $missingTables,
            ],
            'triggers' => [
                'ok' => $missingTriggers === [],
                'missing' => $missingTriggers,
            ],
            'indexes' => [
                'ok' => $missingIndexes === [],
                'missing' => $missingIndexes,
            ],
            'audit_chain' => [
                'ok' => $auditValid,
                'checked' => $audit['checked'] ?? null,
                'issues' => $audit['issues'] ?? [],
            ],
            'retention' => [
                'missing_until' => MedicalRecord::whereNull('retention_until')->count(),
            ],
            'privacy_requests' => [
                'overdue' => PrivacyRequest::overdue()->count(),
                'open' => PrivacyRequest::open()->count(),
            ],
            'attachments' => [
                'total' => MedicalRecordAttachment::withTrashed()->count(),
                'unencrypted' => MedicalRecordAttachment::withTrashed()->whereNull('encryption_version')->count(),
            ],
        ];

        $criticalOk = $checks['tables']['ok']
            && $checks['triggers']['ok']
            && $checks['indexes']['ok']
            && $checks['audit_chain']['ok'];

        if (! $criticalOk && ! $this->option('no-alert')) {
            $this->sendAlert($checks);
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $criticalOk,
                'checks' => $checks,
                'generated_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $criticalOk ? self::SUCCESS : self::FAILURE;
        }

        $this->info('Health check rekam medis');
        $this->table(
            ['Pemeriksaan', 'Status', 'Detail'],
            [
                [
                    'Tabel wajib',
                    $checks['tables']['ok'] ? 'OK' : 'GAGAL',
                    $missingTables === [] ? 'lengkap' : implode(', ', $missingTables),
                ],
                [
                    'Trigger append-only',
                    $checks['triggers']['ok'] ? 'OK' : 'GAGAL',
                    $missingTriggers === [] ? 'aktif' : implode(', ', $missingTriggers),
                ],
                [
                    'Indeks performa',
                    $checks['indexes']['ok'] ? 'OK' : 'GAGAL',
                    $missingIndexes === [] ? 'lengkap' : implode(', ', $missingIndexes),
                ],
                [
                    'Rantai audit',
                    $checks['audit_chain']['ok'] ? 'OK' : 'GAGAL',
                    'diperiksa: ' . ($audit['checked'] ?? 0) . ', masalah: ' . count($audit['issues'] ?? []),
                ],
                ['Retensi kosong', 'INFO', (string) $checks['retention']['missing_until']],
                ['PDP terbuka', 'INFO', (string) $checks['privacy_requests']['open']],
                ['PDP lewat SLA', 'INFO', (string) $checks['privacy_requests']['overdue']],
                ['Lampiran belum terenkripsi', 'INFO', (string) $checks['attachments']['unencrypted']],
            ]
        );

        return $criticalOk ? self::SUCCESS : self::FAILURE;
    }

    private function sendAlert(array $checks): void
    {
        if (! (bool) config('medical_records.health_alert_enabled', true)) {
            return;
        }

        $webhook = trim((string) config('medical_records.health_alert_webhook', ''));

        if ($webhook === '') {
            return;
        }

        try {
            $request = Http::timeout((int) config('medical_records.health_alert_timeout', 5))
                ->acceptJson();

            $token = trim((string) config('medical_records.health_alert_token', ''));

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post($webhook, [
                'event' => 'medical_records.health_check_failed',
                'ok' => false,
                'generated_at' => now()->toIso8601String(),
                'checks' => [
                    'tables' => $checks['tables'] ?? [],
                    'triggers' => $checks['triggers'] ?? [],
                    'indexes' => $checks['indexes'] ?? [],
                    'audit_chain' => $checks['audit_chain'] ?? [],
                    'retention_missing_until' => $checks['retention']['missing_until'] ?? null,
                    'privacy_overdue' => $checks['privacy_requests']['overdue'] ?? null,
                    'attachments_unencrypted' => $checks['attachments']['unencrypted'] ?? null,
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Medical record health alert rejected', [
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Medical record health alert failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
