<?php

use App\Models\ActivityEcomUserAction;
use App\Support\EcomActivityCommerceEvents;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

test('commerce events builds payment order with customer and line items', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'payment_success',
            'payment_success' => [
                'order_id' => '1295775947',
                'amount_paid' => 35.50,
                'payment_method' => 'card',
                'checkout_info' => [
                    'customer' => [
                        'first_name' => 'Paul',
                        'last_name' => 'McCann',
                        'email' => 'mccann@example.com',
                        'phone' => '7708431485',
                    ],
                    'shipping' => [
                        'line_1' => '31 Glebe Manor',
                        'town_city' => 'Newtownabbey',
                        'postcode' => 'BT36 6HF',
                    ],
                    'totals' => [
                        'delivery_charge' => 0,
                        'service_charge' => 3,
                        'subtotal' => 35.50,
                        'grand_total' => 35.50,
                    ],
                    'items' => [[
                        'product_name' => 'Cargo Shorts',
                        'product_code' => 'MS31262180',
                        'size_name' => '32',
                        'color_name' => 'Whisper White',
                        'qty' => 1,
                        'price' => 18.50,
                    ]],
                ],
            ],
            'created_at' => now(),
        ]),
    ]);

    $events = EcomActivityCommerceEvents::fromActions($actions);

    expect($events)->toHaveCount(1)
        ->and($events[0]['trigger_label'])->toBe('#1295775947 · £35.50')
        ->and($events[0]['stage'])->toBe('payment_success')
        ->and($events[0]['info_groups'][0]['title'])->toBe('Order info')
        ->and($events[0]['products'][0]['title'])->toContain('Cargo Shorts')
        ->and($events[0]['products'][0]['price'])->toBe('£18.50');
});

test('commerce events returns separate payment events for multiple orders', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'payment_success',
            'payment_success' => ['order_id' => 'ORD-1', 'amount_paid' => 10],
            'created_at' => now()->subMinutes(10),
        ]),
        new ActivityEcomUserAction([
            'id' => 2,
            'action_type' => 'payment_success',
            'payment_success' => ['order_id' => 'ORD-2', 'amount_paid' => 20],
            'created_at' => now()->subMinutes(5),
        ]),
    ]);

    $events = EcomActivityCommerceEvents::fromActions($actions);

    expect($events)->toHaveCount(2)
        ->and(collect($events)->pluck('trigger_label')->all())->toBe(['#ORD-2 · £20.00', '#ORD-1 · £10.00']);
});

test('commerce events dedupes duplicate payment events for the same order id', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'payment_success',
            'payment_success' => ['order_id' => 'ORD-1', 'amount_paid' => 10],
            'created_at' => now()->subMinutes(10),
        ]),
        new ActivityEcomUserAction([
            'id' => 2,
            'action_type' => 'payment_success',
            'payment_success' => ['order_id' => 'ORD-1', 'amount_paid' => 10],
            'created_at' => now()->subMinutes(5),
        ]),
    ]);

    $events = EcomActivityCommerceEvents::fromActions($actions);

    expect($events)->toHaveCount(1);
});

test('commerce events keeps only the latest funnel action when no order exists', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'add_to_cart',
            'add_to_cart' => [
                'cart_total' => 44,
                'items' => [[
                    'product_name' => 'Oxford Shirt',
                    'qty' => 1,
                    'price' => 44,
                ]],
            ],
            'created_at' => now()->subMinutes(20),
        ]),
        new ActivityEcomUserAction([
            'id' => 2,
            'action_type' => 'begin_checkout',
            'begin_checkout' => ['cart_total' => 44],
            'created_at' => now()->subMinutes(10),
        ]),
        new ActivityEcomUserAction([
            'id' => 3,
            'action_type' => 'proceed_checkout',
            'proceed_to_checkout' => [
                'cart_total' => 44,
                'customer' => ['full_name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ],
            'created_at' => now(),
        ]),
    ]);

    $events = EcomActivityCommerceEvents::fromActions($actions);

    expect($events)->toHaveCount(1)
        ->and($events[0]['stage'])->toBe('proceed_checkout')
        ->and($events[0]['trigger_label'])->toBe('Proceed · £44.00');
});

test('commerce events shows orders only and hides earlier funnel actions', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'add_to_cart',
            'add_to_cart' => ['cart_total' => 20],
            'created_at' => now()->subMinutes(30),
        ]),
        new ActivityEcomUserAction([
            'id' => 2,
            'action_type' => 'payment_success',
            'payment_success' => ['order_id' => 'ORD-1', 'amount_paid' => 20],
            'created_at' => now()->subMinutes(5),
        ]),
    ]);

    $events = EcomActivityCommerceEvents::fromActions($actions);

    expect($events)->toHaveCount(1)
        ->and($events[0]['stage'])->toBe('payment_success')
        ->and($events[0]['trigger_label'])->toBe('#ORD-1 · £20.00');
});

test('commerce events builds compact cart totals row for multi-item carts', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'add_to_cart',
            'add_to_cart' => [
                'cart_total' => 40.50,
                'qty' => 3,
                'items' => [
                    [
                        'product_name' => 'Oxford Shirt',
                        'qty' => 2,
                        'price' => 20,
                    ],
                    [
                        'product_name' => 'Trouser',
                        'qty' => 1,
                        'price' => 20.50,
                    ],
                ],
            ],
            'created_at' => now()->setTime(8, 36),
        ]),
    ]);

    $events = EcomActivityCommerceEvents::fromActions($actions);

    expect($events)->toHaveCount(1)
        ->and($events[0]['layout'])->toBe('compact')
        ->and($events[0]['cart_qty'])->toBe(3)
        ->and($events[0]['cart_total'])->toBe('£40.50');
});

test('commerce events compact cart exposes totals without date summary line', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'add_to_cart',
            'add_to_cart' => [
                'cart_total' => 44.99,
                'qty' => 1,
                'items' => [[
                    'product_name' => 'Cargo Trouser',
                    'qty' => 1,
                    'price' => 44.99,
                ]],
            ],
            'created_at' => now()->setTime(13, 34),
        ]),
    ]);

    $events = EcomActivityCommerceEvents::fromActions($actions);

    expect($events)->toHaveCount(1)
        ->and($events[0])->not->toHaveKey('summary_line')
        ->and($events[0]['cart_qty'])->toBe(1)
        ->and($events[0]['cart_total'])->toBe('£44.99');
});

test('commerce events ignores non funnel actions', function () {
    $actions = collect([
        new ActivityEcomUserAction([
            'id' => 1,
            'action_type' => 'product_view',
            'created_at' => now(),
        ]),
    ]);

    expect(EcomActivityCommerceEvents::fromActions($actions))->toBe([]);
});
