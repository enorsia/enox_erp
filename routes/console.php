<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tracker:stamp-missing-catalog-ids')
    ->dailyAt('01:20')
    ->timezone(config('tracker.visitor_timezone', 'Europe/London'));

Schedule::command('tracker:rollup-analytics-recent', ['--days' => 2])
    ->dailyAt('01:30')
    ->timezone(config('tracker.visitor_timezone', 'Europe/London'));

Schedule::command('tracker:reconcile-rollups', ['--days' => 2])
    ->dailyAt('01:50')
    ->timezone(config('tracker.visitor_timezone', 'Europe/London'));
