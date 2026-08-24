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
            'proceed_to_checkout' => ['cart_total' => 44.50],
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
