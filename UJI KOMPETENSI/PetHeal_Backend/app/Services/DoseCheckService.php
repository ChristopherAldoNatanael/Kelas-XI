<?php

namespace App\Services;

use App\Models\DoseReference;
use App\Models\Medicine;
use App\Models\Pet;

/**
 * Pemeriksaan dosis non-blocking. Hanya memakai referensi aktif milik klinik.
 * Tidak ada angka generik lintas spesies.
 */
class DoseCheckService
{
    public function check(array $row, ?Pet $pet, Medicine $medicine): array
    {
        $reference = DoseReference::query()
            ->where('clinic_id', $medicine->clinic_id)
            ->where('species', $pet?->species ?? '')
            ->where(function ($query) use ($medicine) {
                $query->where('medicine_name', $medicine->name);
                if ($medicine->drug_class) {
                    $query->orWhere('drug_class', $medicine->drug_class);
                }
            })
            ->when(! empty($row['route']), fn ($q) => $q->where(function ($inner) use ($row) {
                $inner->whereNull('route')->orWhere('route', $row['route']);
            }))
            ->active()
            ->orderByRaw('medicine_name = ? DESC', [$medicine->name])
            ->orderByDesc('effective_from')
            ->first();

        if (! $reference) {
            return [
                'dose_check_status' => 'not_checked',
                'dose_check_notes' => 'Tidak ada referensi dosis aktif untuk spesies/obat ini.',
                'dose_reference_id' => null,
                'dose_reference_source' => null,
                'calculated_dose' => null,
            ];
        }

        $weight = $row['weight_at_prescription'] ?? $pet?->weight;
        $doseValue = isset($row['dose_value']) && $row['dose_value'] !== '' ? (float) $row['dose_value'] : null;
        $basis = $row['dose_basis'] ?? $reference->dose_basis ?? 'fixed';
        $calculated = null;
        $status = 'ok';
        $notes = [];

        if ($doseValue === null) {
            return [
                'dose_check_status' => 'not_checked',
                'dose_check_notes' => "Referensi {$reference->source} tersedia, tetapi nilai dosis belum diisi.",
                'dose_reference_id' => $reference->id,
                'dose_reference_source' => $reference->source,
                'calculated_dose' => null,
            ];
        }

        $compare = $doseValue;

        if ($basis === 'mg_per_kg') {
            if ($weight === null || (float) $weight <= 0) {
                return [
                    'dose_check_status' => 'warning',
                    'dose_check_notes' => 'Dosis berbasis mg/kg tetapi berat hewan tidak tersedia.',
                    'dose_reference_id' => $reference->id,
                    'dose_reference_source' => $reference->source,
                    'calculated_dose' => null,
                ];
            }

            $calculated = round($doseValue * (float) $weight, 4);
            $compare = $doseValue;
        } else {
            $calculated = $doseValue;
        }

        if ($reference->dose_min !== null && $compare + 0.00001 < (float) $reference->dose_min) {
            $status = 'warning';
            $notes[] = "Di bawah rentang referensi ({$reference->dose_min}-{$reference->dose_max} {$reference->dose_unit}).";
        }

        if ($reference->dose_max !== null && $compare - 0.00001 > (float) $reference->dose_max) {
            $status = 'warning';
            $notes[] = "Di atas rentang referensi ({$reference->dose_min}-{$reference->dose_max} {$reference->dose_unit}).";
        }

        if ($reference->max_dose !== null && $calculated !== null
            && (float) $calculated - 0.00001 > (float) $reference->max_dose) {
            $status = 'warning';
            $notes[] = "Melebihi batas maksimum {$reference->max_dose} {$reference->dose_unit}.";
        }

        return [
            'dose_check_status' => $status,
            'dose_check_notes' => $notes === []
                ? "Dalam rentang referensi {$reference->source}."
                : implode(' ', $notes),
            'dose_reference_id' => $reference->id,
            'dose_reference_source' => $reference->source,
            'calculated_dose' => $calculated,
        ];
    }
}
