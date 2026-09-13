<?php

namespace App\Support;

final class EcomTrackerCompareSupport
{
    /**
     * @return list<array{key: string, label: string, tip: ?string, format: string, higher_is_good: bool, period_a_formatted: string, period_b_formatted: string, period_a_value: float, period_b_value: float, delta_pct: ?float, delta_direction: ?string, delta_sentiment: string}>
     */
    public static function buildExecutiveSummary(array $periodA, array $periodB): array
    {
        $metrics = self::executiveMetricDefinitions();
        $rows = [];

        foreach ($metrics as $definition) {
            $valueA = self::resolveMetricValue($periodA, $definition);
            $valueB = self::resolveMetricValue($periodB, $definition);
            $delta = self::computeDelta((float) $valueA, (float) $valueB);
            $sentiment = self::deltaSentiment(
                $delta['delta_direction'],
                $definition['higher_is_good'],
            );

            $rows[] = array_merge($definition, [
                'period_a_formatted' => self::formatMetricValue($valueA, $definition['format'], $periodA, $definition),
                'period_b_formatted' => self::formatMetricValue($valueB, $definition['format'], $periodB, $definition),
                'period_a_value' => (float) $valueA,
                'period_b_value' => (float) $valueB,
                'delta_pct' => $delta['delta_pct'],
                'delta_direction' => $delta['delta_direction'],
                'delta_sentiment' => $sentiment,
            ]);
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{label: string, tone: string}
     */
    public static function buildPerformanceVerdict(array $rows): array
    {
        $good = 0;
        $bad = 0;

        foreach ($rows as $row) {
            match ($row['delta_sentiment'] ?? 'neutral') {
                'good' => $good++,
                'bad' => $bad++,
                default => null,
            };
        }

        if ($good > $bad && $bad === 0) {
            return ['label' => 'Improved', 'tone' => 'good'];
        }

        if ($bad > $good && $good === 0) {
            return ['label' => 'Declined', 'tone' => 'bad'];
        }

        if ($good > $bad) {
            return ['label' => 'Improved', 'tone' => 'good'];
        }

        if ($bad > $good) {
            return ['label' => 'Declined', 'tone' => 'bad'];
        }

        return ['label' => 'Mixed', 'tone' => 'neutral'];
    }

    /**
     * @return list<array{key: string, label: string, tip: ?string, format: string, higher_is_good: bool, source: string, source_key: ?string}>
     */
    private static function executiveMetricDefinitions(): array
    {
        return [
            [
                'key' => 'sale_amount',
                'label' => 'Sale amount',
                'tip' => 'Total sale amount from completed orders in the period.',
                'format' => 'currency',
                'higher_is_good' => true,
                'source' => 'sale_conversion',
                'source_key' => 'revenue',
            ],
            [
                'key' => 'items_sold',
                'label' => 'Items sold',
                'tip' => 'Total product units sold from completed orders in the period.',
                'format' => 'number',
                'higher_is_good' => true,
                'source' => 'sale_conversion',
                'source_key' => 'item_qty',
            ],
            [
                'key' => 'sessions',
                'label' => 'Sessions',
                'tip' => 'Sessions in the selected period.',
                'format' => 'number',
                'higher_is_good' => true,
                'source' => 'kpi',
                'source_key' => 'Sessions',
            ],
            [
                'key' => 'unique_visitors',
                'label' => 'Unique visitors',
                'tip' => 'Distinct visitor IDs among sessions in the selected period.',
                'format' => 'number',
                'higher_is_good' => true,
                'source' => 'kpi',
                'source_key' => 'Unique visitors',
            ],
            [
                'key' => 'payments',
                'label' => 'Payments',
                'tip' => 'Share of all sessions that completed a payment.',
                'format' => 'funnel_rate',
                'higher_is_good' => true,
                'source' => 'funnel_dropoff',
                'source_key' => 'payments',
            ],
            [
                'key' => 'cart_drop',
                'label' => 'Cart drop',
                'tip' => 'Sessions that added to cart but did not begin checkout.',
                'format' => 'funnel_rate',
                'higher_is_good' => false,
                'source' => 'funnel_dropoff',
                'source_key' => 'cart_drop',
            ],
            [
                'key' => 'checkout_drop',
                'label' => 'Checkout drop',
                'tip' => 'Sessions that began checkout but did not proceed.',
                'format' => 'funnel_rate',
                'higher_is_good' => false,
                'source' => 'funnel_dropoff',
                'source_key' => 'checkout_drop',
            ],
            [
                'key' => 'proceed_drop',
                'label' => 'Proceed drop',
                'tip' => 'Sessions that proceeded to checkout but did not pay.',
                'format' => 'funnel_rate',
                'higher_is_good' => false,
                'source' => 'funnel_dropoff',
                'source_key' => 'proceed_drop',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $definition
     */
    private static function resolveMetricValue(array $dashboard, array $definition): float|int
    {
        if ($definition['source'] === 'kpi') {
            $kpi = collect($dashboard['kpis'] ?? [])->firstWhere('label', $definition['source_key']);

            return (float) ($kpi['value'] ?? 0);
        }

        $group = $dashboard[$definition['source']] ?? [];
        $metric = $group[$definition['source_key']] ?? null;

        if (! is_array($metric)) {
            return 0.0;
        }

        if (($definition['format'] ?? '') === 'funnel_rate') {
            return (float) ($metric['value'] ?? 0);
        }

        return (float) ($metric['value'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $definition
     */
    private static function formatMetricValue(float|int $value, string $format, array $dashboard, array $definition): string
    {
        if ($format === 'funnel_rate') {
            $group = $dashboard[$definition['source']] ?? [];
            $metric = $group[$definition['source_key']] ?? null;

            if (is_array($metric) && filled($metric['formatted'] ?? null)) {
                return (string) $metric['formatted'];
            }

            return number_format((float) $value, 1).'%';
        }

        if ($format === 'currency') {
            return '£'.number_format((float) $value, 2);
        }

        return number_format((float) $value);
    }

    /**
     * @return array{delta_pct: ?float, delta_direction: ?string}
     */
    private static function computeDelta(float $current, float $compare): array
    {
        if ($compare == 0.0) {
            if ($current > 0) {
                return ['delta_pct' => null, 'delta_direction' => 'up'];
            }

            return ['delta_pct' => 0.0, 'delta_direction' => 'flat'];
        }

        if ($current == 0.0) {
            return ['delta_pct' => -100.0, 'delta_direction' => 'down'];
        }

        $deltaPct = (($current - $compare) / $compare) * 100;

        return [
            'delta_pct' => round($deltaPct, 1),
            'delta_direction' => $deltaPct > 0 ? 'up' : ($deltaPct < 0 ? 'down' : 'flat'),
        ];
    }

    private static function deltaSentiment(?string $direction, bool $higherIsGood): string
    {
        if ($direction === null || $direction === 'flat') {
            return 'neutral';
        }

        $improved = $higherIsGood ? $direction === 'up' : $direction === 'down';

        return $improved ? 'good' : 'bad';
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array<string, mixed>  $otherDashboard
     * @return array<string, mixed>
     */
    public static function enrichDashboardWithCrossComparison(
        array $dashboard,
        array $otherDashboard,
        string $otherPeriodLabel,
    ): array {
        $dashboard['kpis'] = collect($dashboard['kpis'] ?? [])
            ->map(function (array $kpi) use ($otherDashboard, $otherPeriodLabel) {
                $other = collect($otherDashboard['kpis'] ?? [])->firstWhere('label', $kpi['label'] ?? '');
                $current = (float) ($kpi['value'] ?? 0);
                $previous = (float) ($other['value'] ?? 0);

                return array_merge($kpi, [
                    'comparison' => self::buildMetricComparison(
                        $current,
                        $previous,
                        (string) ($other['formatted'] ?? self::formatScalarValue($previous, 'number')),
                        $otherPeriodLabel,
                    ),
                ]);
            })
            ->values()
            ->all();

        foreach (['sale_conversion', 'funnel_dropoff'] as $groupKey) {
            $dashboard[$groupKey] = self::enrichMetricGroupWithCrossComparison(
                $dashboard[$groupKey] ?? [],
                $otherDashboard[$groupKey] ?? [],
                $otherPeriodLabel,
            );
        }

        return $dashboard;
    }

    /**
     * @param  array<string, array<string, mixed>>  $group
     * @param  array<string, array<string, mixed>>  $otherGroup
     * @return array<string, array<string, mixed>>
     */
    private static function enrichMetricGroupWithCrossComparison(
        array $group,
        array $otherGroup,
        string $otherPeriodLabel,
    ): array {
        foreach ($group as $key => $metric) {
            if (! is_array($metric)) {
                continue;
            }

            $other = $otherGroup[$key] ?? [];
            $current = (float) ($metric['value'] ?? 0);
            $previous = (float) ($other['value'] ?? 0);

            $group[$key] = array_merge($metric, [
                'comparison' => self::buildMetricComparison(
                    $current,
                    $previous,
                    (string) ($other['formatted'] ?? number_format($previous)),
                    $otherPeriodLabel,
                ),
            ]);
        }

        return $group;
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildMetricComparison(
        float $current,
        float $previous,
        string $previousFormatted,
        string $comparisonLabel,
    ): array {
        $delta = self::computeDelta($current, $previous);

        return array_merge($delta, [
            'previous' => $previous,
            'previous_formatted' => $previousFormatted,
            'comparison_label' => $comparisonLabel,
            'delta_label' => $delta['delta_pct'] === null && $delta['delta_direction'] === 'up' ? 'new' : null,
        ]);
    }

    private static function formatScalarValue(float $value, string $format): string
    {
        if ($format === 'currency') {
            return '£'.number_format($value, 2);
        }

        if ($format === 'percent') {
            return number_format($value, $value >= 10 ? 1 : 2).'%';
        }

        return number_format($value);
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function buildTableCompareDeltas(array $left, array $right): array
    {
        return [
            'devices' => self::rowDeltaMap(
                $left['devices']['by_device'] ?? [],
                $right['devices']['by_device'] ?? [],
                fn (array $row): string => (string) ($row['label'] ?? ''),
                'sessions',
                true,
            ),
            'browsers' => self::rowDeltaMap(
                $left['devices']['by_browser'] ?? [],
                $right['devices']['by_browser'] ?? [],
                fn (array $row): string => (string) ($row['label'] ?? ''),
                'sessions',
                true,
            ),
            'traffic' => self::rowDeltaMap(
                $left['traffic_sources'] ?? [],
                $right['traffic_sources'] ?? [],
                fn (array $row): string => (string) ($row['source'] ?? '').'|'.(string) ($row['medium'] ?? ''),
                'revenue',
                true,
            ),
            'products' => self::rowDeltaMap(
                $left['products'] ?? [],
                $right['products'] ?? [],
                fn (array $row): string => (string) ($row['name'] ?? ''),
                'revenue',
                true,
            ),
            'categories' => self::rowDeltaMap(
                self::flattenCategoryRows($left['category_departments'] ?? []),
                self::flattenCategoryRows($right['category_departments'] ?? []),
                fn (array $row): string => (string) ($row['key'] ?? ''),
                'sale_amount',
                true,
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $leftRows
     * @param  list<array<string, mixed>>  $rightRows
     * @return array<string, array{delta_pct: ?float, delta_direction: ?string, delta_sentiment: string, highlight?: string}>
     */
    public static function rowDeltaMap(
        array $leftRows,
        array $rightRows,
        callable $keyFn,
        string $metricKey,
        bool $higherIsGood = true,
    ): array {
        $rightByKey = [];

        foreach ($rightRows as $row) {
            $rightByKey[$keyFn($row)] = $row;
        }

        $map = [];
        $ranked = [];

        foreach ($leftRows as $row) {
            $key = $keyFn($row);
            $leftValue = (float) ($row[$metricKey] ?? 0);
            $rightValue = (float) ($rightByKey[$key][$metricKey] ?? 0);
            $delta = self::computeDelta($leftValue, $rightValue);

            $map[$key] = [
                'delta_pct' => $delta['delta_pct'],
                'delta_direction' => $delta['delta_direction'],
                'delta_sentiment' => self::deltaSentiment($delta['delta_direction'], $higherIsGood),
            ];

            if ($delta['delta_pct'] !== null) {
                $ranked[] = ['key' => $key, 'pct' => $delta['delta_pct']];
            }
        }

        $topUp = collect($ranked)->sortByDesc('pct')->take(3)->pluck('key')->all();
        $topDown = collect($ranked)->sortBy('pct')->take(3)->pluck('key')->all();

        foreach ($map as $key => &$entry) {
            if (in_array($key, $topUp, true) && ($entry['delta_pct'] ?? 0) > 0) {
                $entry['highlight'] = 'up';
            } elseif (in_array($key, $topDown, true) && ($entry['delta_pct'] ?? 0) < 0) {
                $entry['highlight'] = 'down';
            }
        }
        unset($entry);

        return $map;
    }

    /**
     * @param  list<array<string, mixed>>  $departments
     * @return list<array<string, mixed>>
     */
    private static function flattenCategoryRows(array $departments): array
    {
        $rows = [];

        foreach ($departments as $department) {
            $departmentName = (string) ($department['name'] ?? '');
            $rows[] = [
                'key' => 'dept:'.$departmentName,
                'sale_amount' => (float) ($department['sale_amount'] ?? 0),
            ];

            foreach ($department['categories'] ?? [] as $category) {
                $rows[] = [
                    'key' => 'cat:'.$departmentName.'/'.($category['category_name'] ?? $category['name'] ?? ''),
                    'sale_amount' => (float) ($category['sale_amount'] ?? 0),
                ];
            }
        }

        return $rows;
    }
}
