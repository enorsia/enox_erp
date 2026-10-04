<?php

use App\Support\EcomAnalyticsRangeSplitter;
use App\Support\TrackerTime;
use Carbon\Carbon;

uses(Tests\TestCase::class);

test('range splitter always uses live tables', function () {
    $from = Carbon::parse('2026-09-01', TrackerTime::timezone())->startOfDay();
    $to = Carbon::parse('2026-09-30', TrackerTime::timezone())->endOfDay();

    $split = EcomAnalyticsRangeSplitter::split($from, $to, '30d');

    expect($split['use_rollups'])->toBeFalse()
        ->and($split['closed_dates'])->toBe([])
        ->and($split['live_from'])->not->toBeNull()
        ->and($split['live_to'])->not->toBeNull();
});
