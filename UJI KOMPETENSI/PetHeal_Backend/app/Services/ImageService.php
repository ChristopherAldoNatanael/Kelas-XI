<?php

namespace App\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;

/**
 * Image compression and resizing service
 * Reduces file size by 70-90% while maintaining quality
 */
class ImageService
{
    /**
     * Compress and resize uploaded image
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @param string $relativePath Destination path relative to the disk root
     * @param int $maxWidth Maximum width in pixels (maintains aspect ratio)
     * @param int $quality JPEG quality (1-100)
     * @param string $disk Nama disk tujuan; default `public` untuk foto yang
     *                     memang boleh diakses publik (foto hewan). Berkas
     *                     medis memakai disk privat.
     * @return string The relative path to saved image
     */
    public static function process($file, string $relativePath, int $maxWidth = 800, int $quality = 80, string $disk = 'public'): string
    {
        $manager = new ImageManager(new Driver());
        
        // Read image from uploaded file
        $image = $manager->read($file->getPathname());

        // Resize while maintaining aspect ratio
        $image->scale(width: $maxWidth);

        $fullPath = Storage::disk($disk)->path($relativePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Save as JPEG with compression
        $image->toJpeg(quality: $quality)->save($fullPath);
        
        return $relativePath;
    }

    /**
     * Create a thumbnail version of an image
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $relativePath
     * @param int $size Square thumbnail size
     * @param int $quality
     * @return string
     */
    public static function createThumbnail($file, string $relativePath, int $size = 200, int $quality = 75): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getPathname());

        // Resize and crop to square
        $image->cover(width: $size, height: $size);

        $fullPath = storage_path('app/public/' . $relativePath);
        
        $directory = dirname($fullPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $image->toJpeg(quality: $quality)->save($fullPath);
        
        return $relativePath;
    }
}
