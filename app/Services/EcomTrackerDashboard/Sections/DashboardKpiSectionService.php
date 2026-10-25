<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Support\TrackerDatabase;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardKpiSectionService
{
    private function totals(Collection $rows, int $uniqueVisitors): array
    {
        $totals = ['unique_visitors' => $uniqueVisitors];

        foreach ([
            'sessions', 'stay_seconds', 'cart_sessions', 'checkout_sessions', 'proceed_sessions',
            'cart_drops', 'checkout_drops', 'proceed_drops', 'payments', 'items_sold',
        ] as $column) {
            $totals[$column] = (int) $rows->sum($column);
        }

        $totals['sale_amount'] = round((float) $rows->sum('sale_amount'), 2);
        $totals['avg_stay_seconds'] = $totals['sessions'] > 0 ? (int) round($totals['stay_seconds'] / $totals['sessions']) : 0;

        return $totals;
    }

    public function groups(array $current, array $previous, string $comparisonLabel): array
    {
        $metric = fn (string $label, string $key, callable $format, string $tip, array $activity = []) => [
            'label' => $label,
            'formatted' => $format($current[$key]),
            'tip' => $tip,
            'activity' => $activity,
            'comparison' => $this->comparison($current[$key], $previous[$key], $format($previous[$key]), $comparisonLabel),
        ];

        $count = fn (float|int $value) => number_format($value);
        $duration = fn (float|int $value) => format_duration((int) $value);

        $currentFunnel = $this->funnelKpisFromTotals($current);
        $previousFunnel = $this->funnelKpisFromTotals($previous);

        $payments = $this->funnelDropMetric(
            'Payments',
            $currentFunnel['payment_rate'],
            $currentFunnel['payment_count'],
            $previousFunnel['payment_rate'],
            $previousFunnel['payment_count'],
            'Share of sessions with at least one payment; count is completed orders (multiple orders in one session each count).',
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
                $this->funnelDropMetric(
                    'Cart drop',
                    $currentFunnel['cart_drop_rate'],
                    $currentFunnel['cart_drop_count'],
                    $previousFunnel['cart_drop_rate'],
                    $previousFunnel['cart_drop_count'],
                    'Sessions that added to cart but did not begin checkout (matches Cart abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 3],
                ),
                $this->funnelDropMetric(
                    'Checkout drop',
                    $currentFunnel['checkout_drop_rate'],
                    $currentFunnel['checkout_drop_count'],
                    $previousFunnel['checkout_drop_rate'],
                    $previousFunnel['checkout_drop_count'],
                    'Sessions that began checkout but did not proceed (matches Begin checkout abandoned drill-down).',
                    $comparisonLabel,
                    ['funnel' => 4],
                ),
                $this->funnelDropMetric(
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
    private function funnelKpisFromTotals(array $totals): array
    {
        $sessions = (int) ($totals['sessions'] ?? 0);
        $cartStage = (int) ($totals['cart_sessions'] ?? 0);
        $checkoutStage = (int) ($totals['checkout_sessions'] ?? 0);
        $proceedStage = (int) ($totals['proceed_sessions'] ?? 0);
        $payments = (int) ($totals['payments'] ?? 0);
        $purchaseSessions = (int) ($totals['purchase_sessions'] ?? $payments);
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
            'payment_rate' => $sessions > 0 ? round($purchaseSessions / $sessions * 100, 1) : 0.0,
            'payment_count' => $payments,
        ];
    }

    private function formatFunnelDropValue(float $rate, int $count): string
    {
        return number_format($rate, 1).'% / '.number_format($count);
    }

    /**
     * @return array<string, mixed>
     */
    private function funnelDropMetric(
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
            'formatted' => $this->formatFunnelDropValue($currentRate, $currentCount),
            'tip' => $tip,
            'activity' => $activity,
            'comparison' => $this->comparison(
                $currentRate,
                $previousRate,
                $this->formatFunnelDropValue($previousRate, $previousCount),
                $comparisonLabel,
            ),
        ];
    }

    /**
     * @return array{items_sold: int, sale_amount: float}
     */
    private function saleTotalsFromOrders(string $from, string $to): array
    {
        $row = TrackerDatabase::table('tracking_orders')
            ->whereBetween('metric_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(items_qty), 0) as items_sold')
            ->selectRaw('COALESCE(SUM(amount_paid), 0) as sale_amount')
            ->first();

        return [
            'items_sold' => (int) ($row->items_sold ?? 0),
            'sale_amount' => round((float) ($row->sale_amount ?? 0), 2),
        ];
    }

    /**
     * @return array{delta_pct: ?float, delta_direction: string, delta_label: null, previous_formatted: string, comparison_label: string}
     */
    private function comparison(float|int $current, float|int $previous, string $previousFormatted, string $comparisonLabel): array
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

    public function comparisonLabel(string $period, Carbon $prevFrom, Carbon $prevTo): string
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
