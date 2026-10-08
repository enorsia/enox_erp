<?php

namespace App\Services;

use App\Support\TrackerTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Ecom activity list: tracking_session rows + the latest action for each session's funnel stage
 * (used to build the Commerce column and its expandable order / checkout / cart details).
 */
class EcomActivityListService
{
    private const STAGE_KEYS = [
        3 => 'add_to_cart',
        4 => 'begin_checkout',
        5 => 'proceed_checkout',
        6 => 'payment_success',
    ];

    private const STAGE_LABELS = [
        'add_to_cart' => 'Cart',
        'begin_checkout' => 'Checkout',
        'proceed_checkout' => 'Proceed',
        'payment_success' => 'Order',
    ];

    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        $stageActionType = "CASE ts.latest_funnel_stage
            WHEN 6 THEN 'payment_success'
            WHEN 5 THEN 'proceed_checkout'
            WHEN 4 THEN 'begin_checkout'
            WHEN 3 THEN 'add_to_cart'
        END";

        return DB::table('tracking_session as ts')
            ->leftJoin('activity_ecom_user_actions as ca', function ($join) use ($stageActionType) {
                $join->on('ca.session_id', '=', 'ts.session_id')
                    ->whereRaw("ca.id = (
                        SELECT MAX(a2.id) FROM activity_ecom_user_actions a2
                        WHERE a2.session_id = ts.session_id AND a2.action_type = {$stageActionType}
                    )");
            })
            ->select([
                'ts.*',
                'ca.id as commerce_action_id',
                'ca.order_id as commerce_order_id',
                'ca.amount_paid as commerce_amount_paid',
                'ca.commerce_total',
                'ca.commerce_subtotal',
                'ca.commerce_shipping',
                'ca.commerce_discount',
                'ca.coupon_code as commerce_coupon_code',
                'ca.item_qty as commerce_item_qty',
                'ca.line_count as commerce_line_count',
                'ca.add_to_cart as commerce_add_to_cart',
                'ca.begin_checkout as commerce_begin_checkout',
                'ca.proceed_to_checkout as commerce_proceed_to_checkout',
                'ca.payment_success as commerce_payment_success',
                'ca.created_at as commerce_at',
            ])
            ->orderByDesc('ts.last_active_at')
            ->paginate($perPage)
            ->through(function (object $row) {
                $row->commerce = $this->commerceCell($row);

                return $row;
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function commerceCell(object $row): array
    {
        $stage = (int) ($row->latest_funnel_stage ?? 0);
        $stageKey = self::STAGE_KEYS[$stage] ?? null;

        if ($stageKey === null) {
            return [
                'commerce_display' => $stage > 0 ? 'View' : '—',
                'commerce_has_order' => false,
                'expandable_commerce_events' => [],
            ];
        }

        $label = self::STAGE_LABELS[$stageKey];
        $isOrder = $stageKey === 'payment_success';
        $orderId = trim((string) ($row->commerce_order_id ?? ''));
        $amount = $this->amount($isOrder ? ($row->commerce_amount_paid ?: $row->commerce_total) : $row->commerce_total);
        $triggerLabel = ($isOrder ? ($orderId !== '' ? '#'.$orderId : 'Order') : $label)
            .($amount !== null ? ' · '.$this->money($amount) : '');

        $event = $row->commerce_action_id
            ? $this->commerceEvent($row, $stageKey, $label, $triggerLabel, $amount, $orderId)
            : null;

        return [
            'commerce_display' => $triggerLabel,
            'commerce_has_order' => $isOrder,
            'expandable_commerce_events' => $event ? [$event] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commerceEvent(object $row, string $stageKey, string $label, string $triggerLabel, ?float $amount, string $orderId): array
    {
        $json = json_decode((string) ($row->{'commerce_'.($stageKey === 'proceed_checkout' ? 'proceed_to_checkout' : $stageKey)} ?? ''), true) ?: [];
        $items = match ($stageKey) {
            'payment_success' => $json['checkout_info']['items'] ?? [],
            'add_to_cart' => $json['items'] ?? [],
            default => $json['cart_items'] ?? [],
        };

        $products = array_values(array_map(
            fn (array $item) => $this->product($item),
            array_filter((array) $items, 'is_array'),
        ));

        $qty = (int) ($row->commerce_item_qty ?? 0)
            ?: (int) array_sum(array_map(fn (array $product) => (int) $product['qty'], $products))
            ?: (int) ($row->commerce_line_count ?? 0);

        $event = [
            'id' => $stageKey.':'.$row->commerce_action_id,
            'stage' => $stageKey,
            'stage_label' => $label,
            'trigger_label' => $triggerLabel,
            'occurred_at' => TrackerTime::formatFromStorage($row->commerce_at),
            'products' => $products,
        ];

        if ($stageKey !== 'payment_success') {
            $discount = (float) ($row->commerce_discount ?? 0);
            $shipping = (float) ($row->commerce_shipping ?? 0);
            $coupon = trim((string) ($row->commerce_coupon_code ?? ''));

            return $event + [
                'title' => $stageKey === 'add_to_cart' ? 'Cart details' : $label,
                'layout' => 'compact',
                'cart_qty' => $qty,
                'cart_total' => $amount !== null ? $this->money($amount) : null,
                'footer_note' => implode(' · ', array_filter([
                    $coupon !== '' ? 'Coupon '.$coupon : null,
                    $discount > 0 ? 'Discount '.$this->money($discount) : null,
                    $shipping > 0 ? 'Shipping '.$this->money($shipping) : null,
                ])) ?: null,
            ];
        }

        $customer = $json['checkout_info']['customer'] ?? [];
        $totals = $json['checkout_info']['totals'] ?? [];

        return $event + [
            'title' => 'Order details'.($orderId !== '' ? ': #'.$orderId : ''),
            'layout' => 'detail',
            'info_groups' => array_values(array_filter([
                [
                    'title' => 'Order info',
                    'fields' => array_values(array_filter([
                        $orderId !== '' ? ['label' => 'Order ID', 'value' => $orderId] : null,
                        $amount !== null ? ['label' => 'Total', 'value' => $this->money($amount), 'emphasis' => true] : null,
                        $qty > 0 ? ['label' => 'Quantity', 'value' => (string) $qty] : null,
                        filled($json['payment_method'] ?? null) ? ['label' => 'Payment', 'value' => (string) $json['payment_method']] : null,
                        ['label' => 'Ordered', 'value' => TrackerTime::formatFromStorage($row->commerce_at, 'Y-m-d h:i A') ?? '—'],
                    ])),
                ],
                (filled($customer['email'] ?? null) || filled($customer['phone'] ?? null))
                    ? [
                        'title' => 'Customer',
                        'fields' => array_values(array_filter([
                            filled($customer['phone'] ?? null) ? ['label' => 'Phone', 'value' => (string) $customer['phone']] : null,
                            filled($customer['email'] ?? null) ? ['label' => 'Email', 'value' => (string) $customer['email']] : null,
                        ])),
                    ]
                    : null,
                [
                    'title' => 'Prices',
                    'fields' => array_values(array_filter([
                        $this->moneyField('Sub total', $row->commerce_subtotal ?? ($totals['subtotal'] ?? null)),
                        $this->moneyField('Delivery charge', $row->commerce_shipping ?? ($totals['delivery_charge'] ?? null)),
                        $this->moneyField('Discount', $row->commerce_discount ?? null, true),
                        $amount !== null ? ['label' => 'Grand total', 'value' => $this->money($amount), 'emphasis' => true] : null,
                    ])),
                ],
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function product(array $item): array
    {
        $title = trim((string) ($item['product_name'] ?? ''));
        $code = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));

        return array_filter([
            'title' => $title !== '' && $code !== '' ? "{$title} ({$code})" : ($title ?: ($code ?: 'Product')),
            'size' => trim((string) ($item['size_name'] ?? '')),
            'color_po' => trim((string) ($item['color_name'] ?? '')),
            'qty' => (string) max(1, (int) ($item['qty'] ?? 1)),
            'price' => is_numeric($item['price'] ?? null) ? $this->money((float) $item['price']) : '—',
        ], fn ($value) => $value !== '');
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private function moneyField(string $label, mixed $value, bool $negative = false): ?array
    {
        $amount = $this->amount($value);

        return $amount !== null
            ? ['label' => $label, 'value' => ($negative ? '- ' : '').$this->money($amount)]
            : null;
    }

    private function amount(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    private function money(float $amount): string
    {
        return '£'.number_format($amount, 2);
    }
}
