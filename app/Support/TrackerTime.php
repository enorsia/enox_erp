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
}
