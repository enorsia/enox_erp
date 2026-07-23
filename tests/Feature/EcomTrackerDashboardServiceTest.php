<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('ecom tracker dashboard resolves 24h rolling range', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $range = $service->resolveDateRange(['period' => '24h']);

    expect($range['label'])->toBe('Last 24 hours');
    expect($range['period'])->toBe('24h');
    expect($range['days'])->toBe(1);
    expect((int) TrackerTime::toLocal($range['from'])?->diffInHours(TrackerTime::toLocal($range['to'])))->toBe(24);

    Carbon::setTestNow();
});

test('ecom tracker dashboard sale excludes incomplete payment events and sums checkout line items', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-20 00:00:00');
    $to = Carbon::parse('2026-07-20 23:59:59');

    foreach ([
        Str::uuid()->toString(),
        Str::uuid()->toString(),
        Str::uuid()->toString(),
    ] as $index => $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);

        if ($index < 2) {
            ActivityEcomUserAction::query()->create([
                'event_id' => Str::uuid()->toString(),
                'session_id' => $sessionId,
                'action_type' => 'payment_success',
                'payment_success' => [
                    'order_id' => 'ORDER-'.$index,
                    'amount_paid' => $index === 0 ? 45 : 35,
                    'checkout_info' => [
                        'order_pk' => (string) (4801 + $index),
                        'items' => [[
                            'product_code' => 'SKU-'.$index,
                            'qty' => 1,
                            'price' => $index === 0 ? 45 : 35,
                        ]],
                        'totals' => ['grand_total' => $index === 0 ? 45 : 35],
                    ],
                ],
                'created_at' => $from->copy()->addHours(2 + $index),
                'start_time' => $from->copy()->addHours(2 + $index),
                'end_time' => $from->copy()->addHours(2 + $index)->addSeconds(10),
            ]);

            continue;
        }

        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'payment_success',
            'payment_success' => [
                'order_id' => 'ORD-E2E-1',
                'amount_paid' => 99.99,
                'payment_method' => 'card',
                'currency' => 'GBP',
            ],
            'created_at' => $from->copy()->addHours(5),
            'start_time' => $from->copy()->addHours(5),
            'end_time' => $from->copy()->addHours(5)->addSeconds(10),
        ]);
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-20',
        'date_to' => '2026-07-20',
    ]);

    expect(collect($data['kpis'])->firstWhere('label', 'Sale')['value'])->toBe(80.0);
    expect(collect($data['kpis'])->firstWhere('label', 'Average sale')['value'])->toBe(40.0);

    Carbon::setTestNow();
});

test('ecom tracker dashboard resolves custom date range', function () {
    $service = app(EcomTrackerDashboardService::class);

    $range = $service->resolveDateRange([
        'period' => 'custom',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-10',
    ]);

    expect(TrackerTime::toLocal($range['from'])?->toDateString())->toBe('2026-07-01');
    expect(TrackerTime::toLocal($range['to'])?->toDateString())->toBe('2026-07-10');
    expect($range['days'])->toBe(10);
});

