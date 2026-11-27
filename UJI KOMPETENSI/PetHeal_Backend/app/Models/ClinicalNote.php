<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan perkembangan klinis.
 *
 * Catatan `signed` tidak boleh diedit. Koreksi membuat catatan baru bertipe
 * `correction` yang menunjuk catatan lama; catatan lama tetap `signed` atau
 * di-void dengan alasan.
 */
class ClinicalNote extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_VOID = 'void';

    public const TYPE_PROGRESS = 'progress';
    public const TYPE_MONITORING = 'monitoring';
    public const TYPE_PROCEDURE = 'procedure';
    public const TYPE_NURSING = 'nursing';
    public const TYPE_SHIFT = 'shift';
    public const TYPE_INPATIENT = 'inpatient';
    public const TYPE_DISCHARGE = 'discharge';
    public const TYPE_CORRECTION = 'correction';

    public const SHIFT_DAY = 'pagi';
    public const SHIFT_EVENING = 'siang';
    public const SHIFT_NIGHT = 'malam';

    public const SHIFTS = [
        self::SHIFT_DAY,
        self::SHIFT_EVENING,
        self::SHIFT_NIGHT,
    ];

    public const TYPES = [
        self::TYPE_PROGRESS,
        self::TYPE_MONITORING,
        self::TYPE_PROCEDURE,
        self::TYPE_NURSING,
        self::TYPE_SHIFT,
        self::TYPE_INPATIENT,
        self::TYPE_DISCHARGE,
        self::TYPE_CORRECTION,
    ];

    protected $fillable = [
        'clinic_id',
        'encounter_id',
        'medical_record_id',
        'note_type',
        'shift',
        'status',
        'subjective',
        'objective',
        'assessment',
        'plan',
        'body',
        'recorded_by',
        'recorded_at',
        'signed_by',
        'signed_at',
        'content_hash',
        'correction_of_id',
        'void_reason',
        'voided_at',
        'voided_by',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'signed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(ClinicalEncounter::class);
    }

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_id');
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED && $this->signed_at !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function hasCorrections(): bool
    {
        return static::where('correction_of_id', $this->id)->exists();
    }

    protected static function booted(): void
    {
        static::updating(function (ClinicalNote $note) {
            if ($note->getOriginal('status') !== self::STATUS_SIGNED) {
                return;
            }

            $allowed = [
                'status', 'void_reason', 'voided_at', 'voided_by', 'updated_at',
            ];

            foreach (array_keys($note->getDirty()) as $column) {
                if (! in_array($column, $allowed, true)) {
                    throw new \RuntimeException(
                        'Catatan klinis yang sudah ditandatangani tidak boleh diubah; buat koreksi baru.'
                    );
                }
            }

            if ($note->status === self::STATUS_VOID
                && (! $note->void_reason || ! $note->voided_at || ! $note->voided_by)) {
                throw new \RuntimeException('Void catatan klinis wajib menyertakan alasan dan pelaku.');
            }

            if (! in_array($note->status, [self::STATUS_SIGNED, self::STATUS_VOID], true)) {
                throw new \RuntimeException('Status catatan bertanda tangan tidak valid.');
            }
        });

        static::deleting(function (ClinicalNote $note) {
            if ($note->getOriginal('status') === self::STATUS_SIGNED) {
                throw new \RuntimeException('Catatan klinis yang sudah ditandatangani tidak boleh dihapus.');
            }
        });
    }
}
