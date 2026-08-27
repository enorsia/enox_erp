<?php

use App\Support\CommercePricingExtractor;

uses(Tests\TestCase::class);

test('commerce pricing extractor derives mixed discount type', function () {
    $pricing = CommercePricingExtractor::fromPayload('begin_checkout', [
        'totals' => [
            'subtotal' => 100,
            'coupon_discount' => 5,
            'scs_discount' => 2,
            'grand_total' => 93,
        ],
        'coupon_code' => 'SAVE5',
    ]);

    expect($pricing['discount_type'])->toBe('mixed')
        ->and($pricing['commerce_discount'])->toBe(7.0)
        ->and($pricing['coupon_code'])->toBe('SAVE5');
});

test('commerce pricing extractor resolves payment order id', function () {
    $pricing = CommercePricingExtractor::fromPayload('payment_success', [
        'order_id' => 'ORD-99',
        'amount_paid' => 25,
    ]);

    expect($pricing['order_id'])->toBe('ORD-99')
        ->and($pricing['amount_paid'])->toBe(25.0);
});