test('ecom tracker dashboard builds funnel and kpis from tracked actions', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-10 00:00:00');
    $to = Carbon::parse('2026-07-10 23:59:59');

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'mobile',
        'utm_source' => 'google',
        'utm_medium' => 'organic',
        'country' => 'UK',
        'city' => 'London',
        'is_logged_in' => false,
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    foreach ([
        ['action_type' => 'category_view', 'category_name' => 'Women'],
        ['action_type' => 'product_view', 'product_code' => 'SKU-1', 'product_name' => 'Dress', 'general_color_name' => 'Navy'],
        ['action_type' => 'add_to_cart', 'add_to_cart' => ['cart_total' => 80, 'product_id' => '1']],
        ['action_type' => 'begin_checkout', 'begin_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
        ['action_type' => 'proceed_checkout', 'proceed_to_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
        ['action_type' => 'payment_success', 'payment_success' => ['amount_paid' => 80, 'checkout_info' => ['items' => [['product_code' => 'SKU-1', 'product_name' => 'Dress', 'qty' => 1, 'price' => 80, 'color_name' => 'Navy']]]]],
    ] as $index => $payload) {
        ActivityEcomUserAction::query()->create(array_merge([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'created_at' => $from->copy()->addMinutes($index),
            'start_time' => $from->copy()->addMinutes($index),
            'end_time' => $from->copy()->addMinutes($index)->addSeconds(30),
        ], $payload));
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-10',
        'date_to' => '2026-07-10',
    ]);

    expect($data['funnel'])->not->toBeEmpty();
    expect(collect($data['funnel'])->firstWhere('stage', 'Purchase')['count'])->toBe(1);
    expect($data['categories'][0]['name'] ?? null)->toBe('Women');
    expect($data['products'][0]['code'] ?? null)->toBe('SKU-1');
    expect($data['products'][0]['variants'][0]['color'] ?? null)->toBe('Navy');
    expect($data['products'][0]['variants'][0]['purchases'] ?? null)->toBe(1);

    Carbon::setTestNow();
});

test('ecom tracker dashboard matches product views and purchases by product name', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-12 00:00:00');
    $productName = 'Womens Pink Coloured Slim Fit Stretch Jeans';

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_code' => 'GS-PARENT-SKU',
        'product_name' => $productName,
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 22.99,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'WJEPI10009400',
                    'product_name' => $productName,
                    'qty' => 1,
                    'price' => 22.99,
                    'color_name' => 'Pink',
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute()->addSeconds(10),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-12',
        'date_to' => '2026-07-12',
    ]);

    expect($data['products'])->not->toBeEmpty();

    $product = collect($data['products'])->first(fn (array $row) => $row['purchases'] > 0);

    expect($product)->not->toBeNull();
    expect($product['code'])->toBe('WJEPI10009400');
    expect($product['views'])->toBeGreaterThanOrEqual(1);
    expect($product['purchases'])->toBe(1);
    expect($product['revenue'])->toBe(22.99);
});

test('ecom tracker dashboard trend uses full filtered range with purchase qty', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-01 00:00:00');
    $to = Carbon::parse('2026-07-05 23:59:59');

    foreach (range(1, 5) as $day) {
        $sessionId = Str::uuid()->toString();
        $createdAt = $from->copy()->addDays($day - 1)->addHours(10);

        Carbon::setTestNow($createdAt);

        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'updated_at' => $createdAt,
            'last_active_at' => $createdAt,
        ]);

        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'payment_success',
            'payment_success' => ['amount_paid' => 50],
            'created_at' => $createdAt->copy()->addHour(),
            'start_time' => $createdAt->copy()->addHour(),
            'end_time' => $createdAt->copy()->addHours(2),
        ]);
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-05',
    ]);

    expect($data['trend']['total_days'])->toBe(5);
    expect($data['trend']['bucket'])->toBe('day');
    expect($data['trend']['labels'])->toHaveCount(5);
    expect($data['trend']['sessions'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['purchases'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['conversion_rates'])->toBe([100.0, 100.0, 100.0, 100.0, 100.0]);
    expect($data['trend']['use_log_scale'])->toBeFalse();

    Carbon::setTestNow();
});

