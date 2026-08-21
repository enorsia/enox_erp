<?php

use App\Models\ActivityEcomDailyVisitor;
use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\VisitorAnalyticsService;
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

test('visitor analytics counts visitors in last 3 hours', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorA = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorA,
        'created_at' => Carbon::parse('2026-07-16 13:00:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 13:30:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 13:30:00', 'Europe/London'),
        'session_duration_seconds' => 1800,
    ]);

    ActivityEcomDailyVisitor::query()->create([
        'visitor_id' => $visitorA,
        'visit_date' => '2026-07-16',
        'first_seen_at' => Carbon::parse('2026-07-16 13:00:00', 'Europe/London'),
        'last_seen_at' => Carbon::parse('2026-07-16 13:30:00', 'Europe/London'),
        'total_duration_seconds' => 1800,
        'session_count' => 1,
    ]);

    $since = $service->resolveWindow('3h');

    expect($service->countActiveVisitors($since))->toBe(1);
    expect($service->countSessions($since))->toBe(1);
    expect($service->avgSessionDuration($since))->toBe(1800);
});

test('visitor analytics supports custom day window', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => Carbon::parse('2026-07-10 12:00:00'),
        'updated_at' => Carbon::parse('2026-07-10 12:20:00'),
        'last_active_at' => Carbon::parse('2026-07-10 12:20:00'),
        'session_duration_seconds' => 1200,
    ]);

    $since = $service->resolveWindow('days', 7);

    expect($service->countActiveVisitors($since))->toBe(1);
    expect($service->formatDuration(125))->toBe('2m 5s');
});

test('visitor analytics ignores legacy sessions without visitor_id for unique counts', function () {
    $service = app(VisitorAnalyticsService::class);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => null,
        'created_at' => Carbon::parse('2026-07-16 14:30:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 14:45:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 14:45:00', 'Europe/London'),
        'session_duration_seconds' => null,
    ]);

    $since = $service->resolveWindow('3h');

    expect($service->countActiveVisitors($since))->toBe(0);
    expect($service->countNewVisitors($since))->toBe(0);
    expect($service->countSessions($since))->toBe(1);

    $breakdown = $service->buildVisitorBreakdown($since, 25);
    expect($breakdown->total())->toBe(0);
});

test('unique visitors count uses lifetime first session only once per cookie', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorId = (string) Str::uuid();

    ActivityEcomDailyVisitor::query()->create([
        'visitor_id' => $visitorId,
        'visit_date' => '2026-07-16',
        'first_seen_at' => Carbon::parse('2026-07-16 10:00:00', 'Europe/London'),
        'last_seen_at' => Carbon::parse('2026-07-16 12:00:00', 'Europe/London'),
        'total_duration_seconds' => 3600,
        'session_count' => 2,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => Carbon::parse('2026-07-16 10:00:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 10:30:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 10:30:00', 'Europe/London'),
        'session_duration_seconds' => 1800,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => Carbon::parse('2026-07-16 12:00:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 12:20:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 12:20:00', 'Europe/London'),
        'session_duration_seconds' => 1200,
    ]);

    $since = $service->resolveWindow('24h');

    expect($service->countNewVisitors($since))->toBe(1);
    expect($service->countSessions($since))->toBe(2);
    expect($service->countActiveVisitors($since))->toBe(1);
});

test('visitor analytics supports custom datetime range', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => Carbon::parse('2026-07-16 10:00:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 10:30:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 10:30:00', 'Europe/London'),
        'session_duration_seconds' => 1800,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => Carbon::parse('2026-07-16 14:00:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 14:20:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 14:20:00', 'Europe/London'),
        'session_duration_seconds' => 1200,
    ]);

    $from = Carbon::parse('2026-07-16 09:00:00', 'Europe/London');
    $to = Carbon::parse('2026-07-16 12:00:00', 'Europe/London');

    expect($service->countActiveVisitors($from, $to))->toBe(1);
    expect($service->countSessions($from, $to))->toBe(1);
});

test('visitor breakdown groups stay time per visitor', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => Carbon::parse('2026-07-16 12:00:00'),
        'updated_at' => Carbon::parse('2026-07-16 12:10:00'),
        'last_active_at' => Carbon::parse('2026-07-16 12:10:00'),
        'session_duration_seconds' => 600,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => Carbon::parse('2026-07-16 13:00:00'),
        'updated_at' => Carbon::parse('2026-07-16 13:20:00'),
        'last_active_at' => Carbon::parse('2026-07-16 13:20:00'),
        'session_duration_seconds' => 1200,
    ]);

    $since = $service->resolveWindow('24h');
    $breakdown = $service->buildVisitorBreakdown($since, 25);

    expect($breakdown->total())->toBe(1);
    expect($breakdown->items()[0]['session_count'])->toBe(2);
    expect($breakdown->items()[0]['total_stay_seconds'])->toBe(1800);
});

