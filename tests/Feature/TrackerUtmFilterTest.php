<?php

use App\Models\ActivityEcomUser;
use App\Models\TrackerUtmFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('tracker utm filter exposes configured source and medium options', function () {
    expect(TrackerUtmFilter::sources())->toHaveKeys(['google', 'facebook', 'instagram', 'tiktok', 'youtube', 'awin']);
    expect(TrackerUtmFilter::mediums())->toHaveKeys(['organic', 'social', 'affiliate', 'awin', 'none']);
    expect(TrackerUtmFilter::sources()['facebook'])->toBe('Facebook');
});

test('tracker utm filter resolves only allowed dropdown values', function () {
    expect(TrackerUtmFilter::resolveSource('google'))->toBe('google');
    expect(TrackerUtmFilter::resolveSource('custom-network'))->toBe('custom-network');
    expect(TrackerUtmFilter::resolveSource('bad value'))->toBeNull();
    expect(TrackerUtmFilter::resolveMedium('cpc'))->toBe('cpc');
    expect(TrackerUtmFilter::resolveMedium('random'))->toBeNull();
});

test('tracker utm filter resolves source aliases', function () {
    expect(TrackerUtmFilter::resolveSource('fb'))->toBe('facebook');
    expect(TrackerUtmFilter::resolveSource('ig'))->toBe('instagram');
    expect(TrackerUtmFilter::resolveSource('custom-network'))->toBe('custom-network');
    expect(TrackerUtmFilter::resolveSource('bad value'))->toBeNull();
});

test('tracker utm filter applies direct and none sentinels', function () {
    ActivityEcomUser::query()->create([
        'session_id' => 'direct-session',
        'device_type' => 'desktop',
        'utm_source' => null,
        'utm_medium' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => 'google-session',
        'device_type' => 'desktop',
        'utm_source' => 'google',
        'utm_medium' => 'organic',
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    $directQuery = ActivityEcomUser::query();
    TrackerUtmFilter::applySourceFilter($directQuery, '(direct)');
    expect($directQuery->pluck('session_id')->all())->toBe(['direct-session']);

    $googleQuery = ActivityEcomUser::query();
    TrackerUtmFilter::applySourceFilter($googleQuery, 'google');
    TrackerUtmFilter::applyMediumFilter($googleQuery, 'organic');
    expect($googleQuery->pluck('session_id')->all())->toBe(['google-session']);
});

test('tracker utm filter matches google ads sessions from click ids in action urls', function () {
    ActivityEcomUser::query()->create([
        'session_id' => 'google-ads-session',
        'device_type' => 'desktop',
        'utm_source' => null,
        'utm_medium' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    \App\Models\ActivityEcomUserAction::query()->create([
        'session_id' => 'google-ads-session',
        'action_type' => 'product_view',
        'page_url' => 'https://enorsia.com/style/test?gad_source=1&gad_campaignid=23588680250&gclid=abc123',
        'referer' => 'https://www.google.com/',
        'created_at' => now(),
    ]);

    $sourceQuery = ActivityEcomUser::query();
    TrackerUtmFilter::applySourceFilter($sourceQuery, 'google');
    expect($sourceQuery->pluck('session_id')->all())->toBe(['google-ads-session']);

    $mediumQuery = ActivityEcomUser::query();
    TrackerUtmFilter::applyMediumFilter($mediumQuery, 'paid');
    expect($mediumQuery->pluck('session_id')->all())->toBe(['google-ads-session']);

    $combinedQuery = ActivityEcomUser::query();
    TrackerUtmFilter::applySourceFilter($combinedQuery, 'google');
    TrackerUtmFilter::applyMediumFilter($combinedQuery, 'paid');
    expect($combinedQuery->pluck('session_id')->all())->toBe(['google-ads-session']);

    $counts = TrackerUtmFilter::sourceCountsFrom(ActivityEcomUser::query());
    expect($counts)->toHaveKey('google');
    expect($counts['google'])->toBe(1);

    $mediumCounts = TrackerUtmFilter::mediumCountsFrom(ActivityEcomUser::query());
    expect($mediumCounts)->toHaveKey('paid');
    expect($mediumCounts['paid'])->toBe(1);
});

test('tracker utm filter matches facebook sessions from fbclid and aliases', function () {
    ActivityEcomUser::query()->create([
        'session_id' => 'facebook-ads-session',
        'device_type' => 'desktop',
        'utm_source' => null,
        'utm_medium' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    \App\Models\ActivityEcomUserAction::query()->create([
        'session_id' => 'facebook-ads-session',
        'action_type' => 'product_view',
        'page_url' => 'https://enorsia.com/style/test?fbclid=abc123',
        'referer' => 'https://www.facebook.com/',
        'created_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => 'facebook-alias-session',
        'device_type' => 'desktop',
        'utm_source' => 'fb',
        'utm_medium' => 'paid',
        'created_at' => now(),
        'updated_at' => now(),
        'last_active_at' => now(),
    ]);

    $sourceQuery = ActivityEcomUser::query();
    TrackerUtmFilter::applySourceFilter($sourceQuery, 'facebook');
    expect($sourceQuery->pluck('session_id')->all())->toMatchArray([
        'facebook-ads-session',
        'facebook-alias-session',
    ]);

    $counts = TrackerUtmFilter::sourceCountsFrom(ActivityEcomUser::query());
    expect($counts['facebook'] ?? 0)->toBe(2);
});
