<?php

use App\Support\VisitorSessionRedis;
use Illuminate\Support\Facades\Cache;

test('tracker redis connection is isolated from the default app cache', function () {
    expect(config('tracker.redis_connection'))->toBe('tracker');
    expect(config('cache.default'))->toBe('array');
    expect(config('database.redis.tracker.database'))->toBe('3');
    expect(config('database.redis.tracker.options.prefix'))->toBe('enox:tracker:');
    expect(config('database.redis.cache.database'))->not->toBe(config('database.redis.tracker.database'));
    expect(config('cache.stores'))->not->toHaveKey('tracker');
});

test('visitor session redis uses the dedicated tracker store without touching app cache', function () {
    VisitorSessionRedis::flushMemoryStore();

    $redis = app(VisitorSessionRedis::class);
    $visitorId = 'test-visitor-'.uniqid();

    $redis->put($visitorId, [
        'last_active_at' => now()->toIso8601String(),
        'last_date' => now()->toDateString(),
        'session_id' => 'session-123',
    ]);

    expect($redis->get($visitorId))->toMatchArray([
        'session_id' => 'session-123',
    ]);

    expect(Cache::get('last_activity:'.$visitorId))->toBeNull();

    $redis->forget($visitorId);

    expect($redis->get($visitorId))->toBeNull();
});
