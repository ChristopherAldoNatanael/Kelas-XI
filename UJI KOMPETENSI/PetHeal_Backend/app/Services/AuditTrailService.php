<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Audit akses tanpa menyalin isi klinis atau token.
 */
class AuditTrailService
{
    private const SAFE_METADATA_KEYS = [
        'format',
        'purpose',
        'version_id',
        'export_id',
        'record_id',
        'recipient_type',
        'token_ttl_minutes',
        'access_count',
        'request_source',
        'request_type',
        'status',
        'legal_hold',
    ];

    public function record(
        string $action,
        ?Model $model,
        ?User $actor,
        string $result,
        ?string $reason,
        array $metadata = [],
        ?int $clinicId = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actor?->id ?: auth()->id(),
            'clinic_id' => $clinicId ?? currentClinicId(),
            'action' => mb_substr($action, 0, 255),
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->getKey(),
            'description' => null,
            'ip_address' => $ip ?: request()->ip(),
            'user_agent' => $userAgent ?: request()->userAgent(),
            'request_id' => $this->requestId(),
            'result' => in_array($result, ['allowed', 'denied', 'failed'], true) ? $result : 'failed',
            'reason' => $reason === null ? null : mb_substr($reason, 0, 120),
            'metadata' => $this->safeMetadata($metadata),
        ]);
    }

    private function requestId(): string
    {
        $incoming = trim((string) request()->header('X-Request-Id', ''));

        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._:-]{1,64}$/', $incoming)) {
            return $incoming;
        }

        $key = '_petheal_audit_request_id';

        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, Str::random(32));
        }

        return (string) request()->attributes->get($key);
    }

    private function safeMetadata(array $metadata): ?array
    {
        $safe = [];

        foreach (self::SAFE_METADATA_KEYS as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];

            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe === [] ? null : $safe;
    }
}
