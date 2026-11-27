<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ClinicalEncounter;
use App\Models\MedicalRecord;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya penulis relasi booking ↔ encounter ↔ rekam medis.
 *
 * Tiga kolom kompatibilitas tidak boleh ditulis dari controller:
 * - `clinical_encounters.booking_id` (sumber kebenaran)
 * - `medical_records.encounter_id`
 * - `medical_records.booking_id`
 * - `bookings.medical_record_id`
 *
 * Semua penulisan berjalan dalam transaksi dan memakai row lock. Bila data
 * antar tabel berbeda, service menolak dan meninggalkan jejak untuk manual
 * review; tidak ada penebakan otomatis.
 */
class RecordLinkService
{
    public function linkBooking(
        MedicalRecord $record,
        Booking $booking,
        array $encounterAttributes = []
    ): ClinicalEncounter {
        if (! $record->exists) {
            throw new RuntimeException('Rekam medis harus disimpan sebelum encounter ditautkan.');
        }

        return DB::transaction(function () use ($record, $booking, $encounterAttributes) {
            $lockedBooking = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            $lockedRecord = MedicalRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $this->assertSameClinicAndPet($lockedRecord, $lockedBooking);

            if ($lockedRecord->booking_id !== null
                && (int) $lockedRecord->booking_id !== (int) $lockedBooking->id) {
                throw new RuntimeException(
                    "Rekam medis #{$lockedRecord->id} sudah tertaut ke booking lain."
                );
            }

            $encounter = ClinicalEncounter::where('booking_id', $lockedBooking->id)
                ->lockForUpdate()
                ->first();

            if ($encounter) {
                $this->assertEncounterBelongsTo($encounter, $lockedBooking, $lockedRecord);
            } else {
                $encounterType = match ($lockedBooking->service_type) {
                    'emergency' => ClinicalEncounter::TYPE_EMERGENCY,
                    'walk_in' => ClinicalEncounter::TYPE_WALK_IN,
                    default => ClinicalEncounter::TYPE_BOOKING,
                };

                $encounter = ClinicalEncounter::create(array_merge([
                    'clinic_id' => $lockedBooking->clinic_id,
                    'pet_id' => $lockedBooking->pet_id,
                    'booking_id' => $lockedBooking->id,
                    'type' => $encounterType,
                    'status' => ClinicalEncounter::STATUS_IN_PROGRESS,
                    'attending_doctor_id' => $lockedBooking->doctor_id,
                    'triage_level' => $lockedBooking->triage_level,
                    'chief_complaint' => $lockedBooking->notes,
                    'opened_at' => now(),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ], $this->safeEncounterAttributes($encounterAttributes)));
            }

            $existingRecord = MedicalRecord::where('encounter_id', $encounter->id)
                ->whereKeyNot($lockedRecord->id)
                ->lockForUpdate()
                ->first();

            if ($existingRecord) {
                throw new RuntimeException(
                    "Encounter #{$encounter->id} sudah memiliki rekam medis #{$existingRecord->id}."
                );
            }

            $lockedRecord->forceFill([
                'encounter_id' => $encounter->id,
                'booking_id' => $lockedBooking->id,
            ])->save();

            $lockedBooking->forceFill([
                'medical_record_id' => $lockedRecord->id,
            ])->save();

            $record->setRawAttributes($lockedRecord->getAttributes(), true);
            $record->setRelation('encounter', $encounter);

            return $encounter->fresh();
        });
    }

    /**
     * Walk-in, emergency, follow-up, atau rawat inap tanpa booking.
     */
    public function openWithoutBooking(
        MedicalRecord $record,
        string $type,
        array $encounterAttributes = []
    ): ClinicalEncounter {
        if (! $record->exists) {
            throw new RuntimeException('Rekam medis harus disimpan sebelum encounter dibuat.');
        }

        if (! in_array($type, [
            ClinicalEncounter::TYPE_WALK_IN,
            ClinicalEncounter::TYPE_EMERGENCY,
            ClinicalEncounter::TYPE_FOLLOW_UP,
            ClinicalEncounter::TYPE_INPATIENT,
        ], true)) {
            throw new RuntimeException("Tipe encounter '{$type}' tidak valid untuk tanpa booking.");
        }

        return DB::transaction(function () use ($record, $type, $encounterAttributes) {
            $lockedRecord = MedicalRecord::whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedRecord->encounter_id !== null) {
                return ClinicalEncounter::findOrFail($lockedRecord->encounter_id);
            }

            if ($lockedRecord->booking_id !== null) {
                throw new RuntimeException(
                    "Rekam medis #{$lockedRecord->id} sudah memiliki booking; gunakan linkBooking()."
                );
            }

            $encounter = ClinicalEncounter::create(array_merge([
                'clinic_id' => $lockedRecord->clinic_id,
                'pet_id' => $lockedRecord->pet_id,
                'booking_id' => null,
                'type' => $type,
                'status' => ClinicalEncounter::STATUS_IN_PROGRESS,
                'attending_doctor_id' => $lockedRecord->doctor_id,
                'opened_at' => now(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ], $this->safeEncounterAttributes($encounterAttributes)));

            $lockedRecord->forceFill([
                'encounter_id' => $encounter->id,
                'booking_id' => null,
            ])->save();

            $record->setRawAttributes($lockedRecord->getAttributes(), true);
            $record->setRelation('encounter', $encounter);

            return $encounter->fresh();
        });
    }

    private function assertSameClinicAndPet(MedicalRecord $record, Booking $booking): void
    {
        if ((int) $record->clinic_id !== (int) $booking->clinic_id) {
            throw new RuntimeException(
                "Klinik rekam medis #{$record->id} tidak sama dengan klinik booking #{$booking->id}."
            );
        }

        if ((int) $record->pet_id !== (int) $booking->pet_id) {
            throw new RuntimeException(
                "Hewan rekam medis #{$record->id} tidak sama dengan hewan booking #{$booking->id}."
            );
        }
    }

    private function assertEncounterBelongsTo(
        ClinicalEncounter $encounter,
        Booking $booking,
        MedicalRecord $record
    ): void {
        if ((int) $encounter->clinic_id !== (int) $booking->clinic_id
            || (int) $encounter->pet_id !== (int) $booking->pet_id
            || (int) $encounter->booking_id !== (int) $booking->id) {
            throw new RuntimeException(
                "Encounter #{$encounter->id} tidak konsisten dengan booking #{$booking->id}."
            );
        }

        if ((int) $record->clinic_id !== (int) $encounter->clinic_id) {
            throw new RuntimeException(
                "Rekam medis #{$record->id} berada di klinik yang berbeda dari encounter #{$encounter->id}."
            );
        }
    }

    /**
     * Hanya kolom yang aman untuk diisi dari form. Relasi, klinik, hewan,
     * booking, dan status tidak boleh ditimpa dari payload.
     */
    private function safeEncounterAttributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip([
            'chief_complaint',
            'triage_level',
            'disposition',
            'supervising_doctor_id',
            'opened_at',
            'closed_at',
        ]));
    }
}
