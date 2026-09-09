<?php

use App\Services\EcomActivityExportQuery;
use App\Services\EcomActivityFunnelSessions;
use App\Services\EcomTrackerDashboardService;
use App\Support\EcomActivityFocus;
use Carbon\Carbon;

uses(Tests\TestCase::class);

function makeExportQuery(): EcomActivityExportQuery
{
    return new EcomActivityExportQuery(
        app(EcomTrackerDashboardService::class),
        app(EcomActivityFunnelSessions::class),
    );
}

test('normalize query params parses indexed bracket keys from export json', function () {
    $query = makeExportQuery();

    expect($query->normalizeQueryParams([
        'funnel[0]' => 'payment_success',
        'device_type[0]' => 'mobile',
        'device_type[1]' => 'desktop',
        'utm_source[0]' => 'google',
        'category[0]' => 'Dresses',
        'period' => '7d',
    ]))->toBe([
        'funnel' => ['payment_success'],
        'device_type' => ['mobile', 'desktop'],
        'utm_source' => ['google'],
        'category' => ['Dresses'],
        'period' => '7d',
    ]);
});

test('prepare request applies drawer funnel filter after bracket normalization', function () {
    $query = makeExportQuery();

    $request = $query->prepareRequest([
        'funnel[0]' => 'payment_success',
        'period' => '7d',
    ]);

    expect(EcomActivityFocus::drawerFunnelFilterValues($request))
        ->toBe(['payment_success'])
        ->and(EcomActivityFocus::shouldApplyDrawerFunnelFilter($request))->toBeTrue();
});

test('resolve range works without recursive request preparation', function () {
    $query = makeExportQuery();

    $range = $query->resolveRange(['period' => '7d']);

    expect($range)->toHaveKeys(['from', 'to', 'label', 'period'])
        ->and($range['period'])->toBe('7d')
        ->and($range['from'])->toBeInstanceOf(Carbon::class)
        ->and($range['to'])->toBeInstanceOf(Carbon::class);
});
