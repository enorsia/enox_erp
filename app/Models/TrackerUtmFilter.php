<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Static UTM source / medium options for tracker analytics filters.
 */
final class TrackerUtmFilter
{
    /**
     * @return array<string, string>
     */
    public static function sources(): array
    {
        return config('tracker.utm_sources', []);
    }

    /**
     * @return array<string, string>
     */
    public static function mediums(): array
    {
        return config('tracker.utm_mediums', []);
    }

    public static function resolveSource(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return array_key_exists($value, self::sources()) ? $value : null;
    }

    public static function resolveMedium(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return array_key_exists($value, self::mediums()) ? $value : null;
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applySourceFilter(Builder $query, ?string $source): void
    {
        $source = self::resolveSource($source);

        if ($source === null) {
            return;
        }

        if ($source === '(direct)') {
            $query->where(function (Builder $inner) {
                $inner->whereNull('utm_source')->orWhere('utm_source', '');
            });

            return;
        }

        $query->where('utm_source', $source);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applyMediumFilter(Builder $query, ?string $medium): void
    {
        $medium = self::resolveMedium($medium);

        if ($medium === null) {
            return;
        }

        if ($medium === 'none') {
            $query->where(function (Builder $inner) {
                $inner->whereNull('utm_medium')->orWhere('utm_medium', '');
            });

            return;
        }

        $query->where('utm_medium', $medium);
    }
}
