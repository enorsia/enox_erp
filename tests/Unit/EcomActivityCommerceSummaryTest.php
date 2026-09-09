<?php

use App\Models\ActivityEcomUserAction;
use App\Support\EcomActivityCommerceSummary;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

test('commerce summary prefers order value over earlier checkout stages', function () {
    $sessionId = Str::uuid()->toString();

    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'add_to_cart',
            'add_to_cart' => ['cart_total' => 20],
            'created_at' => now()->subMinutes(30),
        ]),
        new ActivityEcomUserAction([
            'id' => 2,
            'action_type' => 'begin_checkout',
            'begin_checkout' => ['cart_total' => 25],
            'created_at' => now()->subMinutes(20),
        ]),
        new ActivityEcomUserAction([
            'id' => 3,
            'action_type' => 'payment_success',
            'payment_success' => ['amount_paid' => 15.49, 'order_id' => 'ORD-1'],
            'created_at' => now()->subMinutes(5),
        ]),
    ]);

    $summary = EcomActivityCommerceSummary::summarizeActions($actions);

    expect($summary['commerce_label'])->toBe('Order')
        ->and($summary['commerce_has_order'])->toBeTrue()
        ->and($summary['commerce_display'])->toBe('#ORD-1 · £15.49');
});

test('commerce summary shows proceed checkout when payment is missing', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 10,
            'action_type' => 'proceed_checkout',
            'proceed_to_checkout' => [
                'cart_total' => 44.50,
                'totals' => [
                    'subtotal' => 40,
                    'shipping_cost' => 4.50,
                    'grand_total' => 44.50,
                ],
            ],
            'created_at' => now(),
        ]),
    ]);

    $summary = EcomActivityCommerceSummary::summarizeActions($actions);

    expect($summary['commerce_label'])->toBe('Proceed')
        ->and($summary['commerce_has_order'])->toBeFalse()
        ->and($summary['commerce_display'])->toBe('Proceed · £44.50');
});

test('commerce summary returns dash when no funnel actions exist', function () {
    $summary = EcomActivityCommerceSummary::summarizeActions(collect());

    expect($summary['commerce_display'])->toBe('—');
});

test('catalog commerce summary ignores orders outside the filtered category', function () {
    $dashboard = app(\App\Services\EcomTrackerDashboardService::class);
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 20,
            'action_type' => 'payment_success',
            'payment_success' => [
                'amount_paid' => 38,
                'checkout_info' => [
                    'items' => [
                        [
                            'product_name' => 'Trouser',
                            'product_code' => 'TR-1',
                            'category_name' => 'Trousers',
                            'department_name' => 'Women',
                            'qty' => 1,
                            'price' => 38,
                        ],
                    ],
                ],
            ],
            'created_at' => now(),
        ]),
    ]);

    $summary = EcomActivityCommerceSummary::summarizeCatalogActions($actions, [
        'department' => 'Women',
        'category' => 'Tops and T-Shirts',
    ], $dashboard);

    expect($summary['commerce_display'])->toBe('—');
});

test('catalog commerce summary sums all category-scoped payments in a session', function () {
    $dashboard = app(\App\Services\EcomTrackerDashboardService::class);
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 21,
            'action_type' => 'payment_success',
            'payment_success' => [
                'amount_paid' => 24.99,
                'checkout_info' => [
                    'items' => [[
                        'product_name' => 'Jumpsuit A',
                        'product_code' => 'JMP-1',
                        'category_name' => 'Jumpsuits',
                        'department_name' => 'Women',
                        'qty' => 1,
                        'price' => 24.99,
                    ]],
                ],
            ],
            'created_at' => now()->subMinutes(10),
        ]),
        new ActivityEcomUserAction([
            'id' => 22,
            'action_type' => 'payment_success',
            'payment_success' => [
                'amount_paid' => 16.00,
                'checkout_info' => [
                    'items' => [[
                        'product_name' => 'Jumpsuit B',
                        'product_code' => 'JMP-2',
                        'category_name' => 'Jumpsuits',
                        'department_name' => 'Women',
                        'qty' => 1,
                        'price' => 16.00,
                    ]],
                ],
            ],
            'created_at' => now()->subMinutes(5),
        ]),
    ]);

    $summary = EcomActivityCommerceSummary::summarizeCatalogActions($actions, [
        'department' => 'Women',
        'category' => 'Jumpsuits',
    ], $dashboard);

    expect($summary['commerce_display'])->toBe('Order · £40.99')
        ->and($summary['commerce_value'])->toBe(40.99);
});

