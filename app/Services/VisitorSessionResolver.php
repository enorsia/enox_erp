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
use Illuminate\Support\Facades\Cache;
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
        $lock = Cache::lock('tracker:visitor_session_resolve:'.$visitorId, 10);

        return $lock->block(5, fn () => $this->resolveWithinLock($visitorId, $context));
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
    private function resolveWithinLock(string $visitorId, array $context = []): array
    {
        $startedAt = microtime(true);
        TrackerRedisSupport::logFrontendHealth('resolve_visit');

        $now = $this->redis->now();
        $today = $this->redis->todayString($now);
        $record = $this->redis->get($visitorId);
        $gapMinutes = (int) config('tracker.session_gap_minutes', 30);
        $hasVisitedBefore = $this->hasVisitedBefore($visitorId);

        EcomTrackerLogger::frontend()->info('session.resolve.start', 'Finding visitor session', [
            'visitor_id' => $visitorId,
            'has_redis_record' => $record !== null,
            'has_visited_before' => $hasVisitedBefore,
            'gap_seconds' => TrackerSessionClock::gapSeconds(),
            'gap_minutes' => $gapMinutes,
        ]);

        $isNewUniqueVisitor = ! $hasVisitedBefore;
        $isNewSession = false;
        $sessionId = '';
        $resolveReason = 'continue';

        if ($record === null) {
            $latestSession = $hasVisitedBefore ? $this->latestSession($visitorId) : null;

            if (
                $latestSession !== null
                && $this->minutesSince((string) $latestSession->getRawOriginal('last_active_at'), $now) <= $gapMinutes
                && ! TrackerSessionClock::gapExpired((string) $latestSession->getRawOriginal('last_active_at'), $now)
                && ! $this->sessionWallClockExpired($latestSession, $now, $gapMinutes)
            ) {
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
        } elseif (
            TrackerSessionClock::gapExpired($record['last_active_at'], $now)
            || $this->minutesSince($record['last_active_at'], $now) > $gapMinutes
        ) {
            $isNewSession = true;
            $sessionId = (string) Str::uuid();
            $resolveReason = 'gap_expired';
        } else {
            $sessionId = $record['session_id'] !== '' ? $record['session_id'] : (string) Str::uuid();
            $existing = $this->findSessionById($sessionId);

            if ($this->sessionWallClockExpired($existing, $now, $gapMinutes)) {
                $isNewSession = true;
                $sessionId = (string) Str::uuid();
                $resolveReason = 'session_max_age';
            } else {
                $isNewSession = false;
                $resolveReason = 'continue_redis';
            }
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
        $gapMinutes = (int) config('tracker.session_gap_minutes', 30);
        $activeSessionId = $record !== null ? (string) ($record['session_id'] ?? '') : '';
        $existing = $activeSessionId !== '' ? $this->findSessionById($activeSessionId) : null;

        $needsFullResolve = $record === null
            || $record['last_date'] !== $today
            || TrackerSessionClock::gapExpired($record['last_active_at'], $now)
            || $this->minutesSince($record['last_active_at'] ?? '', $now) > $gapMinutes
            || $this->sessionWallClockExpired($existing, $now, $gapMinutes);

        if ($needsFullResolve) {
            $latest = $this->latestSession($visitorId);

            if (
                $latest !== null
                && ! TrackerSessionClock::gapExpired((string) $latest->getRawOriginal('last_active_at'), $now)
                && ! $this->sessionWallClockExpired($latest, $now, $gapMinutes)
            ) {
                $sessionId = $latest->session_id;

                $this->redis->put($visitorId, [
                    'last_active_at' => $now->toIso8601String(),
                    'last_date' => $today,
                    'session_id' => $sessionId,
                ]);

                EcomTrackerLogger::frontend()->debug('session.resolve.ingest', 'Resumed open session from database', [
                    'visitor_id' => $visitorId,
                    'session_id' => $sessionId,
                    'proposed_session_id' => $proposedSessionId,
                ]);

                return $this->buildResult($visitorId, $sessionId, false, false);
            }

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

    private function findSessionById(string $sessionId): ?ActivityEcomUser
    {
        return ActivityEcomUser::query()
            ->where('session_id', $sessionId)
            ->first();
    }

    private function sessionWallClockExpired(?ActivityEcomUser $session, Carbon $now, int $gapMinutes): bool
    {
        if ($session === null) {
            return false;
        }

        $createdAt = TrackerTime::toUtc($session->getRawOriginal('created_at'));

        if ($createdAt === null) {
            return false;
        }

        return $this->minutesSince(TrackerTime::formatUtc($createdAt) ?? '', $now) >= $gapMinutes;
    }

    private function minutesSince(string $lastActiveAt, Carbon $now): int
    {
        if ($lastActiveAt === '') {
            return PHP_INT_MAX;
        }

        return (int) TrackerTime::toUtc($lastActiveAt)?->diffInMinutes($now) ?? PHP_INT_MAX;
    }

    /**
     * Pick the session that owns this event's wall-clock time (for delayed queue replay).
     *
     * @param  array<string, mixed>  $context
     */
    public function sessionIdForEventClock(string $visitorId, Carbon $eventAt, array $context = []): string
    {
        $liveSessionId = isset($context['live_session_id']) ? (string) $context['live_session_id'] : '';
        $reference = TrackerTime::nowUtc();

        if ($liveSessionId !== '' && TrackerSessionClock::isLiveActivity($eventAt, $reference)) {
            return $liveSessionId;
        }

        $sessions = ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->orderBy('created_at')
            ->get();

        $matches = $sessions->filter(
            fn (ActivityEcomUser $session) => TrackerSessionClock::eventFallsInSessionWindow($eventAt, $session)
        );

        if ($matches->isNotEmpty()) {
            return $this->pickBestSessionForEvent($eventAt, $matches, $context)->session_id;
        }

        $sessionId = $this->matchIngestBackfillPlan($eventAt, $context);

        if ($sessionId !== null) {
            return $sessionId;
        }

        $latest = $this->latestSession($visitorId);

        if ($latest !== null && TrackerSessionClock::eventFallsInSessionWindow($eventAt, $latest)) {
            return $latest->session_id;
        }

        if ($latest !== null && ! TrackerSessionClock::gapExpired((string) $latest->getRawOriginal('last_active_at'), $eventAt)) {
            return $latest->session_id;
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
     * @param  \Illuminate\Support\Collection<int, ActivityEcomUser>  $matches
     * @param  array<string, mixed>  $context
     */
    private function pickBestSessionForEvent(Carbon $eventAt, $matches, array $context): ActivityEcomUser
    {
        $liveSessionId = isset($context['live_session_id']) ? (string) $context['live_session_id'] : '';
        $reference = TrackerTime::nowUtc();

        $ranked = $matches->values()->all();

        usort($ranked, function (ActivityEcomUser $left, ActivityEcomUser $right) use ($eventAt, $liveSessionId, $reference): int {
            return $this->sessionEventRank($left, $eventAt, $liveSessionId, $reference)
                <=> $this->sessionEventRank($right, $eventAt, $liveSessionId, $reference);
        });

        return $ranked[0];
    }

    private function sessionEventRank(
        ActivityEcomUser $session,
        Carbon $eventAt,
        string $liveSessionId,
        Carbon $reference,
    ): string {
        $created = TrackerTime::toUtc($session->created_at ?? $session->getRawOriginal('created_at'));
        $lastActive = TrackerTime::toUtc($session->last_active_at ?? $session->getRawOriginal('last_active_at')) ?? $created;

        $inCore = $created !== null
            && $lastActive !== null
            && $eventAt->greaterThanOrEqualTo($created)
            && $eventAt->lessThanOrEqualTo($lastActive);

        $isLiveSession = $liveSessionId !== '' && $session->session_id === $liveSessionId;
        $staleForLive = $isLiveSession && ! TrackerSessionClock::isLiveActivity($eventAt, $reference);

        return sprintf(
            '%d-%d-%010d',
            $inCore ? 0 : 1,
            $staleForLive ? 1 : 0,
            $created?->getTimestamp() ?? PHP_INT_MAX,
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function matchIngestBackfillPlan(Carbon $eventAt, array $context): ?string
    {
        $plan = $context['backfill_plan'] ?? null;

        if (! is_array($plan) || $plan === []) {
            return null;
        }

        foreach ($plan as $sessionId => $bounds) {
            if (! is_string($sessionId) || ! is_array($bounds)) {
                continue;
            }

            $created = TrackerTime::toUtc($bounds['first_at'] ?? null);
            $lastActive = TrackerTime::toUtc($bounds['last_at'] ?? null);

            if ($created === null || $lastActive === null) {
                continue;
            }

            if (TrackerSessionClock::eventFallsInActivitySpan($eventAt, $created, $lastActive)) {
                return $sessionId;
            }
        }

        return null;
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
