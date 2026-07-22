<?php

namespace App\Support;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Tracker Redis health checks and simple logging (frontend + backend flows).
 */
final class TrackerRedisSupport
{
    public static function usesMemoryBypass(): bool
    {
        return (bool) config('tracker.redis_use_memory_store', false);
    }

    public static function connectionName(): string
    {
        return (string) config('tracker.redis_connection', 'tracker');
    }

    public static function connection(): Connection
    {
        return Redis::connection(self::connectionName());
    }

    /**
     * @return bool|null true = OK, false = failed, null = memory bypass (Redis not used)
     */
    public static function ping(): ?bool
    {
        if (self::usesMemoryBypass()) {
            return null;
        }

        try {
            $response = self::connection()->ping();

            if (is_bool($response)) {
                return $response;
            }

            return is_string($response) && strtoupper($response) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    public static function logFrontendHealth(string $action): void
    {
        self::logHealth('frontend', $action);
    }

    public static function logBackendHealth(string $action): void
    {
        self::logHealth('backend', $action);
    }

    private static function logHealth(string $flow, string $action): void
    {
        $context = [
            'action' => $action,
            'connection' => self::connectionName(),
            'memory_bypass' => self::usesMemoryBypass(),
        ];

        $logger = $flow === 'backend'
            ? EcomTrackerLogger::backend()
            : EcomTrackerLogger::frontend();

        if (self::usesMemoryBypass()) {
            $logger->warning('redis.bypass', 'Redis bypass ON — using server memory', $context);

            return;
        }

        if (self::ping() === true) {
            $logger->info('redis.health', 'Redis server is working', $context);

            return;
        }

        $logger->error('redis.health', 'Redis server is NOT working', $context);
    }
}
