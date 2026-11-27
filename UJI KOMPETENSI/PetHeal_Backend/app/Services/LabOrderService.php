<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabResult;
use App\Models\LabSpecimen;
use App\Models\MedicalRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alur laboratorium: order -> item -> spesimen -> hasil -> verifikasi.
 *
 * Status order, item, spesimen, dan hasil tidak saling menimpa. Hasil legacy
 * tidak dapat langsung diverifikasi; koreksi resmi membuat baris baru.
 */
class LabOrderService
{
    public function __construct(private readonly LabReferenceService $references)
    {
    }

    public function create(MedicalRecord $record, array $data, User $actor): LabOrder
    {
        $this->assertClinic($record->clinic_id, $actor);

        $items = $data['items'] ?? [];

        if (! is_array($items) || $items === []) {
            throw new MedicalRecordLifecycleException(
                'LAB_ORDER_ITEMS_REQUIRED',
                'Order laboratorium wajib memiliki minimal satu pemeriksaan.',
                422
            );
        }

        return DB::transaction(function () use ($record, $data, $items, $actor) {
            $record = MedicalRecord::whereKey($record->id)->lockForUpdate()->firstOrFail();

            $priority = in_array(($data['priority'] ?? ''), LabOrder::PRIORITIES, true)
                ? $data['priority']
                : LabOrder::PRIORITY_ROUTINE;

            $order = LabOrder::create([
                'clinic_id' => $record->clinic_id,
                'medical_record_id' => $record->id,
                'encounter_id' => $record->encounter_id,
                'ordered_by' => $actor->id,
                'ordered_at' => now(),
                'priority' => $priority,
                'status' => LabOrder::STATUS_ORDERED,
                'clinical_notes' => $this->text($data['clinical_notes'] ?? null),
            ]);

            foreach ($items as $item) {
                $name = trim((string) ($item['test_name'] ?? ''));

                if ($name === '') {
                    throw new MedicalRecordLifecycleException(
                        'LAB_ORDER_ITEM_NAME_REQUIRED',
                        'Nama pemeriksaan laboratorium wajib diisi.',
                        422
                    );
                }

                LabOrderItem::create([
                    'clinic_id' => $record->clinic_id,
                    'lab_order_id' => $order->id,
                    'test_code' => $this->text($item['test_code'] ?? null, 40),
                    'test_name' => mb_substr($name, 0, 120),
                    'specimen_type' => $this->text($item['specimen_type'] ?? null, 60),
                    'status' => LabOrderItem::STATUS_ORDERED,
                    'notes' => $this->text($item['notes'] ?? null),
                ]);
            }

            return $order->load(['items', 'specimens', 'results']);
        });
    }

    public function collectSpecimen(LabOrder $order, array $data, User $actor): LabSpecimen
    {
        $this->assertClinic($order->clinic_id, $actor);

        if ($order->status === LabOrder::STATUS_CANCELLED) {
            throw new MedicalRecordLifecycleException(
                'LAB_ORDER_CANCELLED',
                'Spesimen tidak dapat dikumpulkan untuk order yang dibatalkan.',
                409
            );
        }

        $type = trim((string) ($data['specimen_type'] ?? ''));

        if ($type === '') {
            throw new MedicalRecordLifecycleException(
                'LAB_SPECIMEN_TYPE_REQUIRED',
                'Jenis spesimen wajib diisi.',
                422
            );
        }

        return DB::transaction(function () use ($order, $data, $actor, $type) {
            $order = LabOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $collectedAt = ! empty($data['collected_at'])
                ? \Illuminate\Support\Carbon::parse($data['collected_at'])
                : now();

            $specimen = LabSpecimen::create([
                'clinic_id' => $order->clinic_id,
                'lab_order_id' => $order->id,
                'accession_number' => $this->accessionNumber($order->clinic_id),
                'specimen_type' => mb_substr($type, 0, 60),
                'collected_at' => $collectedAt,
                'collected_by' => $actor->id,
                'status' => LabSpecimen::STATUS_COLLECTED,
            ]);

            $this->refreshOrderStatus($order);

            return $specimen;
        });
    }

