<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;

class VisitorSessionRedis
{
    /** @var array<string, array{last_active_at: string, last_date: string, session_id: string}> */
    private static array $memoryStore = [];

    /** @var array<string, true> */
    private static array $memorySeen = [];

    /** @var array<string, true> */
    private static array $memoryRollupLocks = [];

    public static function flushMemoryStore(): void
    {
        self::$memoryStore = [];
        self::$memorySeen = [];
        self::$memoryRollupLocks = [];
    }

    private function usesMemoryStore(): bool
    {
        return (bool) config('tracker.redis_use_memory_store', false);
    }

    private function redis(): Connection
    {
        return Redis::connection((string) config('tracker.redis_connection', 'tracker'));
    }

    private function key(string $visitorId): string
    {
        return 'last_activity:'.$visitorId;
    }

    private function seenKey(string $visitorId): string
    {
        return 'seen:'.$visitorId;
    }

    private function rollupKey(string $visitorId, string $visitDate): string
    {
        return 'rollup:'.$visitorId.':'.$visitDate;
    }

    public function hasSeenBefore(string $visitorId): bool
    {
        if ($this->usesMemoryStore()) {
            return isset(self::$memorySeen[$this->seenKey($visitorId)]);
        }

        return (bool) $this->redis()->exists($this->seenKey($visitorId));
    }

    public function markSeenBefore(string $visitorId): void
    {
        $ttl = (int) config('tracker.visitor_seen_ttl_seconds', 31536000);

        if ($this->usesMemoryStore()) {
            self::$memorySeen[$this->seenKey($visitorId)] = true;

            return;
        }

        $this->redis()->setex($this->seenKey($visitorId), $ttl, '1');
    }

    public function acquireRollupLock(string $visitorId, string $visitDate): bool
    {
        $ttl = (int) config('tracker.rollup_lock_seconds', 45);
        $key = $this->rollupKey($visitorId, $visitDate);

        if ($this->usesMemoryStore()) {
            if (isset(self::$memoryRollupLocks[$key])) {
                return false;
            }

            self::$memoryRollupLocks[$key] = true;

            return true;
        }

        return (bool) $this->redis()->set($key, '1', 'EX', $ttl, 'NX');
    }

    /**
     * @return array{last_active_at: string, last_date: string, session_id: string}|null
     */
    public function get(string $visitorId): ?array
    {
        if ($this->usesMemoryStore()) {
            return self::$memoryStore[$this->key($visitorId)] ?? null;
        }

        $raw = $this->redis()->get($this->key($visitorId));

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'last_active_at' => (string) ($decoded['last_active_at'] ?? ''),
            'last_date' => (string) ($decoded['last_date'] ?? ''),
            'session_id' => (string) ($decoded['session_id'] ?? ''),
        ];
    }

    /**
     * @param  array{last_active_at: string, last_date: string, session_id: string}  $state
     */
    public function put(string $visitorId, array $state): void
    {
        if ($this->usesMemoryStore()) {
            self::$memoryStore[$this->key($visitorId)] = $state;

            return;
        }

        $this->redis()->setex(
            $this->key($visitorId),
            (int) config('tracker.redis_ttl_seconds', 172800),
            json_encode($state) ?: '{}',
        );
    }

    public function touch(string $visitorId, string $sessionId): void
    {
        $now = $this->now();
        $record = $this->get($visitorId);

        if ($record === null) {
            $this->put($visitorId, [
                'last_active_at' => $now->toIso8601String(),
                'last_date' => $this->todayString($now),
                'session_id' => $sessionId,
            ]);

            return;
        }

        $record['last_active_at'] = $now->toIso8601String();
        $record['session_id'] = $sessionId;

        $this->put($visitorId, $record);
    }

    public function forget(string $visitorId): void
    {
        if ($this->usesMemoryStore()) {
            unset(self::$memoryStore[$this->key($visitorId)]);

            return;
        }

        $this->redis()->del($this->key($visitorId));
    }

    public function now(): Carbon
    {
        return TrackerTime::nowUtc();
    }

    public function todayString(?Carbon $now = null): string
    {
        return TrackerTime::localDate($now);
    }
}
