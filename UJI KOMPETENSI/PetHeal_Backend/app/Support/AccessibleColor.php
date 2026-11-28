<?php

namespace App\Support;

/**
 * Warna aksen klinik dipakai sebagai latar tombol berteks putih. Warna asli
 * dari database bisa terlalu terang, jadi digelapkan bertahap sampai kontras
 * teks putih minimal 4.5:1.
 */
class AccessibleColor
{
    public static function primary(?string $hex, string $fallback = '#047857'): string
    {
        $hex = ltrim((string) $hex, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }

        $channel = static fn (int $value): float => $value <= 10
            ? $value / 3294
            : (($value / 255 + 0.055) / 1.055) ** 2.4;

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        for ($factor = 1.0; $factor >= 0.35; $factor -= 0.04) {
            $rr = (int) round($r * $factor);
            $gg = (int) round($g * $factor);
            $bb = (int) round($b * $factor);
            $luminance = 0.2126 * $channel($rr) + 0.7152 * $channel($gg) + 0.0722 * $channel($bb);
            $contrast = 1.05 / ($luminance + 0.05);

            if ($contrast >= 4.5) {
                return sprintf('#%02x%02x%02x', $rr, $gg, $bb);
            }
        }

        return $fallback;
    }
}
