<?php

use App\Support\CheckoutPayloadTotals;

uses(Tests\TestCase::class);

test('checkout payload totals prefer grand total over cart total', function () {
    $amount = CheckoutPayloadTotals::grandTotal([
        'cart_total' => 40,
        'totals' => [
            'subtotal' => 50,
            'shipping_cost' => 4.99,
            'coupon_discount' => 5,
            'grand_total' => 49.99,
        ],
    ]);

    expect($amount)->toBe(49.99);
});

test('checkout payload totals fall back to cart total when grand total missing', function () {
    $amount = CheckoutPayloadTotals::grandTotal([
        'cart_total' => 32.5,
    ]);

    expect($amount)->toBe(32.5);
});