    public function transitionSpecimen(
        LabSpecimen $specimen,
        string $status,
        User $actor,
        ?string $rejectionReason = null
    ): LabSpecimen {
        $this->assertClinic($specimen->clinic_id, $actor);

        if (! in_array($status, [
            LabSpecimen::STATUS_RECEIVED,
            LabSpecimen::STATUS_REJECTED,
            LabSpecimen::STATUS_PROCESSED,
        ], true)) {
            throw new MedicalRecordLifecycleException(
                'LAB_SPECIMEN_STATUS_INVALID',
                'Status spesimen tidak valid.',
                422
            );
        }

        if ($specimen->status === LabSpecimen::STATUS_REJECTED) {
            throw new MedicalRecordLifecycleException(
                'LAB_SPECIMEN_ALREADY_REJECTED',
                'Spesimen yang sudah ditolak tidak dapat diproses lagi.',
                409
            );
        }

        if ($status === LabSpecimen::STATUS_REJECTED && trim((string) $rejectionReason) === '') {
            throw new MedicalRecordLifecycleException(
                'LAB_SPECIMEN_REJECTION_REASON_REQUIRED',
                'Alasan penolakan spesimen wajib diisi.',
                422
            );
        }

        $specimen->forceFill([
            'status' => $status,
            'rejection_reason' => $status === LabSpecimen::STATUS_REJECTED
                ? mb_substr(trim((string) $rejectionReason), 0, 255)
                : $specimen->rejection_reason,
        ])->save();

        $this->refreshOrderStatus($specimen->order);

        return $specimen->refresh();
    }

    public function markItemCollected(LabOrderItem $item, User $actor): LabOrderItem
    {
        $this->assertClinic($item->clinic_id, $actor);

        if ($item->status === LabOrderItem::STATUS_CANCELLED) {
            throw new MedicalRecordLifecycleException(
                'LAB_ORDER_ITEM_CANCELLED',
                'Pemeriksaan yang dibatalkan tidak dapat dikumpulkan.',
                409
            );
        }

        $item->forceFill(['status' => LabOrderItem::STATUS_COLLECTED])->save();
        $this->refreshOrderStatus($item->order);

        return $item->refresh();
    }

