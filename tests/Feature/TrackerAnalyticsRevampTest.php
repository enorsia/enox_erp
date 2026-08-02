<?php

use App\Models\ActivityEcomDailyVisitor;
use App\Models\ActivityEcomUser;
use App\Services\EcomTrackerDashboardService;
use App\Services\VisitorAnalyticsService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 15:00:00', 'Europe/London'));
    config(['tracker.visitor_timezone' => 'Europe/London']);
});

afterEach(function () {
    Carbon::setTestNow();
});

function utcAt(string $london): string
{
    return TrackerTime::formatUtc(Carbon::parse($london, 'Europe/London'));
}

test('tracker time stores utc and displays london', function () {
    $utc = TrackerTime::formatUtc(Carbon::parse('2026-07-16 14:30:00', 'Europe/London'));

    expect($utc)->toBe('2026-07-16 13:30:00');
    expect(TrackerTime::toLocal($utc)?->format('Y-m-d H:i'))->toBe('2026-07-16 14:30');
    expect(TrackerTime::fromStorage($utc)?->format('Y-m-d H:i'))->toBe('2026-07-16 14:30');
});

test('tracker time parses naive db datetimes as utc storage', function () {
    $utc = TrackerTime::toUtc('2026-07-16 13:30:00');

    expect($utc?->format('Y-m-d H:i:s'))->toBe('2026-07-16 13:30:00');
    expect(TrackerTime::fromStorage('2026-07-16 13:30:00')?->format('Y-m-d H:i'))->toBe('2026-07-16 14:30');
});

test('tracker time parses iso frontend timestamps with timezone', function () {
    $utc = TrackerTime::toUtc('2026-07-16T13:30:00.000Z');

    expect($utc?->format('Y-m-d H:i:s'))->toBe('2026-07-16 13:30:00');
    expect(TrackerTime::fromStorage('2026-07-16T13:30:00.000Z')?->format('Y-m-d H:i'))->toBe('2026-07-16 14:30');
});

test('storage range uses utc bounds for activity table filters', function () {
    $range = TrackerTime::todayRangeUtc();
    [$from, $to] = TrackerTime::storageRange($range['from'], $range['to']);

    expect($from)->toBe('2026-07-15 23:00:01');
    expect($to)->toBe('2026-07-16 22:59:59');
});

test('visitor analytics counts visitors in last 3 hours with utc storage', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorA = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorA,
        'created_at' => utcAt('2026-07-16 13:00:00'),
        'updated_at' => utcAt('2026-07-16 13:30:00'),
        'last_active_at' => utcAt('2026-07-16 13:30:00'),
        'session_duration_seconds' => 1800,
    ]);

    ActivityEcomDailyVisitor::query()->create([
        'visitor_id' => $visitorA,
        'visit_date' => '2026-07-16',
        'first_seen_at' => utcAt('2026-07-16 13:00:00'),
        'last_seen_at' => utcAt('2026-07-16 13:30:00'),
        'total_duration_seconds' => 1800,
        'session_count' => 1,
    ]);

    $since = $service->resolveWindow('3h');

    expect($since->timezone->getName())->toBe('UTC');
    expect($service->countActiveVisitors($since))->toBe(1);
    expect($service->countSessions($since))->toBe(1);
    expect($service->avgSessionDuration($since))->toBe(1800);
});

test('build visitor trend and new vs returning methods work', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorId = (string) Str::uuid();

    ActivityEcomDailyVisitor::query()->create([
        'visitor_id' => $visitorId,
        'visit_date' => '2026-07-16',
        'first_seen_at' => utcAt('2026-07-16 10:00:00'),
        'last_seen_at' => utcAt('2026-07-16 10:30:00'),
        'total_duration_seconds' => 1800,
        'session_count' => 1,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => utcAt('2026-07-16 10:00:00'),
        'updated_at' => utcAt('2026-07-16 10:30:00'),
        'last_active_at' => utcAt('2026-07-16 10:30:00'),
        'session_duration_seconds' => 1800,
    ]);

    $from = $service->resolveWindow('24h');
    $trend = $service->buildVisitorTrend($from);
    $split = $service->buildNewVsReturning($from);

    expect($trend['labels'])->not->toBeEmpty();
    expect($split['unique'])->toBe(1);
    expect($split['returning'])->toBe(0);
    expect($split['values'])->toBe([1, 0]);
    expect($service->countUniqueVisitors($from))->toBe(1);
    expect($service->countReturningVisitors($from))->toBe(0);
});

