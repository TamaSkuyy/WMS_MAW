<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Laravel Pulse — only superadmin can access /pulse dashboard
        Gate::define('viewPulse', fn ($user) => $user->hasRole('superadmin'));

        // Spatie Health — only superadmin can access /health dashboard
        Gate::define('viewHealth', fn ($user) => $user->hasRole('superadmin'));

        Health::checks([
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
            DatabaseCheck::new(),
            RedisCheck::new(),
            CacheCheck::new(),
            DebugModeCheck::new()->expectedToBe(false),
            EnvironmentCheck::new()->expectEnvironment('production'),
            QueueCheck::new(),
            ScheduleCheck::new(),
            // WAJIB pakai onDisk() + locatedAt(): tanpa keduanya check memanggil
            // File::glob(''), lalu `new SymfonyFile('')` melempar
            // "The file "" does not exist" → CheckDidNotComplete di log tiap
            // health:check (setiap 10 menit). Backup ditulis ke disk `backup-db`
            // (root /backups, lihat config/filesystems.php + docker-compose.prod.yml)
            // di dalam folder senama config('backup.backup.name').
            $this->backupsCheck(),
        ]);

        // Notifikasi health default aktif, tapi alamat emailnya default kosong
        // (HEALTH_TO_ADDRESS). Kirim notifikasi ke alamat kosong tidak ada
        // gunanya dan berisiko melempar exception saat ada check gagal →
        // matikan otomatis sampai alamatnya diisi di .env.prod.
        if (blank(config('health.notifications.mail.to'))) {
            config(['health.notifications.enabled' => false]);
        }

        if (config('app.force_https') || $this->app->environment('production')) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }

        // Paginator tidak pakai URL generator, dia pakai $request->url() langsung.
        // Override agar pagination URL ikut terpengaruh forceScheme('https').
        // Gunakan PATH RELATIF (bukan URL absolut): aman untuk semua scheme
        // (http/https) dan tidak memicu blokir CSP connect-src 'self' saat
        // halaman https memuat link pagination http (mismatch APP_URL).
        Paginator::currentPathResolver(function () {
            return '/' . ltrim(request()->path(), '/');
        });
    }

    /**
     * Check backup untuk dashboard/health:check (Spatie Health).
     *
     * Penting: `onDisk()` di-resolve SAAT BOOT, jadi folder backup yang tidak
     * bisa dibuat (mis. dev lokal tanpa mount /backups) melempar
     * UnableToCreateDirectory. Kalau tidak ditangani, exception saat boot ini
     * membuat SEMUA request 500. Fallback-nya tetap aman karena `locatedAt()`
     * sudah diisi (tidak memanggil File::glob('') yang bikin Symfony File dari
     * path kosong).
     */
    private function backupsCheck(): BackupsCheck
    {
        $check = BackupsCheck::new()
            ->locatedAt((string) config('backup.backup.name'));

        try {
            $check->onDisk('backup-db');
        } catch (\Throwable $e) {
            logger()->warning('Health check Backups: disk backup-db tidak bisa dipakai, check berjalan tanpa disk.', [
                'exception' => $e->getMessage(),
            ]);
        }

        return $check;
    }
}
