<?php

namespace App\Models;

use App\Services\EcomActivityFilterCounts;
use App\Support\SessionTrafficAttribution;
use Illuminate\Database\Eloquent\Builder;

/**
 * Static UTM source / medium options for tracker analytics filters.
 * Reads session utm_* and landing_page only (no user_actions scans).
 */
final class TrackerUtmFilter
{
    /** @var list<string> */
    private const GOOGLE_SOURCE_URL_NEEDLES = [
        'gclid=',
        'gbraid=',
        'wbraid=',
        'gad_campaignid=',
        'gad_source=',
        'utm_source=google',
    ];

    /** @var list<string> */
    private const AWIN_URL_NEEDLES = [
        'utm_source=awin',
        'source=aw',
        'awc=',
    ];

    /** @var array<string, list<string>> */
    private const SOURCE_URL_NEEDLES = [
        'facebook' => [
            'fbclid=',
            'utm_source=facebook',
            'utm_source=fb',
            'utm_source=meta',
        ],
        'instagram' => [
            'utm_source=instagram',
            'utm_source=ig',
            'utm_source=insta',
        ],
        'tiktok' => [
            'ttclid=',
            'utm_source=tiktok',
            'utm_source=tt',
        ],
        'bing' => [
            'msclkid=',
            'utm_source=bing',
            'utm_source=ms',
        ],
        'youtube' => [
            'utm_source=youtube',
            'utm_source=yt',
        ],
        'pinterest' => [
            'epik=',
            'utm_source=pinterest',
            'utm_source=pin',
        ],
        'linkedin' => [
            'li_fat_id=',
            'utm_source=linkedin',
            'utm_source=li',
        ],
        'twitter' => [
            'twclid=',
            'utm_source=twitter',
            'utm_source=x',
        ],
        'snapchat' => [
            'sc_cid=',
            'utm_source=snapchat',
            'utm_source=snap',
        ],
    ];

    /** @var list<string> */
    private const PAID_MEDIUM_URL_NEEDLES = [
        'gclid=',
        'gbraid=',
        'wbraid=',
        'gad_campaignid=',
        'gad_source=',
        'fbclid=',
        'ttclid=',
        'twclid=',
        'li_fat_id=',
        'epik=',
        'sc_cid=',
        'msclkid=',
    ];

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

        $normalized = SessionTrafficAttribution::normalizeSource($value);

