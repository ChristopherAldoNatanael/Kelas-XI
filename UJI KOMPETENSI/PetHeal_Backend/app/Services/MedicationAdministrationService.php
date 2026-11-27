<?php

namespace App\Services;

use App\Exceptions\MedicalRecordLifecycleException;
use App\Models\InpatientStay;
use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MedicationAdministrationService
{
    public function administer(InpatientStay $stay, array $data, User $actor): MedicationAdministration
    {
        if ($actor->clinic_id && (int) $actor->clinic_id !== (int) $stay->clinic_id) {
            throw new MedicalRecordLifecycleException('MEDICATION_CLINIC_MISMATCH', 'Pemberian obat hanya boleh pada klinik yang sama.', 404);
        }

        if (in_array($stay->status, [InpatientStay::STATUS_DISCHARGED, InpatientStay::STATUS_DIED, InpatientStay::STATUS_TRANSFERRED], true)) {
            throw new MedicalRecordLifecycleException('INPATIENT_NOT_ACTIVE', 'Rawat inap sudah tidak aktif.', 422);
        }

        $key = trim((string) ($data['idempotency_key'] ?? ''));

        if ($key === '' || strlen($key) > 80) {
            throw new MedicalRecordLifecycleException(
                'MEDICATION_IDEMPOTENCY_KEY_REQUIRED',
                'idempotency_key wajib diisi maksimal 80 karakter.',
                422
            );
        }

        $existing = MedicationAdministration::where('idempotency_key', $key)->first();

        if ($existing) {
            if ((int) $existing->inpatient_stay_id !== (int) $stay->id) {
                throw new MedicalRecordLifecycleException('MEDICATION_IDEMPOTENCY_CONFLICT', 'idempotency_key sudah dipakai pada rawat inap lain.', 409);
            }

            return $existing;
        }

        $source = (string) ($data['order_source'] ?? MedicationAdministration::SOURCE_PRESCRIPTION);

        if (! in_array($source, MedicationAdministration::SOURCES, true)) {
            throw new MedicalRecordLifecycleException('MEDICATION_SOURCE_INVALID', 'Sumber order obat tidak valid.', 422);
        }

        $status = (string) ($data['status'] ?? MedicationAdministration::STATUS_GIVEN);

        if (! in_array($status, MedicationAdministration::STATUSES, true)) {
            throw new MedicalRecordLifecycleException('MEDICATION_STATUS_INVALID', 'Status pemberian obat tidak valid.', 422);
        }

        $medicineId = (int) ($data['medicine_id'] ?? 0);
        $medicine = Medicine::whereKey($medicineId)->where('clinic_id', $stay->clinic_id)->first();

        if (! $medicine) {
            throw new MedicalRecordLifecycleException('MEDICATION_MEDICINE_MISMATCH', 'Obat tidak berada di klinik ini.', 404);
        }

        $batchId = $data['batch_id'] ?? null;

        if ($batchId) {
            $batch = MedicineBatch::whereKey($batchId)
                ->where('clinic_id', $stay->clinic_id)
                ->where('medicine_id', $medicine->id)
                ->first();

            if (! $batch) {
                throw new MedicalRecordLifecycleException('MEDICATION_BATCH_MISMATCH', 'Batch tidak cocok dengan obat atau klinik.', 404);
            }
        }

        $itemId = $data['prescription_item_id'] ?? null;

        if ($source === MedicationAdministration::SOURCE_PRESCRIPTION) {
            if (! $itemId) {
                throw new MedicalRecordLifecycleException(
                    'MEDICATION_PRESCRIPTION_ITEM_REQUIRED',
                    'Pemberian dari resep wajib menyertakan prescription_item_id.',
                    422
                );
            }

            $item = PrescriptionItem::whereKey($itemId)
                ->whereHas('prescription', fn ($q) => $q->where('clinic_id', $stay->clinic_id))
                ->first();

            if (! $item) {
                throw new MedicalRecordLifecycleException('MEDICATION_PRESCRIPTION_MISMATCH', 'Item resep tidak berada di klinik ini.', 404);
            }
        } elseif ($itemId) {
            throw new MedicalRecordLifecycleException(
                'MEDICATION_PRESCRIPTION_ITEM_NOT_ALLOWED',
                'prescription_item_id hanya untuk sumber resep.',
                422
            );
        }

        $dose = $data['dose_value'] ?? null;

        if ($dose !== null && (! is_numeric($dose) || (float) $dose < 0)) {
            throw new MedicalRecordLifecycleException('MEDICATION_DOSE_INVALID', 'Dosis aktual tidak valid.', 422);
        }

        try {
            return DB::transaction(function () use ($stay, $data, $actor, $source, $status, $medicine, $batchId, $itemId, $dose, $key) {
                return MedicationAdministration::create([
                    'clinic_id' => $stay->clinic_id,
                    'inpatient_stay_id' => $stay->id,
                    'prescription_item_id' => $itemId,
                    'order_source' => $source,
                    'ordered_by' => $data['ordered_by'] ?? null,
                    'medicine_id' => $medicine->id,
                    'batch_id' => $batchId,
                    'dose_value' => $dose === null ? null : round((float) $dose, 3),
                    'dose_unit' => $this->text($data['dose_unit'] ?? null, 30),
                    'route' => $this->text($data['route'] ?? null, 40),
                    'administered_at' => ! empty($data['administered_at'])
                        ? \Illuminate\Support\Carbon::parse($data['administered_at'])
                        : now(),
                    'administered_by' => $actor->id,
                    'status' => $status,
                    'idempotency_key' => $key,
                    'notes' => $this->text($data['notes'] ?? null, 20000),
                ])->refresh();
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $existing = MedicationAdministration::where('idempotency_key', $key)->first();

            if ($existing && (int) $existing->inpatient_stay_id === (int) $stay->id) {
                return $existing;
            }

            throw $e;
        }
    }

    private function text(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
