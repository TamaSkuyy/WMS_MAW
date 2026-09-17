<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Pembacaan flag fitur (on/off) yang diatur superadmin di halaman Pengaturan.
 *
 * Definisi fitur ada di config/features.php; nilai efektif tersimpan di tabel
 * `settings` dengan key `feature.<nama>`.
 */
class Features
{
    /** Nilai efektif semua fitur: ['nama_fitur' => bool]. */
    public static function all(): array
    {
        $enabled = [];

        foreach (array_keys(static::definitions()) as $key) {
            $enabled[$key] = static::enabled($key);
        }

        return $enabled;
    }

    public static function enabled(string $key): bool
    {
        $definition = static::definitions()[$key] ?? null;

        return Setting::getBool(
            static::settingKey($key),
            (bool) ($definition['default'] ?? false)
        );
    }

    /** @return array<string, array{label: string, description?: string, default?: bool}> */
    public static function definitions(): array
    {
        return config('features', []);
    }

    public static function isKnown(string $key): bool
    {
        return array_key_exists($key, static::definitions());
    }

    public static function set(string $key, bool $enabled): void
    {
        Setting::setValue(static::settingKey($key), $enabled);
    }

    private static function settingKey(string $key): string
    {
        return "feature.{$key}";
    }
}
