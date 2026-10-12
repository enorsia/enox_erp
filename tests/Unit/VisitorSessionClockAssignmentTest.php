<?php

use App\Models\ActivityEcomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);
use App\Services\VisitorSessionResolver;
use App\Support\TrackerSessionClock;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-19 18:50:00', 'UTC'));
    config(['tracker.session_gap_minutes' => 30]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('session clock prefers historical session over live session for stale queued events', function () {
    $visitorId = (string) Str::uuid();

    $historical = ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => '2026-09-15 21:25:35',
        'last_active_at' => '2026-09-15 21:26:32',
    ]);

    $live = ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'visitor_id' => $visitorId,
        'created_at' => '2026-09-19 18:40:58',
        'last_active_at' => '2026-09-19 18:48:47',
    ]);

    $eventAt = TrackerTime::toUtc('2026-09-15 21:25:35');
    expect($eventAt)->not->toBeNull();

    $resolver = app(VisitorSessionResolver::class);
    $picked = $resolver->sessionIdForEventClock($visitorId, $eventAt, [
        'live_session_id' => $live->session_id,
        'backfill_plan' => [],
    ]);

    expect($picked)->toBe($historical->session_id);
});

test('activity span rejects events days outside session window', function () {
    $created = TrackerTime::toUtc('2026-09-19 18:40:58');
    $lastActive = TrackerTime::toUtc('2026-09-19 18:48:47');
    $eventAt = TrackerTime::toUtc('2026-09-15 21:25:35');

    expect($created)->not->toBeNull();
    expect($lastActive)->not->toBeNull();
    expect($eventAt)->not->toBeNull();

    expect(TrackerSessionClock::eventFallsInActivitySpan($eventAt, $created, $lastActive))->toBeFalse();
});