test('catalog commerce summary uses latest commerce action when shopper returns to cart', function () {
    $dashboard = app(\App\Services\EcomTrackerDashboardService::class);
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 40,
            'action_type' => 'begin_checkout',
            'product_code' => 'WS333217',
            'begin_checkout' => ['cart_total' => 93.5],
            'created_at' => now()->subMinutes(20),
        ]),
        new ActivityEcomUserAction([
            'id' => 41,
            'action_type' => 'add_to_cart',
            'product_code' => 'WS333217',
            'add_to_cart' => ['cart_total' => 111.5],
            'created_at' => now()->subMinutes(5),
        ]),
    ]);

    $summary = EcomActivityCommerceSummary::summarizeCatalogActions($actions, [
        'product_code' => 'WS333217',
    ], $dashboard);

    expect($summary['commerce_display'])->toBe('Cart · £111.50')
        ->and(EcomActivityCommerceSummary::funnelStageRankFromSummary($summary))->toBe(2);
});

test('catalog commerce summary shows view when payment is for a different product', function () {
    $dashboard = app(\App\Services\EcomTrackerDashboardService::class);
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 30,
            'action_type' => 'product_view',
            'product_name' => 'Target Tee',
            'product_code' => 'MS31262181',
            'created_at' => now()->subMinutes(10),
        ]),
        new ActivityEcomUserAction([
            'id' => 31,
            'action_type' => 'payment_success',
            'payment_success' => [
                'amount_paid' => 45.0,
                'order_id' => 'ORD-999',
                'checkout_info' => [
                    'items' => [[
                        'product_name' => 'Other Tee',
                        'product_code' => 'MS99999999',
                        'qty' => 1,
                        'price' => 45.0,
                    ]],
                ],
            ],
            'product_code' => 'MS31262181',
            'created_at' => now(),
        ]),
    ]);

    $summary = EcomActivityCommerceSummary::summarizeCatalogActions($actions, [
        'search' => 'MS31262181',
    ], $dashboard);

    expect($summary['commerce_display'])->toBe('View')
        ->and($summary['commerce_has_order'])->toBeFalse()
        ->and($summary['commerce_label'])->toBe('View');
});

test('commerce summary from line items and orders prefers paid order over cart', function () {
    $lines = collect([
        (object) [
            'funnel_stage' => 'add_to_cart',
            'line_total' => 20,
            'event_id' => 'e1',
            'staged_at' => now()->subMinutes(10)->toDateTimeString(),
            'id' => 1,
            'order_id' => null,
        ],
        (object) [
            'funnel_stage' => 'payment_success',
            'line_total' => 15.49,
            'event_id' => 'e2',
            'staged_at' => now()->toDateTimeString(),
            'id' => 2,
            'order_id' => 'ORD-1',
        ],
    ]);
    $orders = collect([
        (object) [
            'order_id' => 'ORD-1',
            'amount_paid' => 15.49,
            'ordered_at' => now()->toDateTimeString(),
            'id' => 1,
            'event_id' => 'e2',
        ],
    ]);

    $summary = EcomActivityCommerceSummary::summarizeFromCommerce($lines, $orders);

    expect($summary['commerce_label'])->toBe('Order')
        ->and($summary['commerce_has_order'])->toBeTrue()
        ->and($summary['commerce_display'])->toBe('#ORD-1 · £15.49')
        ->and($summary['commerce_value'])->toBe(15.49);
});

test('commerce summary from line items shows proceed when payment is missing', function () {
    $lines = collect([
        (object) [
            'funnel_stage' => 'proceed_checkout',
            'line_total' => 44.50,
            'event_id' => 'e3',
            'staged_at' => now()->toDateTimeString(),
            'id' => 3,
            'order_id' => null,
        ],
    ]);

    $summary = EcomActivityCommerceSummary::summarizeFromCommerce($lines, collect());

    expect($summary['commerce_label'])->toBe('Proceed')
        ->and($summary['commerce_has_order'])->toBeFalse()
        ->and($summary['commerce_display'])->toBe('Proceed · £44.50');
});
