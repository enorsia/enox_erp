<?php

namespace App\Support;

use App\Services\EcomTrackerDashboard\EcomTrackerDashboardService;
use Carbon\Carbon;

/**
 * KPI group payloads for the compare page when column filters use session-level totals
 * (mirrors dashboard KPI shape without touching {@see EcomTrackerDashboardService}).
 */
final class EcomTrackerCompareKpiGroups
{
    /**
     * @param  array<string, float|int>  $current
     * @param  array<string, float|int>  $previous
     * @return list<array{title: string, modifier: string, cols: int, metrics: list<array<string, mixed>>}>
     */
    public static function build(array $current, array $previous, string $period, Carbon $prevFrom, Carbon $prevTo): array
    {
        $comparisonLabel = self::comparisonLabel($period, $prevFrom, $prevTo);

        $metric = fn (string $label, string $key, callable $format, string $tip, array $activity = []) => [
            'label' => $label,
            'formatted' => $format($current[$key]),
            'tip' => $tip,
            'activity' => $activity,
            'comparison' => self::comparison($current[$key], $previous[$key], $format($previous[$key]), $comparisonLabel),
        ];

        $count = fn (float|int $value) => number_format($value);
        $duration = fn (float|int $value) => format_duration((int) $value);

        $currentFunnel = self::funnelKpisFromTotals($current);
        $previousFunnel = self::funnelKpisFromTotals($previous);

        $payments = self::funnelDropMetric(
            'Payments',
            $currentFunnel['payment_rate'],
            $currentFunnel['payment_count'],
            $previousFunnel['payment_rate'],
            $previousFunnel['payment_count'],
            'Share of all sessions that completed a payment (one count per session with payment_success).',
            $comparisonLabel,
            ['funnel' => 6],
        );
        $payments['value_class'] = 'etd-kpi-value--success';

        return [
            ['title' => 'Audience & engagement', 'modifier' => 'etd-kpi-group--audience', 'cols' => 4, 'metrics' => [
                $metric('Unique visitors', 'unique_visitors', $count, 'Distinct visitor IDs among sessions in the selected period (same session rules as User activity).'),
                $metric('Sessions', 'sessions', $count, 'Sessions in the selected period using the same date rules as User activity.'),
                $metric('Total stay time', 'stay_seconds', $duration, 'Sum of session Duration values for sessions in the period (same as User activity Duration column).'),
                $metric('Avg stay time', 'avg_stay_seconds', $duration, 'Average Duration per session in the period (total stay time divided by session count).'),
            ]],
            ['title' => 'Sale & conversion', 'modifier' => 'etd-kpi-group--sale', 'cols' => 2, 'metrics' => [
                $metric('Items sold', 'items_sold', $count, 'Total product units sold from completed orders in the period (sums line-item quantities from payment_success events in the date range).', ['has_order' => 1]),
                $metric('Sale amount', 'sale_amount', fn (float|int $value) => '£'.number_format((float) $value, 2), 'Total sale amount from completed orders in the period (sum of payment_success amount_paid in the date range).', ['has_order' => 1]),
            ]],
            ['title' => 'Funnel drop-off', 'modifier' => 'etd-kpi-group--funnel', 'cols' => 4, 'metrics' => [
                self::funnelDropMetric(
                    'Cart drop',
                    $currentFunnel['cart_drop_rate'],
                    $currentFunnel['cart_drop_count'],
                    $previousFunnel['cart_drop_rate'],
                    $previousFunnel['cart_drop_count'],
                    'Sessions that added to cart but did not begin checkout (matches Cart abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 3],
                ),
                self::funnelDropMetric(
                    'Checkout drop',
                    $currentFunnel['checkout_drop_rate'],
                    $currentFunnel['checkout_drop_count'],
                    $previousFunnel['checkout_drop_rate'],
                    $previousFunnel['checkout_drop_count'],
                    'Sessions that began checkout but did not proceed (matches Begin checkout abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 4],
                ),
                self::funnelDropMetric(
                    'Proceed drop',
                    $currentFunnel['proceed_drop_rate'],
                    $currentFunnel['proceed_drop_count'],
                    $previousFunnel['proceed_drop_rate'],
                    $previousFunnel['proceed_drop_count'],
                    'Sessions that proceeded to checkout but did not pay (matches Proceed checkout abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 5],
                ),
                $payments,
            ]],
        ];
    }

