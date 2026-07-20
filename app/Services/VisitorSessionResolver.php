<?php

namespace App\Services;

use App\Jobs\RecordVisitorActivityJob;
use App\Models\ActivityEcomUser;
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
     *     is_new_unique_visitor: bool,
     *     is_new_session: bool
     * }
     */
    public function resolve(string $visitorId, array $context = []): array
    {
        $now = $this->redis->now();
        $today = $this->redis->todayString($now);
        $record = $this->redis->get($visitorId);
        $gapMinutes = (int) config('tracker.session_gap_minutes', 30);
        $hasVisitedBefore = $this->hasVisitedBefore($visitorId);

        $isNewUniqueVisitor = ! $hasVisitedBefore;
        $isNewSession = false;
        $sessionId = '';

        if ($record === null) {
            $latestSession = $hasVisitedBefore ? $this->latestSession($visitorId) : null;

            if ($latestSession !== null && $this->minutesSince((string) $latestSession->getRawOriginal('last_active_at'), $now) <= $gapMinutes) {
                $isNewSession = false;
                $sessionId = $latestSession->session_id;
            } else {
                $isNewSession = true;
                $sessionId = (string) Str::uuid();
            }
        } elseif ($record['last_date'] !== $today) {
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
        } elseif ($this->minutesSince($record['last_active_at'], $now) > $gapMinutes) {
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
        } else {
            $isNewSession = false;
            $sessionId = $record['session_id'] !== '' ? $record['session_id'] : (string) Str::uuid();
        }

        if ($hasVisitedBefore) {
            $isNewUniqueVisitor = false;
        }

        if ($isNewUniqueVisitor) {
            $this->redis->markSeenBefore($visitorId);
        }

        $this->redis->put($visitorId, [
            'last_active_at' => $now->toIso8601String(),
            'last_date' => $today,
            'session_id' => $sessionId,
        ]);

        $this->dispatchVisitorActivityJob(
            visitorId: $visitorId,
            sessionId: $sessionId,
            isNewUniqueVisitor: $isNewUniqueVisitor,
            isNewSession: $isNewSession,
            context: $context,
            resolvedAt: $now->toIso8601String(),
        );

        return $this->buildResult($visitorId, $sessionId, $isNewUniqueVisitor, $isNewSession);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     visitor_id: string,
     *     session_id: string,
     *     is_new_daily_visitor: bool,
     *     is_new_unique_visitor: bool,
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

        return $this->buildResult($visitorId, $sessionId, false, false);
    }

    private function hasVisitedBefore(string $visitorId): bool
    {
        if ($this->redis->hasSeenBefore($visitorId)) {
            return true;
        }

        $exists = ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->exists();

        if ($exists) {
            $this->redis->markSeenBefore($visitorId);
        }

        return $exists;
    }

    private function latestSession(string $visitorId): ?ActivityEcomUser
    {
        return ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->orderByDesc('last_active_at')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function dispatchVisitorActivityJob(
        string $visitorId,
        string $sessionId,
        bool $isNewUniqueVisitor,
        bool $isNewSession,
        array $context,
        string $resolvedAt,
    ): void {
        $job = new RecordVisitorActivityJob(
            visitorId: $visitorId,
            sessionId: $sessionId,
            isNewDailyVisitor: $isNewUniqueVisitor,
            isNewSession: $isNewSession,
            context: $context,
            resolvedAt: $resolvedAt,
        );

        if (config('tracker.queue_async', true)) {
            dispatch($job);

            return;
        }

        dispatch_sync($job);
    }

    /**
     * @return array{
     *     visitor_id: string,
     *     session_id: string,
     *     is_new_daily_visitor: bool,
     *     is_new_unique_visitor: bool,
     *     is_new_session: bool
     * }
     */
    private function buildResult(string $visitorId, string $sessionId, bool $isNewUniqueVisitor, bool $isNewSession): array
    {
        return [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'is_new_daily_visitor' => $isNewUniqueVisitor,
            'is_new_unique_visitor' => $isNewUniqueVisitor,
            'is_new_session' => $isNewSession,
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
