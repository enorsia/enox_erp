<?php

use App\Support\EcomTrackerViewData;
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
        ->and($backUrl)->not->toContain('utm_source')
        ->and($backUrl)->not->toContain('focus=');
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
