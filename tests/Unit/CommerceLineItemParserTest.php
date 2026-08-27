<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\CommerceLineItemParser;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

test('commerce line item parser parses payment checkout items', function () {
    $action = new ActivityEcomUserAction([
        'event_id' => (string) Str::uuid(),
        'session_id' => 'sess-1',
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-1',
            'amount_paid' => 30,
            'checkout_info' => [
                'items' => [[
                    'product_name' => 'Shirt',
                    'product_code' => 'SKU1',
                    'qty' => 2,
                    'price' => 15,
                ]],
            ],
        ],
        'created_at' => now(),
    ]);
    $action->setRelation('session', new ActivityEcomUser(['visitor_id' => 'visitor-1']));

    $lines = CommerceLineItemParser::parseFromAction($action);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['funnel_stage'])->toBe('payment_success')
        ->and($lines[0]['order_id'])->toBe('ORD-1')
        ->and($lines[0]['product_code'])->toBe('SKU1');
});

test('commerce line item parser parses cart items', function () {
    $action = new ActivityEcomUserAction([
        'event_id' => (string) Str::uuid(),
        'session_id' => 'sess-2',
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'cart_total' => 10,
            'items' => [[
                'product_name' => 'Hat',
                'product_code' => 'H1',
                'qty' => 1,
                'price' => 10,
            ]],
        ],
        'created_at' => now(),
    ]);
    $action->setRelation('session', new ActivityEcomUser(['visitor_id' => 'visitor-1']));

    $lines = CommerceLineItemParser::parseFromAction($action);

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['funnel_stage'])->toBe('add_to_cart')
        ->and($lines[0]['order_id'])->toBeNull();
});
