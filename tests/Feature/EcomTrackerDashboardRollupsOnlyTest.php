<?php

use App\Services\EcomTrackerDashboardService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

test('store dashboard treats missing rollups as zero without a rollups banner', function () {
    config(['tracker.dashboard_rollups_only' => true]);

    DB::table('activity_ecom_daily_site_metrics')->delete();

    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-09-29 12:00:00', TrackerTime::timezone()));

    $data = $service->getDashboardData(['period' => '30d']);

    expect($data['rollups_unavailable'] ?? false)->toBeFalse()
        ->and($data['rollups_unavailable_message'] ?? null)->toBeNull()
        ->and($data['sale_conversion']['revenue']['value'] ?? null)->toBe(0.0);

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
