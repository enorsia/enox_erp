<?php

use App\Models\ActivityEcomUserAction;
use App\Support\TrackerPaymentCheckoutEnricher;

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
