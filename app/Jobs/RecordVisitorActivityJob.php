<?php

namespace App\Jobs;

use App\Models\ActivityEcomDailyVisitor;
use App\Models\ActivityEcomUser;
use App\Services\BotContextPersister;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerTime;
use App\Support\UserAgentParser;
use App\Support\VisitorSessionRedis;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecordVisitorActivityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $visitorId,
        public string $sessionId,
        public bool $isNewDailyVisitor,
        public bool $isNewSession,
        public array $context = [],
        public ?string $resolvedAt = null,
    ) {
        $this->onConnection((string) config('tracker.queue_connection', 'tracker'));
        $this->onQueue((string) config('tracker.queue_name', 'tracker'));
    }

    public function handle(): void
    {
        $startedAt = microtime(true);

        EcomTrackerLogger::frontend()->info('job.record_visitor.start', 'Background job started for visitor', [
            'visitor_id' => $this->visitorId,
            'session_id' => $this->sessionId,
            'is_new_session' => $this->isNewSession,
            'is_new_daily_visitor' => $this->isNewDailyVisitor,
        ]);

        $now = TrackerTime::toUtc($this->resolvedAt ?? TrackerTime::nowUtc()) ?? TrackerTime::nowUtc();
        $formattedNow = TrackerTime::formatUtc($now);
        $visitDate = TrackerTime::localDate($now);

        $session = ActivityEcomUser::query()
            ->where('session_id', $this->sessionId)
            ->first();

        if ($this->isNewSession) {
            $this->ensureDailyLedgerRow($formattedNow, $visitDate);

            if ($session === null) {
                $session = $this->createSession($formattedNow);
                $this->afterSessionCreated($session, $formattedNow, $visitDate);
            } else {
                $this->updateExistingSession($session, $now, $formattedNow);
                EcomTrackerLogger::frontend()->debug('job.record_visitor.idempotent', 'Session already exists — updated instead of insert', [
                    'visitor_id' => $this->visitorId,
                    'session_id' => $this->sessionId,
                ]);
            }
        } elseif ($session !== null) {
            $this->updateExistingSession($session, $now, $formattedNow);
        } else {
            EcomTrackerLogger::frontend()->warning('job.record_visitor.missing_session', 'Session not found in database', [
                'visitor_id' => $this->visitorId,
                'session_id' => $this->sessionId,
            ]);
        }

        if ($this->isNewDailyVisitor) {
            app(VisitorSessionRedis::class)->markSeenBefore($this->visitorId);
        }

        if (app(VisitorSessionRedis::class)->acquireRollupLock($this->visitorId, $visitDate)) {
            $this->rollupDailyDuration($this->visitorId, $visitDate, $formattedNow);

            EcomTrackerLogger::frontend()->debug('job.record_visitor.rollup', 'Daily visit time counted', [
                'visitor_id' => $this->visitorId,
                'visit_date' => $visitDate,
            ]);
        }

        EcomTrackerLogger::frontend()->info('job.record_visitor.complete', 'Background job done', [
            'visitor_id' => $this->visitorId,
            'session_id' => $this->sessionId,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }

    public function failed(Throwable $exception): void
    {
        EcomTrackerLogger::frontend()->error('job.record_visitor.failed', 'Background job failed', [
            'visitor_id' => $this->visitorId,
            'session_id' => $this->sessionId,
            'message' => $exception->getMessage(),
        ]);
    }

    private function createSession(string $formattedNow): ActivityEcomUser
    {
        $parsed = UserAgentParser::parse($this->context['user_agent'] ?? null);

        return ActivityEcomUser::query()->firstOrCreate(
            ['session_id' => $this->sessionId],
            [
                'visitor_id' => $this->visitorId,
                'ip' => $this->context['ip'] ?? null,
                'user_agent' => $this->context['user_agent'] ?? null,
                'country' => $this->context['country'] ?? null,
                'device_type' => $parsed['device_type'],
                'browser' => $parsed['browser'],
                'os' => $parsed['os'],
                'last_active_at' => $formattedNow,
                'session_duration_seconds' => 0,
                'created_at' => $formattedNow,
                'updated_at' => $formattedNow,
            ],
        );
    }

    private function afterSessionCreated(ActivityEcomUser $session, string $formattedNow, string $visitDate): void
    {
        if (! $session->wasRecentlyCreated) {
            return;
        }

        EcomTrackerLogger::frontend()->info('job.record_visitor.new_session', 'New session saved in database', [
            'visitor_id' => $this->visitorId,
            'session_id' => $this->sessionId,
            'device_type' => $session->device_type,
            'country' => $this->context['country'] ?? null,
        ]);

        $botResolved = $this->context['bot_resolved'] ?? null;

        if (is_array($botResolved)) {
            app(BotContextPersister::class)->persistIfAbsent($this->sessionId, $botResolved);
        }

        ActivityEcomDailyVisitor::query()
            ->where('visitor_id', $this->visitorId)
            ->whereDate('visit_date', $visitDate)
            ->update([
                'last_seen_at' => $formattedNow,
                'session_count' => DB::raw('session_count + 1'),
            ]);
    }

    private function updateExistingSession(ActivityEcomUser $session, Carbon $now, string $formattedNow): void
    {
        $existingLastActive = TrackerTime::toUtc($session->getRawOriginal('last_active_at'));

        if ($existingLastActive !== null && $existingLastActive->gt($now)) {
            EcomTrackerLogger::frontend()->debug('job.record_visitor.stale', 'Skipping stale session update', [
                'visitor_id' => $this->visitorId,
                'session_id' => $this->sessionId,
                'job_time' => $formattedNow,
            ]);

            return;
        }

        $createdAt = TrackerTime::toUtc($session->getRawOriginal('created_at'));
        $duration = $this->sessionDurationSeconds($createdAt, $now);

        $session->update([
            'last_active_at' => $formattedNow,
            'session_duration_seconds' => $duration,
            'updated_at' => $formattedNow,
        ]);

        EcomTrackerLogger::frontend()->debug('job.record_visitor.update', 'Old session time updated', [
            'visitor_id' => $this->visitorId,
            'session_id' => $this->sessionId,
            'session_duration_seconds' => $duration,
        ]);
    }

    private function sessionDurationSeconds(?Carbon $createdAt, Carbon $now): int
    {
        if ($createdAt === null || $now->lt($createdAt)) {
            return 0;
        }

        return max(0, (int) $createdAt->diffInSeconds($now, true));
    }

    private function ensureDailyLedgerRow(string $formattedNow, string $visitDate): void
    {
        $existing = ActivityEcomDailyVisitor::query()
            ->where('visitor_id', $this->visitorId)
            ->whereDate('visit_date', $visitDate)
            ->first();

        if ($existing !== null) {
            return;
        }

        ActivityEcomDailyVisitor::query()->create([
            'visitor_id' => $this->visitorId,
            'visit_date' => $visitDate,
            'first_seen_at' => $formattedNow,
            'last_seen_at' => $formattedNow,
            'total_duration_seconds' => 0,
            'session_count' => 0,
        ]);

        EcomTrackerLogger::frontend()->debug('job.record_visitor.daily_ledger', 'New daily visitor record created', [
            'visitor_id' => $this->visitorId,
            'visit_date' => $visitDate,
        ]);
    }

    private function rollupDailyDuration(string $visitorId, string $visitDate, string $now): void
    {
        $localStart = TrackerTime::toLocal($visitDate.' 00:00:00');
        $localEnd = TrackerTime::toLocal($visitDate.' 23:59:59');

        if ($localStart === null || $localEnd === null) {
            return;
        }

        $utcFrom = $localStart->copy()->utc()->format('Y-m-d H:i:s');
        $utcTo = $localEnd->copy()->utc()->format('Y-m-d H:i:s');

        $totalDuration = (int) ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->whereBetween('created_at', [$utcFrom, $utcTo])
            ->sum('session_duration_seconds');

        ActivityEcomDailyVisitor::query()
            ->where('visitor_id', $visitorId)
            ->whereDate('visit_date', $visitDate)
            ->update([
                'last_seen_at' => $now,
                'total_duration_seconds' => $totalDuration,
            ]);
    }
}
