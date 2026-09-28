<?php

use App\Services\EcomTrackerDashboardService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

test('store dashboard shows metrics when rollups only and some closed days lack rollups', function () {
    config(['tracker.dashboard_rollups_only' => true]);

    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', TrackerTime::timezone()));

    DB::table('activity_ecom_daily_site_metrics')->where('metric_date', '2026-04-01')->delete();

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-04-01',
        'date_to' => '2026-10-31',
    ]);

    expect($data['rollups_unavailable'] ?? false)->toBeFalse()
        ->and($data['kpis'])->not->toBeEmpty();

    Carbon::setTestNow();
});

test('store dashboard today preset still loads without rollups when rollups only', function () {
    config(['tracker.dashboard_rollups_only' => true]);

    DB::table('activity_ecom_daily_site_metrics')->delete();

    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-09-28 12:00:00', TrackerTime::timezone()));

    $data = $service->getDashboardData(['period' => '24h']);

    expect($data['rollups_unavailable'] ?? false)->toBeFalse();

    Carbon::setTestNow();
});
