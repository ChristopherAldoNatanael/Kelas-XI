<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'clinic_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Jejak perubahan terakhir untuk satu record.
     *
     * Dipakai halaman detail/ubah untuk menjawab "siapa mengubah ini terakhir"
     * tanpa membuka halaman audit log terpisah (lihat UIX-02 pada
     * docs/AUDIT_FIXES_REPORT.md). Bila `$actions` diisi, hanya aksi tersebut
     * yang dipertimbangkan.
     */
    public static function latestFor(?Model $model, array $actions = [])
    {
        if (!$model || !$model->exists) {
            return null;
        }

        return static::query()
            ->with('user:id,name')
            ->where('model_type', $model::class)
            ->where('model_id', $model->getKey())
            ->when($actions, fn ($query) => $query->whereIn('action', $actions))
            ->latest('id')
            ->first();
    }

    public static function log($action, $description = null, $model = null, $userId = null)
    {
        return static::create([
            // `$userId` eksplisit dipakai untuk aksi API (pasien check-in),
            // karena guard default `auth()->id()` bisa null di request Sanctum.
            'user_id' => $userId ?: auth()->id(),
            'clinic_id' => currentClinicId(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->id : null,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
