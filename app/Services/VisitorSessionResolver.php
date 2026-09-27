<?php

namespace App\Services;

use App\Jobs\RecordVisitorActivityJob;
use App\Models\ActivityEcomUser;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerRedisSupport;
use App\Support\TrackerSessionClock;
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
        $startedAt = microtime(true);
        TrackerRedisSupport::logFrontendHealth('resolve_visit');

        $now = $this->redis->now();
        $today = $this->redis->todayString($now);
        $record = $this->redis->get($visitorId);
        $hasVisitedBefore = $this->hasVisitedBefore($visitorId);

        EcomTrackerLogger::frontend()->info('session.resolve.start', 'Finding visitor session', [
            'visitor_id' => $visitorId,
            'has_redis_record' => $record !== null,
            'has_visited_before' => $hasVisitedBefore,
            'gap_seconds' => TrackerSessionClock::gapSeconds(),
        ]);

        $isNewUniqueVisitor = ! $hasVisitedBefore;
        $isNewSession = false;
        $sessionId = '';
        $resolveReason = 'continue';

        if ($record === null) {
            $latestSession = $hasVisitedBefore ? $this->latestSession($visitorId) : null;

            if ($latestSession !== null && ! TrackerSessionClock::gapExpired((string) $latestSession->getRawOriginal('last_active_at'), $now)) {
                $isNewSession = false;
                $sessionId = $latestSession->session_id;
                $resolveReason = 'resume_from_db';
            } else {
                $isNewSession = true;
                $sessionId = (string) Str::uuid();
                $resolveReason = 'new_no_redis';
            }
        } elseif ($record['last_date'] !== $today) {
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
            $resolveReason = 'new_day';
        } elseif (TrackerSessionClock::gapExpired($record['last_active_at'], $now)) {
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
            $resolveReason = 'gap_expired';
        } else {
            $isNewSession = false;
            $sessionId = $record['session_id'] !== '' ? $record['session_id'] : (string) Str::uuid();
            $resolveReason = 'continue_redis';
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

        EcomTrackerLogger::frontend()->info('session.resolve.complete', 'Visitor session is ready', [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'is_new_session' => $isNewSession,
            'is_new_unique_visitor' => $isNewUniqueVisitor,
            'resolve_reason' => $resolveReason,
            'queue_async' => (bool) config('tracker.queue_async', true),
            'redis_bypass' => TrackerRedisSupport::usesMemoryBypass(),
            'redis_working' => TrackerRedisSupport::ping(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

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
        $needsFullResolve = $record === null
            || $record['last_date'] !== $today
            || TrackerSessionClock::gapExpired($record['last_active_at'], $now);

        if ($needsFullResolve) {
            EcomTrackerLogger::frontend()->debug('session.resolve.ingest', 'Need new session for this visitor', [
                'visitor_id' => $visitorId,
                'proposed_session_id' => $proposedSessionId,
            ]);

            return $this->resolve($visitorId, $context);
        }

        $sessionId = $record['session_id'] !== '' ? $record['session_id'] : ($proposedSessionId ?: (string) Str::uuid());

        EcomTrackerLogger::frontend()->debug('session.resolve.ingest', 'Using same session as before', [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]);

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
     * Pick the session that owns this event's wall-clock time (for delayed queue replay).
     *
     * @param  array<string, mixed>  $context
     */
    public function sessionIdForEventClock(string $visitorId, Carbon $eventAt, array $context = []): string
    {
        $sessions = ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->orderByDesc('last_active_at')
            ->get();

        foreach ($sessions as $session) {
            if (TrackerSessionClock::eventFallsInSessionWindow($eventAt, $session)) {
                return $session->session_id;
            }
        }

        $sessionId = (string) Str::uuid();

        EcomTrackerLogger::frontend()->info('session.backfill.plan', 'Will backfill session for delayed event clock', [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'event_clock' => TrackerTime::formatUtc($eventAt),
        ]);

        return $sessionId;
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

            EcomTrackerLogger::frontend()->debug('job.record_visitor.dispatched', 'Visitor job sent to queue', [
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'is_new_session' => $isNewSession,
            ]);

            return;
        }

        EcomTrackerLogger::frontend()->debug('job.record_visitor.sync', 'Visitor job running now', [
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
        ]);

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

    public function recordActivityClock(string $visitorId, string $sessionId, Carbon $activityAt): void
    {
        $now = $this->redis->now();
        $record = $this->redis->get($visitorId);
        $activityIso = TrackerTime::formatUtc($activityAt) ?? $activityAt->toIso8601String();

        if ($record !== null && $record['session_id'] === $sessionId) {
            $existing = TrackerTime::toUtc($record['last_active_at']);

            if ($existing !== null && $existing->greaterThan($activityAt)) {
                $activityIso = TrackerTime::formatUtc($existing) ?? $record['last_active_at'];
            }
        }

        $this->redis->put($visitorId, [
            'last_active_at' => $activityIso,
            'last_date' => $this->redis->todayString($now),
            'session_id' => $sessionId,
        ]);
    }
}
