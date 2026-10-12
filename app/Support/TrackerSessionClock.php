<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use Carbon\Carbon;

class TrackerSessionClock
{
    public static function gapSeconds(): int
    {
        return max(60, (int) config('tracker.session_gap_minutes', 30) * 60);
    }

    public static function clockSkewSeconds(): int
    {
        return 120;
    }

    /**
     * Latest meaningful clock instant on an ingested event (wall clock, not dwell counters).
     *
     * @param  array<string, mixed>  $event
     */
    public static function activityAt(array $event): ?Carbon
    {
        $candidates = [];

        foreach (['end_time', 'created_at', 'start_time'] as $field) {
            if (! isset($event[$field]) || $event[$field] === '' || $event[$field] === null) {
                continue;
            }

            $parsed = TrackerTime::toUtc($event[$field]);

            if ($parsed !== null) {
                $candidates[] = $parsed;
            }
        }

        if ($candidates === []) {
            return null;
        }

        return collect($candidates)->sortByDesc(fn (Carbon $at) => $at->getTimestamp())->first();
    }

    public static function gapExpired(string $lastActiveAt, Carbon $reference): bool
    {
        if ($lastActiveAt === '') {
            return true;
        }

        $last = TrackerTime::toUtc($lastActiveAt);

        if ($last === null) {
            return true;
        }

        return $last->diffInSeconds($reference, absolute: true) > self::gapSeconds();
    }

    public static function eventFallsInSessionWindow(Carbon $eventAt, ActivityEcomUser $session): bool
    {
        $created = TrackerTime::toUtc($session->created_at ?? $session->getRawOriginal('created_at'));
        $lastActive = TrackerTime::toUtc($session->last_active_at ?? $session->getRawOriginal('last_active_at')) ?? $created;

        if ($created === null || $lastActive === null) {
            return false;
        }

        return self::eventFallsInActivitySpan($eventAt, $created, $lastActive);
    }

    public static function eventFallsInActivitySpan(Carbon $eventAt, Carbon $created, Carbon $lastActive): bool
    {
        $windowStart = $created->copy()->subSeconds(self::clockSkewSeconds());
        $windowEnd = $lastActive->copy()->addSeconds(self::gapSeconds());

        return $eventAt->greaterThanOrEqualTo($windowStart) && $eventAt->lessThanOrEqualTo($windowEnd);
    }

    public static function isLiveActivity(Carbon $eventAt, Carbon $reference): bool
    {
        return $eventAt->diffInSeconds($reference, absolute: true) <= self::gapSeconds();
    }
}
