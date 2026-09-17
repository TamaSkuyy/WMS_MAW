<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan aplikasi key/value (diatur superadmin dari halaman Pengaturan).
 *
 * Semua nilai di-cache sekali per request lewat Cache::rememberForever supaya
 * pembacaan flag fitur tidak menambah query di setiap request; cache dibuang
 * setiap kali nilai disimpan.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'settings:all';

    /** @return array<string, string|null> */
    public static function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $values = static::allValues();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $raw = static::getValue($key);

        if ($raw === null || $raw === '') {
            return $default;
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setValue(string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        static::updateOrCreate(['key' => $key], ['value' => $value === null ? null : (string) $value]);

        Cache::forget(self::CACHE_KEY);
    }
}
