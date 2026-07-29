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

test('session traffic attribution parses google ads url aliases', function () {
    $url = 'https://enorsia.com/style/Women/white-full-length-high-waist-leggings?gad_source=1&gad_campaignid=23588680250&gclid=CjwKCAjwyabTBhBFEiwAM3mNUKDny4Me4';

    expect(SessionTrafficAttribution::parseFromUrl($url))->toMatchArray([
        'gad_source' => '1',
        'gad_campaignid' => '23588680250',
        'gclid' => 'CjwKCAjwyabTBhBFEiwAM3mNUKDny4Me4',
        'utm_source' => 'google',
        'utm_medium' => 'paid',
        'utm_campaign' => '23588680250',
    ]);
});

test('session traffic attribution normalizes utm_source aliases', function () {
    expect(SessionTrafficAttribution::normalizeSource('fb'))->toBe('facebook')
        ->and(SessionTrafficAttribution::normalizeSource('meta'))->toBe('facebook')
        ->and(SessionTrafficAttribution::normalizeSource('ig'))->toBe('instagram')
        ->and(SessionTrafficAttribution::normalizeSource('x'))->toBe('twitter')
        ->and(SessionTrafficAttribution::normalizeSource('aw'))->toBe('awin');

    $url = 'https://shop.example/products?utm_source=fb&utm_medium=paid&utm_campaign=spring';

    expect(SessionTrafficAttribution::parseFromUrl($url))->toMatchArray([
        'utm_source' => 'facebook',
        'utm_medium' => 'paid',
        'utm_campaign' => 'spring',
    ]);
});

test('session traffic attribution infers facebook paid traffic from fbclid', function () {
    $url = 'https://enorsia.com/style/test?fbclid=abc123';

    expect(SessionTrafficAttribution::parseFromUrl($url))->toMatchArray([
        'fbclid' => 'abc123',
        'utm_source' => 'facebook',
        'utm_medium' => 'paid',
    ]);
});

test('session traffic attribution infers social platforms from referer', function () {
    expect(SessionTrafficAttribution::inferFromReferer('https://www.facebook.com/'))->toMatchArray([
        'utm_source' => 'facebook',
        'utm_medium' => 'social',
    ]);

    expect(SessionTrafficAttribution::inferFromReferer('https://l.facebook.com/'))->toMatchArray([
        'utm_source' => 'facebook',
        'utm_medium' => 'social',
    ]);

    expect(SessionTrafficAttribution::inferFromReferer('https://www.instagram.com/'))->toMatchArray([
        'utm_source' => 'instagram',
        'utm_medium' => 'social',
    ]);

    expect(SessionTrafficAttribution::inferFromReferer('https://www.tiktok.com/'))->toMatchArray([
        'utm_source' => 'tiktok',
        'utm_medium' => 'social',
    ]);
});

test('session traffic attribution resolves ingest attributes from facebook referer', function () {
    expect(SessionTrafficAttribution::sessionAttributesFromIngest(
        [],
        'https://enorsia.com/c/women',
        'https://www.facebook.com/',
    ))->toMatchArray([
        'utm_source' => 'facebook',
        'utm_medium' => 'social',
        'landing_page' => 'https://enorsia.com/c/women',
    ]);
});

test('session traffic attribution infers google organic from referer', function () {
    expect(SessionTrafficAttribution::inferFromReferer('https://www.google.com/'))->toMatchArray([
        'utm_source' => 'google',
        'utm_medium' => 'organic',
    ]);
});

test('session traffic attribution list row summary resolves google ads traffic', function () {
    $session = new ActivityEcomUser([
        'landing_page' => 'https://enorsia.com/style/test?gad_source=1&gad_campaignid=23588680250&gclid=abc123',
    ]);

    expect(SessionTrafficAttribution::listRowSummary($session))->toMatchArray([
        'source' => 'Google',
        'utm' => 'paid / 23588680250',
        'referer' => 'https://www.google.com/',
    ]);
});

test('session traffic attribution resolves ingest attributes from google ads landing page', function () {
    $landingPage = 'https://enorsia.com/style/test?gad_source=1&gad_campaignid=23588680250&gclid=abc123';

    expect(SessionTrafficAttribution::sessionAttributesFromIngest([
        'landing_page' => $landingPage,
    ]))->toMatchArray([
        'utm_source' => 'google',
        'utm_medium' => 'paid',
        'utm_campaign' => '23588680250',
        'landing_page' => $landingPage,
    ]);
});

test('session traffic attribution resolves ingest attributes from google organic referer', function () {
    expect(SessionTrafficAttribution::sessionAttributesFromIngest([], null, 'https://www.google.com/'))->toMatchArray([
        'utm_source' => 'google',
        'utm_medium' => 'organic',
    ]);
});

test('session traffic attribution backfills google ads session columns from first action url', function () {
    $session = ActivityEcomUser::query()->create([
        'session_id' => 'google-ads-session',
        'device_type' => 'desktop',
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'session_id' => 'google-ads-session',
        'action_type' => 'product_view',
        'page_url' => 'https://enorsia.com/style/test?gad_source=1&gad_campaignid=23588680250&gclid=abc123',
        'referer' => 'https://www.google.com/',
        'created_at' => now(),
    ]);

    expect(SessionTrafficAttribution::backfillFromFirstAction($session))->toBeTrue();

    $session->refresh();

    expect($session->utm_source)->toBe('google')
        ->and($session->utm_medium)->toBe('paid')
        ->and($session->utm_campaign)->toBe('23588680250');
});

test('session traffic attribution backfills google organic from referer during backfill', function () {
    $session = ActivityEcomUser::query()->create([
        'session_id' => 'google-organic-session',
        'device_type' => 'desktop',
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    expect(SessionTrafficAttribution::backfillSession(
        $session,
        'https://enorsia.com/c/women',
        'https://www.google.com/',
    ))->toBeTrue();

    $session->refresh();

    expect($session->utm_source)->toBe('google')
        ->and($session->utm_medium)->toBe('organic')
        ->and($session->landing_page)->toBe('https://enorsia.com/c/women');
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
