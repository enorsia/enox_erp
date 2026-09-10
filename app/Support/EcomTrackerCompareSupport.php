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
}
