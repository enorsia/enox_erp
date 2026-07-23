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
    expect(TrackerUtmFilter::resolveSource('unknown-source'))->toBeNull();
    expect(TrackerUtmFilter::resolveMedium('cpc'))->toBe('cpc');
    expect(TrackerUtmFilter::resolveMedium('random'))->toBeNull();
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