test('visitor breakdown supports sort by total stay', function () {
    $service = app(VisitorAnalyticsService::class);

    $visitorA = (string) Str::uuid();
    $visitorB = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorA,
        'created_at' => Carbon::parse('2026-07-16 10:00:00'),
        'updated_at' => Carbon::parse('2026-07-16 10:05:00'),
        'last_active_at' => Carbon::parse('2026-07-16 10:05:00'),
        'session_duration_seconds' => 300,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorB,
        'created_at' => Carbon::parse('2026-07-16 11:00:00'),
        'updated_at' => Carbon::parse('2026-07-16 11:30:00'),
        'last_active_at' => Carbon::parse('2026-07-16 11:30:00'),
        'session_duration_seconds' => 1800,
    ]);

    $since = $service->resolveWindow('24h');
    $breakdown = $service->buildVisitorBreakdown($since, 25, null, ['sort_by' => 'total_stay_desc']);

    expect($breakdown->items()[0]['visitor_id'])->toBe($visitorB);
    expect($breakdown->items()[1]['visitor_id'])->toBe($visitorA);
});

test('visitor breakdown includes order qty and can sort by orders', function () {
    $service = app(VisitorAnalyticsService::class);

    $buyer = (string) Str::uuid();
    $browser = (string) Str::uuid();
    $buyerSession = (string) Str::uuid();
    $browserSession = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $buyerSession,
        'visitor_id' => $buyer,
        'created_at' => Carbon::parse('2026-07-16 12:00:00'),
        'updated_at' => Carbon::parse('2026-07-16 12:30:00'),
        'last_active_at' => Carbon::parse('2026-07-16 12:30:00'),
        'session_duration_seconds' => 600,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $browserSession,
        'visitor_id' => $browser,
        'created_at' => Carbon::parse('2026-07-16 12:00:00'),
        'updated_at' => Carbon::parse('2026-07-16 12:10:00'),
        'last_active_at' => Carbon::parse('2026-07-16 12:10:00'),
        'session_duration_seconds' => 300,
    ]);

    foreach (range(1, 2) as $index) {
        ActivityEcomUserAction::query()->create([
            'event_id' => (string) Str::uuid(),
            'session_id' => $buyerSession,
            'action_type' => 'payment_success',
            'payment_success' => ['amount_paid' => 50 * $index],
            'created_at' => Carbon::parse('2026-07-16 12:'.str_pad((string) (10 + $index), 2, '0', STR_PAD_LEFT).':00'),
        ]);
    }

    $since = $service->resolveWindow('24h');
    $breakdown = $service->buildVisitorBreakdown($since, 25, null, ['sort_by' => 'orders_desc']);

    expect($breakdown->items()[0]['visitor_id'])->toBe($buyer);
    expect($breakdown->items()[0]['order_qty'])->toBe(2);
    expect($breakdown->items()[1]['order_qty'])->toBe(0);
});

test('visitor breakdown includes latest session for classification badge', function () {
    $service = app(VisitorAnalyticsService::class);
    $visitorId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'visitor_id' => $visitorId,
        'device_type' => 'mobile',
        'browser' => 'Safari',
        'created_at' => Carbon::parse('2026-07-16 12:00:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 12:30:00', 'Europe/London'),
    ]);

    \App\Models\ActivityEcomUserBotContext::query()->create([
        'session_id' => $sessionId,
        'is_bot' => false,
        'bot_confidence' => 'low',
        'bot_reason' => 'no bot signals detected',
    ]);

    $since = $service->resolveWindow('24h');
    $breakdown = $service->buildVisitorBreakdown($since, 25);

    expect($breakdown->items()[0]['latest_session'])->not->toBeNull();
    expect($breakdown->items()[0]['latest_session']->marketer_type_label)->toBe('Real visitor');
});

test('duration buckets include percentage and distribution summary', function () {
    $service = app(VisitorAnalyticsService::class);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => (string) Str::uuid(),
        'created_at' => Carbon::parse('2026-07-16 12:00:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 12:01:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 12:01:00', 'Europe/London'),
        'session_duration_seconds' => 30,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => (string) Str::uuid(),
        'created_at' => Carbon::parse('2026-07-16 13:00:00', 'Europe/London'),
        'updated_at' => Carbon::parse('2026-07-16 13:10:00', 'Europe/London'),
        'last_active_at' => Carbon::parse('2026-07-16 13:10:00', 'Europe/London'),
        'session_duration_seconds' => 600,
    ]);

    $since = $service->resolveWindow('24h');
    $buckets = $service->buildDurationBuckets($since);
    $distribution = $service->buildDurationDistribution($since);

    expect($buckets)->toHaveCount(5)
        ->and($buckets[0])->toHaveKeys(['label', 'count', 'pct', 'min', 'max'])
        ->and(collect($buckets)->sum('count'))->toBe(2)
        ->and($distribution['total_sessions'])->toBe(2)
        ->and($distribution['median_seconds'])->toBe(315);
});
