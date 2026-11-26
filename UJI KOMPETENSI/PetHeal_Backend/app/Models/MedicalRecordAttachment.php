<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Lampiran rekam medis: foto rontgen, hasil laboratorium, atau foto kondisi.
 *
 * Berkas disimpan di disk privat (bukan `public`), sehingga tidak bisa dibuka
 * hanya dengan menebak URL. `url` yang dikirim ke klien adalah URL bertanda
 * tangan berumur pendek yang dibuat saat diminta, bukan tautan permanen.
 *
 * `path` adalah path relatif di disk privat. URL publik dibangun lewat
 * accessor `url` supaya klien (Android) tidak perlu tahu struktur storage —
 * sama seperti foto hewan, tetapi dengan kontrol akses.
 */
class MedicalRecordAttachment extends Model
{
    use SoftDeletes;

    /** Disk privat tempat berkas medis disimpan. */
    public const DISK = 'local';

    protected $fillable = [
        'medical_record_id',
        'path',
        'original_name',
        'caption',
        'mime_type',
        'size_bytes',
        'sha256',
        'uploaded_by',
    ];

    protected $appends = ['url'];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    /**
     * URL bertanda tangan untuk mengunduh berkas. Null bila file sudah tidak
     * ada di disk, supaya UI bisa menampilkan status "berkas hilang" alih-alih
     * gambar rusak.
     */
    public function getUrlAttribute(): ?string
    {
        if (!$this->path || !Storage::disk(self::DISK)->exists($this->path)) {
            return null;
        }

        return URL::temporarySignedRoute(
            'medical-records.attachments.download',
            now()->addMinutes((int) config('medical_records.attachment_url_ttl_minutes', 30)),
            [
                'record' => $this->medical_record_id,
                'attachment' => $this->id,
            ]
        );
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
