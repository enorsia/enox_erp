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

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        // ISO-8601 from the storefront tracker — honour Z / explicit offsets.
        if (preg_match('/[TZ]|(?:[+-]\d{2}:?\d{2})$/', $string)) {
            return Carbon::parse($string)->utc();
        }

        // Naive DATETIME strings in activity tables are stored as UTC.
        return Carbon::parse($string, 'UTC')->utc();
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

    public static function yesterdayPresetLabel(): string
    {
        return 'Yesterday (00:00:01 to 23:59:59)';
    }

    public static function yesterdayPresetButtonLabel(): string
    {
        return 'Yesterday';
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public static function yesterdayRangeUtc(): array
    {
        $fromLocal = self::localNow()->subDay()->startOfDay()->addSecond();
        $toLocal = self::localNow()->subDay()->endOfDay();

        return [
            'from' => $fromLocal->copy()->utc(),
            'to' => $toLocal->copy()->utc(),
        ];
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public static function dayBeforeYesterdayRangeUtc(): array
    {
        $fromLocal = self::localNow()->subDays(2)->startOfDay()->addSecond();
        $toLocal = self::localNow()->subDays(2)->endOfDay();

        return [
            'from' => $fromLocal->copy()->utc(),
            'to' => $toLocal->copy()->utc(),
        ];
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
     * Inclusive UTC datetime bounds for activity table columns (stored as UTC).
     *
     * @return array{0: string, 1: string}
     */
    public static function storageRange(Carbon $from, Carbon $to): array
    {
        $fromUtc = self::toUtc($from) ?? $from->copy()->utc();
        $toUtc = self::toUtc($to) ?? $to->copy()->utc();

        return [
            $fromUtc->format('Y-m-d H:i:s'),
            $toUtc->format('Y-m-d H:i:s'),
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
     * Match /admin/ecom-activity session date rules:
     * - Today (24h): session started OR was last active in range.
     * - All other presets/ranges: session started on a calendar date within the range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public static function applyEcomActivitySessionScope($query, Carbon $from, Carbon $to, ?string $period = null): void
    {
        if ($period === '24h') {
            self::applySessionActivityWindow($query, $from, $to);

            return;
        }

        $fromLocal = self::toLocal($from);
        $toLocal = self::toLocal($to);

        if ($fromLocal !== null && $toLocal !== null) {
            $query->whereBetween('created_at', self::storageRange(
                $fromLocal->copy()->startOfDay()->utc(),
                $toLocal->copy()->endOfDay()->utc(),
            ));

            return;
        }

        $query->whereBetween('created_at', self::storageRange($from, $to));
    }

    /**
     * Parse a UTC DATETIME value from activity tables into visitor-local time.
     */
    public static function fromStorage(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::toLocal($value);
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
