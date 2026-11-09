<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'price',
        'duration',
        'category',
        'service_category_id',
        'is_active',
        'clinic_id',
    ];

    protected function casts(): array
    {
        return [
            'price'               => 'decimal:2',
            'duration'            => 'integer',
            'is_active'           => 'boolean',
            'service_category_id' => 'integer',
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }

    public function serviceCategory()
    {
        return $this->belongsTo(ServiceCategory::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
