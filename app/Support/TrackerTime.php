<?php

namespace App\Support;

use Carbon\Carbon;

class TrackerTime
{
    public static function timezone(): string
    {
        return config('tracker.visitor_timezone', 'Europe/London');
    }

    public static function nowUtc(): Carbon
    {
        return Carbon::now('UTC');
    }

    public static function localNow(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /**
     * Parse a client or DB value and return UTC.
     */
    public static function toUtc(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse($value)->utc();
    }

    /**
     * Return value in the configured visitor timezone (Europe/London).
     */
    public static function toLocal(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        $utc = self::toUtc($value);

        return $utc?->copy()->timezone(self::timezone());
    }

    /**
     * Format a value for UTC storage in the database.
     */
    public static function formatUtc(mixed $value): ?string
    {
        $utc = self::toUtc($value);

        return $utc?->format('Y-m-d H:i:s');
    }

    /**
     * UK calendar date string for visit_date and day-boundary logic.
     */
    public static function localDate(?Carbon $value = null): string
    {
        if ($value === null) {
            return self::localNow()->toDateString();
        }

        return self::toLocal($value)?->toDateString() ?? self::localNow()->toDateString();
    }

    /**
     * Human-readable label for UI notices (IANA id + UTC offset).
     */
    public static function timezoneLabel(): string
    {
        $local = self::localNow();

        return self::timezone().' (UTC'.$local->format('P').')';
    }

    public static function todayPresetLabel(): string
    {
        return 'Today (00:00:01 to 23:59:59)';
    }

    public static function todayPresetButtonLabel(): string
    {
        return 'Today';
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public static function todayRangeUtc(): array
    {
        $fromLocal = self::localNow()->startOfDay()->addSecond();
        $toLocal = self::localNow()->endOfDay();

        return [
            'from' => $fromLocal->copy()->utc(),
            'to' => $toLocal->copy()->utc(),
        ];
    }

    /**
     * Bounds for naive visitor-local DATETIME columns in activity tables.
     *
     * @return array{0: string, 1: string}
     */
    public static function storageRange(Carbon $from, Carbon $to): array
    {
        $fromLocal = self::toLocal($from) ?? $from->copy()->timezone(self::timezone());
        $toLocal = self::toLocal($to) ?? $to->copy()->timezone(self::timezone());

        return [
            $fromLocal->format('Y-m-d H:i:s'),
            $toLocal->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applySessionActivityWindow($query, Carbon $from, Carbon $to, ?string $table = null): void
    {
        [$fromBound, $toBound] = self::storageRange($from, $to);
        $createdAt = $table ? "{$table}.created_at" : 'created_at';
        $lastActiveAt = $table ? "{$table}.last_active_at" : 'last_active_at';

        $query->where(function ($inner) use ($fromBound, $toBound, $createdAt, $lastActiveAt) {
            $inner->whereBetween($createdAt, [$fromBound, $toBound])
                ->orWhereBetween($lastActiveAt, [$fromBound, $toBound]);
        });
    }

    /**
     * Parse a naive visitor-local DATETIME value from activity tables.
     */
    public static function fromStorage(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return Carbon::parse($value->format('Y-m-d H:i:s'), self::timezone());
        }

        return Carbon::parse($value, self::timezone());
    }

    public static function secondsSinceStorage(mixed $value, ?Carbon $reference = null): int
    {
        $stored = self::fromStorage($value);

        if ($stored === null) {
            return 0;
        }

        $reference ??= self::localNow();

        if ($stored->greaterThan($reference)) {
            return 0;
        }

        return (int) $stored->diffInSeconds($reference);
    }

    public static function diffForHumansFromStorage(mixed $value): ?string
    {
        return self::fromStorage($value)?->diffForHumans();
    }

    public static function formatFromStorage(mixed $value, string $format = 'd M Y, H:i'): ?string
    {
        return self::fromStorage($value)?->format($format);
    }

    public static function formatIdleSince(mixed $value): string
    {
        return self::formatIdleSeconds(self::secondsSinceStorage($value));
    }

    public static function formatIdleSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds < 60) {
            return $seconds.'s ago';
        }

        if ($seconds < 3600) {
            return max(1, (int) round($seconds / 60)).'m ago';
        }

        if ($seconds < 86400) {
            return max(1, (int) round($seconds / 3600)).'h ago';
        }

        return max(1, (int) round($seconds / 86400)).'d ago';
    }
}