test('ecom tracker dashboard counts all three funnel abandonment stages', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-15 00:00:00');
    $to = Carbon::parse('2026-07-15 23:59:59');

    $cartAbandonedId = Str::uuid()->toString();
    $beginCheckoutAbandonedId = Str::uuid()->toString();
    $proceedCheckoutAbandonedId = Str::uuid()->toString();
    $completedId = Str::uuid()->toString();

    foreach ([$cartAbandonedId, $beginCheckoutAbandonedId, $proceedCheckoutAbandonedId, $completedId] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $cartAbandonedId,
        'action_type' => 'add_to_cart',
        'add_to_cart' => ['cart_total' => 50, 'product_id' => '1'],
        'created_at' => $from->copy()->addHour(),
        'start_time' => $from->copy()->addHour(),
        'end_time' => $from->copy()->addHour()->addSeconds(30),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $beginCheckoutAbandonedId,
        'action_type' => 'begin_checkout',
        'begin_checkout' => ['cart_total' => 120, 'coupon_code' => 'SAVE20'],
        'created_at' => $from->copy()->addHours(2),
        'start_time' => $from->copy()->addHours(2),
        'end_time' => $from->copy()->addHours(2)->addSeconds(30),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $proceedCheckoutAbandonedId,
        'action_type' => 'proceed_checkout',
        'proceed_to_checkout' => ['cart_total' => 90, 'coupon_code' => 'SAVE10'],
        'created_at' => $from->copy()->addHours(3),
        'start_time' => $from->copy()->addHours(3),
        'end_time' => $from->copy()->addHours(3)->addSeconds(30),
    ]);

    foreach ([
        ['action_type' => 'add_to_cart', 'add_to_cart' => ['cart_total' => 80]],
        ['action_type' => 'begin_checkout', 'begin_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
        ['action_type' => 'proceed_checkout', 'proceed_to_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
        ['action_type' => 'payment_success', 'payment_success' => ['amount_paid' => 80]],
    ] as $index => $payload) {
        ActivityEcomUserAction::query()->create(array_merge([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $completedId,
            'created_at' => $from->copy()->addHours(4 + $index),
            'start_time' => $from->copy()->addHours(4 + $index),
            'end_time' => $from->copy()->addHours(4 + $index)->addSeconds(30),
        ], $payload));
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-15',
        'date_to' => '2026-07-15',
    ]);

    expect($data['cart_abandonment']['session_count'])->toBe(1);
    expect($data['cart_abandonment']['at_stake'])->toBe(50.0);
    expect($data['cart_abandonment']['rows'][0]['session_id'] ?? null)->toBe($cartAbandonedId);

    expect($data['begin_checkout_abandonment']['session_count'])->toBe(1);
    expect($data['begin_checkout_abandonment']['at_stake'])->toBe(120.0);
    expect($data['begin_checkout_abandonment']['rows'][0]['session_id'] ?? null)->toBe($beginCheckoutAbandonedId);

    expect($data['proceed_checkout_abandonment']['session_count'])->toBe(1);
    expect($data['proceed_checkout_abandonment']['at_stake'])->toBe(90.0);
    expect($data['proceed_checkout_abandonment']['rows'][0]['session_id'] ?? null)->toBe($proceedCheckoutAbandonedId);

    Carbon::setTestNow();
});

test('ecom tracker dashboard matches purchased variants when checkout only has product id', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-11 00:00:00');

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_code' => 'SKU-9',
        'product_name' => 'Polo Shirt',
        'general_color_name' => 'Beige',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 45,
            'checkout_info' => [
                'items' => [[
                    'product_id' => '101',
                    'product_name' => 'Polo Shirt',
                    'qty' => 1,
                    'price' => 45,
                    'color_name' => 'Beige',
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute()->addSeconds(10),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-11',
        'date_to' => '2026-07-11',
    ]);

    expect($data['products'][0]['variants'][0]['views'] ?? null)->toBe(1);
    expect($data['products'][0]['variants'][0]['purchases'] ?? null)->toBe(1);
});

