<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Semua setting diambil sekali per window cache. Sebelumnya satu halaman
     * Medicines bisa melakukan 18 query app_settings saat cache dingin.
     *
     * @return array<string, string|null>
     */
    public static function allValues(): array
    {
        return Cache::remember('app_settings_all_v1', 3600, function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function getValue(string $key, ?string $default = null): ?string
    {
        $values = static::allValues();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public static function setValue(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget('app_settings_all_v1');
        Cache::forget("app_setting_{$key}");
    }
}
