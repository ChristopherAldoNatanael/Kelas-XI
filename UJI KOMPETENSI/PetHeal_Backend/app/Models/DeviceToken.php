<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    /** Token masih bisa dipakai untuk pengiriman. */
    public const STATUS_ACTIVE = 'active';

    /** Token ditolak FCM (unregistered / invalid). Tidak dikirim lagi. */
    public const STATUS_INVALID = 'invalid';

    protected $fillable = [
        'user_id',
        'token',
        'device_type',
        'status',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns this device token
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Token yang masih layak dikirimi notifikasi. */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Token yang tidak berlaku lagi → tandai supaya tidak dicoba lagi.
     */
    public function markInvalid(): void
    {
        $this->forceFill(['status' => self::STATUS_INVALID])->save();
    }
}
