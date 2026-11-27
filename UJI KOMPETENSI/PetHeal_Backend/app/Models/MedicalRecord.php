<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class MedicalRecord extends Model
{
    /** Draf belum sah; tidak boleh tampil ke pemilik. */
    public const STATUS_DRAFT = 'draft';

    /** Sedang ditinjau reviewer klinis. */
    public const STATUS_IN_REVIEW = 'in_review';

    /** Rekaman yang sudah disahkan dan menjadi dokumen sah. */
    public const STATUS_FINAL = 'final';

    /** Rekaman yang dibatalkan; tetap tersimpan untuk retensi & audit. */
    public const STATUS_VOID = 'void';

    /** Rekaman yang sudah dipindahkan ke arsip (bukan dihapus). */
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'booking_id',
        'encounter_id',
        'pet_id',
        'doctor_id',
        // PHASE 1 (C4): column exists (2026_10_02_000006, backfilled in
        // 000007). Previously missing here, so Admin/MedicalRecordController
        // silently dropped `clinic_id` on create and clinic-scoped queries
        // could never match those rows.
        'clinic_id',
        // Siklus hidup dokumen. `status` disimpan agar rekaman yang sudah
        // dibatalkan tidak hilang dan tetap bisa diaudit.
        'status',
        'created_by',
        'updated_by',
        'finalized_at',
        'finalized_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'content_hash',
        'current_version_id',
        'version',
        'in_review_at',
        'in_review_by',
        'last_amended_at',
        'retention_until',
        'legal_hold',
        'legal_hold_reason',
        'legal_hold_set_at',
        'legal_hold_set_by',
        'legal_hold_release_requested_at',
        'legal_hold_release_requested_by',
        'legal_hold_release_reason',
        'legal_hold_release_approved_at',
        'legal_hold_release_approved_by',
        'template_key',
        'template_version',
        'archived_at',
        'outcome',
        'cause_of_death',
        'euthanasia_reason',
        'disposal_method',
        // SOAP — catatan terstruktur. `diagnosis`/`treatment` tetap dipertahankan
        // sebagai ringkasan wajib agar rekam medis lama dan ekspor tidak berubah.
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
        'reminder_sent',
        'cost',
        'treatment_cost',
        'medicine_cost',
        'total_medical_cost',
        'extra_payment_amount',
        'extra_payment_paid_amount',
        'extra_payment_status',
        'extra_payment_order_id',
        'extra_payment_date',
    ];

    protected $casts = [
        'next_visit_date' => 'date:Y-m-d',
        'next_visit_time' => 'string',
        'reminder_sent'   => 'boolean',
        'finalized_at'    => 'datetime',
        'voided_at'       => 'datetime',
        'archived_at'     => 'datetime',
        'in_review_at'    => 'datetime',
        'last_amended_at' => 'datetime',
        'retention_until' => 'date',
        'legal_hold'      => 'boolean',
        'legal_hold_set_at' => 'datetime',
        'legal_hold_release_requested_at' => 'datetime',
        'legal_hold_release_approved_at' => 'datetime',
        'version'         => 'integer',
        'template_version'=> 'integer',
        'cost' => 'decimal:2',
        'treatment_cost' => 'decimal:2',
        'medicine_cost' => 'decimal:2',
        'total_medical_cost' => 'decimal:2',
        'extra_payment_amount' => 'decimal:2',
        'extra_payment_paid_amount' => 'decimal:2',
        'extra_payment_date' => 'datetime',
    ];

    public function recalculatePaymentState(float $bookingPaidAmount = 0): void
    {
        $consultationCost = (float) ($this->cost ?? 0);
        $treatmentCost = (float) ($this->treatment_cost ?? 0);
        $medicineCost = (float) ($this->medicine_cost ?? 0);
        $totalMedicalCost = $consultationCost + $treatmentCost + $medicineCost;
        $extraAmount = max(0, $totalMedicalCost - $bookingPaidAmount);
        $paidExtra = min((float) ($this->extra_payment_paid_amount ?? 0), $extraAmount);

        $this->total_medical_cost = $totalMedicalCost;
        $this->extra_payment_amount = $extraAmount;
        $this->extra_payment_paid_amount = $paidExtra;

        if ($extraAmount <= 0) {
            $this->extra_payment_status = 'not_required';
            $this->extra_payment_order_id = null;
            $this->extra_payment_date = null;
        } elseif ($paidExtra >= $extraAmount) {
            $this->extra_payment_status = 'paid';
        } elseif ($paidExtra > 0) {
            // Sebagian sudah dibayar. Tanpa status ini, filter `partial` di
            // daftar rekam medis tidak pernah cocok dengan baris mana pun.
            $this->extra_payment_status = 'partial';
        } elseif ($this->extra_payment_status !== 'pending') {
            $this->extra_payment_status = 'unpaid';
        }
    }

    /**
     * Hitung ulang biaya tambahan untuk satu booking setelah `paid_amount`
     * berubah karena pembayaran.
     *
     * `extra_payment_amount` = total biaya medis - uang yang sudah masuk ke
     * booking. Nilainya dulu hanya dihitung saat rekam medis dibuat/diedit,
     * sehingga pembayaran yang datang BELAKANGAN (pelunasan DP, konfirmasi kas)
     * tidak pernah menurunkannya: pasien yang sudah membayar tetap ditagih sisa
     * sebesar pembayaran yang sudah masuk. Semua jalur settlement memanggil
     * method ini agar angka tagihan selalu mengikuti uang yang benar-benar
     * diterima.
     */
    public static function syncExtraPaymentForBooking(Booking $booking): void
    {
        $records = static::where('booking_id', $booking->id)->get();

        foreach ($records as $record) {
            $paidExtraBefore = (float) ($record->extra_payment_paid_amount ?? 0);

            $record->recalculatePaymentState((float) $booking->paid_amount);
            $record->save();

            // Uang tambahan yang sudah masuk melebihi biaya (mis. tagihan lama
            // dibayar sebelum data diperbaiki) bukan pendapatan: catat supaya
            // muncul sebagai pekerjaan refund, bukan hilang diam-diam.
            if ($paidExtraBefore > (float) $record->extra_payment_amount + 0.005) {
                Log::warning('Pembayaran tambahan rekam medis melebihi biaya; perlu ditinjau untuk refund', [
                    'medical_record_id' => $record->id,
                    'booking_id' => $booking->id,
                    'extra_paid_before' => $paidExtraBefore,
                    'extra_amount_now' => (float) $record->extra_payment_amount,
                ]);
            }
        }
    }

    /**
     * Apakah isi klinis boleh ditampilkan ke pemilik.
     *
     * Isi rekam medis adalah hak pemilik. Penahanan isi klinis karena tagihan
     * hanya boleh terjadi bila klinik secara eksplisit menyalakannya lewat
     * `config('medical_records.gate_clinical_content')`; default-nya terbuka.
     * Yang tetap ditagih adalah pembayaran tambahan, bukan informasi medis.
     */
    public function getCanViewFullRecordAttribute(): bool
    {
        if (!config('medical_records.gate_clinical_content', false)) {
            return true;
        }

        return in_array($this->extra_payment_status, ['not_required', 'paid'], true);
    }

    /**
     * Null out clinical fields while the record is still locked behind the
     * extra-payment gate.
     *
     * MedicalRecordController::serializeRecord applies this gate explicitly.
     * Endpoints that return the model raw (API dashboard, booking detail, pet
     * detail) must call this, otherwise the gate is bypassed and a locked
     * diagnosis leaks to the client.
     */
    public function maskClinicalFields(): self
    {
        if ($this->can_view_full_record) {
            return $this;
        }

        $this->setAttribute('diagnosis', null);
        $this->setAttribute('treatment', null);
        $this->setAttribute('medicine', null);
        $this->setAttribute('notes', null);
        // Tanggal kontrol adalah instruksi medis, bukan data tagihan: jangan
        // pernah disembunyikan meski klinik memilih menahan detail klinis.
        $this->setAttribute('subjective', null);
        $this->setAttribute('objective', null);
        $this->setAttribute('assessment', null);
        $this->setAttribute('plan', null);

        // Lampiran adalah bukti pemeriksaan; sembunyikan saat terkunci.
        $this->unsetRelation('attachments');
        $this->setRelation('attachments', collect());

        return $this;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * @deprecated Gunakan scopeActive(); dipertahankan untuk pemanggil lama.
     */
    public function scopeNotVoided($query)
    {
        return $query->where('status', '!=', self::STATUS_VOID);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_VOID, self::STATUS_ARCHIVED]);
    }

    /**
     * Cap isi dokumen. Dipakai untuk membuktikan rekaman yang dicetak tidak
     * berubah sejak disahkan.
     */
    public function computeContentHash(): string
    {
        $this->loadMissing(['prescriptionItems', 'vitals', 'vitalMeasurements', 'labResults', 'treatmentConsents', 'welfareAssessments', 'referrals', 'inpatientStay.locationLogs', 'inpatientStay.medicationAdministrations', 'notifiableDiseaseReports', 'attachments']);

        $payload = [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'pet_id' => $this->pet_id,
            'doctor_id' => $this->doctor_id,
            'clinic_id' => $this->clinic_id,
            'template_key' => $this->template_key,
            'template_version' => $this->template_version,
            'diagnosis' => $this->diagnosis,
            'treatment' => $this->treatment,
            'subjective' => $this->subjective,
            'objective' => $this->objective,
            'assessment' => $this->assessment,
            'plan' => $this->plan,
            'medicine' => $this->medicine,
            'notes' => $this->notes,
            'next_visit_date' => optional($this->next_visit_date)->toDateString(),
            'next_visit_time' => $this->next_visit_time,
            'prescription' => $this->prescriptionItems->map(fn ($i) => [
                'medicine_name' => $i->medicine_name,
                'dose' => $i->dose,
                'quantity' => (string) $i->quantity,
                'unit' => $i->unit,
            ])->values()->all(),
            'vitals' => $this->vitals ? $this->vitals->only([
                'temperature_c', 'heart_rate', 'respiratory_rate', 'crt_seconds',
                'mucosa', 'hydration', 'bcs', 'pain_score', 'weight_kg',
            ]) : null,
            'vital_series' => $this->vitalMeasurements
                ->where('status', VitalMeasurement::STATUS_RECORDED)
                ->map(fn ($measurement) => $measurement->toSeriesArray())
                ->values()
                ->all(),
            'lab_results' => $this->labResults
                ->map(fn ($result) => [
                    'id' => $result->id,
                    'lab_order_id' => $result->lab_order_id,
                    'test_name' => $result->test_name,
                    'parameter' => $result->parameter,
                    'value' => $result->value,
                    'unit' => $result->unit,
                    'reference_range' => $result->reference_range,
                    'reference_status' => $result->reference_status,
                    'flag' => $result->flag,
                    'status' => $result->status,
                    'verified_by' => $result->verified_by,
                    'verified_at' => $result->verified_at?->toDateTimeString(),
                    'correction_of_id' => $result->correction_of_id,
                ])
                ->values()
                ->all(),
            'treatment_consents' => $this->treatmentConsents
                ->map(fn ($consent) => [
                    'id' => $consent->id,
                    'consent_type' => $consent->consent_type,
                    'template_key' => $consent->template_key,
                    'template_version' => $consent->template_version,
                    'consent_text_hash' => $consent->consent_text_hash,
                    'signer_name' => $consent->signer_name,
                    'signed_at' => $consent->signed_at?->toDateTimeString(),
                    'emergency_basis' => $consent->emergency_basis,
                    'revoked_at' => $consent->revoked_at?->toDateTimeString(),
                ])->values()->all(),
            'welfare_assessments' => $this->welfareAssessments
                ->map(fn ($assessment) => [
                    'id' => $assessment->id,
                    'overall_score' => $assessment->overall_score,
                    'assessed_at' => $assessment->assessed_at?->toDateTimeString(),
                ])->values()->all(),
            'referrals' => $this->referrals
                ->map(fn ($referral) => [
                    'id' => $referral->id,
                    'direction' => $referral->direction,
                    'referral_number' => $referral->referral_number,
                    'referred_to' => $referral->referred_to,
                    'status' => $referral->status,
                    'document_hash' => $referral->document_hash,
                    'voided_at' => $referral->voided_at?->toDateTimeString(),
                ])->values()->all(),
            'notifiable_disease_reports' => $this->notifiableDiseaseReports
                ->map(fn ($report) => [
                    'id' => $report->id,
                    'disease_code' => $report->disease_code,
                    'classification' => $report->classification,
                    'status' => $report->status,
                    'report_deadline_at' => $report->report_deadline_at?->toDateTimeString(),
                    'reported_at' => $report->reported_at?->toDateTimeString(),
                    'isikhnas_reference' => $report->isikhnas_reference,
                ])->values()->all(),
            'inpatient_stay' => $this->inpatientStay ? [
                'id' => $this->inpatientStay->id,
                'status' => $this->inpatientStay->status,
                'ward' => $this->inpatientStay->ward,
                'kennel_code' => $this->inpatientStay->kennel_code,
                'admitted_at' => $this->inpatientStay->admitted_at?->toDateTimeString(),
                'discharged_at' => $this->inpatientStay->discharged_at?->toDateTimeString(),
                'medication_administrations' => $this->inpatientStay->medicationAdministrations
                    ->map(fn ($med) => [
                        'id' => $med->id,
                        'medicine_id' => $med->medicine_id,
                        'dose_value' => (string) $med->dose_value,
                        'administered_at' => $med->administered_at?->toDateTimeString(),
                        'status' => $med->status,
                    ])->values()->all(),
            ] : null,
            'attachments' => $this->attachments
                ->map(fn ($a) => [$a->original_name, $a->sha256])
                ->values()->all(),
        ];

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Sahkan rekaman dan simpan cap integritasnya.
     */
    public function finalize(?int $userId = null): void
    {
        if (config('medical_records.lifecycle_enforcement_enabled', false)
            && auth()->check()
            && auth()->user()->role !== 'doctor') {
            throw new \App\Exceptions\MedicalRecordLifecycleException(
                'ADMIN_CANNOT_FINALIZE',
                'Hanya dokter hewan berwenang yang dapat memfinalkan rekam medis.',
                403
            );
        }

        $this->status = self::STATUS_FINAL;
        $this->finalized_at = $this->finalized_at ?: now();
        $this->finalized_by = $this->finalized_by ?: $userId;
        $this->content_hash = $this->computeContentHash();
        $this->save();
    }

    /**
     * Batalkan rekaman tanpa menghapusnya. Isi tetap utuh untuk retensi,
     * ekspor, dan pemeriksaan.
     */
    public function void(string $reason, ?int $userId = null): void
    {
        $this->status = self::STATUS_VOID;
        $this->voided_at = now();
        $this->voided_by = $userId;
        $this->void_reason = $reason;
        $this->reminder_sent = true;
        $this->save();
    }

    /**
     * Lampiran pemeriksaan (rontgen/lab/foto kondisi).
     */
    public function attachments()
    {
        return $this->hasMany(MedicalRecordAttachment::class, 'medical_record_id');
    }

    /**
     * Item resep terstruktur. Menggantikan peran teks bebas `medicine` untuk
     * keperluan pemotongan stok, tetapi kolom teks lama tetap ada.
     */
    public function prescriptionItems()
    {
        return $this->hasMany(PrescriptionItem::class, 'medical_record_id');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'medical_record_id');
    }

    public function vitals()
    {
        return $this->hasOne(MedicalRecordVitals::class, 'medical_record_id');
    }

    public function vitalMeasurements()
    {
        return $this->hasMany(VitalMeasurement::class, 'medical_record_id')
            ->orderBy('measured_at');
    }

    public function revisions()
    {
        return $this->hasMany(MedicalRecordRevision::class, 'medical_record_id')->latest('id');
    }

    public function treatmentConsents()
    {
        return $this->hasMany(TreatmentConsent::class, 'medical_record_id');
    }

    public function welfareAssessments()
    {
        return $this->hasMany(AnimalWelfareAssessment::class, 'medical_record_id')
            ->orderBy('assessed_at');
    }

    public function notifiableDiseaseReports()
    {
        return $this->hasMany(NotifiableDiseaseReport::class, 'medical_record_id');
    }

    public function exports()
    {
        return $this->hasMany(MedicalRecordExport::class, 'medical_record_id');
    }

    public function inpatientStay()
    {
        return $this->hasOne(InpatientStay::class, 'medical_record_id');
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class, 'medical_record_id');
    }

    public function labResults()
    {
        return $this->hasMany(LabResult::class, 'medical_record_id');
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class, 'medical_record_id');
    }

    public function credentialSnapshots()
    {
        return $this->hasMany(MedicalRecordCredentialSnapshot::class, 'medical_record_id');
    }

    public function credentialExceptions()
    {
        return $this->hasMany(CredentialException::class, 'medical_record_id');
    }

    /** Total harga seluruh item resep. */
    public function getPrescriptionTotalAttribute(): float
    {
        return (float) $this->prescriptionItems->sum(
            fn ($item) => (float) $item->quantity * (float) $item->unit_price
        );
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Get the booking associated with this record
     */
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function encounter()
    {
        return $this->belongsTo(ClinicalEncounter::class, 'encounter_id');
    }

    public function currentVersion()
    {
        return $this->belongsTo(MedicalRecordVersion::class, 'current_version_id');
    }

    public function versions()
    {
        return $this->hasMany(MedicalRecordVersion::class)->orderBy('version_no');
    }

    public function amendments()
    {
        return $this->hasMany(MedicalRecordAmendment::class)->latest('id');
    }

    public function pendingAmendment()
    {
        return $this->hasOne(MedicalRecordAmendment::class)
            ->whereIn('status', [MedicalRecordAmendment::STATUS_REQUESTED, MedicalRecordAmendment::STATUS_APPROVED]);
    }

    /**
     * Get the pet associated with this record
     */
    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Get the doctor who created this record
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Scope for records with upcoming visits
     */
    public function scopeUpcomingVisits($query)
    {
        return $query->whereDate('next_visit_date', '>=', today())
            ->where('reminder_sent', false);
    }

    /**
     * Scope for records with visits tomorrow
     */
    public function scopeVisitsTomorrow($query)
    {
        return $query->whereDate('next_visit_date', today()->addDay())
            ->where('reminder_sent', false);
    }
}