test('unique vs returning uses sessions minus unique visitors for same-day returns', function () {
    $service = app(VisitorAnalyticsService::class);
    $returningVisitor = (string) Str::uuid();
    $newVisitor = (string) Str::uuid();

    ActivityEcomDailyVisitor::query()->create([
        'visitor_id' => $returningVisitor,
        'visit_date' => '2026-07-16',
        'first_seen_at' => utcAt('2026-07-16 10:00:00'),
        'last_seen_at' => utcAt('2026-07-16 12:30:00'),
        'total_duration_seconds' => 3600,
        'session_count' => 2,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $returningVisitor,
        'created_at' => utcAt('2026-07-16 10:00:00'),
        'updated_at' => utcAt('2026-07-16 10:30:00'),
        'last_active_at' => utcAt('2026-07-16 10:30:00'),
        'session_duration_seconds' => 1800,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $returningVisitor,
        'created_at' => utcAt('2026-07-16 12:00:00'),
        'updated_at' => utcAt('2026-07-16 12:30:00'),
        'last_active_at' => utcAt('2026-07-16 12:30:00'),
        'session_duration_seconds' => 1800,
    ]);

    ActivityEcomDailyVisitor::query()->create([
        'visitor_id' => $newVisitor,
        'visit_date' => '2026-07-16',
        'first_seen_at' => utcAt('2026-07-16 12:00:00'),
        'last_seen_at' => utcAt('2026-07-16 12:20:00'),
        'total_duration_seconds' => 1200,
        'session_count' => 1,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $newVisitor,
        'created_at' => utcAt('2026-07-16 12:00:00'),
        'updated_at' => utcAt('2026-07-16 12:20:00'),
        'last_active_at' => utcAt('2026-07-16 12:20:00'),
        'session_duration_seconds' => 1200,
    ]);

    $from = $service->resolveWindow('24h');
    $split = $service->buildNewVsReturning($from);

    expect($service->countUniqueVisitors($from))->toBe(2);
    expect($service->countSessions($from))->toBe(3);
    expect($service->countReturningVisitors($from))->toBe(1);
    expect($split['unique'])->toBe(2);
    expect($split['returning'])->toBe(1);
    expect($split['values'])->toBe([2, 1]);
});

test('unique visitors only count first ever visit not each calendar day', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => utcAt('2026-07-15 10:00:00'),
        'updated_at' => utcAt('2026-07-15 10:30:00'),
        'last_active_at' => utcAt('2026-07-15 10:30:00'),
        'session_duration_seconds' => 1800,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => utcAt('2026-07-16 10:00:00'),
        'updated_at' => utcAt('2026-07-16 10:30:00'),
        'last_active_at' => utcAt('2026-07-16 10:30:00'),
        'session_duration_seconds' => 1800,
    ]);

    $from = $service->resolveWindow('24h');

    expect($service->countUniqueVisitors($from))->toBe(0);
    expect($service->countSessions($from))->toBe(1);
    expect($service->countReturningVisitors($from))->toBe(1);
});

test('dashboard detail sections return uncapped data', function () {
    $service = app(EcomTrackerDashboardService::class);

    foreach (['trend', 'categories', 'products', 'colors', 'cart-abandonment', 'begin-checkout-abandonment', 'proceed-checkout-abandonment', 'devices', 'traffic-sources', 'geography', 'engagement'] as $section) {
        $detail = $service->getSectionDetail($section, ['period' => '30d'], [], null);

        expect($detail['section'])->toBe($section === 'colors' ? 'products' : $section);
        expect($detail['range'])->toHaveKeys(['from', 'to', 'label']);
    }
});

test('dashboard resolve date range uses london day boundaries in utc', function () {
    $service = app(EcomTrackerDashboardService::class);
    $range = $service->resolveDateRange(['period' => '7d']);

    expect($range['from']->timezone->getName())->toBe('UTC');
    expect(TrackerTime::toLocal($range['from'])?->format('H:i:s'))->toBe('00:00:00');
});
