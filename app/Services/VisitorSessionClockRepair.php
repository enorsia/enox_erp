<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\TrackerSessionClock;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VisitorSessionClockRepair
{
    /**
     * Fix only sessions whose actions span longer than the tracker gap (clear mis-merges).
     * Does not touch healthy sessions or other visitors' data.
     *
     * @return array{sessions_fixed: int, moved: int}
     */
    public function repairAllMismergedSessions(): array
    {
        $sessionsFixed = 0;
        $moved = 0;

        ActivityEcomUser::query()
            ->whereNotNull('visitor_id')
            ->where('visitor_id', '!=', '')
            ->orderBy('id')
            ->chunkById(500, function ($sessions) use (&$sessionsFixed, &$moved) {
                foreach ($sessions as $session) {
                    $result = DB::transaction(
                        fn () => $this->repairSessionIfMismerged((string) $session->session_id),
                    );

                    if ($result['segments'] > 1) {
                        $sessionsFixed++;
                    }

                    $moved += $result['moved'];
                }
            });

        return ['sessions_fixed' => $sessionsFixed, 'moved' => $moved];
    }

    /**
     * Split one session when its own actions break the 30-minute gap rule.
     * The first time chunk stays on the original session_id; later chunks get new sessions.
     *
     * @return array{moved: int, segments: int}
     */
    public function repairSessionIfMismerged(string $sessionId): array
    {
        $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

        if ($session === null || trim((string) ($session->visitor_id ?? '')) === '') {
            return ['moved' => 0, 'segments' => 0];
        }

        $visitorId = (string) $session->visitor_id;

        $actions = ActivityEcomUserAction::query()
            ->where('session_id', $sessionId)
            ->get()
            ->sortBy(fn (ActivityEcomUserAction $action) => $this->actionActivityAt($action)->getTimestamp())
            ->values();

        if ($actions->count() < 2) {
            return ['moved' => 0, 'segments' => 0];
        }

        $firstAt = $this->actionActivityAt($actions->first());
        $lastAt = $this->actionActivityAt($actions->last());

        if ($lastAt->diffInSeconds($firstAt, absolute: true) <= TrackerSessionClock::gapSeconds()) {
            return ['moved' => 0, 'segments' => 0];
        }

        $segments = $this->segmentActionsByGap($actions);

        if ($segments->count() <= 1) {
            return ['moved' => 0, 'segments' => 1];
        }

        $moved = 0;
        $rollupIds = [$sessionId];

        foreach ($segments as $index => $segment) {
            $segmentFirst = $this->actionActivityAt($segment->first());
            $segmentLast = $this->actionActivityAt($segment->last());

            if ($index === 0) {
                $targetSessionId = $sessionId;
            } else {
                $targetSessionId = (string) Str::uuid();
                $this->ensureSessionExists(
                    $targetSessionId,
                    $visitorId,
                    $sessionId,
                    $segmentFirst,
                    $segmentLast,
                );
                $rollupIds[] = $targetSessionId;
            }

            foreach ($segment as $action) {
                if ($action->session_id !== $targetSessionId) {
                    $action->update(['session_id' => $targetSessionId]);
                    $moved++;
                }
            }
        }

        foreach (array_unique($rollupIds) as $rollupId) {
            $this->rollupSession($rollupId);
        }

        return ['moved' => $moved, 'segments' => $segments->count()];
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return Collection<int, Collection<int, ActivityEcomUserAction>>
     */
    private function segmentActionsByGap(Collection $actions): Collection
    {
        $segments = collect();
        $current = collect();
        $lastAt = null;

        foreach ($actions as $action) {
            $at = $this->actionActivityAt($action);

            if ($lastAt !== null && $at->diffInSeconds($lastAt, absolute: true) > TrackerSessionClock::gapSeconds()) {
                if ($current->isNotEmpty()) {
                    $segments->push($current);
                }

                $current = collect();
            }

            $current->push($action);
            $lastAt = $at;
        }

        if ($current->isNotEmpty()) {
            $segments->push($current);
        }

        return $segments;
    }

    private function actionActivityAt(ActivityEcomUserAction $action): Carbon
    {
        $fromEvent = TrackerSessionClock::activityAt([
            'end_time' => $action->end_time,
            'created_at' => $action->created_at,
            'start_time' => $action->start_time,
        ]);

        return $fromEvent ?? TrackerTime::toUtc($action->created_at) ?? TrackerTime::nowUtc();
    }

    private function ensureSessionExists(
        string $targetSessionId,
        string $visitorId,
        string $templateSessionId,
        Carbon $firstAt,
        Carbon $lastAt,
    ): void {
        if (ActivityEcomUser::query()->where('session_id', $targetSessionId)->exists()) {
            return;
        }

        $template = ActivityEcomUser::query()
            ->where('session_id', $templateSessionId)
            ->first();

        if ($template === null) {
            throw new \RuntimeException("Cannot create session {$targetSessionId}: template {$templateSessionId} missing.");
        }

        $now = TrackerTime::formatUtc(TrackerTime::nowUtc());

        ActivityEcomUser::query()->create([
            'session_id' => $targetSessionId,
            'visitor_id' => $visitorId,
            'user_id' => $template->user_id,
            'user_name' => $template->user_name,
            'user_email' => $template->user_email,
            'user_phone' => $template->user_phone,
            'ip' => $template->ip,
            'user_agent' => $template->user_agent,
            'device_type' => $template->device_type,
            'browser' => $template->browser,
            'os' => $template->os,
            'country' => $template->country,
            'city' => $template->city,
            'utm_source' => $template->utm_source,
            'utm_medium' => $template->utm_medium,
            'utm_campaign' => $template->utm_campaign,
            'landing_page' => $template->landing_page,
            'is_logged_in' => $template->is_logged_in,
            'has_add_to_cart' => false,
            'has_begin_checkout' => false,
            'has_proceed_checkout' => false,
            'has_payment_success' => false,
            'latest_funnel_stage' => null,
            'actions_count' => 0,
            'session_duration_seconds' => 0,
            'created_at' => TrackerTime::formatUtc($firstAt),
            'last_active_at' => TrackerTime::formatUtc($lastAt),
            'updated_at' => $now,
        ]);
    }

    private function rollupSession(string $sessionId): void
    {
        $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

        if ($session === null) {
            return;
        }

        $actions = ActivityEcomUserAction::query()
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get();

        if ($actions->isEmpty()) {
            return;
        }

        $first = $this->actionActivityAt($actions->first());
        $last = $first->copy();

        foreach ($actions as $action) {
            $at = $this->actionActivityAt($action);

            if ($at->lessThan($first)) {
                $first = $at->copy();
            }

            if ($at->greaterThan($last)) {
                $last = $at->copy();
            }
        }

        $session->update([
            'created_at' => TrackerTime::formatUtc($first),
            'last_active_at' => TrackerTime::formatUtc($last),
            'actions_count' => $actions->count(),
            'session_duration_seconds' => max(0, (int) $first->diffInSeconds($last, absolute: true)),
            'updated_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
        ]);
    }
}
