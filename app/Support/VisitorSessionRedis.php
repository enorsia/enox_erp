<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Throwable;

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
        return TrackerRedisSupport::usesMemoryBypass();
    }

    private function redis(): Connection
    {
        return Redis::connection(TrackerRedisSupport::connectionName());
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

        try {
            return (bool) $this->redis()->exists($this->seenKey($visitorId));
        } catch (Throwable $e) {
            $this->logRedisFailed('redis.seen.read_failed', 'Could not check visitor in Redis', $visitorId, $e);

            return false;
        }
    }

    public function markSeenBefore(string $visitorId): void
    {
        $ttl = (int) config('tracker.visitor_seen_ttl_seconds', 31536000);

        if ($this->usesMemoryStore()) {
            self::$memorySeen[$this->seenKey($visitorId)] = true;
            EcomTrackerLogger::frontend()->debug('redis.seen.write', 'Visitor marked in memory', [
                'visitor_id' => $visitorId,
                'storage' => 'memory',
            ]);

            return;
        }

        try {
            $this->redis()->setex($this->seenKey($visitorId), $ttl, '1');
            EcomTrackerLogger::frontend()->debug('redis.seen.write', 'Visitor marked in Redis OK', [
                'visitor_id' => $visitorId,
                'storage' => 'redis',
            ]);
        } catch (Throwable $e) {
            $this->logRedisFailed('redis.seen.write_failed', 'Could not mark visitor in Redis', $visitorId, $e);
        }
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

        try {
            return (bool) $this->redis()->set($key, '1', 'EX', $ttl, 'NX');
        } catch (Throwable $e) {
            $this->logRedisFailed('redis.lock.failed', 'Could not get Redis lock', $visitorId, $e);

            return false;
        }
    }

    /**
     * @return array{last_active_at: string, last_date: string, session_id: string}|null
     */
    public function get(string $visitorId): ?array
    {
        if ($this->usesMemoryStore()) {
            $record = self::$memoryStore[$this->key($visitorId)] ?? null;

            EcomTrackerLogger::frontend()->debug('redis.session.read', $record
                ? 'Session read from memory OK'
                : 'No session in memory', [
                'visitor_id' => $visitorId,
                'storage' => 'memory',
                'found' => $record !== null,
                'session_id' => $record['session_id'] ?? null,
            ]);

            return $record;
        }

        try {
            $raw = $this->redis()->get($this->key($visitorId));

            if (! is_string($raw) || $raw === '') {
                EcomTrackerLogger::frontend()->debug('redis.session.read', 'No session in Redis', [
                    'visitor_id' => $visitorId,
                    'storage' => 'redis',
                    'found' => false,
                ]);

                return null;
            }

            $decoded = json_decode($raw, true);

            if (! is_array($decoded)) {
                EcomTrackerLogger::frontend()->warning('redis.session.read_bad', 'Bad session data in Redis', [
                    'visitor_id' => $visitorId,
                    'storage' => 'redis',
                ]);

                return null;
            }

            $record = [
                'last_active_at' => (string) ($decoded['last_active_at'] ?? ''),
                'last_date' => (string) ($decoded['last_date'] ?? ''),
                'session_id' => (string) ($decoded['session_id'] ?? ''),
            ];

            EcomTrackerLogger::frontend()->debug('redis.session.read', 'Session read from Redis OK', [
                'visitor_id' => $visitorId,
                'storage' => 'redis',
                'found' => true,
                'session_id' => $record['session_id'],
            ]);

            return $record;
        } catch (Throwable $e) {
            $this->logRedisFailed('redis.session.read_failed', 'Could not read session from Redis', $visitorId, $e);

            return null;
        }
    }

    /**
     * @param  array{last_active_at: string, last_date: string, session_id: string}  $state
     */
    public function put(string $visitorId, array $state): void
    {
        if ($this->usesMemoryStore()) {
            self::$memoryStore[$this->key($visitorId)] = $state;

            EcomTrackerLogger::frontend()->info('redis.session.write', 'Session saved in memory OK', [
                'visitor_id' => $visitorId,
                'storage' => 'memory',
                'session_id' => $state['session_id'] ?? null,
            ]);

            return;
        }

        try {
            $this->redis()->setex(
                $this->key($visitorId),
                (int) config('tracker.redis_ttl_seconds', 172800),
                json_encode($state) ?: '{}',
            );

            EcomTrackerLogger::frontend()->info('redis.session.write', 'Session saved in Redis OK', [
                'visitor_id' => $visitorId,
                'storage' => 'redis',
                'session_id' => $state['session_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->logRedisFailed('redis.session.write_failed', 'Could not save session in Redis', $visitorId, $e, [
                'session_id' => $state['session_id'] ?? null,
            ]);
        }
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

        try {
            $this->redis()->del($this->key($visitorId));
        } catch (Throwable $e) {
            $this->logRedisFailed('redis.session.delete_failed', 'Could not delete session from Redis', $visitorId, $e);
        }
    }

    public function now(): Carbon
    {
        return TrackerTime::nowUtc();
    }

    public function todayString(?Carbon $now = null): string
    {
        return TrackerTime::localDate($now);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function logRedisFailed(string $step, string $message, string $visitorId, Throwable $e, array $extra = []): void
    {
        EcomTrackerLogger::frontend()->error($step, $message, array_merge([
            'visitor_id' => $visitorId,
            'storage' => 'redis',
            'redis_working' => false,
            'message' => $e->getMessage(),
        ], $extra));
    }
}