        if ($normalized !== null && array_key_exists($normalized, self::sources())) {
            return $normalized;
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
        $sessionTable = $query->getModel()->getTable();
        $cases = [];

        foreach (config('tracker.utm_source_aliases', []) as $alias => $canonical) {
            $cases[] = "WHEN {$sessionTable}.utm_source = '".self::escapeLike($alias)."' THEN '".self::escapeLike($canonical)."'";
        }

        $cases[] = "WHEN {$sessionTable}.utm_source IS NOT NULL AND {$sessionTable}.utm_source != '' THEN {$sessionTable}.utm_source";

        foreach (self::inferredSourceUrlMatches($sessionTable) as $source => $matchSql) {
            $cases[] = "WHEN {$matchSql} THEN '".self::escapeLike($source)."'";
        }

        $cases[] = "ELSE '(direct)'";
        $bucketSql = 'CASE '.implode(' ', $cases).' END';

        return EcomActivityFilterCounts::aggregateQuery($query)
            ->selectRaw("{$bucketSql} as bucket, COUNT(*) as total")
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
        $sessionTable = $query->getModel()->getTable();
        $paidMatch = self::sqlColumnMatchesAny($sessionTable.'.landing_page', self::PAID_MEDIUM_URL_NEEDLES);

        $bucketSql = "CASE
            WHEN {$sessionTable}.utm_medium IS NOT NULL AND {$sessionTable}.utm_medium != '' THEN {$sessionTable}.utm_medium
            WHEN {$paidMatch} THEN 'paid'
            ELSE 'none'
        END";

        return EcomActivityFilterCounts::aggregateQuery($query)
            ->selectRaw("{$bucketSql} as bucket, COUNT(*) as total")
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
                self::excludeInferredSourceUrlMatches($inner);
            });

            return;
        }

        if ($source === 'awin') {
            $query->where(function (Builder $inner) {
                $inner->whereIn('utm_source', self::sourceColumnValues('awin'));
                self::applyAwinUrlMatches($inner, 'or');
            });

            return;
        }

        if ($source === 'google') {
            $query->where(function (Builder $inner) {
                $inner->whereIn('utm_source', self::sourceColumnValues('google'));
                self::applyGoogleUrlMatches($inner, 'or');
            });

            return;
        }

        if (isset(self::SOURCE_URL_NEEDLES[$source])) {
            $query->where(function (Builder $inner) use ($source) {
                $inner->whereIn('utm_source', self::sourceColumnValues($source));
                self::applyUrlNeedleMatches($inner, self::SOURCE_URL_NEEDLES[$source], 'or');
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
            })->where(function (Builder $inner) {
                self::excludePaidMediumUrlMatches($inner);
            });

            return;
        }

        if (in_array($medium, ['paid', 'cpc'], true)) {
            $query->where(function (Builder $inner) use ($medium) {
                $inner->where('utm_medium', $medium);

                if ($medium === 'paid') {
                    $inner->orWhere('utm_medium', 'cpc');
                } else {
                    $inner->orWhere('utm_medium', 'paid');
                }

                self::applyPaidMediumUrlMatches($inner, 'or');
            });

            return;
        }

        $query->where('utm_medium', $medium);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyGoogleUrlMatches(Builder $query, string $boolean = 'and'): void
    {
        self::applyUrlNeedleMatches($query, self::GOOGLE_SOURCE_URL_NEEDLES, $boolean);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyPaidMediumUrlMatches(Builder $query, string $boolean = 'and'): void
    {
        self::applyUrlNeedleMatches($query, self::PAID_MEDIUM_URL_NEEDLES, $boolean);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function excludePaidMediumUrlMatches(Builder $query): void
    {
        self::excludeUrlNeedleMatches($query, self::PAID_MEDIUM_URL_NEEDLES);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function excludeInferredSourceUrlMatches(Builder $query): void
    {
        self::excludeAwinUrlMatches($query);
        self::excludeGoogleUrlMatches($query);

        foreach (self::SOURCE_URL_NEEDLES as $needles) {
            self::excludeUrlNeedleMatches($query, $needles);
        }
    }

    /**
     * @return array<string, string>
     */
    private static function inferredSourceUrlMatches(string $sessionTable): array
    {
        $matches = [
            'google' => self::sqlColumnMatchesAny($sessionTable.'.landing_page', self::GOOGLE_SOURCE_URL_NEEDLES),
            'awin' => self::sqlColumnMatchesAny($sessionTable.'.landing_page', self::AWIN_URL_NEEDLES),
        ];

        foreach (self::SOURCE_URL_NEEDLES as $source => $needles) {
            $matches[$source] = self::sqlColumnMatchesAny($sessionTable.'.landing_page', $needles);
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    private static function sourceColumnValues(string $canonical): array
    {
        $values = [$canonical];

        foreach (config('tracker.utm_source_aliases', []) as $alias => $target) {
            if ($target === $canonical) {
                $values[] = $alias;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $needles
     */
    private static function applyUrlNeedleMatches(Builder $query, array $needles, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $query->{$method}(function (Builder $inner) use ($needles) {
            self::applyColumnNeedleMatches($inner, 'landing_page', $needles);
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $needles
     */
    private static function applyColumnNeedleMatches(Builder $query, string $column, array $needles): void
    {
        $query->where(function (Builder $inner) use ($column, $needles) {
            foreach ($needles as $needle) {
                $inner->orWhere($column, 'like', '%'.$needle.'%');
            }
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyAwinUrlMatches(Builder $query, string $boolean = 'and'): void
    {
        self::applyUrlNeedleMatches($query, self::AWIN_URL_NEEDLES, $boolean);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function excludeAwinUrlMatches(Builder $query): void
    {
        self::excludeUrlNeedleMatches($query, self::AWIN_URL_NEEDLES);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function excludeGoogleUrlMatches(Builder $query): void
    {
        self::excludeUrlNeedleMatches($query, self::GOOGLE_SOURCE_URL_NEEDLES);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $needles
     */
    private static function excludeUrlNeedleMatches(Builder $query, array $needles): void
    {
        $query->where(function (Builder $inner) {
            $inner->whereNull('landing_page')->orWhere('landing_page', '');
        })->orWhere(function (Builder $inner) use ($needles) {
            foreach ($needles as $needle) {
                $inner->where('landing_page', 'not like', '%'.$needle.'%');
            }
        });
    }

    /**
     * @param  list<string>  $needles
     */
    private static function sqlColumnMatchesAny(string $column, array $needles): string
    {
        $parts = array_map(
            fn (string $needle) => '('.$column." LIKE '%".self::escapeLike($needle)."%')",
            $needles,
        );

        return '('.implode(' OR ', $parts).')';
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(["'", '%', '_'], ["''", '\%', '\_'], $value);
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
