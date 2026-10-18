<?php

namespace App\Support;

use App\Exceptions\CommerceParseException;

final class CommercePricingExtractor
{
    private const INGEST_JSON_COLUMNS = [
        'add_to_cart' => 'add_to_cart',
        'begin_checkout' => 'begin_checkout',
        'proceed_checkout' => 'proceed_to_checkout',
        'payment_success' => 'payment_success',
    ];

    /**
     * Copy checkout JSON on an ingest action row into commerce scalar columns.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function applyScalarsToActionRow(string $actionType, array $row): array
    {
        $payloadKey = self::INGEST_JSON_COLUMNS[$actionType] ?? null;

        if ($payloadKey === null) {
            return $row;
        }

        $payload = self::decodeIngestPayload($row[$payloadKey] ?? null);

        if ($payload === null || $payload === []) {
            return $row;
        }

        try {
            $extracted = self::fromPayload($actionType, $payload);
        } catch (CommerceParseException) {
            return $row;
        }

        foreach ([
            'commerce_total',
            'commerce_subtotal',
            'commerce_shipping',
            'commerce_discount',
            'coupon_code',
            'discount_type',
            'order_id',
            'amount_paid',
        ] as $column) {
            if (! array_key_exists($column, $extracted) || $extracted[$column] === null) {
                continue;
            }

            $row[$column] = $extracted[$column];
        }

        $counts = self::lineItemCounts($payload);

        if ($counts['line_count'] > 0) {
            $row['line_count'] = $counts['line_count'];
        }

        if ($counts['item_qty'] > 0) {
            $row['item_qty'] = $counts['item_qty'];
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{line_count: int, item_qty: int}
     */
    public static function lineItemCounts(array $payload): array
    {
        $items = $payload['items'] ?? $payload['cart_items'] ?? null;

        if (! is_array($items) || $items === []) {
            $checkout = $payload['checkout_info'] ?? [];
            $items = is_array($checkout) ? ($checkout['items'] ?? []) : [];
        }

        if (! is_array($items) || $items === []) {
            return ['line_count' => 0, 'item_qty' => 0];
        }

        $lineCount = 0;
        $itemQty = 0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lineCount++;
            $itemQty += max(1, (int) ($item['qty'] ?? 1));
        }

        return ['line_count' => $lineCount, 'item_qty' => $itemQty];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeIngestPayload(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function fromPayload(string $actionType, array $payload, ?string $eventId = null): array
    {
        if ($payload === []) {
            throw new CommerceParseException(
                'Empty commerce payload',
                self::payloadSignature($payload),
                $eventId,
                $actionType,
            );
        }

        $totals = CheckoutPayloadTotals::totals($payload);
        $couponDiscount = self::money($totals['coupon_discount'] ?? null);
        $scsDiscount = self::money($totals['scs_discount'] ?? null);
        $smsDiscount = self::money($totals['sms_discount'] ?? null);
        $discountAmount = round($couponDiscount + $scsDiscount + $smsDiscount, 2);

        $shipping = self::money($totals['delivery_charge'] ?? $totals['shipping_cost'] ?? null);
        $subtotal = self::money($totals['subtotal'] ?? null);
        $grandTotal = CheckoutPayloadTotals::grandTotal($payload);
        $commerceTotal = $grandTotal ?? self::money($payload['cart_total'] ?? null);

        if ($subtotal <= 0) {
            $inferred = CheckoutPayloadTotals::itemsSubtotal($payload);

            if ($inferred !== null) {
                $subtotal = $inferred;
            } elseif ($commerceTotal !== null && $commerceTotal > 0 && $discountAmount <= 0 && $shipping <= 0) {
                $subtotal = $commerceTotal;
            }
        }

        $checkoutInfo = is_array($payload['checkout_info'] ?? null) ? $payload['checkout_info'] : [];
        $couponCode = trim((string) ($payload['coupon_code'] ?? $checkoutInfo['coupon_code'] ?? ''));

        return [
            'commerce_total' => $commerceTotal,
            'commerce_subtotal' => $subtotal,
            'commerce_shipping' => $shipping > 0 ? $shipping : null,
            'commerce_discount' => $discountAmount > 0 ? $discountAmount : null,
            'coupon_code' => $couponCode !== '' ? $couponCode : null,
            'discount_type' => self::discountType($couponDiscount, $scsDiscount, $smsDiscount),
            'coupon_discount' => $couponDiscount > 0 ? $couponDiscount : null,
            'scs_discount' => $scsDiscount > 0 ? $scsDiscount : null,
            'sms_discount' => $smsDiscount > 0 ? $smsDiscount : null,
            'discount_amount' => $discountAmount > 0 ? $discountAmount : null,
            'shipping_charge' => $shipping > 0 ? $shipping : null,
            'service_charge' => self::moneyOrNull($totals['service_charge'] ?? null),
            'extra_charges_total' => self::moneyOrNull($totals['extra_charges_total'] ?? null),
            'subtotal' => $subtotal,
            'grand_total' => $grandTotal,
            'currency' => trim((string) ($payload['currency'] ?? '')) ?: null,
            'amount_paid' => $actionType === 'payment_success'
                ? self::money($payload['amount_paid'] ?? $grandTotal)
                : null,
            'payment_method' => $actionType === 'payment_success'
                ? (trim((string) ($payload['payment_method'] ?? '')) ?: null)
                : null,
            'order_id' => $actionType === 'payment_success' ? self::orderId($payload) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function orderId(array $payload): ?string
    {
        $orderId = trim((string) ($payload['order_id'] ?? ''));
        if ($orderId !== '') {
            return $orderId;
        }

        $checkout = is_array($payload['checkout_info'] ?? null) ? $payload['checkout_info'] : [];
        $orderId = trim((string) ($checkout['order_number'] ?? $checkout['order_pk'] ?? ''));

        return $orderId !== '' ? $orderId : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function payloadSignature(array $payload): string
    {
        return hash('sha256', json_encode(self::shapeKeys($payload)) ?: '');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private static function shapeKeys(array $data): array
    {
        $keys = array_keys($data);
        sort($keys);

        $shape = [];
        foreach ($keys as $key) {
            $value = $data[$key];
            if (is_array($value)) {
                $shape[] = $key.':{'.implode(',', self::shapeKeys($value)).'}';
            } else {
                $shape[] = $key;
            }
        }

        return $shape;
    }

    private static function discountType(float $coupon, float $scs, float $sms): ?string
    {
        $active = array_filter([
            'coupon' => $coupon > 0,
            'scs' => $scs > 0,
            'sms' => $sms > 0,
        ]);

        return match (count($active)) {
            0 => null,
            1 => array_key_first($active),
            default => 'mixed',
        };
    }

    private static function money(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private static function moneyOrNull(mixed $value): ?float
    {
        $amount = self::money($value);

        return $amount > 0 ? $amount : null;
    }
}
