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
            BackupsCheck::new(),
        ]);

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
}
