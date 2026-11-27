<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\Clinic;
use App\Models\ClinicalTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClinicalTemplateService
{
    public function publish(Clinic $clinic, array $data, User $actor): ClinicalTemplate
    {
        $this->assertClinic($clinic->id, $actor);

        $key = trim((string) ($data['template_key'] ?? ''));

        if (! preg_match('/^[a-z0-9][a-z0-9_.-]{1,79}$/', $key)) {
            throw new MedicalRecordLifecycleException(
                'TEMPLATE_KEY_INVALID',
                'template_key wajib huruf kecil, angka, titik, garis bawah, atau strip.',
                422
            );
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new MedicalRecordLifecycleException('TEMPLATE_NAME_REQUIRED', 'Nama template wajib diisi.', 422);
        }

        $schema = $data['schema'] ?? null;

        if (is_string($schema)) {
            $schema = json_decode($schema, true);
        }

        if (! is_array($schema) || ! isset($schema['fields']) || ! is_array($schema['fields']) || $schema['fields'] === []) {
            throw new MedicalRecordLifecycleException(
                'TEMPLATE_SCHEMA_INVALID',
                'Schema template wajib memiliki daftar fields.',
                422
            );
        }

        foreach ($schema['fields'] as $field) {
            if (! is_array($field) || empty($field['key']) || empty($field['label']) || empty($field['type'])) {
                throw new MedicalRecordLifecycleException(
                    'TEMPLATE_SCHEMA_FIELD_INVALID',
                    'Setiap field wajib memiliki key, label, dan type.',
                    422
                );
            }
        }

        return DB::transaction(function () use ($clinic, $data, $actor, $key, $name, $schema) {
            $current = ClinicalTemplate::where('clinic_id', $clinic->id)
                ->where('template_key', $key)
                ->lockForUpdate()
                ->orderByDesc('version')
                ->first();

            if ($current) {
                $current->forceFill([
                    'is_current' => false,
                    'active_key' => null,
                ])->save();
            }

            return ClinicalTemplate::create([
                'clinic_id' => $clinic->id,
                'template_key' => $key,
                'name' => mb_substr($name, 0, 150),
                'species' => $this->text($data['species'] ?? null, 40),
                'version' => $current ? $current->version + 1 : 1,
                'schema' => $schema,
                'is_current' => true,
                'active_key' => $clinic->id . ':' . $key,
                'created_by' => $actor->id,
            ])->refresh();
        });
    }

    public function current(Clinic $clinic, string $key, User $actor): ClinicalTemplate
    {
        $this->assertClinic($clinic->id, $actor);

        $template = ClinicalTemplate::where('clinic_id', $clinic->id)
            ->where('template_key', $key)
            ->where('is_current', true)
            ->first();

        if (! $template) {
            throw new MedicalRecordLifecycleException('TEMPLATE_NOT_FOUND', 'Template klinis tidak ditemukan.', 404);
        }

        return $template;
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function assertClinic(?int $clinicId, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $clinicId) {
            throw new MedicalRecordLifecycleException(
                'TEMPLATE_CLINIC_MISMATCH',
                'Template hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }
}
