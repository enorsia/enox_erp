<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class VisitorSessionRedis
{
    private function key(string $visitorId): string
    {
        return config('tracker.redis_prefix', 'enox:tracker:').'last_activity:'.$visitorId;
    }

    /**
     * @return array{last_active_at: string, last_date: string, session_id: string}|null
     */
    public function get(string $visitorId): ?array
    {
        $decoded = Cache::get($this->key($visitorId));

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
        Cache::put(
            $this->key($visitorId),
            $state,
            (int) config('tracker.redis_ttl_seconds', 172800),
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
        Cache::forget($this->key($visitorId));
    }

    public function now(): Carbon
    {
        return Carbon::now(config('tracker.visitor_timezone', 'Europe/London'));
    }

    public function todayString(?Carbon $now = null): string
    {
        return ($now ?? $this->now())->toDateString();
    }
}
