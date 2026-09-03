<?php

namespace App\Support;

use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Collection;

final class EcomActivityCommerceSummary
{
    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return array{
     *     commerce_label: ?string,
     *     commerce_value: ?float,
     *     commerce_has_order: bool,
     *     commerce_display: string,
     *     commerce_tip: ?string,
     * }
     */
    public static function summarizeActions(Collection $actions): array
    {
        $latest = self::latestByStage($actions);

        if ($latest === null) {
            return self::emptySummary();
        }

        [$stage, $action] = $latest;
        $value = self::amountForStage($stage, $action);
        $label = self::labelForStage($stage);
        $commerceDisplay = $stage === 'payment_success'
            ? self::formatOrderDisplay(self::orderIdFromPaymentAction($action), $value)
            : self::formatDisplay($label, $value);

        return [
            'commerce_label' => $label,
            'commerce_value' => $value,
            'commerce_has_order' => $stage === 'payment_success',
            'commerce_display' => $commerceDisplay,
            'commerce_tip' => self::tipForStage($stage, $action, $value),
        ];
    }

    /**
     * Summarize commerce using only actions that match catalog drill-down filters.
     *
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @param  array<string, mixed>  $options
     */
    public static function summarizeCatalogActions(
        Collection $actions,
        array $options,
        EcomTrackerDashboardService $dashboard,
    ): array {
        $filtered = $actions->filter(function (ActivityEcomUserAction $action) use ($dashboard, $options) {
            if ($action->action_type === 'payment_success') {
                return $dashboard->catalogPaymentHasMatchingLines($action, $options);
            }

            return $dashboard->actionMatchesCatalogOptions($action, $options);
        });

        if ($filtered->isEmpty()) {
            return self::emptySummary();
        }

        $paymentActions = $filtered
            ->where('action_type', 'payment_success')
            ->sortBy(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])
            ->values();

        $totalRevenue = 0.0;
        $matchingPayments = 0;
        $latestPayment = null;

        foreach ($paymentActions as $payment) {
            $lines = $dashboard->sumCatalogPaymentLines($payment, $options);

            if ($lines['revenue'] <= 0) {
                continue;
            }

            $totalRevenue += $lines['revenue'];
            $matchingPayments++;
            $latestPayment = $payment;
        }

        if ($totalRevenue > 0 && $latestPayment instanceof ActivityEcomUserAction) {
            $roundedTotal = round($totalRevenue, 2);
            $orderId = self::orderIdFromPaymentAction($latestPayment);

            return [
                'commerce_label' => 'Order',
                'commerce_value' => $roundedTotal,
                'commerce_has_order' => true,
                'commerce_display' => self::formatOrderDisplay($orderId, $roundedTotal),
                'commerce_tip' => $matchingPayments > 1
                    ? 'Order · £'.number_format($roundedTotal, 2).' · '.$matchingPayments.' payments'
                    : self::tipForStage('payment_success', $latestPayment, $roundedTotal),
            ];
        }

        $commerceActions = $filtered->whereIn('action_type', [
            'add_to_cart',
            'begin_checkout',
            'proceed_checkout',
            'payment_success',
        ]);

        if ($commerceActions->isNotEmpty()) {
            return self::summarizeLatestCommerceAction($commerceActions);
        }

