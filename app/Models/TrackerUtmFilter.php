<?php

namespace App\Models;

use App\Services\EcomActivityFilterCounts;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerMultiSelectFilter;
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
        'srsltid=',
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
     * @return array{sources: array<string, string>, mediums: array<string, string>, selected_source: string, selected_medium: string, selected_sources: list<string>, selected_mediums: list<string>}
     */
    public static function formState(mixed $source = null, mixed $medium = null, array $sourceCounts = [], array $mediumCounts = []): array
    {
        $selectedSources = array_values(array_filter(array_map(
            static fn (?string $value) => self::resolveSource($value),
            TrackerMultiSelectFilter::values($source),
        )));
        $selectedMediums = array_values(array_filter(array_map(
            static fn (?string $value) => self::resolveMedium($value),
            TrackerMultiSelectFilter::values($medium),
        )));

        return [
            'sources' => self::labeledOptions($sourceCounts, 'source'),
            'mediums' => self::labeledOptions($mediumCounts, 'medium'),
            'selected_source' => $selectedSources[0] ?? '',
            'selected_medium' => $selectedMediums[0] ?? '',
            'selected_sources' => $selectedSources,
            'selected_mediums' => $selectedMediums,
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
        $bucketSql = self::sessionSourceBucketSql($sessionTable);

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
    public static function conversionSourceCountsFrom(Builder $query): array
    {
        $sessionTable = $query->getModel()->getTable();
        $bucketSql = "CASE
            WHEN {$sessionTable}.conversion_utm_source IS NOT NULL AND {$sessionTable}.conversion_utm_source != ''
            THEN {$sessionTable}.conversion_utm_source
            ELSE '(direct)'
        END";

        return EcomActivityFilterCounts::aggregateQuery($query)
            ->selectRaw("{$bucketSql} as bucket, COUNT(*) as total")
            ->groupBy('bucket')
            ->orderByDesc('total')
            ->pluck('total', 'bucket')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public static function listTrafficSourceCountsFrom(Builder $query): array
    {
        return self::listTrafficFacetCountsFrom($query)['utm_source'];
    }

    public static function listTrafficMediumCountsFrom(Builder $query): array
    {
        return self::listTrafficFacetCountsFrom($query)['utm_medium'];
    }

    /**
     * @return array{utm_source: array<string, int>, utm_medium: array<string, int>}
     */
    public static function listTrafficFacetCountsFrom(Builder $query): array
    {
        $sessionTable = $query->getModel()->getTable();
        $sourceCounts = [];
        $mediumCounts = [];

        $storedRows = EcomActivityFilterCounts::aggregateQuery($query)
            ->whereNotNull("{$sessionTable}.list_traffic_utm_source")
            ->where("{$sessionTable}.list_traffic_utm_source", '!=', '')
            ->selectRaw("{$sessionTable}.list_traffic_utm_source as source_bucket, {$sessionTable}.list_traffic_utm_medium as medium_bucket, COUNT(*) as total")
            ->groupBy("{$sessionTable}.list_traffic_utm_source", "{$sessionTable}.list_traffic_utm_medium")
            ->get();

        foreach ($storedRows as $row) {
            $sourceKey = (string) $row->source_bucket;
            $mediumKey = (string) ($row->medium_bucket ?? 'none');
            $total = (int) $row->total;
            $sourceCounts[$sourceKey] = ($sourceCounts[$sourceKey] ?? 0) + $total;
            $mediumCounts[$mediumKey] = ($mediumCounts[$mediumKey] ?? 0) + $total;
        }

        $legacySourceSql = self::legacyListTrafficSourceBucketSql($sessionTable);
        $legacyMediumSql = self::sessionMediumBucketSql($sessionTable);
        $legacyRows = EcomActivityFilterCounts::aggregateQuery($query)
            ->where(function (Builder $inner) use ($sessionTable) {
                self::applyListTrafficColumnUnsetConstraint($inner, $sessionTable);
            })
            ->selectRaw("({$legacySourceSql}) as source_bucket, ({$legacyMediumSql}) as medium_bucket, COUNT(*) as total")
            ->groupBy('source_bucket', 'medium_bucket')
            ->get();

        foreach ($legacyRows as $row) {
            $sourceKey = (string) $row->source_bucket;
            $mediumKey = (string) ($row->medium_bucket ?? 'none');
            $total = (int) $row->total;
            $sourceCounts[$sourceKey] = ($sourceCounts[$sourceKey] ?? 0) + $total;
            $mediumCounts[$mediumKey] = ($mediumCounts[$mediumKey] ?? 0) + $total;
        }

        return [
            'utm_source' => collect($sourceCounts)->sortDesc()->map(fn ($count) => (int) $count)->all(),
            'utm_medium' => collect($mediumCounts)->sortDesc()->map(fn ($count) => (int) $count)->all(),
        ];
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @return array<string, int>
     */
    public static function mediumCountsFrom(Builder $query): array
    {
        $sessionTable = $query->getModel()->getTable();
        $bucketSql = self::sessionMediumBucketSql($sessionTable);

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
    public static function applySourceFilter(Builder $query, mixed $source): void
    {
        self::applySourceFilters($query, TrackerMultiSelectFilter::values($source));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applyListTrafficSourceFilter(Builder $query, mixed $source): void
    {
        self::applyListTrafficSourceFilters($query, TrackerMultiSelectFilter::values($source));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applyListTrafficMediumFilter(Builder $query, mixed $medium): void
    {
        self::applyListTrafficMediumFilters($query, TrackerMultiSelectFilter::values($medium));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    public static function applyConversionSourceFilter(Builder $query, mixed $source): void
    {
        self::applyConversionSourceFilters($query, TrackerMultiSelectFilter::values($source));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $sources
     */
    public static function applyConversionSourceFilters(Builder $query, array $sources): void
    {
        $sources = array_values(array_filter(array_map(
            static fn (string $value) => self::resolveSource($value),
            TrackerMultiSelectFilter::values($sources),
        )));

        if ($sources === []) {
            return;
        }

        if (count($sources) === 1) {
            self::applyResolvedConversionSourceFilter($query, $sources[0]);

            return;
        }

        $query->where(function (Builder $inner) use ($sources) {
            foreach ($sources as $index => $source) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $inner->{$method}(function (Builder $branch) use ($source) {
                    self::applyResolvedConversionSourceFilter($branch, $source);
                });
            }
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyResolvedConversionSourceFilter(Builder $query, string $source): void
    {
        if ($source === '(direct)') {
            $query->where(function (Builder $inner) {
                $inner->whereNull('conversion_utm_source')->orWhere('conversion_utm_source', '');
            });

            return;
        }

        $values = self::sourceColumnValues($source);
        $query->whereIn('conversion_utm_source', $values);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $sources
     */
    public static function applySourceFilters(Builder $query, array $sources): void
    {
        self::applyResolvedSourceFilters($query, $sources, self::applyResolvedSourceFilter(...));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $sources
     */
    public static function applyListTrafficSourceFilters(Builder $query, array $sources): void
    {
        self::applyResolvedSourceFilters($query, $sources, self::applyResolvedListTrafficSourceFilter(...));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $mediums
     */
    public static function applyListTrafficMediumFilters(Builder $query, array $mediums): void
    {
        $mediums = array_values(array_filter(array_map(
            static fn (string $value) => self::resolveMedium($value),
            TrackerMultiSelectFilter::values($mediums),
        )));

        if ($mediums === []) {
            return;
        }

        if (count($mediums) === 1) {
            self::applyResolvedListTrafficMediumFilter($query, $mediums[0]);

            return;
        }

        $query->where(function (Builder $inner) use ($mediums) {
            foreach ($mediums as $index => $medium) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $inner->{$method}(function (Builder $branch) use ($medium) {
                    self::applyResolvedListTrafficMediumFilter($branch, $medium);
                });
            }
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $sources
     * @param  callable(Builder<ActivityEcomUser>, string): void  $applyResolved
     */
    private static function applyResolvedSourceFilters(Builder $query, array $sources, callable $applyResolved): void
    {
        $sources = array_values(array_filter(array_map(
            static fn (string $value) => self::resolveSource($value),
            TrackerMultiSelectFilter::values($sources),
        )));

        if ($sources === []) {
            return;
        }

        if (count($sources) === 1) {
            $applyResolved($query, $sources[0]);

            return;
        }

        $query->where(function (Builder $inner) use ($sources, $applyResolved) {
            foreach ($sources as $index => $source) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $inner->{$method}(function (Builder $branch) use ($source, $applyResolved) {
                    $applyResolved($branch, $source);
                });
            }
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyResolvedListTrafficSourceFilter(Builder $query, string $source): void
    {
        $sessionTable = $query->getModel()->getTable();
        $legacySql = self::legacyListTrafficSourceBucketSql($sessionTable);

        if ($source === '(direct)') {
            $query->where(function (Builder $inner) use ($sessionTable, $legacySql) {
                $inner->where("{$sessionTable}.list_traffic_utm_source", '(direct)')
                    ->orWhere(function (Builder $fallback) use ($sessionTable, $legacySql) {
                        $fallback->where(function (Builder $unset) use ($sessionTable) {
                            self::applyListTrafficColumnUnsetConstraint($unset, $sessionTable);
                        })->whereRaw("({$legacySql}) = ?", ['(direct)']);
                    });
            });

            return;
        }

        $values = self::sourceColumnValues($source);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        $query->where(function (Builder $inner) use ($sessionTable, $legacySql, $values, $placeholders) {
            $inner->whereIn("{$sessionTable}.list_traffic_utm_source", $values)
                ->orWhere(function (Builder $fallback) use ($sessionTable, $legacySql, $values, $placeholders) {
                    $fallback->where(function (Builder $unset) use ($sessionTable) {
                        self::applyListTrafficColumnUnsetConstraint($unset, $sessionTable);
                    })->whereRaw("({$legacySql}) IN ({$placeholders})", $values);
                });
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyResolvedListTrafficMediumFilter(Builder $query, string $medium): void
    {
        $sessionTable = $query->getModel()->getTable();
        $legacySql = self::sessionMediumBucketSql($sessionTable);

        if ($medium === 'none') {
            $query->where(function (Builder $inner) use ($sessionTable, $legacySql) {
                $inner->where(function (Builder $stored) use ($sessionTable) {
                    $stored->whereNotNull("{$sessionTable}.list_traffic_utm_source")
                        ->where("{$sessionTable}.list_traffic_utm_source", '!=', '')
                        ->where("{$sessionTable}.list_traffic_utm_medium", 'none');
                })->orWhere(function (Builder $fallback) use ($sessionTable, $legacySql) {
                    $fallback->where(function (Builder $unset) use ($sessionTable) {
                        self::applyListTrafficColumnUnsetConstraint($unset, $sessionTable);
                    })->whereRaw("({$legacySql}) = ?", ['none']);
                });
            });

            return;
        }

        if (in_array($medium, ['paid', 'cpc'], true)) {
            $query->where(function (Builder $inner) use ($sessionTable, $legacySql) {
                $inner->where(function (Builder $stored) use ($sessionTable) {
                    $stored->whereNotNull("{$sessionTable}.list_traffic_utm_source")
                        ->where("{$sessionTable}.list_traffic_utm_source", '!=', '')
                        ->whereIn("{$sessionTable}.list_traffic_utm_medium", ['paid', 'cpc']);
                })->orWhere(function (Builder $fallback) use ($sessionTable, $legacySql) {
                    $fallback->where(function (Builder $unset) use ($sessionTable) {
                        self::applyListTrafficColumnUnsetConstraint($unset, $sessionTable);
                    })->whereRaw("({$legacySql}) IN ('paid', 'cpc')");
                });
            });

            return;
        }

        $query->where(function (Builder $inner) use ($sessionTable, $legacySql, $medium) {
            $inner->where(function (Builder $stored) use ($sessionTable, $medium) {
                $stored->whereNotNull("{$sessionTable}.list_traffic_utm_source")
                    ->where("{$sessionTable}.list_traffic_utm_source", '!=', '')
                    ->where("{$sessionTable}.list_traffic_utm_medium", $medium);
            })->orWhere(function (Builder $fallback) use ($sessionTable, $legacySql, $medium) {
                $fallback->where(function (Builder $unset) use ($sessionTable) {
                    self::applyListTrafficColumnUnsetConstraint($unset, $sessionTable);
                })->whereRaw("({$legacySql}) = ?", [$medium]);
            });
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyResolvedSourceFilter(Builder $query, string $source): void
    {
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
    public static function applyMediumFilter(Builder $query, mixed $medium): void
    {
        self::applyMediumFilters($query, TrackerMultiSelectFilter::values($medium));
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  list<string>  $mediums
     */
    public static function applyMediumFilters(Builder $query, array $mediums): void
    {
        $mediums = array_values(array_filter(array_map(
            static fn (string $value) => self::resolveMedium($value),
            TrackerMultiSelectFilter::values($mediums),
        )));

        if ($mediums === []) {
            return;
        }

        if (count($mediums) === 1) {
            self::applyResolvedMediumFilter($query, $mediums[0]);

            return;
        }

        $query->where(function (Builder $inner) use ($mediums) {
            foreach ($mediums as $index => $medium) {
                $method = $index === 0 ? 'where' : 'orWhere';

                $inner->{$method}(function (Builder $branch) use ($medium) {
                    self::applyResolvedMediumFilter($branch, $medium);
                });
            }
        });
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyResolvedMediumFilter(Builder $query, string $medium): void
    {
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
     * @param  Builder<ActivityEcomUser>  $query
     */
    private static function applyListTrafficColumnUnsetConstraint(Builder $query, string $sessionTable): void
    {
        $query->whereNull("{$sessionTable}.list_traffic_utm_source")
            ->orWhere("{$sessionTable}.list_traffic_utm_source", '');
    }

    /**
     * @return array<string, string>
     */
    private static function legacyListTrafficSourceBucketSql(string $sessionTable): string
    {
        $sessionBucket = self::sessionSourceBucketSql($sessionTable);

        return "CASE
            WHEN {$sessionTable}.has_payment_success = 1
                AND {$sessionTable}.conversion_utm_source IS NOT NULL
                AND {$sessionTable}.conversion_utm_source != ''
            THEN {$sessionTable}.conversion_utm_source
            ELSE ({$sessionBucket})
        END";
    }

    private static function effectiveListTrafficSourceSql(string $sessionTable): string
    {
        $legacy = self::legacyListTrafficSourceBucketSql($sessionTable);
        $stored = self::storedListTrafficSourceCaseSql($sessionTable);

        return "COALESCE(({$stored}), ({$legacy}))";
    }

    /**
     * Dashboard traffic table: matches {@see SessionTrafficAttribution::dashboardTrafficDisplayBucket()} in SQL.
     */
    public static function dashboardTrafficSourceBucketSql(string $sessionTable): string
    {
        $stored = self::storedListTrafficSourceCaseSql($sessionTable);
        $sessionBucket = self::sessionSourceBucketSql($sessionTable);

        return "COALESCE(
            ({$stored}),
            CASE
                WHEN {$sessionTable}.has_payment_success = 1
                    AND {$sessionTable}.conversion_utm_source IS NOT NULL
                    AND {$sessionTable}.conversion_utm_source != ''
                THEN {$sessionTable}.conversion_utm_source
            END,
            CASE
                WHEN {$sessionTable}.utm_source IS NOT NULL AND {$sessionTable}.utm_source != ''
                THEN {$sessionTable}.utm_source
            END,
            ({$sessionBucket}),
            '(direct)'
        )";
    }

    /**
     * Dashboard traffic table medium bucket (SQL).
     */
    public static function dashboardTrafficMediumBucketSql(string $sessionTable): string
    {
        $legacyMedium = self::sessionMediumBucketSql($sessionTable);

        return "COALESCE(
            CASE
                WHEN {$sessionTable}.list_traffic_utm_source IS NOT NULL
                    AND {$sessionTable}.list_traffic_utm_source != ''
                THEN {$sessionTable}.list_traffic_utm_medium
            END,
            CASE
                WHEN {$sessionTable}.has_payment_success = 1
                    AND {$sessionTable}.conversion_utm_source IS NOT NULL
                    AND {$sessionTable}.conversion_utm_source != ''
                THEN {$sessionTable}.conversion_utm_medium
            END,
            CASE
                WHEN {$sessionTable}.utm_source IS NOT NULL AND {$sessionTable}.utm_source != ''
                THEN {$sessionTable}.utm_medium
            END,
            ({$legacyMedium}),
            'none'
        )";
    }

    private static function storedListTrafficSourceCaseSql(string $sessionTable): string
    {
        return "CASE
            WHEN {$sessionTable}.list_traffic_utm_source IS NOT NULL
                AND {$sessionTable}.list_traffic_utm_source != ''
            THEN {$sessionTable}.list_traffic_utm_source
        END";
    }

    private static function effectiveListTrafficMediumSql(string $sessionTable): string
    {
        $legacy = self::sessionMediumBucketSql($sessionTable);
        $stored = "CASE
            WHEN {$sessionTable}.list_traffic_utm_source IS NOT NULL
                AND {$sessionTable}.list_traffic_utm_source != ''
            THEN {$sessionTable}.list_traffic_utm_medium
        END";

        return "COALESCE(({$stored}), ({$legacy}))";
    }

    private static function sessionMediumBucketSql(string $sessionTable): string
    {
        $paidMatch = self::sqlColumnMatchesAny($sessionTable.'.landing_page', self::PAID_MEDIUM_URL_NEEDLES);
        $googleOrganicMatch = self::sqlColumnMatchesAny($sessionTable.'.landing_page', ['srsltid=']);

        return "CASE
            WHEN {$sessionTable}.utm_medium IS NOT NULL AND {$sessionTable}.utm_medium != '' THEN {$sessionTable}.utm_medium
            WHEN {$paidMatch} THEN 'paid'
            WHEN {$googleOrganicMatch} THEN 'organic'
            ELSE 'none'
        END";
    }

    private static function sessionSourceBucketSql(string $sessionTable): string
    {
        $cases = [];

        foreach (config('tracker.utm_source_aliases', []) as $alias => $canonical) {
            $cases[] = "WHEN {$sessionTable}.utm_source = '".self::escapeLike($alias)."' THEN '".self::escapeLike($canonical)."'";
        }

        $cases[] = "WHEN {$sessionTable}.utm_source IS NOT NULL AND {$sessionTable}.utm_source != '' THEN {$sessionTable}.utm_source";

        foreach (self::inferredSourceUrlMatches($sessionTable) as $source => $matchSql) {
            $cases[] = "WHEN {$matchSql} THEN '".self::escapeLike($source)."'";
        }

        $cases[] = "ELSE '(direct)'";

        return 'CASE '.implode(' ', $cases).' END';
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

    /**
     * Per-session source bucket used by activity UTM source filters and counts (utm_* + landing_page only).
     */
    public static function sessionSourceBucket(object $session): string
    {
        $rawSource = $session->utm_source ?? null;

        if (filled($rawSource)) {
            return SessionTrafficAttribution::normalizeSource((string) $rawSource) ?? (string) $rawSource;
        }

        $landing = (string) ($session->landing_page ?? '');

        if ($landing === '') {
            return '(direct)';
        }

        if (self::urlContainsAnyNeedle($landing, self::GOOGLE_SOURCE_URL_NEEDLES)) {
            return 'google';
        }

        if (self::urlContainsAnyNeedle($landing, self::AWIN_URL_NEEDLES)) {
            return 'awin';
        }

        foreach (self::SOURCE_URL_NEEDLES as $source => $needles) {
            if (self::urlContainsAnyNeedle($landing, $needles)) {
                return $source;
            }
        }

        return '(direct)';
    }

    /**
     * Per-session medium bucket used by activity UTM medium filters and counts.
     */
    public static function sessionMediumBucket(object $session): string
    {
        $rawMedium = $session->utm_medium ?? null;

        if (filled($rawMedium)) {
            return trim((string) $rawMedium);
        }

        $landing = (string) ($session->landing_page ?? '');

        if ($landing !== '' && self::urlContainsAnyNeedle($landing, self::PAID_MEDIUM_URL_NEEDLES)) {
            return 'paid';
        }

        return 'none';
    }

    /**
     * @return array{source: string, medium: string}
     */
    public static function sessionFilterTrafficBucket(object $session): array
    {
        return [
            'source' => self::sessionSourceBucket($session),
            'medium' => self::sessionMediumBucket($session),
        ];
    }

    /**
     * @param  list<string>  $needles
     */
    private static function urlContainsAnyNeedle(string $url, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($url, $needle)) {
                return true;
            }
        }

        return false;
    }
}
