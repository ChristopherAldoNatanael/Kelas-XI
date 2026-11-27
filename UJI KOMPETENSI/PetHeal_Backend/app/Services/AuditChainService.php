<?php

namespace App\Services;

use App\Models\MedicalRecordRevision;
use Illuminate\Support\Carbon;

/**
 * Hash berantai untuk `medical_record_revisions`.
 *
 * Baris dianggap utuh bila SHA-256(prev_hash + kanonik(baris)) sama dengan
 * entry_hash. Baris pertama memakai 64 nol sebagai prev_hash.
 */
class AuditChainService
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public function payload(MedicalRecordRevision $revision): array
    {
        return [
            'medical_record_id' => (int) $revision->medical_record_id,
            'changed_by' => $revision->changed_by !== null ? (int) $revision->changed_by : null,
            'action' => (string) $revision->action,
            'before' => $revision->before,
            'after' => $revision->after,
            'reason' => $revision->reason,
            'ip_address' => $revision->ip_address,
            'user_agent' => $revision->user_agent,
            'created_at' => $this->timestamp($revision->created_at),
        ];
    }

    /**
     * Verifikasi rantai tanpa menulis apa pun.
     *
     * @return array{checked: int, issues: array<int, array<string, mixed>>, valid: bool}
     */
    public function verify(?int $recordId = null): array
    {
        $query = MedicalRecordRevision::query()
            ->orderBy('medical_record_id')
            ->orderBy('id');

        if ($recordId !== null) {
            $query->where('medical_record_id', $recordId);
        }

        $issues = [];
        $checked = 0;
        $currentRecord = null;
        $previousHash = self::GENESIS;

        foreach ($query->cursor() as $row) {
            if ($currentRecord !== $row->medical_record_id) {
                $currentRecord = $row->medical_record_id;
                $previousHash = self::GENESIS;
            }

            $checked++;

            if ($row->entry_hash === null || $row->prev_hash === null) {
                $issues[] = [
                    'record_id' => $row->medical_record_id,
                    'revision_id' => $row->id,
                    'error' => 'MISSING_HASH',
                ];
            } else {
                if (! hash_equals($previousHash, (string) $row->prev_hash)) {
                    $issues[] = [
                        'record_id' => $row->medical_record_id,
                        'revision_id' => $row->id,
                        'error' => 'BROKEN_PREV_HASH',
                    ];
                }

                $expected = $this->entryHash($previousHash, $this->payload($row));

                if (! hash_equals($expected, (string) $row->entry_hash)) {
                    $issues[] = [
                        'record_id' => $row->medical_record_id,
                        'revision_id' => $row->id,
                        'error' => 'ENTRY_HASH_MISMATCH',
                    ];
                }
            }

            $previousHash = (string) ($row->entry_hash ?: '');
        }

        return [
            'checked' => $checked,
            'issues' => $issues,
            'valid' => $issues === [],
        ];
    }

    public function entryHash(?string $prevHash, array $payload): string
    {
        return hash('sha256', ($prevHash ?: self::GENESIS) . '|' . $this->canonicalJson($payload));
    }

    public function canonicalJson(array $payload): string
    {
        $value = $this->canonicalize($payload);

        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function timestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->format('Y-m-d\TH:i:s\Z');
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn ($item) => $this->canonicalize($item), $value);
            }

            ksort($value, SORT_STRING);

            foreach ($value as $key => $item) {
                $value[$key] = $this->canonicalize($item);
            }

            return $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
        }

        return $value;
    }
}
