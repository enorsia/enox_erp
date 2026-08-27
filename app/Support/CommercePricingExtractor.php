<?php

namespace App\Support;

use App\Exceptions\CommerceParseException;

final class CommercePricingExtractor
{
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
