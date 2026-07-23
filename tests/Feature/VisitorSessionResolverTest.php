<?php

use App\Models\ActivityEcomDailyVisitor;
use App\Models\ActivityEcomUser;
use App\Services\VisitorSessionResolver;
use App\Support\VisitorSessionRedis;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    VisitorSessionRedis::flushMemoryStore();
    Carbon::setTestNow(Carbon::parse('2026-07-16 10:00:00', 'Europe/London'));
    config([
        'tracker.session_gap_minutes' => 30,
        'tracker.visitor_timezone' => 'Europe/London',
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('first visit creates new daily visitor and session', function () {
    $visitorId = (string) Str::uuid();
    $resolver = app(VisitorSessionResolver::class);

    $result = $resolver->resolve($visitorId);

    expect($result['is_new_daily_visitor'])->toBeTrue();
    expect($result['is_new_unique_visitor'])->toBeTrue();
    expect($result['is_new_session'])->toBeTrue();
    expect(ActivityEcomDailyVisitor::where('visitor_id', $visitorId)->count())->toBe(1);
    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(1);
});

test('returning visitor within 30 minutes reuses session', function () {
    $visitorId = (string) Str::uuid();
    $resolver = app(VisitorSessionResolver::class);

    $first = $resolver->resolve($visitorId);

    Carbon::setTestNow(Carbon::parse('2026-07-16 10:10:00', 'Europe/London'));

    $second = $resolver->resolve($visitorId);

    expect($second['is_new_daily_visitor'])->toBeFalse();
    expect($second['is_new_session'])->toBeFalse();
    expect($second['session_id'])->toBe($first['session_id']);
    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(1);
    expect(ActivityEcomDailyVisitor::where('visitor_id', $visitorId)->count())->toBe(1);
});

test('returning visitor after 30 minutes same day creates new session only', function () {
    $visitorId = (string) Str::uuid();
    $resolver = app(VisitorSessionResolver::class);

    $first = $resolver->resolve($visitorId);

    Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'Europe/London'));

    $second = $resolver->resolve($visitorId);

    expect($second['is_new_daily_visitor'])->toBeFalse();
    expect($second['is_new_session'])->toBeTrue();
    expect($second['session_id'])->not->toBe($first['session_id']);
    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(2);
    expect(ActivityEcomDailyVisitor::where('visitor_id', $visitorId)->count())->toBe(1);
});

test('returning visitor next day creates new daily visitor and session', function () {
    $visitorId = (string) Str::uuid();
    $resolver = app(VisitorSessionResolver::class);

    $resolver->resolve($visitorId);

    Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00', 'Europe/London'));

    $second = $resolver->resolve($visitorId);

    expect($second['is_new_daily_visitor'])->toBeFalse();
    expect($second['is_new_unique_visitor'])->toBeFalse();
    expect($second['is_new_session'])->toBeTrue();
    expect(ActivityEcomDailyVisitor::where('visitor_id', $visitorId)->count())->toBe(2);
    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(2);
});

test('resolve for ingest reuses session within manager gap without new job writes', function () {
    $visitorId = (string) Str::uuid();
    $resolver = app(VisitorSessionResolver::class);

    $first = $resolver->resolveForIngest($visitorId, null);

    Carbon::setTestNow(Carbon::parse('2026-07-16 10:10:00', 'Europe/London'));

    $second = $resolver->resolveForIngest($visitorId, $first['session_id']);

    expect($second['is_new_daily_visitor'])->toBeFalse();
    expect($second['is_new_session'])->toBeFalse();
    expect($second['session_id'])->toBe($first['session_id']);
    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(1);
});

test('resolve for ingest creates new session after manager gap', function () {
    $visitorId = (string) Str::uuid();
    $resolver = app(VisitorSessionResolver::class);

    $first = $resolver->resolveForIngest($visitorId, null);

    Carbon::setTestNow(Carbon::parse('2026-07-16 12:00:00', 'Europe/London'));

    $second = $resolver->resolveForIngest($visitorId, $first['session_id']);

    expect($second['is_new_daily_visitor'])->toBeFalse();
    expect($second['is_new_session'])->toBeTrue();
    expect(ActivityEcomUser::where('visitor_id', $visitorId)->count())->toBe(2);
    expect(ActivityEcomDailyVisitor::where('visitor_id', $visitorId)->count())->toBe(1);
});

test('resolve visit api returns session payload', function () {
    $this->apiKey = 'test-tracker-key-' . Str::random(16);
    config(['tracker.api_key_hash' => bcrypt($this->apiKey)]);

    $visitorId = (string) Str::uuid();

    $response = $this->postJson('/api/tracker/resolve-visit', [
        'visitor_id' => $visitorId,
    ], [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'visitor_id',
            'session_id',
            'is_new_daily_visitor',
            'is_new_session',
        ]);
});
