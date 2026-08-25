<?php

namespace App\Support;

use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Collection;

final class EcomActivityCommerceEvents
{
    private const STAGE_PRIORITY = [
        'payment_success' => 4,
        'proceed_checkout' => 3,
        'begin_checkout' => 2,
        'add_to_cart' => 1,
    ];

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @param  array<string, mixed>  $catalogOptions
     * @return list<array<string, mixed>>
     */
    public static function fromActions(
        Collection $actions,
        array $catalogOptions = [],
        ?EcomTrackerDashboardService $dashboard = null,
    ): array {
        $dashboard ??= app(EcomTrackerDashboardService::class);
        $useCatalogScope = self::usesCatalogScope($catalogOptions);

        $events = [];

        foreach ($actions->sortBy(fn (ActivityEcomUserAction $action) => [
            $action->created_at?->timestamp ?? 0,
            $action->id,
        ]) as $action) {
            if (! in_array($action->action_type, array_keys(self::STAGE_PRIORITY), true)) {
                continue;
            }

            if ($useCatalogScope && ! self::actionMatchesCatalogScope($action, $catalogOptions, $dashboard)) {
                continue;
            }

            $event = match ($action->action_type) {
                'payment_success' => self::paymentEvent($action),
                'proceed_checkout' => self::checkoutEvent($action, 'proceed_checkout', 'Proceed'),
                'begin_checkout' => self::checkoutEvent($action, 'begin_checkout', 'Checkout'),
                'add_to_cart' => self::cartEvent($action),
                default => null,
            };

            if ($event === null) {
                continue;
            }

            $events[] = $event;
        }

        $events = self::dedupePaymentEvents($events);

        return self::collapseToDisplayEvents($events);
    }

