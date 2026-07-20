<?php

namespace App\Support;

use Illuminate\Support\Facades\Redis;

class TrackerRedisCache
{
    public function remember(string $key, int $ttlSeconds, callable $callback): array
    {
        if (! config('tracker.analytics_cache_enabled', true) || $ttlSeconds <= 0) {
            $value = $callback();

            return is_array($value) ? $value : ['data' => $value];
        }

        if ($this->usesMemoryStore()) {
            return $this->rememberInMemory($key, $ttlSeconds, $callback);
        }

        $cacheKey = $this->cacheKey($key);
        $connection = Redis::connection((string) config('tracker.redis_connection', 'tracker'));
        $cached = $connection->get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            $decoded = json_decode($cached, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $value = $callback();
        $payload = [
            'payload' => $value,
            'cached_at' => TrackerTime::nowUtc()->toIso8601String(),
        ];

        $connection->setex($cacheKey, $ttlSeconds, json_encode($payload) ?: '{}');

        return $payload;
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
        return (bool) config('tracker.redis_use_memory_store', false);
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

        return $payload;
    }
}
