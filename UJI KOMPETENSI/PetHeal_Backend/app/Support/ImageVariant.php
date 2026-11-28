<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Thumbnail gambar publik (dokter, hewan, pengguna) agar halaman daftar tidak
 * mengunduh foto 1–2 MB hanya untuk kotak 40 px.
 *
 * File sumber tetap disimpan apa adanya. Thumbnail dibuat sekali di
 * storage/app/public/thumbnails/{width}/..., lalu dipakai ulang. Jika sumber
 * berubah (mtime lebih baru), thumbnail dibuat ulang otomatis.
 */
class ImageVariant
{
    private const MIN_WIDTH = 48;
    private const MAX_WIDTH = 640;

    public static function url(?string $photo, int $width = 96): ?string
    {
        if (! $photo) {
            return null;
        }

        if (filter_var($photo, FILTER_VALIDATE_URL)) {
            return $photo;
        }

        $width = max(self::MIN_WIDTH, min(self::MAX_WIDTH, $width));
        $relative = self::normalize($photo);

        if ($relative === null) {
            return self::originalUrl($photo);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relative)) {
            return self::originalUrl($photo);
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION)) ?: 'jpg';
        $base = preg_replace('/\.[^.]+$/', '', $relative);
        $target = "thumbnails/{$width}/{$base}.webp";

        $sourceModified = (int) $disk->lastModified($relative);
        $targetModified = $disk->exists($target) ? (int) $disk->lastModified($target) : 0;

        if ($targetModified < $sourceModified) {
            try {
                $directory = dirname($disk->path($target));
                if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                    return self::originalUrl($photo);
                }

                $manager = new ImageManager(new Driver());
                $image = $manager->read($disk->path($relative));
                $image->scale(width: min($width, $image->width()));
                $image->toWebp(quality: 78)->save($disk->path($target));
            } catch (\Throwable $e) {
                report($e);

                return self::originalUrl($photo);
            }
        }

        return $disk->url($target);
    }

    private static function normalize(string $photo): ?string
    {
        $photo = ltrim(str_replace('\\', '/', $photo), '/');
        $photo = preg_replace('#^storage/#', '', $photo) ?? $photo;
        $photo = preg_replace('#^public/#', '', $photo) ?? $photo;

        if ($photo === '' || str_contains($photo, '..')) {
            return null;
        }

        return $photo;
    }

    private static function originalUrl(string $photo): string
    {
        if (filter_var($photo, FILTER_VALIDATE_URL)) {
            return $photo;
        }

        return asset('storage/' . ltrim(preg_replace('#^(storage|public)/#', '', $photo) ?? $photo, '/'));
    }
}
