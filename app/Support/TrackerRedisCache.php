<?php

namespace App\Support;

use Throwable;

class TrackerRedisCache
{
    public function remember(string $key, int $ttlSeconds, callable $callback): array
    {
        if (! config('tracker.analytics_cache_enabled', true) || $ttlSeconds <= 0) {
            EcomTrackerLogger::backend()->info('redis.cache.bypass', 'Analytics cache OFF — loading from database', [
                'cache_key' => $key,
                'reason' => 'cache_disabled',
            ]);

            $value = $callback();

            return is_array($value) ? $value : ['data' => $value];
        }

        if ($this->usesMemoryStore()) {
            EcomTrackerLogger::backend()->warning('redis.cache.bypass', 'Analytics using memory (Redis bypass ON)', [
                'cache_key' => $key,
                'storage' => 'memory',
            ]);

            return $this->rememberInMemory($key, $ttlSeconds, $callback);
        }

        TrackerRedisSupport::logBackendHealth('analytics_cache');

        $cacheKey = $this->cacheKey($key);

        try {
            $connection = TrackerRedisSupport::connection();
            $cached = $connection->get($cacheKey);

            if (is_string($cached) && $cached !== '') {
                $decoded = json_decode($cached, true);

                if (is_array($decoded)) {
                    EcomTrackerLogger::backend()->debug('redis.cache.read', 'Analytics data loaded from Redis OK', [
                        'cache_key' => $key,
                        'storage' => 'redis',
                        'hit' => true,
                    ]);

                    return $decoded;
                }
            }

            $value = $callback();
            $payload = [
                'payload' => $value,
                'cached_at' => TrackerTime::nowUtc()->toIso8601String(),
            ];

            $connection->setex($cacheKey, $ttlSeconds, json_encode($payload) ?: '{}');

            EcomTrackerLogger::backend()->debug('redis.cache.write', 'Analytics data saved to Redis OK', [
                'cache_key' => $key,
                'storage' => 'redis',
                'hit' => false,
                'ttl_seconds' => $ttlSeconds,
            ]);

            return $payload;
        } catch (Throwable $e) {
            EcomTrackerLogger::backend()->error('redis.cache.failed', 'Redis cache failed — loading from database', [
                'cache_key' => $key,
                'storage' => 'redis',
                'redis_working' => false,
                'message' => $e->getMessage(),
            ]);

            $value = $callback();

            return [
                'payload' => $value,
                'cached_at' => TrackerTime::nowUtc()->toIso8601String(),
            ];
        }
    }

    public function payload(array $cached): mixed
    {
        return $cached['payload'] ?? $cached;
    }

    public function cachedAt(array $cached): ?string
    {
        return isset($cached['cached_at']) ? (string) $cached['cached_at'] : null;
    }

    /** @var array<string, array{expires_at: int, payload: array<string, mixed>}> */
    private static array $memoryCache = [];

    public static function flushMemoryCache(): void
    {
        self::$memoryCache = [];
    }

    private function usesMemoryStore(): bool
    {
        return TrackerRedisSupport::usesMemoryBypass();
    }

    private function cacheKey(string $key): string
    {
        return 'analytics:'.$key;
    }

    /**
     * @return array{payload: mixed, cached_at: string}
     */
    private function rememberInMemory(string $key, int $ttlSeconds, callable $callback): array
    {
        $now = time();
        $entry = self::$memoryCache[$key] ?? null;

        if (is_array($entry) && $entry['expires_at'] >= $now) {
            EcomTrackerLogger::backend()->debug('redis.cache.read', 'Analytics data loaded from memory OK', [
                'cache_key' => $key,
                'storage' => 'memory',
                'hit' => true,
            ]);

            return $entry['payload'];
        }

        $value = $callback();
        $payload = [
            'payload' => $value,
            'cached_at' => TrackerTime::nowUtc()->toIso8601String(),
        ];

        self::$memoryCache[$key] = [
            'expires_at' => $now + $ttlSeconds,
            'payload' => $payload,
        ];

        EcomTrackerLogger::backend()->debug('redis.cache.write', 'Analytics data saved in memory OK', [
            'cache_key' => $key,
            'storage' => 'memory',
            'hit' => false,
            'ttl_seconds' => $ttlSeconds,
        ]);

        return $payload;
    }
}
