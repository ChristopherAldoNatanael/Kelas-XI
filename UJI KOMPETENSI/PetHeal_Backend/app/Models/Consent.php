<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persetujuan pemrosesan data pribadi (UU 27/2022).
 *
 * Baris tidak dihapus saat pemilik mencabut persetujuan; `revoked_at` diisi
 * agar riwayat persetujuan tetap bisa dibuktikan.
 */
class Consent extends Model
{
    protected $fillable = [
        'user_id',
        'purpose',
        'policy_version',
        'policy_hash',
        'granted_at',
        'revoked_at',
        'withdrawn_at',
        'withdrawal_reason',
        'evidence_path',
        'privacy_request_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNotNull('granted_at')->whereNull('revoked_at');
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null || $this->revoked_at !== null;
    }
}
