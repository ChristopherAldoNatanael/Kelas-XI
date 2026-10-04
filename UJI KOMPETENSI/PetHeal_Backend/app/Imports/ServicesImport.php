<?php

namespace App\Imports;

use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ServicesImport implements ToCollection, WithHeadingRow
{
    public array $summary = [
        'total' => 0,
        'success' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    public array $errorRows = [];

    public function __construct(
        private readonly string $duplicateStrategy = 'update',
        // PHASE 1 (C5): tenant scope. Lookup + create/update are confined to
        // this clinic so Clinic A can never find/overwrite Clinic B's service
        // by name collision. Null = no tenant context (must be rejected by
        // the caller for tenant roles; super_admin overview has no target).
        private readonly ?int $clinicId = null,
    ) {
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $this->summary['total']++;

            $status = strtolower(trim((string) ($row['status'] ?? 'active')));
            $name = trim((string) ($row['name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $price = $row['price'] ?? null;
            $duration = $row['duration'] ?? null;

            $payload = [
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'price' => $price,
                'duration' => $duration !== '' ? $duration : null,
                'is_active' => $status !== 'inactive',
            ];

            $validator = Validator::make($payload + ['status' => $status], [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                // PHASE 3 (B6): align with manual create (integer|min:1) —
                // decimals/0 previously slipped through import only.
                'price' => 'required|numeric|min:0',
                'duration' => 'nullable|integer|min:1',
                'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                $this->summary['failed']++;
                $this->pushErrorRow($rowNumber, $payload, implode(', ', $validator->errors()->all()));
                continue;
            }

            // PHASE 1 (C5, fail-closed): without a tenant target, refuse to
            // touch the shared table instead of creating clinic-less orphans
            // or matching another clinic's rows.
            if (!$this->clinicId) {
                $this->summary['failed']++;
                $this->pushErrorRow($rowNumber, $payload, 'Import membutuhkan konteks klinik. Pilih klinik terlebih dahulu.');
                continue;
            }

            $existing = Service::where('clinic_id', $this->clinicId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();

            if ($existing && $this->duplicateStrategy === 'skip') {
                $this->summary['skipped']++;
                continue;
            }

            if ($existing) {
                // PHASE 3 (B6): blank CSV cells must not wipe existing data.
                // Only overwrite fields actually present in the row.
                $updatePayload = ['name' => $name, 'is_active' => $payload['is_active']];
                if ($description !== '') {
                    $updatePayload['description'] = $description;
                }
                if ($price !== null && $price !== '') {
                    $updatePayload['price'] = $price;
                }
                if ($duration !== null && $duration !== '') {
                    $updatePayload['duration'] = $duration;
                }
                $existing->update($updatePayload);
                $this->summary['updated']++;
                continue;
            }

            Service::create($payload + ['clinic_id' => $this->clinicId]);
            $this->summary['success']++;
        }
    }

    private function pushErrorRow(int $rowNumber, array $payload, string $errorMessage): void
    {
        $this->errorRows[] = [
            'row_number' => $rowNumber,
            'name' => $payload['name'] ?? '',
            'description' => $payload['description'] ?? '',
            'price' => $payload['price'] ?? '',
            'duration' => $payload['duration'] ?? '',
            'status' => ($payload['is_active'] ?? true) ? 'active' : 'inactive',
            'error_message' => $errorMessage,
        ];
    }
}
