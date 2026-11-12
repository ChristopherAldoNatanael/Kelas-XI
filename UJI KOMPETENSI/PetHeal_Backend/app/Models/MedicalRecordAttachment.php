<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Lampiran rekam medis: foto rontgen, hasil laboratorium, atau foto kondisi.
 *
 * `path` adalah path relatif di disk `public`. URL publik dibangun lewat
 * accessor `url` supaya klien (Android) tidak perlu tahu struktur storage —
 * sama seperti foto hewan.
 */
class MedicalRecordAttachment extends Model
{
    protected $fillable = [
        'medical_record_id',
        'path',
        'original_name',
        'caption',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected $appends = ['url'];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    /**
     * URL publik file. Null bila file sudah tidak ada di disk, supaya UI bisa
     * menampilkan status "berkas hilang" alih-alih gambar rusak.
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->path || !Storage::disk('public')->exists($this->path)) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
