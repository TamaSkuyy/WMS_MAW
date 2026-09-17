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

    /** Log kegagalan cache cukup sekali per proses (tidak membanjiri log). */
    private static bool $cacheFailureLogged = false;

    /**
     * Semua pengaturan (key => value).
     *
     * PENTING: pembacaan ini jalan di SETIAP request (lewat shared prop
     * `features`). Kalau cache store (mis. Redis) sedang bermasalah, exception
     * tidak boleh dibiarkan naik — kalau naik, SEMUA halaman ikut error.
     * Karena itu: fallback ke baca DB langsung, lalu ke array kosong
     * (fitur memakai nilai default di config/features.php).
     *
     * @return array<string, string|null>
     */
    public static function allValues(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, function () {
                return static::query()->pluck('value', 'key')->all();
            });
        } catch (\Throwable $e) {
            if (! self::$cacheFailureLogged) {
                self::$cacheFailureLogged = true;
                logger()->warning('Cache pengaturan tidak bisa dibaca — memakai fallback database.', [
                    'exception' => $e->getMessage(),
                ]);
            }

            try {
                return static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                // Tabel settings belum ada / DB bermasalah → pakai default config.
                return [];
            }
        }
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

        // Kalau cache sedang bermasalah, jangan sampai penyimpanan pengaturan gagal.
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
            logger()->warning('Gagal membersihkan cache pengaturan setelah menyimpan.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
