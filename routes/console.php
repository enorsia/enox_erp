<?php

use App\Jobs\TrackerDashboardSyncJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tracker:stamp-missing-catalog-ids')
    ->dailyAt('01:20')
    ->timezone(config('tracker.visitor_timezone', 'Europe/London'));

Schedule::call(static fn () => TrackerDashboardSyncJob::dispatch())
    ->everyMinute()
    ->name('tracker-dashboard-sync-kick');

