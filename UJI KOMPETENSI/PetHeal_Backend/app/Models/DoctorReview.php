<?php

namespace App\Models;

use App\Services\ClinicRatingService;
use Illuminate\Database\Eloquent\Model;

class DoctorReview extends Model
{
    protected $fillable = [
        'doctor_id',
        'user_id',
        'booking_id',
        'rating',
        'review',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Review adalah sumber rating klinik. Sinkronisasi dilakukan pada level
     * model (bukan hanya controller) supaya pembuatan/penghapusan lewat jalur
     * mana pun tetap memperbarui `clinics.rating` dan `clinics.reviews_count`.
     */
    protected static function booted(): void
    {
        static::saved(function (self $review) {
            ClinicRatingService::refreshForDoctor((int) $review->doctor_id);
        });

        static::deleted(function (self $review) {
            ClinicRatingService::refreshForDoctor((int) $review->doctor_id);
        });
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
