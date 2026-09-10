<?php

use App\Support\EcomTrackerViewData;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

test('traffic source drill down link includes dashboard back url', function () {
    $dashboardUrl = 'https://example.test/admin/ecom-tracker/dashboard?period=24h';
    $url = EcomTrackerViewData::activitySourceLink(
        ['period' => '24h'],
        '(direct)',
        $dashboardUrl,
    );

    expect($url)->toContain('focus=traffic')
        ->and($url)->toContain('utm_source='.urlencode('(direct)'))
        ->and($url)->toContain('back=');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect(EcomTrackerViewData::resolveBackUrl($query['back'] ?? null))
        ->toBe($dashboardUrl);
});

test('dashboard traffic source links include a back url to store performance', function () {
    $request = Request::create('https://example.test/admin/ecom-tracker/dashboard', 'GET', [
        'period' => '24h',
    ]);
    $page = EcomTrackerViewData::forDashboard($request, ['period' => '24h'], 0);
    $url = ($page['activitySourceLink'])('(direct)');

    expect($url)->toContain('focus=traffic')
        ->and($url)->toContain('back=');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect(EcomTrackerViewData::resolveBackUrl($query['back'] ?? null))
        ->toBe($request->fullUrl());
});

test('activity index back url falls back to dashboard for traffic focus without back param', function () {
    $request = Request::create('https://example.test/admin/ecom-activity', 'GET', [
        'period' => '24h',
        'utm_source' => '(direct)',
        'focus' => 'traffic',
    ]);

    $backUrl = EcomTrackerViewData::activityIndexBackUrl($request);

    expect($backUrl)->toContain('ecom-tracker/dashboard')
        ->and($backUrl)->toContain('period=24h')
        ->and($backUrl)->toContain('utm_source')
        ->and($backUrl)->not->toContain('focus=');
});

test('dashboard and activity shortcut links preserve shared session filters', function () {
    $request = Request::create('https://example.test/admin/ecom-tracker/dashboard', 'GET', [
        'period' => '7d',
        'device_type' => 'mobile',
        'logged_in' => '1',
        'utm_source' => 'google',
        'search' => 'shirt',
        'focus' => 'products',
    ]);

    $activityUrl = EcomTrackerViewData::activityShortcutUrl($request);
    $dashboardUrl = EcomTrackerViewData::dashboardShortcutUrl($request);

    parse_str((string) parse_url($activityUrl, PHP_URL_QUERY), $activityQuery);
    parse_str((string) parse_url($dashboardUrl, PHP_URL_QUERY), $dashboardQuery);

    expect($activityQuery)->toMatchArray([
        'period' => '7d',
        'device_type' => 'mobile',
        'logged_in' => '1',
        'utm_source' => 'google',
    ])->and($activityQuery)->not->toHaveKey('search')
        ->and($activityQuery)->not->toHaveKey('focus')
        ->and($dashboardQuery)->toMatchArray([
            'period' => '7d',
            'device_type' => 'mobile',
            'logged_in' => '1',
            'utm_source' => 'google',
        ]);
});

test('activity index back url is omitted when there is no dashboard focus', function () {
    $request = Request::create('https://example.test/admin/ecom-activity', 'GET', [
        'period' => '24h',
    ]);

    expect(EcomTrackerViewData::activityIndexBackUrl($request))->toBeNull();
});

test('session duration bucket drill down includes activity filter and dashboard back url', function () {
    $request = Request::create('https://example.test/admin/ecom-tracker/dashboard', 'GET', [
        'period' => '24h',
    ]);
    $page = EcomTrackerViewData::forDashboard($request, ['period' => '24h'], 0);
    $url = ($page['activityFocusLink'])('duration', ['duration_bucket' => '0-1']);

    expect($url)->toContain('focus=duration')
        ->and($url)->toContain('duration_bucket=0-1')
        ->and($url)->toContain('back=');
});

test('activity back url keeps dashboard scroll hash', function () {
    $back = 'https://example.test/admin/ecom-tracker/dashboard?period=24h#etd-y=1420';

    expect(EcomTrackerViewData::resolveBackUrl($back))->toBe($back)
        ->and(EcomTrackerViewData::activityIndexBackUrl(Request::create(
            'https://example.test/admin/ecom-activity',
            'GET',
            ['focus' => 'duration', 'back' => $back],
        )))->toBe($back);
});

test('compare day navigation uses prefixed period query keys', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00', TrackerTime::timezone()));

    $range = TrackerTime::yesterdayRangeUtc();
    $baseQuery = [
        'left_period' => 'yesterday',
        'right_period' => '24h',
        'back' => 'http://127.0.0.1:8001/admin/ecom-tracker/dashboard?period=7d',
    ];

    $dayNav = EcomTrackerViewData::dashboardDayNavigation(
        $baseQuery,
        $range,
        'admin.ecom-tracker.dashboard.compare',
        'left_period',
        'left_date_from',
        'left_date_to',
    );

    parse_str((string) parse_url($dayNav['previous_url'], PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('left_period')
        ->and($query)->not->toHaveKey('period')
        ->and($query['left_period'])->toBe('custom')
        ->and($query['left_date_from'])->toBe('2026-09-08')
        ->and($query['left_date_to'])->toBe('2026-09-08')
        ->and($query['right_period'])->toBe('24h');
});

test('compare page query strips unprefixed period keys', function () {
    $request = Request::create('/admin/ecom-tracker/dashboard/compare', 'GET', [
        'left_period' => 'yesterday',
        'right_period' => '24h',
        'period' => 'custom',
        'date_from' => '2026-09-08',
        'date_to' => '2026-09-08',
        'back' => 'http://127.0.0.1:8001/admin/ecom-tracker/dashboard?period=7d',
    ]);

    $query = EcomTrackerViewData::comparePageQuery(
        $request,
        EcomTrackerViewData::compareSideFiltersFromRequest($request, 'left'),
        EcomTrackerViewData::compareSideFiltersFromRequest($request, 'right'),
    );

    expect($query)->toHaveKeys(['left_period', 'right_period', 'back'])
        ->and($query)->not->toHaveKeys(['period', 'date_from', 'date_to']);
});