    public function recordResult(
        LabOrder $order,
        LabOrderItem $item,
        array $data,
        User $actor
    ): LabResult {
        $this->assertClinic($order->clinic_id, $actor);

        if ((int) $item->lab_order_id !== (int) $order->id) {
            throw new MedicalRecordLifecycleException(
                'LAB_ORDER_ITEM_MISMATCH',
                'Pemeriksaan tidak termasuk order ini.',
                404
            );
        }

        if ($item->status === LabOrderItem::STATUS_CANCELLED) {
            throw new MedicalRecordLifecycleException(
                'LAB_ORDER_ITEM_CANCELLED',
                'Pemeriksaan yang dibatalkan tidak dapat diberi hasil.',
                409
            );
        }

        $value = trim((string) ($data['value'] ?? ''));

        if ($value === '') {
            throw new MedicalRecordLifecycleException(
                'LAB_RESULT_VALUE_REQUIRED',
                'Nilai hasil laboratorium wajib diisi.',
                422
            );
        }

        $parameter = trim((string) ($data['parameter'] ?? ''));

        if ($parameter === '') {
            throw new MedicalRecordLifecycleException(
                'LAB_RESULT_PARAMETER_REQUIRED',
                'Nama parameter hasil laboratorium wajib diisi.',
                422
            );
        }

        return DB::transaction(function () use ($order, $item, $data, $actor, $value, $parameter) {
            $order = LabOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $item = LabOrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $record = $order->medicalRecord()->with('pet')->firstOrFail();

            $specimen = null;
            if (! empty($data['lab_specimen_id'])) {
                $specimen = LabSpecimen::whereKey($data['lab_specimen_id'])
                    ->where('lab_order_id', $order->id)
                    ->first();

                if (! $specimen) {
                    throw new MedicalRecordLifecycleException(
                        'LAB_SPECIMEN_MISMATCH',
                        'Spesimen tidak termasuk order ini.',
                        404
                    );
                }
            }

            $testCode = $item->test_code ?: (string) ($data['test_code'] ?? '');
            $testCode = $testCode !== '' ? $testCode : Str::slug($item->test_name);
            $method = $this->text($data['method'] ?? null, 80);
            $reference = $this->references->resolve(
                $record->pet,
                $order->clinic_id,
                $testCode,
                $parameter,
                $method,
                $this->text($data['analyzer'] ?? null, 80),
                $item->specimen_type ?: ($specimen?->specimen_type)
            );

            $flag = $reference['reference_status'] === LabResult::REFERENCE_MATCHED
                ? $this->references->flagForValue($value, $reference['reference_low'], $reference['reference_high'])
                : null;

            $isAdditional = (bool) ($data['is_additional'] ?? false);
            if ($isAdditional && $actor->role !== 'doctor') {
                throw new MedicalRecordLifecycleException(
                    'LAB_ADDITIONAL_APPROVER_REQUIRED',
                    'Temuan tambahan harus disetujui dokter.',
                    403
                );
            }

            $result = LabResult::create([
                'clinic_id' => $order->clinic_id,
                'medical_record_id' => $order->medical_record_id,
                'lab_order_id' => $order->id,
                'lab_order_item_id' => $item->id,
                'lab_specimen_id' => $specimen?->id,
                'test_name' => $item->test_name,
                'parameter' => mb_substr($parameter, 0, 120),
                'species' => $record->pet?->species,
                'value' => mb_substr($value, 0, 60),
                'unit' => $this->text($data['unit'] ?? null, 30) ?: $reference['unit'],
                'reference_range' => $reference['reference_range'],
                'reference_low' => $reference['reference_low'],
                'reference_high' => $reference['reference_high'],
                'reference_text' => $reference['reference_text'],
                'reference_status' => $reference['reference_status'],
                'reference_range_id' => $reference['reference_range_id'],
                'flag' => $flag,
                'status' => LabResult::STATUS_PRELIMINARY,
                'method' => $method,
                'collected_at' => $specimen?->collected_at ?: data_get($data, 'collected_at'),
                'resulted_at' => now(),
                'performed_at' => now(),
                'is_additional' => $isAdditional,
                'additional_approved_by' => $isAdditional ? $actor->id : null,
                'notes' => $this->text($data['notes'] ?? null),
            ]);

            $item->forceFill(['status' => LabOrderItem::STATUS_RESULTED])->save();
            $this->refreshOrderStatus($order);

            return $result;
        });
    }

    public function verifyResult(LabResult $result, User $actor): LabResult
    {
        $this->assertClinic($result->clinic_id, $actor);

        if ($actor->role !== 'doctor') {
            throw new MedicalRecordLifecycleException(
                'LAB_VERIFIER_NOT_DOCTOR',
                'Hanya dokter hewan yang dapat memverifikasi hasil laboratorium.',
                403
            );
        }

        if ($result->status === LabResult::STATUS_LEGACY) {
            throw new MedicalRecordLifecycleException(
                'LAB_RESULT_LEGACY_REQUIRES_CORRECTION',
                'Hasil legacy tidak dapat langsung diverifikasi; buat koreksi resmi.',
                409
            );
        }

        if (in_array($result->status, [
            LabResult::STATUS_VERIFIED,
            LabResult::STATUS_CORRECTED,
        ], true)) {
            throw new MedicalRecordLifecycleException(
                'LAB_RESULT_ALREADY_VERIFIED',
                'Hasil laboratorium ini sudah final.',
                409
            );
        }

        if ($result->status === LabResult::STATUS_CANCELLED || $result->voided_at) {
            throw new MedicalRecordLifecycleException(
                'LAB_RESULT_NOT_VERIFIABLE',
                'Hasil laboratorium ini tidak dapat diverifikasi.',
                409
            );
        }

        $result->forceFill([
            'status' => LabResult::STATUS_VERIFIED,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ])->save();

        return $result->refresh();
    }

