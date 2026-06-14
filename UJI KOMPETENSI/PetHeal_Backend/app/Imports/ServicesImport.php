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

    public function __construct(private readonly string $duplicateStrategy = 'update')
    {
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
                'price' => 'required|numeric|min:0',
                'duration' => 'nullable|numeric|min:0',
                'status' => 'nullable|in:active,inactive',
            ]);

            if ($validator->fails()) {
                $this->summary['failed']++;
                $this->pushErrorRow($rowNumber, $payload, implode(', ', $validator->errors()->all()));
                continue;
            }

            $existing = Service::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

            if ($existing && $this->duplicateStrategy === 'skip') {
                $this->summary['skipped']++;
                continue;
            }

            if ($existing) {
                $existing->update($payload);
                $this->summary['updated']++;
                continue;
            }

            Service::create($payload);
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
