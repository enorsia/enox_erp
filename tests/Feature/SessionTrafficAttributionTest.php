<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackerUtmFilter;
use App\Support\SessionTrafficAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('session traffic attribution parses awin affiliate url aliases', function () {
    $url = 'https://enorsia.com/style/test?source=aw&utm_source=awin&sv1=affiliate&sv_campaign_id=1922145&awc=abc123';

    expect(SessionTrafficAttribution::parseFromUrl($url))->toMatchArray([
        'utm_source' => 'awin',
        'utm_medium' => 'affiliate',
        'utm_campaign' => '1922145',
        'awc' => 'abc123',
    ]);
});

test('session traffic attribution maps source=aw to awin when utm_source missing', function () {
    $url = 'https://enorsia.com/style/test?source=aw&sv1=affiliate&awc=abc123';

    expect(SessionTrafficAttribution::parseFromUrl($url))->toMatchArray([
        'utm_source' => 'awin',
        'utm_medium' => 'affiliate',
        'awc' => 'abc123',
    ]);
});

test('session traffic attribution parses utm and click ids from url', function () {
    $url = 'https://shop.example/products?utm_source=facebook&utm_medium=paid&utm_campaign=spring&media_type=video&fbclid=abc123';

    expect(SessionTrafficAttribution::parseFromUrl($url))->toMatchArray([
        'utm_source' => 'facebook',
        'utm_medium' => 'paid',
        'utm_campaign' => 'spring',
        'media_type' => 'video',
        'fbclid' => 'abc123',
    ]);
});

test('session traffic attribution backfills session columns from first action url', function () {
    $session = ActivityEcomUser::query()->create([
        'session_id' => 'utm-session',
        'device_type' => 'desktop',
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'session_id' => 'utm-session',
        'action_type' => 'page_view',
        'page_url' => 'https://shop.example/?utm_source=facebook&utm_medium=paid&utm_campaign=sale',
        'created_at' => now(),
    ]);

    expect(SessionTrafficAttribution::backfillFromFirstAction($session))->toBeTrue();

    $session->refresh();

    expect($session->utm_source)->toBe('facebook')
        ->and($session->utm_medium)->toBe('paid')
        ->and($session->utm_campaign)->toBe('sale')
        ->and($session->landing_page)->toContain('shop.example');
});

test('tracker utm filter resolves dynamic source values and labels counts', function () {
    expect(TrackerUtmFilter::resolveSource('facebook'))->toBe('facebook');
    expect(TrackerUtmFilter::resolveSource('custom-network'))->toBe('custom-network');
    expect(TrackerUtmFilter::resolveSource('bad value'))->toBeNull();

    $options = TrackerUtmFilter::labeledOptions([
        'facebook' => 13,
        '(direct)' => 4,
    ], 'source');

    expect($options)->toBe([
        'facebook' => 'Facebook (13)',
        '(direct)' => 'Direct (4)',
    ]);

    $summary = SessionTrafficAttribution::listRowSummary(new ActivityEcomUser([
        'utm_source' => 'facebook',
        'utm_medium' => 'paid',
        'utm_campaign' => 'spring',
    ]));

    expect($summary)->toMatchArray([
        'source' => 'Facebook',
        'utm' => 'paid / spring',
        'referer' => null,
    ]);
});
