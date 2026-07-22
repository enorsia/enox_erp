<?php

use App\Services\BotTrafficAnalyticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->service = app(BotTrafficAnalyticsService::class);
});

test('computeDelta returns new label when compare is zero and current is positive', function () {
    $result = $this->service->computeDelta(40, 0);

    expect($result['delta_pct'])->toBeNull();
    expect($result['delta_label'])->toBe('new');
});

test('computeDelta returns no prior data when both counts are zero', function () {
    $result = $this->service->computeDelta(0, 0);

    expect($result['delta_pct'])->toBeNull();
    expect($result['delta_label'])->toBe('no_prior_data');
});

test('computeDelta calculates percentage when both periods have data', function () {
    $result = $this->service->computeDelta(150, 100);

    expect($result['delta_pct'])->toBe(50.0);
    expect($result['delta_direction'])->toBe('up');
    expect($result['delta_label'])->toBeNull();
});

test('computeDelta returns negative hundred when current is zero and compare positive', function () {
    $result = $this->service->computeDelta(0, 10);

    expect($result['delta_pct'])->toBe(-100.0);
    expect($result['delta_direction'])->toBe('down');
});

test('resolveComparisonRange previous period is equal length before current', function () {
    $current = [
        'from' => Carbon::parse('2026-07-08 00:00:00', 'UTC'),
        'to' => Carbon::parse('2026-07-14 23:59:59', 'UTC'),
        'label' => 'test',
    ];

    $compare = $this->service->resolveComparisonRange($current, 'previous_period');

    expect($compare['from']->toDateString())->toBe('2026-07-01');
    expect($compare['to']->toDateString())->toBe('2026-07-07');
    expect($compare['mode'])->toBe('previous_period');
});

test('summaryOnly uses cache when enabled', function () {
    config(['tracker.analytics_cache_enabled' => true]);

    $filters = ['period' => '7d'];
    $first = $this->service->summaryOnly($filters);
    $second = $this->service->summaryOnly($filters);

    expect($first)->toBe($second);
    expect($first)->toHaveKeys(['real_shoppers', 'automated_traffic', 'not_classified', 'uk_shoppers']);
});

test('buildReport country breakdown groups detected countries for real visitors', function () {
    $gbSession = \Illuminate\Support\Str::uuid()->toString();
    $usSession = \Illuminate\Support\Str::uuid()->toString();

    \App\Models\ActivityEcomUser::query()->create([
        'session_id' => $gbSession,
        'device_type' => 'desktop',
        'last_active_at' => now(),
        'created_at' => now(),
    ]);
    \App\Models\ActivityEcomUserBotContext::query()->create([
        'session_id' => $gbSession,
        'is_bot' => false,
        'bot_confidence' => 'low',
        'bot_reason' => 'no bot signals detected',
        'ip_country' => 'GB',
    ]);

    \App\Models\ActivityEcomUser::query()->create([
        'session_id' => $usSession,
        'device_type' => 'desktop',
        'last_active_at' => now(),
        'created_at' => now(),
    ]);
    \App\Models\ActivityEcomUserBotContext::query()->create([
        'session_id' => $usSession,
        'is_bot' => false,
        'bot_confidence' => 'low',
        'bot_reason' => 'no bot signals detected',
        'ip_country' => 'US',
    ]);

    $report = $this->service->buildReport([
        'period' => '24h',
        'compare' => 'none',
    ]);

    expect($report['country_breakdown'])->toHaveCount(2);
    expect(collect($report['country_breakdown'])->pluck('label'))->toContain('United Kingdom (GB)', 'United States (US)');
    expect(collect($report['country_breakdown'])->sum('count'))->toBe(2);
});
