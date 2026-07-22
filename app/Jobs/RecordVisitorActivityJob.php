<?php

namespace App\Jobs;

use App\Models\ActivityEcomDailyVisitor;
use App\Models\ActivityEcomUser;
use App\Services\BotContextPersister;
use App\Support\TrackerTime;
use App\Support\UserAgentParser;
use App\Support\VisitorSessionRedis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

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
        $now = TrackerTime::toUtc($this->resolvedAt ?? TrackerTime::nowUtc()) ?? TrackerTime::nowUtc();
        $formattedNow = TrackerTime::formatUtc($now);
        $visitDate = TrackerTime::localDate($now);

        if ($this->isNewSession) {
            $this->ensureDailyLedgerRow($formattedNow, $visitDate);

            $parsed = UserAgentParser::parse($this->context['user_agent'] ?? null);

            ActivityEcomUser::query()->create([
                'session_id' => $this->sessionId,
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
        } else {
            $session = ActivityEcomUser::query()
                ->where('session_id', $this->sessionId)
                ->first();

            if ($session) {
                $createdAt = TrackerTime::toUtc($session->getRawOriginal('created_at'));
                $duration = $createdAt ? (int) $createdAt->diffInSeconds($now) : 0;

                $session->update([
                    'last_active_at' => $formattedNow,
                    'session_duration_seconds' => $duration,
                    'updated_at' => $formattedNow,
                ]);
            }
        }

        if ($this->isNewDailyVisitor) {
            app(VisitorSessionRedis::class)->markSeenBefore($this->visitorId);
        }

        if (app(VisitorSessionRedis::class)->acquireRollupLock($this->visitorId, $visitDate)) {
            $this->rollupDailyDuration($this->visitorId, $visitDate, $formattedNow);
        }
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
