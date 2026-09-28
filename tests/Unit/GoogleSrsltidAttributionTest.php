<?php

uses(Tests\TestCase::class);

use App\Support\AttributionRules;
use App\Support\SessionTrafficAttribution;
use App\Models\TrackerUtmFilter;

test('srsltid url parses as google organic marketing touch', function () {
    $url = 'https://enorsia.com/?srsltid=AU7gw4XiJboHLxigC8HTTtD5mvjiEa80RbjxTr9D6oRqf6_0oivU0qx8';

    expect(SessionTrafficAttribution::parseFromUrl($url))->toMatchArray([
        'srsltid' => 'AU7gw4XiJboHLxigC8HTTtD5mvjiEa80RbjxTr9D6oRqf6_0oivU0qx8',
        'utm_source' => 'google',
        'utm_medium' => 'organic',
    ]);
    expect(AttributionRules::isMarketingQualifyingTouch([], $url))->toBeTrue();
    expect(AttributionRules::isPaidQualifyingTouch([], $url))->toBeFalse();
});

test('srsltid landing infers google in session filter bucket', function () {
    $session = (object) [
        'utm_source' => null,
        'landing_page' => 'https://enorsia.com/?srsltid=abc',
    ];

    expect(TrackerUtmFilter::sessionSourceBucket($session))->toBe('google');
});

test('list traffic display recomputes when stored direct but landing has srsltid', function () {
    $session = new \App\Models\ActivityEcomUser([
        'session_id' => 'srsltid-session',
        'utm_source' => null,
        'utm_medium' => null,
        'landing_page' => 'https://enorsia.com/?srsltid=abc',
        'list_traffic_utm_source' => '(direct)',
        'list_traffic_utm_medium' => 'none',
        'has_payment_success' => false,
    ]);

    expect(SessionTrafficAttribution::listTrafficDisplayBucket($session))->toMatchArray([
        'source' => '(direct)',
        'medium' => 'none',
    ]);

    expect(SessionTrafficAttribution::dashboardTrafficDisplayBucket($session))->toMatchArray([
        'source' => '(direct)',
        'medium' => 'none',
    ]);

    $fields = SessionTrafficAttribution::displayFields($session, collect());

    expect($fields['UTM source'])->toBe('google')
        ->and($fields['UTM medium'])->toBe('organic');
});
