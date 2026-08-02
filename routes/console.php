<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Spatie Backup (toggle via BACKUP_SCHEDULE_ENABLED=true) ──
if (env('BACKUP_SCHEDULE_ENABLED', true)) {
    Schedule::command('backup:run --only-db')->dailyAt('02:00');
    Schedule::command('backup:clean')->dailyAt('03:00');
    Schedule::command('backup:monitor')->twiceDaily(8, 16);
}

// ── Health Check — every 10 mins ────────────────────────────
Schedule::command('health:check')->everyTenMinutes();
