<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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

        if (config('app.force_https') || $this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Paginator tidak pakai URL generator, dia pakai $request->url() langsung.
        // Override agar pagination URL ikut terpengaruh forceScheme('https').
        Paginator::currentPathResolver(function () {
            return app('url')->current();
        });
    }
}
