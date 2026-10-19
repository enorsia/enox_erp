<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\TrackerPaymentCheckoutEnricher;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

test('enriches payment checkout line from matching product view action', function () {
    $sessionActions = collect([
        new ActivityEcomUserAction([
            'action_type' => 'product_view',
            'product_id' => '1039',
            'product_code' => 'WA42431085',
            'product_name' => 'Blue Frill Detail Sleeveless Jersey Maxi Dress',
            'category_name' => 'Dresses',
            'department_name' => 'Women',
        ]),
    ]);

    $enricher = new TrackerPaymentCheckoutEnricher();
    $line = $enricher->enrichLineItem([
        'product_id' => '1039',
        'product_code' => 'WA42431085',
        'product_name' => 'Blue Frill Detail Sleeveless Jersey Maxi Dress',
        'qty' => 1,
        'price' => 16,
    ], $sessionActions);

    expect($line['category_name'])->toBe('Dresses');
    expect($line['department_name'])->toBe('Women');
});

test('enriches payment checkout line from matching add to cart item', function () {
    $sessionActions = collect([
        new ActivityEcomUserAction([
            'action_type' => 'add_to_cart',
            'product_code' => 'WA42431085',
            'add_to_cart' => [
                'items' => [[
                    'product_id' => '1039',
                    'product_code' => 'WA42431085',
                    'options' => [
                        'category_name' => 'Dresses',
                        'department_name' => 'Women',
                    ],
                ]],
            ],
        ]),
    ]);

    $enricher = new TrackerPaymentCheckoutEnricher();
    $line = $enricher->enrichLineItem([
        'product_id' => '1039',
        'product_code' => 'WA42431085',
        'qty' => 1,
        'price' => 16,
    ], $sessionActions);

    expect($line['category_name'])->toBe('Dresses');
    expect($line['department_name'])->toBe('Women');
});

test('does not overwrite existing checkout line category metadata', function () {
    $sessionActions = collect([
        new ActivityEcomUserAction([
            'action_type' => 'product_view',
            'product_code' => 'WA3285442',
            'category_name' => 'Dresses',
            'department_name' => 'Women',
        ]),
    ]);

    $enricher = new TrackerPaymentCheckoutEnricher();
    $line = $enricher->enrichLineItem([
        'product_code' => 'WA3285442',
        'category_name' => 'Jumpsuits',
        'department_name' => 'Women',
        'qty' => 1,
        'price' => 24.99,
    ], $sessionActions);

    expect($line['category_name'])->toBe('Jumpsuits');
});

test('merges checkout discount totals into payment payload when payment totals are net-only', function () {
    $sessionId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'visitor_id' => (string) Str::uuid(),
        'device_type' => 'desktop',
        'created_at' => '2026-10-19 05:50:00',
        'last_active_at' => '2026-10-19 05:55:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $sessionId,
        'action_type' => 'proceed_checkout',
        'created_at' => '2026-10-19 05:55:00',
        'proceed_to_checkout' => [
            'cart_total' => 42,
            'totals' => [
                'subtotal' => 45,
                'coupon_discount' => 3,
                'grand_total' => 42,
            ],
        ],
    ]);

    $enricher = new TrackerPaymentCheckoutEnricher();
    $payload = $enricher->enrichPayload($sessionId, [
        'order_id' => 'ORD-1',
        'amount_paid' => 42,
        'checkout_info' => [
            'items' => [
                ['product_code' => 'WA1', 'qty' => 1, 'price' => 45],
            ],
            'totals' => [
                'subtotal' => 42,
                'grand_total' => 42,
            ],
        ],
    ]);

    expect($payload['checkout_info']['totals']['subtotal'])->toBe(45)
        ->and($payload['checkout_info']['totals']['coupon_discount'])->toBe(3)
        ->and($payload['checkout_info']['totals']['grand_total'])->toBe(42);
});

test('does not fabricate category name from department only', function () {
    $sessionActions = collect([
        new ActivityEcomUserAction([
            'action_type' => 'product_view',
            'product_code' => 'WA42431085',
            'department_name' => 'Women',
        ]),
    ]);

    $enricher = new TrackerPaymentCheckoutEnricher();
    $line = $enricher->enrichLineItem([
        'product_code' => 'WA42431085',
        'qty' => 1,
        'price' => 16,
    ], $sessionActions);

    expect($line['department_name'] ?? null)->toBe('Women');
    expect($line['category_name'] ?? null)->toBeNull();
});
