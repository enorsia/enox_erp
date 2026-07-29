<?php

namespace App\Models;

use App\Services\EcomActivityFilterCounts;
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

        if (array_key_exists($value, self::sources())) {
            return $value;
        }

        return self::isValidToken($value) ? $value : null;
    }

    public static function resolveMedium(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (array_key_exists($value, self::mediums())) {
            return $value;
        }

        return self::isValidToken($value) ? $value : null;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array{sources: array<string, string>, mediums: array<string, string>, selected_source: string, selected_medium: string}
     */
    public static function formState(?string $source = null, ?string $medium = null, array $sourceCounts = [], array $mediumCounts = []): array
    {
        return [
            'sources' => self::labeledOptions($sourceCounts, 'source'),
            'mediums' => self::labeledOptions($mediumCounts, 'medium'),
            'selected_source' => self::resolveSource($source) ?? '',
            'selected_medium' => self::resolveMedium($medium) ?? '',
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, string>
     */
    public static function labeledOptions(array $counts, string $type): array
    {
        if ($counts === []) {
            return $type === 'source' ? self::sources() : self::mediums();
        }

        $labels = $type === 'source' ? self::sources() : self::mediums();
        $options = [];

        foreach ($counts as $value => $count) {
            if ($count < 1) {
                continue;
            }

            $label = $labels[$value] ?? self::humanizeToken((string) $value);
            $options[(string) $value] = "{$label} ({$count})";
        }

        return $options;
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @return array<string, int>
     */
    public static function sourceCountsFrom(Builder $query): array
    {
        $table = $query->getModel()->getTable();

        return EcomActivityFilterCounts::aggregateQuery($query)
            ->selectRaw("COALESCE(NULLIF({$table}.utm_source, ''), '(direct)') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderByDesc('total')
            ->pluck('total', 'bucket')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @return array<string, int>
     */
    public static function mediumCountsFrom(Builder $query): array
    {
        $table = $query->getModel()->getTable();

        return EcomActivityFilterCounts::aggregateQuery($query)
            ->selectRaw("COALESCE(NULLIF({$table}.utm_medium, ''), 'none') as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderByDesc('total')
            ->pluck('total', 'bucket')
            ->map(fn ($count) => (int) $count)
            ->all();
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
            })->where(function (Builder $inner) {
                self::excludeAwinUrlMatches($inner);
            });

            return;
        }

        if ($source === 'awin') {
            $query->where(function (Builder $inner) {
                $inner->where('utm_source', 'awin');
                self::applyAwinUrlMatches($inner, 'or');
            });

            return;
        }

        if ($source === 'google') {
            $query->where(function (Builder $inner) {
                $inner->where('utm_source', 'google');
                self::applyGoogleUrlMatches($inner, 'or');
            });

            return;
        }

        $query->where('utm_source', $source);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyGoogleUrlMatches(Builder $query, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $query->{$method}(function (Builder $inner) {
            foreach (['gclid=', 'gbraid=', 'wbraid=', 'gad_campaignid=', 'gad_source=', 'utm_source=google'] as $needle) {
                $inner->orWhere('landing_page', 'like', '%'.$needle.'%');
            }

            $inner->orWhereHas('actions', fn (Builder $actions) => $actions
                ->where(function (Builder $urls) {
                    foreach (['gclid=', 'gbraid=', 'wbraid=', 'gad_campaignid=', 'gad_source=', 'utm_source=google'] as $needle) {
                        $urls->orWhere('page_url', 'like', '%'.$needle.'%');
                    }
                }));
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyAwinUrlMatches(Builder $query, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $query->{$method}(function (Builder $inner) {
            $inner->where('landing_page', 'like', '%utm_source=awin%')
                ->orWhere('landing_page', 'like', '%source=aw%')
                ->orWhere('landing_page', 'like', '%awc=%')
                ->orWhereHas('actions', fn (Builder $actions) => $actions
                    ->where('page_url', 'like', '%utm_source=awin%')
                    ->orWhere('page_url', 'like', '%source=aw%')
                    ->orWhere('page_url', 'like', '%awc=%'));
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function excludeAwinUrlMatches(Builder $query): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereNull('landing_page')->orWhere('landing_page', '');
        })->orWhere(function (Builder $inner) {
            $inner->where('landing_page', 'not like', '%utm_source=awin%')
                ->where('landing_page', 'not like', '%source=aw%')
                ->where('landing_page', 'not like', '%awc=%');
        })->whereDoesntHave('actions', fn (Builder $actions) => $actions
            ->where('page_url', 'like', '%utm_source=awin%')
            ->orWhere('page_url', 'like', '%source=aw%')
            ->orWhere('page_url', 'like', '%awc=%'));
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

    private static function humanizeToken(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    private static function isValidToken(string $value): bool
    {
        return (bool) preg_match('/^[\w().\-]+$/', $value);
    }
}