    /**
     * @param  array<string, float|int>  $totals
     * @return array<string, float|int>
     */
    private static function funnelKpisFromTotals(array $totals): array
    {
        $sessions = (int) ($totals['sessions'] ?? 0);
        $cartStage = (int) ($totals['cart_sessions'] ?? 0);
        $checkoutStage = (int) ($totals['checkout_sessions'] ?? 0);
        $proceedStage = (int) ($totals['proceed_sessions'] ?? 0);
        $payments = (int) ($totals['payments'] ?? 0);
        $cartAbandoned = (int) ($totals['cart_drops'] ?? 0);
        $checkoutAbandoned = (int) ($totals['checkout_drops'] ?? 0);
        $proceedAbandoned = (int) ($totals['proceed_drops'] ?? 0);

        return [
            'cart_drop_rate' => $cartStage > 0 ? round($cartAbandoned / $cartStage * 100, 1) : 0.0,
            'cart_drop_count' => $cartAbandoned,
            'checkout_drop_rate' => $checkoutStage > 0 ? round($checkoutAbandoned / $checkoutStage * 100, 1) : 0.0,
            'checkout_drop_count' => $checkoutAbandoned,
            'proceed_drop_rate' => $proceedStage > 0 ? round($proceedAbandoned / $proceedStage * 100, 1) : 0.0,
            'proceed_drop_count' => $proceedAbandoned,
            'payment_rate' => $sessions > 0 ? round($payments / $sessions * 100, 1) : 0.0,
            'payment_count' => $payments,
        ];
    }

    private static function formatFunnelDropValue(float $rate, int $count): string
    {
        return number_format($rate, 1).'% / '.number_format($count);
    }

    /**
     * @return array<string, mixed>
     */
    private static function funnelDropMetric(
        string $label,
        float $currentRate,
        int $currentCount,
        float $previousRate,
        int $previousCount,
        string $tip,
        string $comparisonLabel,
        array $activity = [],
    ): array {
        return [
            'label' => $label,
            'formatted' => self::formatFunnelDropValue($currentRate, $currentCount),
            'tip' => $tip,
            'activity' => $activity,
            'comparison' => self::comparison(
                $currentRate,
                $previousRate,
                self::formatFunnelDropValue($previousRate, $previousCount),
                $comparisonLabel,
            ),
        ];
    }

    /**
     * @return array{delta_pct: ?float, delta_direction: string, delta_label: null, previous_formatted: string, comparison_label: string}
     */
    private static function comparison(float|int $current, float|int $previous, string $previousFormatted, string $comparisonLabel): array
    {
        [$deltaPct, $direction] = match (true) {
            $previous == 0 => $current > 0 ? [null, 'up'] : [0.0, 'flat'],
            $current == 0 => [-100.0, 'down'],
            default => [round(($current - $previous) / $previous * 100, 1), $current > $previous ? 'up' : ($current < $previous ? 'down' : 'flat')],
        };

        return [
            'delta_pct' => $deltaPct,
            'delta_direction' => $direction,
            'delta_label' => null,
            'previous_formatted' => $previousFormatted,
            'comparison_label' => $comparisonLabel,
        ];
    }

    private static function comparisonLabel(string $period, Carbon $prevFrom, Carbon $prevTo): string
    {
        return match (true) {
            $period === '24h' => 'yesterday',
            $period === '7d' => 'previous 7 days',
            $period === '30d' => 'previous 30 days',
            $prevFrom->isSameDay($prevTo) => $prevFrom->format('d M Y'),
            default => $prevFrom->format('d M').' – '.$prevTo->format('d M Y'),
        };
    }
}
