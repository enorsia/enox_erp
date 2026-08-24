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
                return $dashboard->paymentActionMatchesCategoryCatalog($action, $options);
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

        return self::summarizeActions($filtered);
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
        $payload = is_array($action->payment_success) ? $action->payment_success : [];
        $checkout = is_array($payload['checkout_info'] ?? null) ? $payload['checkout_info'] : [];

        return trim((string) ($payload['order_id'] ?? $checkout['order_number'] ?? ''));
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
        if ($stage === 'payment_success') {
            $payload = is_array($action->payment_success) ? $action->payment_success : [];
            $total = 0.0;

            foreach ([$payload['amount_paid'] ?? null, $payload['checkout_info']['totals']['grand_total'] ?? null] as $candidate) {
                if (is_numeric($candidate)) {
                    $total = max($total, round((float) $candidate, 2));
                }
            }

            return $total > 0 ? $total : null;
        }

        $payloadKey = match ($stage) {
            'proceed_checkout' => 'proceed_to_checkout',
            'begin_checkout' => 'begin_checkout',
            'add_to_cart' => 'add_to_cart',
            default => null,
        };

        if ($payloadKey === null) {
            return null;
        }

        $payload = is_array($action->{$payloadKey} ?? null) ? $action->{$payloadKey} : [];
        $amount = (float) ($payload['cart_total'] ?? $payload['amount_paid'] ?? 0);

        return $amount > 0 ? round($amount, 2) : null;
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
