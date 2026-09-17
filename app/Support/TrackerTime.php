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
        return self::todayPresetButtonLabel();
    }

    public static function todayPresetButtonLabel(): string
    {
        return 'Today';
    }

    public static function yesterdayPresetLabel(): string
    {
        return self::yesterdayPresetButtonLabel();
    }

    public static function yesterdayPresetButtonLabel(): string
    {
        return 'Yesterday';
    }

    public static function formatLocalDateLabel(Carbon $date): string
    {
        return $date->format('j M Y');
    }

    public static function formatLocalDateRangeLabel(Carbon $from, Carbon $to): string
    {
        $fromLocal = self::toLocal($from) ?? $from;
        $toLocal = self::toLocal($to) ?? $to;

        if ($fromLocal->toDateString() === $toLocal->toDateString()) {
            return self::formatLocalDateLabel($fromLocal);
        }

        return self::formatLocalDateLabel($fromLocal).' – '.self::formatLocalDateLabel($toLocal);
    }

    /**
     * @param  array{from?: Carbon|null, to?: Carbon|null, label?: string, period?: string}  $range
     * @return array{type: 'single', label: string}|array{type: 'range', from: string, to: string}
     */
    public static function rangeDisplayParts(array $range): array
    {
        $label = trim((string) ($range['label'] ?? ''));
        $period = (string) ($range['period'] ?? '');
        $from = $range['from'] ?? null;
        $to = $range['to'] ?? null;

        if ($period === '24h') {
            return ['type' => 'single', 'label' => self::todayPresetLabel()];
        }

        if ($period === 'yesterday') {
            return ['type' => 'single', 'label' => self::yesterdayPresetLabel()];
        }

        if ($from instanceof Carbon && $to instanceof Carbon) {
            $fromLocal = self::toLocal($from);
            $toLocal = self::toLocal($to);

            if ($fromLocal && $toLocal) {
                $today = self::localNow()->toDateString();
                $yesterday = self::localNow()->copy()->subDay()->toDateString();
                $fromDate = $fromLocal->toDateString();
                $toDate = $toLocal->toDateString();

                if ($fromDate === $today && $toDate === $today) {
                    return ['type' => 'single', 'label' => self::todayPresetLabel()];
                }

                if ($fromDate === $yesterday && $toDate === $yesterday) {
                    return ['type' => 'single', 'label' => self::yesterdayPresetLabel()];
                }

                if ($fromDate === $toDate) {
                    return ['type' => 'single', 'label' => self::formatLocalDateLabel($fromLocal)];
                }

                return [
                    'type' => 'range',
                    'from' => self::formatLocalDateLabel($fromLocal),
                    'to' => self::formatLocalDateLabel($toLocal),
                ];
            }
        }

        return ['type' => 'single', 'label' => self::shortRangeDisplayLabel($label)];
    }

    /**
     * @param  array{from?: Carbon|null, to?: Carbon|null, label?: string, period?: string}  $range
     */
    public static function rangeDisplayLabel(array $range): string
    {
        $parts = self::rangeDisplayParts($range);

        if (($parts['type'] ?? '') === 'range') {
            return $parts['from'].' – '.$parts['to'];
        }

        return $parts['label'] ?? '';
    }

    /**
     * @return array{type: 'single', label: string}|array{type: 'range', from: string, to: string}
     */
    public static function comparisonLabelParts(?string $label): array
    {
        $label = self::shortRangeDisplayLabel($label);

        if ($label === '') {
            return ['type' => 'single', 'label' => ''];
        }

        if (preg_match('/^(.+?)\s+–\s+(.+)$/', $label, $matches)) {
            $from = trim($matches[1]);
            $to = trim($matches[2]);

            if (preg_match('/^\d{1,2} \w{3} \d{4}$/', $from) && preg_match('/^\d{1,2} \w{3} \d{4}$/', $to)) {
                if ($from === $to) {
                    return ['type' => 'single', 'label' => $from];
                }

                return ['type' => 'range', 'from' => $from, 'to' => $to];
            }
        }

        return ['type' => 'single', 'label' => $label];
    }

    /**
     * Compact UI label without trailing time-window parentheticals.
     */
    public static function shortRangeDisplayLabel(?string $label): string
    {
        if ($label === null || $label === '') {
            return '';
        }

        $label = trim($label);

        if (preg_match('/^(.+?)\s*\([^)]*\)\s*$/', $label, $matches)) {
            return trim($matches[1]);
        }

        return $label;
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
    public static function applyEcomActivitySessionScope($query, Carbon $from, Carbon $to, ?string $period = null, ?string $table = null): void
    {
        if ($period === '24h') {
            self::applySessionActivityWindow($query, $from, $to, $table);

            return;
        }

        $createdAt = $table ? "{$table}.created_at" : 'created_at';
        $fromLocal = self::toLocal($from);
        $toLocal = self::toLocal($to);

        if ($fromLocal !== null && $toLocal !== null) {
            $query->whereBetween($createdAt, self::storageRange(
                $fromLocal->copy()->startOfDay()->utc(),
                $toLocal->copy()->endOfDay()->utc(),
            ));

            return;
        }

        $query->whereBetween($createdAt, self::storageRange($from, $to));
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

    /**
     * UTC instant used for admin "last active" display and list ordering.
     * Server updated_at is preferred over client last_active_at so local/dev
     * machines stay correct when DB rows were exported from another timezone.
     */
    public static function latestActivityUtc(mixed ...$values): ?Carbon
    {
        $latest = null;

        foreach ($values as $value) {
            $utc = self::toUtc($value);

            if ($utc === null) {
                continue;
            }

            if ($latest === null || $utc->greaterThan($latest)) {
                $latest = $utc;
            }
        }

        return $latest;
    }

    public static function diffForHumansLatestActivity(mixed ...$values): ?string
    {
        return self::diffForHumansFromStorage(self::latestActivityUtc(...$values));
    }

    public static function formatLatestActivityFromStorage(mixed ...$values): ?string
    {
        return self::formatFromStorage(self::latestActivityUtc(...$values));
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