    /**
     * Keep every distinct order; otherwise only the latest funnel action.
     *
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function collapseToDisplayEvents(array $events): array
    {
        $payments = array_values(array_filter(
            $events,
            fn (array $event) => ($event['stage'] ?? '') === 'payment_success',
        ));
        $funnel = array_values(array_filter(
            $events,
            fn (array $event) => ($event['stage'] ?? '') !== 'payment_success',
        ));

        if ($payments !== []) {
            usort($payments, fn (array $left, array $right) => ($right['sort_at'] ?? 0) <=> ($left['sort_at'] ?? 0));

            return $payments;
        }

        if ($funnel === []) {
            return [];
        }

        usort($funnel, function (array $left, array $right) {
            $leftTime = (int) ($left['sort_at'] ?? 0);
            $rightTime = (int) ($right['sort_at'] ?? 0);

            if ($leftTime !== $rightTime) {
                return $rightTime <=> $leftTime;
            }

            return (self::STAGE_PRIORITY[$right['stage']] ?? 0) <=> (self::STAGE_PRIORITY[$left['stage']] ?? 0);
        });

        return [$funnel[0]];
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     */
    private static function usesCatalogScope(array $catalogOptions): bool
    {
        return filled($catalogOptions['category'] ?? null)
            || filled($catalogOptions['product_code'] ?? null)
            || filled($catalogOptions['product_name'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     */
    private static function actionMatchesCatalogScope(
        ActivityEcomUserAction $action,
        array $catalogOptions,
        EcomTrackerDashboardService $dashboard,
    ): bool {
        if ($action->action_type === 'payment_success') {
            return $dashboard->paymentActionMatchesCategoryCatalog($action, $catalogOptions);
        }

        return $dashboard->actionMatchesCatalogOptions($action, $catalogOptions);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function paymentEvent(ActivityEcomUserAction $action): ?array
    {
        $payload = is_array($action->payment_success) ? $action->payment_success : [];
        $checkout = is_array($payload['checkout_info'] ?? null) ? $payload['checkout_info'] : [];
        $customer = is_array($checkout['customer'] ?? null) ? $checkout['customer'] : [];
        $shipping = is_array($checkout['shipping'] ?? null) ? $checkout['shipping'] : [];
        $totals = is_array($checkout['totals'] ?? null) ? $checkout['totals'] : [];
        $items = is_array($checkout['items'] ?? null) ? $checkout['items'] : [];

        $orderId = trim((string) ($payload['order_id'] ?? $checkout['order_number'] ?? ''));
        $amount = self::moneyAmount($payload['amount_paid'] ?? $totals['grand_total'] ?? null);
        $qty = self::sumItemQty($items);

        if ($orderId === '' && $amount === null && $items === []) {
            return null;
        }

        $customerName = trim(implode(' ', array_filter([
            trim((string) ($customer['first_name'] ?? '')),
            trim((string) ($customer['last_name'] ?? '')),
        ])));

        if ($customerName === '') {
            $customerName = trim((string) ($customer['full_name'] ?? ''));
        }

        $addressParts = array_filter([
            trim((string) ($shipping['line_1'] ?? '')),
            trim((string) ($shipping['line_2'] ?? '')),
            trim((string) ($shipping['town_city'] ?? '')),
            trim((string) ($shipping['postcode'] ?? '')),
        ]);

        return [
            'id' => 'payment:'.($orderId !== '' ? $orderId : (string) $action->id),
            'stage' => 'payment_success',
            'stage_label' => 'Order',
            'trigger_label' => self::orderTriggerLabel($orderId, $amount),
            'title' => 'Order details'.($orderId !== '' ? ': #'.$orderId : ''),
            'sort_at' => $action->created_at?->timestamp ?? 0,
            'occurred_at' => TrackerTime::formatFromStorage($action->created_at),
            'info_groups' => array_values(array_filter([
                [
                    'title' => 'Order info',
                    'fields' => array_values(array_filter([
                        $orderId !== '' ? ['label' => 'Order ID', 'value' => $orderId] : null,
                        $amount !== null ? ['label' => 'Total', 'value' => self::formatMoney($amount), 'emphasis' => true] : null,
                        $qty > 0 ? ['label' => 'Quantity', 'value' => (string) $qty] : null,
                        filled($payload['payment_method'] ?? null) ? ['label' => 'Payment', 'value' => (string) $payload['payment_method']] : null,
                        ['label' => 'Ordered', 'value' => TrackerTime::formatFromStorage($action->created_at, 'Y-m-d h:i A') ?? '—'],
                    ])),
                ],
                ($customerName !== '' || filled($customer['email'] ?? null) || filled($customer['phone'] ?? null) || $addressParts !== [])
                    ? [
                        'title' => 'Customer',
                        'fields' => array_values(array_filter([
                            $customerName !== '' ? ['label' => 'Name', 'value' => $customerName] : null,
                            filled($customer['phone'] ?? null) ? ['label' => 'Phone', 'value' => (string) $customer['phone']] : null,
                            filled($customer['email'] ?? null) ? ['label' => 'Email', 'value' => (string) $customer['email']] : null,
                            $addressParts !== [] ? ['label' => 'Address', 'value' => implode(', ', $addressParts)] : null,
                            filled($shipping['town_city'] ?? null) || filled($shipping['postcode'] ?? null)
                                ? ['label' => 'City & zip', 'value' => trim(implode(', ', array_filter([
                                    (string) ($shipping['town_city'] ?? ''),
                                    (string) ($shipping['postcode'] ?? ''),
                                ])))]
                                : null,
                        ])),
                    ]
                    : null,
                [
                    'title' => 'Prices',
                    'fields' => array_values(array_filter([
                        self::moneyField('Delivery charge', $totals['delivery_charge'] ?? $totals['shipping_cost'] ?? null),
                        self::moneyField('Smart cart saver', $totals['service_charge'] ?? null, true),
                        self::moneyField('Sub total', $totals['subtotal'] ?? null),
                        $amount !== null ? ['label' => 'Grand total', 'value' => self::formatMoney($amount), 'emphasis' => true] : null,
                    ])),
                ],
            ])),
            'products' => self::mapLineItems($items),
            'layout' => 'detail',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function checkoutEvent(ActivityEcomUserAction $action, string $stage, string $title): ?array
    {
        $payloadKey = $stage === 'proceed_checkout' ? 'proceed_to_checkout' : 'begin_checkout';
        $payload = is_array($action->{$payloadKey} ?? null) ? $action->{$payloadKey} : [];
        $items = self::normalizeItems($payload);
        $amount = self::moneyAmount(CheckoutPayloadTotals::commerceAmount($payload));

        if ($amount === null && $items === []) {
            return null;
        }

        $coupon = trim((string) ($payload['coupon_code'] ?? ''));
        $itemQty = self::sumItemQty($items);
        $totals = CheckoutPayloadTotals::totals($payload);
        $discountTotal = (self::moneyAmount($totals['coupon_discount'] ?? null) ?? 0)
            + (self::moneyAmount($totals['scs_discount'] ?? null) ?? 0)
            + (self::moneyAmount($totals['sms_discount'] ?? null) ?? 0);
        $shippingCost = self::moneyAmount($totals['shipping_cost'] ?? $totals['delivery_charge'] ?? null) ?? 0;

        return [
            'id' => $stage.':'.$action->id,
            'stage' => $stage,
            'stage_label' => $title,
            'trigger_label' => $amount !== null ? $title.' · '.self::formatMoney($amount) : $title,
            'title' => $title,
            'sort_at' => $action->created_at?->timestamp ?? 0,
            'occurred_at' => TrackerTime::formatFromStorage($action->created_at),
            'layout' => 'compact',
            'cart_qty' => $itemQty,
            'cart_total' => $amount !== null ? self::formatMoney($amount) : null,
            'footer_note' => self::joinSummaryParts(array_filter([
                $coupon !== '' ? 'Coupon '.$coupon : null,
                $discountTotal > 0 ? 'Discount '.self::formatMoney($discountTotal) : null,
                $shippingCost > 0 ? 'Shipping '.self::formatMoney($shippingCost) : null,
            ])) ?: null,
            'products' => self::mapLineItems($items),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function cartEvent(ActivityEcomUserAction $action): ?array
    {
        $payload = is_array($action->add_to_cart) ? $action->add_to_cart : [];
        $items = self::normalizeItems($payload);
        $amount = self::moneyAmount($payload['cart_total'] ?? null);
        $qty = max((int) ($payload['qty'] ?? 0), self::sumItemQty($items));

        if ($amount === null && $items === [] && $qty === 0) {
            return null;
        }

        return [
            'id' => 'cart:'.$action->id,
            'stage' => 'add_to_cart',
            'stage_label' => 'Cart',
            'trigger_label' => $amount !== null ? 'Cart · '.self::formatMoney($amount) : 'Cart',
            'title' => 'Cart details',
            'sort_at' => $action->created_at?->timestamp ?? 0,
            'occurred_at' => TrackerTime::formatFromStorage($action->created_at),
            'layout' => 'compact',
            'cart_qty' => $qty,
            'cart_total' => $amount !== null ? self::formatMoney($amount) : null,
            'footer_note' => filled($payload['source'] ?? null) ? (string) $payload['source'] : null,
            'products' => self::mapLineItems($items),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private static function normalizeItems(array $payload): array
    {
        $items = $payload['items'] ?? $payload['cart_items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function mapLineItems(array $items): array
    {
        return array_values(array_map(function (array $item) {
            $qty = (int) ($item['qty'] ?? $item['quantity'] ?? 1);
            $price = self::moneyAmount($item['price'] ?? $item['line_total'] ?? null);
            $title = trim((string) ($item['product_name'] ?? $item['name'] ?? ''));
            $code = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));

            if ($title !== '' && $code !== '') {
                $title .= ' ('.$code.')';
            } elseif ($title === '' && $code !== '') {
                $title = $code;
            }

            return array_filter([
                'title' => $title !== '' ? $title : 'Product',
                'size' => trim((string) ($item['size_name'] ?? '')),
                'color_po' => trim((string) ($item['color_name'] ?? $item['general_color_name'] ?? '')),
                'color_ecommerce' => trim((string) ($item['color_name'] ?? $item['general_color_name'] ?? '')),
                'qty' => $qty > 0 ? (string) $qty : '1',
                'price' => $price !== null ? self::formatMoney($price) : '—',
                'image_url' => trim((string) ($item['image_url'] ?? $item['product_image'] ?? '')),
            ], fn ($value) => $value !== '' && $value !== null);
        }, $items));
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function dedupePaymentEvents(array $events): array
    {
        $seen = [];
        $result = [];

        foreach ($events as $event) {
            if (($event['stage'] ?? '') !== 'payment_success') {
                $result[] = $event;

                continue;
            }

            $orderKey = preg_replace('/^payment:/', '', (string) ($event['id'] ?? ''));

            if ($orderKey !== '' && isset($seen[$orderKey])) {
                continue;
            }

            if ($orderKey !== '') {
                $seen[$orderKey] = true;
            }

            $result[] = $event;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private static function sumItemQty(array $items): int
    {
        $qty = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $qty += max(1, (int) ($item['qty'] ?? $item['quantity'] ?? 1));
        }

        return $qty;
    }

    private static function moneyAmount(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $amount = round((float) $value, 2);

        return $amount > 0 ? $amount : null;
    }

    private static function orderTriggerLabel(string $orderId, ?float $amount): string
    {
        $label = $orderId !== '' ? '#'.$orderId : 'Order';

        if ($amount !== null) {
            return $label.' · '.self::formatMoney($amount);
        }

        return $label;
    }

    private static function formatMoney(float $amount): string
    {
        return '£'.number_format($amount, 2);
    }

    /**
     * @param  list<string|null>  $parts
     */
    private static function joinSummaryParts(array $parts): string
    {
        return implode(' · ', array_values(array_filter($parts, fn (?string $part) => filled($part))));
    }

    /**
     * @return array{label: string, value: string, emphasis?: bool}|null
     */
    private static function moneyField(string $label, mixed $value, bool $negative = false): ?array
    {
        $amount = self::moneyAmount($value);

        if ($amount === null) {
            return null;
        }

        $formatted = self::formatMoney($amount);

        if ($negative) {
            $formatted = '- '.$formatted;
        }

        return ['label' => $label, 'value' => $formatted];
    }
}
