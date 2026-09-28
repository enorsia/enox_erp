<?php

use App\Support\EcomAnalyticsRangeSplitter;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('split uses only live range for 24h period', function () {
    config(['tracker.use_daily_rollups' => true]);

    $from = Carbon::now('UTC')->subHours(12);
    $to = Carbon::now('UTC');

    $split = EcomAnalyticsRangeSplitter::split($from, $to, '24h');

    expect($split['use_rollups'])->toBeFalse()
        ->and($split['closed_dates'])->toBe([]);
});

test('split separates closed days from today for calendar ranges', function () {
    config(['tracker.use_daily_rollups' => true]);

    $today = TrackerTime::localNow()->startOfDay();
    $from = $today->copy()->subDays(6)->utc();
    $to = $today->copy()->endOfDay()->utc();

    $split = EcomAnalyticsRangeSplitter::split($from, $to, '7d');

    expect($split['use_rollups'])->toBeTrue()
        ->and(count($split['closed_dates']))->toBe(6)
        ->and($split['live_from'])->not->toBeNull();
});
