<?php

use App\Support\TrackerRedisSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

uses(Tests\TestCase::class);

test('tracker redis support logs memory bypass on frontend health check', function () {
    config([
        'tracker.logging_enabled' => true,
        'tracker.redis_use_memory_store' => true,
    ]);

    $channel = Mockery::mock();
    Log::shouldReceive('channel')->once()->with('ecom_tracker')->andReturn($channel);
    $channel->shouldReceive('warning')
        ->once()
        ->with('[EcomTracker Frontend] Redis bypass ON — using server memory', Mockery::on(
            fn (array $context) => ($context['step'] ?? '') === 'redis.bypass'
                && ($context['memory_bypass'] ?? false) === true
        ));

    TrackerRedisSupport::logFrontendHealth('test');
});

test('tracker redis cache bypass logs when analytics cache disabled', function () {
    config([
        'tracker.logging_enabled' => true,
        'tracker.analytics_cache_enabled' => false,
        'tracker.redis_use_memory_store' => false,
    ]);

    $channel = Mockery::mock();
    Log::shouldReceive('channel')->once()->with('ecom_tracker')->andReturn($channel);
    $channel->shouldReceive('info')
        ->once()
        ->with('[EcomTracker Backend] Analytics cache OFF — loading from database', Mockery::type('array'));

    $cache = app(\App\Support\TrackerRedisCache::class);
    $result = $cache->remember('test-key', 60, fn () => ['total' => 5]);

    expect($result)->toHaveKey('total', 5);
});

test('tracker redis ping accepts predis status response', function () {
    $connection = Mockery::mock(\Illuminate\Redis\Connections\Connection::class);
    $connection->shouldReceive('ping')->once()->andReturn(
        new \Predis\Response\Status('PONG'),
    );

    Redis::shouldReceive('connection')->once()->with('tracker')->andReturn($connection);
    config(['tracker.redis_use_memory_store' => false, 'tracker.redis_connection' => 'tracker']);

    expect(TrackerRedisSupport::ping())->toBeTrue();
});