    public function correctResult(
        LabResult $original,
        array $data,
        string $reason,
        User $actor
    ): LabResult {
        $reason = trim($reason);

        if ($reason === '') {
            throw new MedicalRecordLifecycleException(
                'LAB_CORRECTION_REASON_REQUIRED',
                'Alasan koreksi hasil laboratorium wajib diisi.',
                422
            );
        }

        $this->assertClinic($original->clinic_id, $actor);

        if ($actor->role !== 'doctor') {
            throw new MedicalRecordLifecycleException(
                'LAB_CORRECTION_NOT_DOCTOR',
                'Koreksi hasil laboratorium hanya dapat dilakukan dokter hewan.',
                403
            );
        }

        if (! in_array($original->status, [
            LabResult::STATUS_LEGACY,
            LabResult::STATUS_VERIFIED,
            LabResult::STATUS_CORRECTED,
        ], true)) {
            throw new MedicalRecordLifecycleException(
                'LAB_CORRECTION_NOT_ALLOWED',
                'Hanya hasil legacy/final yang dapat dikoreksi.',
                409
            );
        }

        $record = $original->record()->with('pet')->firstOrFail();
        $item = $original->orderItem;
        $testCode = $item?->test_code ?: Str::slug($original->test_name);
        $parameter = trim((string) ($data['parameter'] ?? $original->parameter));
        $method = $this->text($data['method'] ?? $original->method, 80);
        $reference = $this->references->resolve(
            $record->pet,
            $original->clinic_id,
            $testCode,
            $parameter,
            $method,
            $this->text($data['analyzer'] ?? null, 80),
            $item?->specimen_type
        );

        $value = trim((string) ($data['value'] ?? $original->value));
        $flag = $reference['reference_status'] === LabResult::REFERENCE_MATCHED
            ? $this->references->flagForValue($value, $reference['reference_low'], $reference['reference_high'])
            : null;

        return LabResult::create([
            'clinic_id' => $original->clinic_id,
            'medical_record_id' => $original->medical_record_id,
            'lab_order_id' => $original->lab_order_id,
            'lab_order_item_id' => $original->lab_order_item_id,
            'lab_specimen_id' => $original->lab_specimen_id,
            'test_name' => $original->test_name,
            'parameter' => mb_substr($parameter, 0, 120),
            'species' => $original->species ?: $record->pet?->species,
            'value' => mb_substr($value, 0, 60),
            'unit' => $this->text($data['unit'] ?? $original->unit, 30),
            'reference_range' => $reference['reference_range'],
            'reference_low' => $reference['reference_low'],
            'reference_high' => $reference['reference_high'],
            'reference_text' => $reference['reference_text'],
            'reference_status' => $reference['reference_status'],
            'reference_range_id' => $reference['reference_range_id'],
            'flag' => $flag,
            'status' => LabResult::STATUS_CORRECTED,
            'method' => $method,
            'collected_at' => $original->collected_at,
            'resulted_at' => now(),
            'performed_at' => now(),
            'verified_at' => now(),
            'verified_by' => $actor->id,
            'correction_of_id' => $original->id,
            'correction_reason' => mb_substr($reason, 0, 255),
            'notes' => $this->text($data['notes'] ?? $original->notes),
        ])->refresh();
    }

    private function refreshOrderStatus(LabOrder $order): void
    {
        $order->refresh();

        if ($order->status === LabOrder::STATUS_CANCELLED) {
            return;
        }

        $items = $order->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $allResulted = $items->every(fn (LabOrderItem $item) => $item->status === LabOrderItem::STATUS_RESULTED);
        $anyStarted = $items->contains(fn (LabOrderItem $item) => in_array($item->status, [
            LabOrderItem::STATUS_COLLECTED,
            LabOrderItem::STATUS_RESULTED,
        ], true)) || $order->specimens()->exists();

        $status = $allResulted
            ? LabOrder::STATUS_COMPLETED
            : ($anyStarted ? LabOrder::STATUS_PARTIALLY_COLLECTED : LabOrder::STATUS_ORDERED);

        $order->forceFill(['status' => $status])->save();
    }

    private function accessionNumber(int $clinicId): string
    {
        do {
            $number = sprintf('LAB-%d-%s-%s', $clinicId, now()->format('Ymd'), Str::upper(Str::random(6)));
        } while (LabSpecimen::where('clinic_id', $clinicId)->where('accession_number', $number)->exists());

        return $number;
    }

    private function text(mixed $value, int $max = 20000): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function assertClinic(int $clinicId, User $actor): void
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $clinicId) {
            throw new MedicalRecordLifecycleException(
                'LAB_CLINIC_MISMATCH',
                'Data laboratorium hanya boleh diakses pada klinik yang sama.',
                404
            );
        }
    }
}
