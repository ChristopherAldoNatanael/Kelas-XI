<?php

namespace App\Services;

use App\Models\MedicalRecord;
use App\Models\MedicalRecordRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Mencatat perubahan isi rekam medis.
 *
 * Controller memotret kondisi SEBELUM perubahan lewat `snapshot()`, lalu
 * memanggil `record()` setelah data tersimpan. Yang disimpan hanya field yang
 * benar-benar berubah, ditambah salinan resep saat ini — sehingga koreksi
 * resep tetap meninggalkan bukti obat yang pernah diberikan.
 */
class MedicalRecordRevisionService
{
    /** Field yang dianggap isi dokumen dan wajib punya riwayat. */
    private const TRACKED_FIELDS = [
        'status',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'diagnosis',
        'treatment',
        'medicine',
        'notes',
        'next_visit_date',
        'next_visit_time',
        'cost',
        'treatment_cost',
        'medicine_cost',
        'outcome',
        'cause_of_death',
        'euthanasia_reason',
        'disposal_method',
        'void_reason',
    ];

    /**
     * Potret isi rekam medis dalam bentuk yang stabil (string/array), supaya
     * mudah dibandingkan dan disimpan sebagai JSON.
     */
    public function snapshot(MedicalRecord $record): array
    {
        $record->loadMissing(['prescriptionItems', 'vitals']);

        $data = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $value = $record->{$field};

            if ($value instanceof Carbon) {
                $value = $value->toDateTimeString();
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $value = (string) $value;
            }

            $data[$field] = $value;
        }

        $data['prescription'] = $record->prescriptionItems
            ->map(fn ($item) => [
                'medicine_id' => $item->medicine_id,
                'medicine_name' => $item->medicine_name,
                'dose' => $item->dose,
                'route' => $item->route,
                'frequency' => $item->frequency,
                'duration_days' => $item->duration_days,
                'instruction' => $item->instruction,
                'quantity' => (string) $item->quantity,
                'unit' => $item->unit,
                'unit_price' => (string) $item->unit_price,
            ])
            ->values()
            ->all();

        $data['vitals'] = $record->vitals
            ? $record->vitals->only([
                'temperature_c', 'heart_rate', 'respiratory_rate', 'crt_seconds',
                'mucosa', 'hydration', 'bcs', 'pain_score', 'weight_kg', 'notes',
            ])
            : null;

        return $data;
    }

    /**
     * Simpan satu baris revisi. Mengembalikan null bila tidak ada perubahan
     * (mis. simpan ulang tanpa mengubah apa pun) agar riwayat tidak penuh
     * baris kosong.
     */
    public function record(
        MedicalRecord $record,
        string $action,
        array $before,
        array $after,
        ?string $reason = null,
        ?int $userId = null
    ): ?MedicalRecordRevision {
        $changedBefore = [];
        $changedAfter = [];

        foreach ($after as $key => $value) {
            $old = $before[$key] ?? null;

            if ($this->same($old, $value)) {
                continue;
            }

            $changedBefore[$key] = $old;
            $changedAfter[$key] = $value;
        }

        if (empty($changedAfter) && $action !== 'create') {
            return null;
        }

        return MedicalRecordRevision::create([
            'medical_record_id' => $record->id,
            'changed_by' => $userId ?: Auth::id(),
            'action' => $action,
            'before' => $changedBefore ?: null,
            'after' => $changedAfter ?: null,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    private function same($old, $new): bool
    {
        // Nilai numerik dari database bisa berupa string ("100.00") dan dari
        // form berupa float; bandingkan sebagai string ternormalisasi agar
        // tidak tercatat sebagai perubahan palsu.
        if (is_numeric($old) && is_numeric($new)) {
            return abs((float) $old - (float) $new) < 0.00001;
        }

        if (is_array($old) || is_array($new)) {
            return $old == $new;
        }

        return (string) $old === (string) $new;
    }
}
