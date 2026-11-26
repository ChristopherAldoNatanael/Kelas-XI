<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Alergi hewan. Ditampilkan sebagai peringatan di form resep supaya obat yang
 * sudah pernah menimbulkan reaksi tidak diresepkan lagi tanpa disadari.
 */
class PetAllergy extends Model
{
    protected $fillable = [
        'pet_id',
        'allergen',
        'reaction',
        'severity',
        'is_active',
        'noted_by',
        'noted_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'noted_at' => 'datetime',
    ];

    public function pet()
    {
        return $this->belongsTo(Pet::class);
    }
}
