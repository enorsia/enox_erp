<?php

use App\Jobs\RecordVisitorActivityJob;
use App\Models\ActivityEcomUser;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class, Tests\TestCase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-22 12:00:00', 'UTC'));
    config(['tracker.visitor_timezone' => 'Europe/London']);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('record visitor job is idempotent when new session already exists', function () {
    $visitorId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();
    $resolvedAt = TrackerTime::formatUtc(Carbon::parse('2026-07-22 11:00:00', 'UTC'));

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'visitor_id' => $visitorId,
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'macOS',
        'last_active_at' => $resolvedAt,
        'session_duration_seconds' => 0,
        'created_at' => $resolvedAt,
        'updated_at' => $resolvedAt,
    ]);

    $job = new RecordVisitorActivityJob(
        visitorId: $visitorId,
        sessionId: $sessionId,
        isNewDailyVisitor: false,
        isNewSession: true,
        context: ['user_agent' => 'Mozilla/5.0'],
        resolvedAt: $resolvedAt,
    );

    $job->handle();

    expect(ActivityEcomUser::query()->where('session_id', $sessionId)->count())->toBe(1);
});

test('record visitor job ignores stale out of order updates', function () {
    $visitorId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();
    $createdAt = TrackerTime::formatUtc(Carbon::parse('2026-07-22 12:00:00', 'UTC'));
    $olderPing = TrackerTime::formatUtc(Carbon::parse('2026-07-22 11:59:50', 'UTC'));

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'visitor_id' => $visitorId,
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'macOS',
        'last_active_at' => $createdAt,
        'session_duration_seconds' => 0,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $job = new RecordVisitorActivityJob(
        visitorId: $visitorId,
        sessionId: $sessionId,
        isNewDailyVisitor: false,
        isNewSession: false,
        context: [],
        resolvedAt: $olderPing,
    );

    $job->handle();

    $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

    expect($session->session_duration_seconds)->toBe(0)
        ->and($session->getRawOriginal('last_active_at'))->toBe($createdAt);
});

test('record visitor job never writes negative session duration', function () {
    $visitorId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();
    $createdAt = TrackerTime::formatUtc(Carbon::parse('2026-07-22 12:00:10', 'UTC'));
    $earlierPing = TrackerTime::formatUtc(Carbon::parse('2026-07-22 12:00:00', 'UTC'));

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'visitor_id' => $visitorId,
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'macOS',
        'last_active_at' => $createdAt,
        'session_duration_seconds' => 0,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $job = new RecordVisitorActivityJob(
        visitorId: $visitorId,
        sessionId: $sessionId,
        isNewDailyVisitor: false,
        isNewSession: false,
        context: [],
        resolvedAt: $earlierPing,
    );

    $job->handle();

    expect(ActivityEcomUser::query()->where('session_id', $sessionId)->value('session_duration_seconds'))->toBe(0);
});