        $latestView = $filtered
            ->whereIn('action_type', ['product_view', 'product_view_popup'])
            ->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])
            ->first();

        if ($latestView instanceof ActivityEcomUserAction) {
            return self::productViewSummary($latestView);
        }

        return self::emptySummary();
    }

    public static function funnelStageRankFromSummary(array $summary): int
    {
        return match ($summary['commerce_label'] ?? null) {
            'Order' => 5,
            'Proceed' => 4,
            'Checkout' => 3,
            'Cart' => 2,
            'View' => 1,
            default => 0,
        };
    }

    /**
     * Commerce cell for the activity list, from line items + orders (not user_actions).
     *
     * @param  Collection<int, object>  $lines
     * @param  Collection<int, object>  $orders
     * @return array{
     *     commerce_label: ?string,
     *     commerce_value: ?float,
     *     commerce_has_order: bool,
     *     commerce_display: string,
     *     commerce_tip: ?string,
     * }
     */
    public static function summarizeFromCommerce(Collection $lines, Collection $orders): array
    {
        $paymentLines = $lines->where('funnel_stage', 'payment_success')->values();

        if ($orders->isNotEmpty() || $paymentLines->isNotEmpty()) {
            $latestOrder = $orders
                ->sortByDesc(fn (object $order) => [
                    strtotime((string) ($order->ordered_at ?? '')) ?: 0,
                    (int) ($order->id ?? 0),
                ])
                ->first();

            if ($latestOrder === null && $paymentLines->isNotEmpty()) {
                $latestLine = $paymentLines
                    ->sortByDesc(fn (object $line) => [
                        strtotime((string) ($line->staged_at ?? '')) ?: 0,
                        (int) ($line->id ?? 0),
                    ])
                    ->first();
                $value = round((float) $paymentLines->sum(fn (object $line) => (float) ($line->line_total ?? 0)), 2);
                $orderId = trim((string) ($latestLine->order_id ?? ''));

                return [
                    'commerce_label' => 'Order',
                    'commerce_value' => $value > 0 ? $value : null,
                    'commerce_has_order' => true,
                    'commerce_display' => self::formatOrderDisplay($orderId, $value > 0 ? $value : null),
                    'commerce_tip' => self::tipFromParts('Order', $value > 0 ? $value : null, $orderId, $latestLine->staged_at ?? null),
                ];
            }

            $value = $orders->isNotEmpty()
                ? round((float) $orders->sum(fn (object $order) => (float) ($order->amount_paid ?? 0)), 2)
                : round((float) $paymentLines->sum(fn (object $line) => (float) ($line->line_total ?? 0)), 2);
            $orderId = trim((string) ($latestOrder->order_id ?? ''));

            return [
                'commerce_label' => 'Order',
                'commerce_value' => $value > 0 ? $value : null,
                'commerce_has_order' => true,
                'commerce_display' => self::formatOrderDisplay($orderId, $value > 0 ? $value : null),
                'commerce_tip' => self::tipFromParts('Order', $value > 0 ? $value : null, $orderId, $latestOrder->ordered_at ?? null),
            ];
        }

        foreach (['proceed_checkout' => 'Proceed', 'begin_checkout' => 'Checkout', 'add_to_cart' => 'Cart'] as $stage => $label) {
            $stageLines = $lines->where('funnel_stage', $stage)->values();

            if ($stageLines->isEmpty()) {
                continue;
            }

            $latest = $stageLines
                ->sortByDesc(fn (object $line) => [
                    strtotime((string) ($line->staged_at ?? '')) ?: 0,
                    (int) ($line->id ?? 0),
                ])
                ->first();
            $eventLines = $stageLines->where('event_id', $latest->event_id ?? null);
            $value = round((float) $eventLines->sum(fn (object $line) => (float) ($line->line_total ?? 0)), 2);

            return [
                'commerce_label' => $label,
                'commerce_value' => $value > 0 ? $value : null,
                'commerce_has_order' => false,
                'commerce_display' => self::formatDisplay($label, $value > 0 ? $value : null),
                'commerce_tip' => self::tipFromParts($label, $value > 0 ? $value : null, null, $latest->staged_at ?? null),
            ];
        }

        return self::emptySummary();
    }

    /**
     * @param  Collection<int, object>  $lines
     * @return array{
     *     commerce_label: string,
     *     commerce_value: null,
     *     commerce_has_order: false,
     *     commerce_display: string,
     *     commerce_tip: null,
     * }|null
     */
    public static function summarizeFromViewLines(Collection $lines): ?array
    {
        $viewLines = $lines->filter(
            fn (object $line) => in_array((string) ($line->funnel_stage ?? ''), [
                'product_view',
                'product_view_popup',
                'category_view',
            ], true),
        );

        if ($viewLines->isEmpty()) {
            return null;
        }

        return [
            'commerce_label' => 'View',
            'commerce_value' => null,
            'commerce_has_order' => false,
            'commerce_display' => 'View',
            'commerce_tip' => null,
        ];
    }

    private static function tipFromParts(string $label, ?float $value, ?string $orderId, mixed $occurredAt): ?string
    {
        $parts = [$label];

        if ($value !== null && $value > 0) {
            $parts[] = '£'.number_format($value, 2);
        }

        if (filled($orderId)) {
            $parts[] = 'Order #'.$orderId;
        }

        $when = TrackerTime::formatFromStorage($occurredAt);

        if ($when !== null) {
            $parts[] = $when;
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return array{
     *     commerce_label: ?string,
     *     commerce_value: ?float,
     *     commerce_has_order: bool,
     *     commerce_display: string,
     *     commerce_tip: ?string,
     * }
     */
    private static function summarizeLatestCommerceAction(Collection $actions): array
    {
        $latest = $actions
            ->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])
            ->first();

        if (! $latest instanceof ActivityEcomUserAction) {
            return self::emptySummary();
        }

        $stage = (string) $latest->action_type;
        $value = self::amountForStage($stage, $latest);
        $label = self::labelForStage($stage);
        $commerceDisplay = $stage === 'payment_success'
            ? self::formatOrderDisplay(self::orderIdFromPaymentAction($latest), $value)
            : self::formatDisplay($label, $value);

        return [
            'commerce_label' => $label,
            'commerce_value' => $value,
            'commerce_has_order' => $stage === 'payment_success',
            'commerce_display' => $commerceDisplay,
            'commerce_tip' => self::tipForStage($stage, $latest, $value),
        ];
    }

    /**
     * @return array{
     *     commerce_label: string,
     *     commerce_value: null,
     *     commerce_has_order: false,
     *     commerce_display: string,
     *     commerce_tip: ?string,
     * }
     */
    private static function productViewSummary(ActivityEcomUserAction $action): array
    {
        return [
            'commerce_label' => 'View',
            'commerce_value' => null,
            'commerce_has_order' => false,
            'commerce_display' => 'View',
            'commerce_tip' => null,
        ];
    }

    public static function formatDisplay(?string $label, ?float $value): string
    {
        if ($label === null) {
            return '—';
        }

        if ($value !== null && $value > 0) {
            return $label.' · £'.number_format($value, 2);
        }

        return $label;
    }

    public static function orderIdFromPaymentAction(ActivityEcomUserAction $action): string
    {
        return CommerceReadSupport::orderIdForAction($action);
    }

    public static function formatOrderDisplay(string $orderId, ?float $value): string
    {
        $label = $orderId !== '' ? '#'.$orderId : 'Order';

        if ($value !== null && $value > 0) {
            return $label.' · £'.number_format($value, 2);
        }

        return $label;
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return array{0: string, 1: ActivityEcomUserAction}|null
     */
    private static function latestByStage(Collection $actions): ?array
    {
        $priority = [
            'payment_success',
            'proceed_checkout',
            'begin_checkout',
            'add_to_cart',
        ];

        $grouped = $actions
            ->filter(fn (ActivityEcomUserAction $action) => in_array($action->action_type, $priority, true))
            ->groupBy('action_type');

        foreach ($priority as $stage) {
            $stageActions = $grouped->get($stage);

            if ($stageActions === null || $stageActions->isEmpty()) {
                continue;
            }

            $latest = $stageActions->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->id,
            ])->first();

            if ($latest instanceof ActivityEcomUserAction) {
                return [$stage, $latest];
            }
        }

        return null;
    }

    private static function labelForStage(string $stage): string
    {
        return match ($stage) {
            'payment_success' => 'Order',
            'proceed_checkout' => 'Proceed',
            'begin_checkout' => 'Checkout',
            'add_to_cart' => 'Cart',
            default => '—',
        };
    }

    private static function amountForStage(string $stage, ActivityEcomUserAction $action): ?float
    {
        return CommerceReadSupport::amountForAction($action);
    }

    private static function tipForStage(string $stage, ActivityEcomUserAction $action, ?float $value): ?string
    {
        $parts = [self::labelForStage($stage)];

        if ($value !== null && $value > 0) {
            $parts[] = '£'.number_format($value, 2);
        }

        if ($stage === 'payment_success') {
            $orderId = self::orderIdFromPaymentAction($action);

            if ($orderId !== '') {
                $parts[] = 'Order #'.$orderId;
            }
        }

        $when = TrackerTime::formatFromStorage($action->created_at);

        if ($when !== null) {
            $parts[] = $when;
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * @return array{
     *     commerce_label: null,
     *     commerce_value: null,
     *     commerce_has_order: false,
     *     commerce_display: string,
     *     commerce_tip: null,
     * }
     */
    private static function emptySummary(): array
    {
        return [
            'commerce_label' => null,
            'commerce_value' => null,
            'commerce_has_order' => false,
            'commerce_display' => '—',
            'commerce_tip' => null,
        ];
    }
}