test('product catalog filters apply funnel scenario and activity events with or logic', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-12 00:00:00');
    $to = Carbon::parse('2026-07-12 23:59:59');

    $createSession = function (string $suffix) use ($from) {
        $sessionId = "session-{$suffix}";
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);

        return $sessionId;
    };

    $viewOnly = $createSession('view');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $viewOnly,
        'action_type' => 'product_view',
        'product_code' => 'SKU-VIEW',
        'product_name' => 'View Only Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    $cartOnly = $createSession('cart');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $cartOnly,
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'product_code' => 'SKU-CART',
            'product_id' => '2',
        ],
        'product_name' => 'Cart Only Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    $purchased = $createSession('buy');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $purchased,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 120,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BUY',
                    'product_name' => 'Purchased Dress',
                    'qty' => 1,
                    'price' => 120,
                ]],
            ],
        ],
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    $catalog = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'event_scenario' => 'viewed_not_purchased',
    ]);

    expect(collect($catalog['products'])->pluck('name')->all())->toBe(['View Only Dress']);

    $activityCatalog = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'activity' => 'purchases',
    ]);

    expect(collect($activityCatalog['products'])->pluck('name')->all())
        ->toBe(['Purchased Dress']);

    $sorted = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'sort_by' => 'top_purchases',
    ]);

    expect($sorted['products'][0]['name'])->toBe('Purchased Dress');
    expect($sorted['sort_by'])->toBe('top_purchases');
});

test('product catalog counts purchases as orders and qty as units with full sale value', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-13 00:00:00');
    $to = Carbon::parse('2026-07-13 23:59:59');

    $createSession = function (string $suffix) use ($from) {
        $sessionId = "purchase-session-{$suffix}";
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);

        return $sessionId;
    };

    $bulkOrder = $createSession('bulk');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $bulkOrder,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-BULK',
            'amount_paid' => 75,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BULK',
                    'product_name' => 'Bulk Dress',
                    'quantity' => 3,
                    'price' => 25,
                ]],
            ],
        ],
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    foreach (['A', 'B'] as $suffix) {
        $sessionId = $createSession($suffix);
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'payment_success',
            'payment_success' => [
                'order_id' => "ORD-{$suffix}",
                'amount_paid' => 40,
                'checkout_info' => [
                    'items' => [[
                        'product_code' => 'SKU-MULTI',
                        'product_name' => 'Multi Order Dress',
                        'qty' => 1,
                        'price' => 40,
                    ]],
                ],
            ],
            'created_at' => $from->copy()->addMinutes(5),
            'start_time' => $from->copy()->addMinutes(5),
            'end_time' => $from->copy()->addMinutes(5),
        ]);
    }

    $catalog = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'sort_by' => 'top_purchases',
    ]);

    $bulk = collect($catalog['products'])->firstWhere('name', 'Bulk Dress');
    expect($bulk['purchases'])->toBe(1);
    expect($bulk['qty'])->toBe(3);
    expect($bulk['revenue'])->toBe(75.0);

    $multi = collect($catalog['products'])->firstWhere('name', 'Multi Order Dress');
    expect($multi['purchases'])->toBe(2);
    expect($multi['qty'])->toBe(2);
    expect($multi['revenue'])->toBe(80.0);

    expect($catalog['products'][0]['name'])->toBe('Multi Order Dress');
});

test('product catalog with has order session filter shows only purchased products', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-14 00:00:00');
    $to = Carbon::parse('2026-07-14 23:59:59');
    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_code' => 'SKU-VIEWED',
        'product_name' => 'Only Viewed Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_code' => 'SKU-BOUGHT',
        'product_name' => 'Purchased Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-ONLY-ONE',
            'amount_paid' => 55,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BOUGHT',
                    'product_name' => 'Purchased Dress',
                    'qty' => 1,
                    'price' => 55,
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute(),
    ]);

    $allProducts = $service->buildProductCatalogPerformance($from, $to, null, [], []);
    expect(collect($allProducts['products'])->pluck('name')->all())
        ->toContain('Only Viewed Dress', 'Purchased Dress');

    $orderedOnly = $service->buildProductCatalogPerformance($from, $to, null, ['has_order' => '1'], []);
    expect(collect($orderedOnly['products'])->pluck('name')->all())
        ->toBe(['Purchased Dress']);
    expect($orderedOnly['products'][0]['purchases'])->toBe(1);
});

