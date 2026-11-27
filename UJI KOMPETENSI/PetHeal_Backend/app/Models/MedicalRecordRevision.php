<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu perubahan isi rekam medis.
 *
 * `before`/`after` hanya memuat field yang berubah, sehingga mudah dibaca
 * manusia dan tidak menggandakan seluruh isi dokumen di setiap edit.
 */
class MedicalRecordRevision extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'medical_record_id',
        'changed_by',
        'action',
        'before',
        'after',
        'reason',
        'ip_address',
        'user_agent',
        'prev_hash',
        'entry_hash',
        'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function record()
    {
        return $this->belongsTo(MedicalRecord::class, 'medical_record_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
