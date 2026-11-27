<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyRequest extends Model
{
    public const TYPE_ACCESS = 'access';
    public const TYPE_CORRECTION = 'correction';
    public const TYPE_ERASURE = 'erasure';
    public const TYPE_RESTRICTION = 'restriction';
    public const TYPE_PORTABILITY = 'portability';
    public const TYPE_OBJECTION = 'objection';

    public const TYPES = [
        self::TYPE_ACCESS,
        self::TYPE_CORRECTION,
        self::TYPE_ERASURE,
        self::TYPE_RESTRICTION,
        self::TYPE_PORTABILITY,
        self::TYPE_OBJECTION,
    ];

    public const STATUS_RECEIVED = 'received';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_VERIFIED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'user_id', 'clinic_id', 'request_type', 'status', 'description',
        'requested_at', 'due_at', 'verified_at', 'completed_at',
        'handled_by', 'response_path', 'rejection_reason',
        'response_file_path', 'response_file_name', 'response_file_mime',
        'response_file_size', 'response_file_sha256', 'response_file_encrypted',
        'response_download_count', 'response_last_downloaded_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'due_at' => 'datetime',
        'verified_at' => 'datetime',
        'completed_at' => 'datetime',
        'response_last_downloaded_at' => 'datetime',
        'response_file_encrypted' => 'boolean',
        'response_file_size' => 'integer',
        'response_download_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_REJECTED], true);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_REJECTED]);
    }

    public function scopeOverdue($query)
    {
        return $query
            ->where('due_at', '<', now())
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_REJECTED]);
    }
}