test('product catalog funnel and activity filters only show purchased products when required', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-15 00:00:00');
    $to = Carbon::parse('2026-07-15 23:59:59');
    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    foreach ([
        ['SKU-VIEW', 'Only Viewed Dress'],
        ['SKU-BUY', 'Purchased Dress'],
    ] as [$code, $name]) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'product_view',
            'product_code' => $code,
            'product_name' => $name,
            'created_at' => $from,
            'start_time' => $from,
            'end_time' => $from,
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-FUNNEL-1',
            'amount_paid' => 60,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BUY',
                    'product_name' => 'Purchased Dress',
                    'qty' => 1,
                    'price' => 60,
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute(),
    ]);

    $purchasedOnly = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'event_scenario' => 'purchased_only',
    ]);
    expect(collect($purchasedOnly['products'])->pluck('name')->all())->toBe(['Purchased Dress']);

    $activityPurchases = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'activity' => 'purchases',
    ]);
    expect(collect($activityPurchases['products'])->pluck('name')->all())->toBe(['Purchased Dress']);

    $viewedNotPurchased = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'event_scenario' => 'viewed_not_purchased',
    ]);
    expect(collect($viewedNotPurchased['products'])->pluck('name')->all())->toBe(['Only Viewed Dress']);
});

test('product catalog empty period defaults to last 24 hours', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15 16:00:00', TrackerTime::timezone()));

    $range = $service->resolveDateRange(['period' => '']);

    expect($range['period'])->toBe('24h');
    expect($range['label'])->toBe('Last 24 hours');

    Carbon::setTestNow();
});

test('dashboard product table defaults to last 24 hours and applies session filters', function () {
    $service = app(EcomTrackerDashboardService::class);
    $now = Carbon::parse('2026-07-15 16:00:00', TrackerTime::timezone());

    Carbon::setTestNow($now);

    $recentSession = Str::uuid()->toString();
    $oldSession = Str::uuid()->toString();
    $recentFrom = $now->copy()->subHours(6)->utc();
    $oldFrom = $now->copy()->subDays(3)->utc();

    foreach ([[$recentSession, $recentFrom], [$oldSession, $oldFrom]] as [$sessionId, $createdAt]) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'last_active_at' => $createdAt,
        ]);

        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'product_view',
            'product_code' => "SKU-{$sessionId}",
            'product_name' => $sessionId === $recentSession ? 'Recent Dress' : 'Old Dress',
            'created_at' => $createdAt,
            'start_time' => $createdAt,
            'end_time' => $createdAt,
        ]);
    }

    $defaultData = $service->getDashboardData([]);
    expect($defaultData['range']['period'])->toBe('24h');
    expect(collect($defaultData['products'])->pluck('name')->all())->toBe(['Recent Dress']);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $recentSession,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 50,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-RECENT',
                    'product_name' => 'Recent Dress',
                    'qty' => 1,
                    'price' => 50,
                ]],
            ],
        ],
        'created_at' => $recentFrom,
        'start_time' => $recentFrom,
        'end_time' => $recentFrom,
    ]);

    $orderedData = $service->getDashboardData(['has_order' => '1']);
    expect(collect($orderedData['products'])->pluck('name')->all())->toBe(['Recent Dress']);

    $searchData = $service->getDashboardData(['search' => 'Old']);
    expect(collect($searchData['products'])->pluck('name')->all())->toBe([]);

    Carbon::setTestNow();
});

test('ecom tracker dashboard includes visitor quality summary', function () {
    $service = app(EcomTrackerDashboardService::class);

    $data = $service->getDashboardData(['period' => '7d']);

    expect($data)->toHaveKey('visitor_quality');
    expect($data['visitor_quality'])->toHaveKeys(['real_shoppers', 'automated_traffic', 'not_classified', 'uk_shoppers']);
});
