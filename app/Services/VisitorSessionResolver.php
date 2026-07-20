<?php

namespace App\Services;

use App\Jobs\RecordVisitorActivityJob;
use App\Support\TrackerTime;
use App\Support\VisitorSessionRedis;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VisitorSessionResolver
{
    public function __construct(
        private VisitorSessionRedis $redis,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     visitor_id: string,
     *     session_id: string,
     *     is_new_daily_visitor: bool,
     *     is_new_session: bool
     * }
     */
    public function resolve(string $visitorId, array $context = []): array
    {
        $now = $this->redis->now();
        $today = $this->redis->todayString($now);
        $record = $this->redis->get($visitorId);

        $isNewDailyVisitor = false;
        $isNewSession = false;
        $sessionId = '';

        if ($record === null) {
            $isNewDailyVisitor = true;
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
        } elseif ($record['last_date'] !== $today) {
            $isNewDailyVisitor = true;
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
        } elseif ($this->minutesSince($record['last_active_at'], $now) > (int) config('tracker.session_gap_minutes', 30)) {
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
        } else {
            $sessionId = $record['session_id'] !== '' ? $record['session_id'] : (string) Str::uuid();
        }

        $this->redis->put($visitorId, [
            'last_active_at' => $now->toIso8601String(),
            'last_date' => $today,
            'session_id' => $sessionId,
        ]);

        RecordVisitorActivityJob::dispatchSync(
            visitorId: $visitorId,
            sessionId: $sessionId,
            isNewDailyVisitor: $isNewDailyVisitor,
            isNewSession: $isNewSession,
            context: $context,
            resolvedAt: $now->toIso8601String(),
        );

        return [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'is_new_daily_visitor' => $isNewDailyVisitor,
            'is_new_session' => $isNewSession,
        ];
    }

    /**
     * Apply manager rules during track ingest without dispatching a job on routine updates.
     *
     * @param  array<string, mixed>  $context
     * @return array{
     *     visitor_id: string,
     *     session_id: string,
     *     is_new_daily_visitor: bool,
     *     is_new_session: bool
     * }
     */
    public function resolveForIngest(string $visitorId, ?string $proposedSessionId, array $context = []): array
    {
        $now = $this->redis->now();
        $today = $this->redis->todayString($now);
        $record = $this->redis->get($visitorId);
        $gapMinutes = (int) config('tracker.session_gap_minutes', 30);

        $needsFullResolve = $record === null
            || $record['last_date'] !== $today
            || $this->minutesSince($record['last_active_at'], $now) > $gapMinutes;

        if ($needsFullResolve) {
            return $this->resolve($visitorId, $context);
        }

        $sessionId = $record['session_id'] !== '' ? $record['session_id'] : ($proposedSessionId ?: (string) Str::uuid());

        $this->redis->put($visitorId, [
            'last_active_at' => $now->toIso8601String(),
            'last_date' => $today,
            'session_id' => $sessionId,
        ]);

        return [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'is_new_daily_visitor' => false,
            'is_new_session' => false,
        ];
    }

    private function minutesSince(string $lastActiveAt, Carbon $now): int
    {
        if ($lastActiveAt === '') {
            return PHP_INT_MAX;
        }

        return (int) TrackerTime::toUtc($lastActiveAt)?->diffInMinutes($now) ?? PHP_INT_MAX;
    }
}
