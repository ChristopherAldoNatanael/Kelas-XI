<?php

namespace App\Traits;

/**
 * Trait for models that have a photo attribute.
 * Provides a consistent way to get the full URL for photos.
 */
trait HasPhotoUrl
{
    /**
     * Get the full URL for the photo attribute.
     * Handles both local storage paths and external URLs.
     *
     * @return string|null
     */
    public function getPhotoUrlAttribute(): ?string
    {
        if (!$this->photo) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (filter_var($this->photo, FILTER_VALIDATE_URL)) {
            return $this->photo;
        }

        // Clean up the path
        $photo = ltrim($this->photo, '/');
        $photo = preg_replace('#^storage/#', '', $photo);
        $photo = preg_replace('#^public/#', '', $photo);

        return asset('storage/' . $photo);
    }
}
